<?php

namespace App\Services;

use App\Models\Setting;
use PDO;
use RuntimeException;
use Throwable;

final class FillInRotationService
{
    public function __construct(private ?FillInAvailabilityPolicy $policy = null)
    {
        $this->policy ??= new FillInAvailabilityPolicy();
    }

    /** @return array<int,array<string,mixed>> */
    public function rotate(
        PDO $db,
        bool $dryRun = false,
        bool $force = false,
        ?int $yearID = null,
        ?int $languageID = null
    ): array {
        if ($force && !$dryRun) {
            throw new RuntimeException('--force is only permitted with --dry-run. Enable the feature gate for a live rotation.');
        }
        if (!$dryRun && !filter_var(getenv('ENABLE_FILL_IN_ROTATION') ?: false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Fill-in rotation is disabled. Set ENABLE_FILL_IN_ROTATION=true before a live rotation.');
        }

        FillInAvailabilityPolicy::acquireLock($db, 0);

        try {
            $sql = '
                SELECT state.FillInRotationStateID, eligible.YearID, eligible.LanguageID,
                       state.CurrentFillInRotationSetID, currentSet.SortOrder AS CurrentSortOrder
                FROM (
                    SELECT DISTINCT YearID, LanguageID
                    FROM FillInRotationSets
                    WHERE IsEnabled = 1
                ) eligible
                INNER JOIN Years y ON y.YearID = eligible.YearID
                LEFT JOIN FillInRotationState state
                    ON state.YearID = eligible.YearID AND state.LanguageID = eligible.LanguageID
                LEFT JOIN FillInRotationSets currentSet
                    ON currentSet.FillInRotationSetID = state.CurrentFillInRotationSetID
                WHERE (? IS NULL OR eligible.YearID = ?)
                  AND (? IS NULL OR eligible.LanguageID = ?)
                  AND (? IS NOT NULL OR y.IsCurrent = 1)
                ORDER BY eligible.YearID, eligible.LanguageID';
            $statesStmt = $db->prepare($sql);
            $statesStmt->execute([$yearID, $yearID, $languageID, $languageID, $yearID]);
            $states = $statesStmt->fetchAll();

            $results = [];
            foreach ($states as $state) {
                $results[] = $this->rotateState($state, $db, $dryRun);
            }
            return $results;
        } finally {
            FillInAvailabilityPolicy::releaseLock($db);
        }
    }

    /** @return array<string,mixed> */
    private function rotateState(array $state, PDO $db, bool $dryRun): array
    {
        $nextSet = $this->nextSet(
            (int)$state['YearID'],
            (int)$state['LanguageID'],
            isset($state['CurrentSortOrder']) ? (int)$state['CurrentSortOrder'] : null,
            $db
        );
        if ($nextSet === null) {
            return [
                'didSucceed' => false,
                'dryRun' => $dryRun,
                'yearID' => (int)$state['YearID'],
                'languageID' => (int)$state['LanguageID'],
                'message' => 'No enabled curated rotation set is available.',
            ];
        }

        $audit = $this->policy->auditRotationSet((int)$nextSet['FillInRotationSetID'], $db);
        if (!$audit['allowed']) {
            $message = implode(' ', $audit['errors']);
            if (!$dryRun) {
                $this->writeAudit($state, $nextSet, false, $audit, $message, $db);
            }
            return [
                'didSucceed' => false,
                'dryRun' => $dryRun,
                'yearID' => (int)$state['YearID'],
                'languageID' => (int)$state['LanguageID'],
                'nextSetID' => (int)$nextSet['FillInRotationSetID'],
                'audit' => $audit,
                'message' => $message,
            ];
        }

        if ($dryRun) {
            return [
                'didSucceed' => true,
                'dryRun' => true,
                'yearID' => (int)$state['YearID'],
                'languageID' => (int)$state['LanguageID'],
                'nextSetID' => (int)$nextSet['FillInRotationSetID'],
                'nextSetName' => (string)$nextSet['Name'],
                'audit' => $audit,
                'message' => 'Validation passed; no database state was changed.',
            ];
        }

        $db->beginTransaction();
        try {
            $stateID = (int)($state['FillInRotationStateID'] ?? 0);
            if ($stateID <= 0) {
                $insertState = $db->prepare(
                    'INSERT IGNORE INTO FillInRotationState (YearID, LanguageID, RotationVersion)
                     VALUES (?, ?, 0)'
                );
                $insertState->execute([(int)$state['YearID'], (int)$state['LanguageID']]);
                $stateLookup = $db->prepare(
                    'SELECT FillInRotationStateID FROM FillInRotationState
                     WHERE YearID = ? AND LanguageID = ? FOR UPDATE'
                );
                $stateLookup->execute([(int)$state['YearID'], (int)$state['LanguageID']]);
                $stateID = (int)$stateLookup->fetchColumn();
                if ($stateID <= 0) {
                    throw new RuntimeException('Unable to initialize fill-in rotation state.');
                }
            }

            $deactivate = $db->prepare(
                "UPDATE Questions q
                 INNER JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1
                 INNER JOIN Verses v ON v.VerseID = q.StartVerseID
                 INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
                 INNER JOIN Books b ON b.BookID = c.BookID
                 SET q.IsActive = 0
                 WHERE b.YearID = ? AND q.LanguageID = ? AND q.Type = 'bible-qna-fill'"
            );
            $deactivate->execute([(int)$state['YearID'], (int)$state['LanguageID']]);

            $activate = $db->prepare(
                'UPDATE Questions q
                 INNER JOIN FillInRotationSetQuestions map ON map.QuestionID = q.QuestionID
                 INNER JOIN QuestionBanks qb
                    ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1 AND qb.IsDeleted = 0
                 INNER JOIN Verses v ON v.VerseID = q.StartVerseID
                 INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
                 INNER JOIN Books b ON b.BookID = c.BookID
                 SET q.IsActive = 1
                 WHERE map.FillInRotationSetID = ? AND q.IsDeleted = 0
                   AND q.Type = ? AND q.LanguageID = ? AND b.YearID = ?'
            );
            $activate->execute([
                (int)$nextSet['FillInRotationSetID'],
                'bible-qna-fill',
                (int)$state['LanguageID'],
                (int)$state['YearID'],
            ]);

            $chapters = $this->deriveActiveChapterLabels((int)$state['YearID'], $db);
            Setting::saveSetting(Setting::CurrentFillInChapters(), implode(', ', $chapters), $db);

            $updateState = $db->prepare(
                'UPDATE FillInRotationState
                 SET CurrentFillInRotationSetID = ?, LastRotatedAt = CURRENT_TIMESTAMP,
                     RotationVersion = RotationVersion + 1
                 WHERE FillInRotationStateID = ?'
            );
            $updateState->execute([(int)$nextSet['FillInRotationSetID'], $stateID]);
            $this->writeAudit($state, $nextSet, true, $audit, 'Rotation completed.', $db);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->writeAudit($state, $nextSet, false, $audit, $exception->getMessage(), $db);
            throw $exception;
        }

        return [
            'didSucceed' => true,
            'dryRun' => false,
            'yearID' => (int)$state['YearID'],
            'languageID' => (int)$state['LanguageID'],
            'nextSetID' => (int)$nextSet['FillInRotationSetID'],
            'nextSetName' => (string)$nextSet['Name'],
            'audit' => $audit,
            'message' => 'Rotation completed.',
        ];
    }

    private function nextSet(int $yearID, int $languageID, ?int $currentSortOrder, PDO $db): ?array
    {
        $stmt = $db->prepare(
            'SELECT FillInRotationSetID, Name, SortOrder
             FROM FillInRotationSets
             WHERE YearID = ? AND LanguageID = ? AND IsEnabled = 1
               AND (? IS NULL OR SortOrder > ?)
             ORDER BY SortOrder, FillInRotationSetID LIMIT 1'
        );
        $stmt->execute([$yearID, $languageID, $currentSortOrder, $currentSortOrder]);
        $set = $stmt->fetch();
        if ($set) {
            return $set;
        }
        $stmt = $db->prepare(
            'SELECT FillInRotationSetID, Name, SortOrder
             FROM FillInRotationSets
             WHERE YearID = ? AND LanguageID = ? AND IsEnabled = 1
             ORDER BY SortOrder, FillInRotationSetID LIMIT 1'
        );
        $stmt->execute([$yearID, $languageID]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,string> */
    private function deriveActiveChapterLabels(int $yearID, PDO $db): array
    {
        $verseStmt = $db->prepare(
            'SELECT v.VerseID, b.Name AS BookName, c.Number AS ChapterNumber
             FROM Verses v
             INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
             INNER JOIN Books b ON b.BookID = c.BookID
             WHERE b.YearID = ?
             ORDER BY b.BibleOrder, b.BookID, c.Number, v.Number, v.VerseID'
        );
        $verseStmt->execute([$yearID]);
        $verses = $verseStmt->fetchAll();
        $positions = [];
        foreach ($verses as $position => $verse) {
            $positions[(int)$verse['VerseID']] = $position;
        }
        $rangesStmt = $db->prepare(
            'SELECT q.StartVerseID, COALESCE(q.EndVerseID, q.StartVerseID) AS EndVerseID
             FROM Questions q
             INNER JOIN QuestionBanks qb
                ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1 AND qb.IsDeleted = 0
             INNER JOIN Verses v ON v.VerseID = q.StartVerseID
             INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
             INNER JOIN Books b ON b.BookID = c.BookID
             WHERE b.YearID = ? AND q.Type = ? AND q.IsDeleted = 0 AND q.IsActive = 1
             ORDER BY b.BibleOrder, c.Number, v.Number, q.QuestionID'
        );
        $rangesStmt->execute([$yearID, 'bible-qna-fill']);
        $labels = [];
        foreach ($rangesStmt->fetchAll() as $range) {
            if (!isset($positions[(int)$range['StartVerseID']], $positions[(int)$range['EndVerseID']])) {
                continue;
            }
            $start = $positions[(int)$range['StartVerseID']];
            $end = $positions[(int)$range['EndVerseID']];
            for ($position = $start; $position <= $end; $position++) {
                $label = $verses[$position]['BookName'] . ' ' . $verses[$position]['ChapterNumber'];
                $labels[$label] = $label;
            }
        }
        return array_values($labels);
    }

    private function writeAudit(
        array $state,
        array $nextSet,
        bool $didSucceed,
        array $audit,
        string $message,
        PDO $db
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO FillInRotationAudit
                (YearID, LanguageID, PreviousFillInRotationSetID, NextFillInRotationSetID,
                 WasDryRun, DidSucceed, DistinctVerseCount, Message)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$state['YearID'],
            (int)$state['LanguageID'],
            $state['CurrentFillInRotationSetID'] !== null ? (int)$state['CurrentFillInRotationSetID'] : null,
            (int)$nextSet['FillInRotationSetID'],
            $didSucceed ? 1 : 0,
            (int)($audit['distinctVerseCount'] ?? 0),
            mb_substr($message, 0, 65000),
        ]);
    }
}
