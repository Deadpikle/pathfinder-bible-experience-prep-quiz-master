<?php

namespace App\Services;

use App\Models\Question;
use PDO;

/**
 * Enforces the mechanical portion of the configured Scripture quotation policy.
 * Passing this check is not a grant of rights; source/work-purpose review remains manual.
 */
final class FillInAvailabilityPolicy
{
    public const MAX_DISTINCT_VERSES = 500;
    public const LOCK_NAME = 'pbe_fill_in_availability';

    /** @param array<int,string> $restrictedLanguageAbbreviations */
    public function __construct(private array $restrictedLanguageAbbreviations = ['en'])
    {
    }

    public static function acquireLock(PDO $db, int $timeoutSeconds = 10): void
    {
        $stmt = $db->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([self::LOCK_NAME, max(0, $timeoutSeconds)]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new \RuntimeException('The fill-in availability policy is busy. Please try again.');
        }
    }

    public static function releaseLock(PDO $db): void
    {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([self::LOCK_NAME]);
    }

    /** @return array<string,mixed> */
    public function auditRotationSet(int $rotationSetID, PDO $db): array
    {
        $setStmt = $db->prepare(
            'SELECT YearID, LanguageID FROM FillInRotationSets WHERE FillInRotationSetID = ?'
        );
        $setStmt->execute([$rotationSetID]);
        $set = $setStmt->fetch();
        if (!$set) {
            return self::failure(['Rotation set not found.']);
        }
        $questionsStmt = $db->prepare(
            'SELECT QuestionID FROM FillInRotationSetQuestions
             WHERE FillInRotationSetID = ? ORDER BY SortOrder'
        );
        $questionsStmt->execute([$rotationSetID]);
        $questionIDs = array_map('intval', $questionsStmt->fetchAll(PDO::FETCH_COLUMN));
        return $this->auditQuestionIDs((int)$set['YearID'], (int)$set['LanguageID'], $questionIDs, $db);
    }

    /** @return array<string,mixed> */
    public function auditActive(int $yearID, int $languageID, PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT q.QuestionID
             FROM Questions q
             INNER JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID
             INNER JOIN Verses v ON v.VerseID = q.StartVerseID
             INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
             INNER JOIN Books b ON b.BookID = c.BookID
             WHERE b.YearID = ? AND q.LanguageID = ? AND q.Type = 'bible-qna-fill'
               AND q.IsDeleted = 0 AND q.IsActive = 1 AND qb.IsGlobal = 1"
        );
        $stmt->execute([$yearID, $languageID]);
        return $this->auditQuestionIDs(
            $yearID,
            $languageID,
            array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
            $db
        );
    }

    /** @return array<string,mixed> */
    public function auditProspectiveQuestion(Question $question, PDO $db): array
    {
        if ($question->type !== Question::getBibleQnAFillType() || ($question->startVerseID ?? 0) <= 0) {
            return self::failure(['A Bible fill-in must have a valid start verse.']);
        }
        $contextStmt = $db->prepare(
            'SELECT b.YearID, LOWER(l.Abbreviation) AS Abbreviation
             FROM Verses v
             INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
             INNER JOIN Books b ON b.BookID = c.BookID
             INNER JOIN Languages l ON l.LanguageID = ?
             INNER JOIN QuestionBanks qb
                ON qb.QuestionBankID = ? AND qb.IsGlobal = 1 AND qb.IsDeleted = 0
             WHERE v.VerseID = ?'
        );
        $contextStmt->execute([$question->languageID, $question->questionBankID, $question->startVerseID]);
        $context = $contextStmt->fetch();
        if (!$context) {
            return self::failure(['The selected start verse, language, or global question bank is invalid.']);
        }
        $isRestricted = in_array((string)$context['Abbreviation'], $this->restrictedLanguageAbbreviations, true);

        $ranges = [];
        if ($isRestricted) {
            $rangesStmt = $db->prepare(
                "SELECT q.StartVerseID, COALESCE(q.EndVerseID, q.StartVerseID) AS EndVerseID
                 FROM Questions q
                 INNER JOIN QuestionBanks qb
                    ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1 AND qb.IsDeleted = 0
                 INNER JOIN Verses v ON v.VerseID = q.StartVerseID
                 INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
                 INNER JOIN Books b ON b.BookID = c.BookID
                 WHERE b.YearID = ? AND q.LanguageID = ? AND q.Type = 'bible-qna-fill'
                   AND q.IsDeleted = 0 AND q.IsActive = 1 AND q.QuestionID <> ?"
            );
            $rangesStmt->execute([(int)$context['YearID'], $question->languageID, max(0, $question->questionID)]);
            $ranges = $rangesStmt->fetchAll();
        }
        $ranges[] = [
            'StartVerseID' => $question->startVerseID,
            'EndVerseID' => ($question->endVerseID ?? 0) > 0 ? $question->endVerseID : $question->startVerseID,
        ];
        $evaluation = self::evaluateCoverage(self::loadOrderedVerses((int)$context['YearID'], $db), $ranges);
        $errors = $evaluation['errors'];
        if ($isRestricted && $evaluation['distinctVerseCount'] > self::MAX_DISTINCT_VERSES) {
            $errors[] = 'The active fill-in pool would quote more than ' . self::MAX_DISTINCT_VERSES . ' distinct verses.';
        }
        if ($isRestricted && $evaluation['completeBooks'] !== []) {
            $errors[] = 'The active fill-in pool would contain every verse of: '
                . implode(', ', $evaluation['completeBooks']) . '.';
        }
        return [
            'allowed' => $errors === [],
            'skipped' => !$isRestricted,
            'distinctVerseCount' => $evaluation['distinctVerseCount'],
            'completeBooks' => $evaluation['completeBooks'],
            'errors' => $errors,
            'manualReviewRequired' => true,
            'questionIDs' => [],
        ];
    }

    /** @return array<string,mixed> */
    public function auditQuestionIDs(int $yearID, int $languageID, array $questionIDs, PDO $db): array
    {
        $questionIDs = array_values(array_unique(array_filter(array_map('intval', $questionIDs), static fn(int $id): bool => $id > 0)));
        if ($questionIDs === []) {
            return self::failure(['A rotation set must contain at least one valid question.']);
        }

        $languageStmt = $db->prepare('SELECT Abbreviation FROM Languages WHERE LanguageID = ?');
        $languageStmt->execute([$languageID]);
        $abbreviation = $languageStmt->fetchColumn();
        if ($abbreviation === false) {
            return self::failure(['The rotation set language does not exist.'], $questionIDs);
        }
        $abbreviation = strtolower((string)$abbreviation);
        $isRestricted = in_array($abbreviation, $this->restrictedLanguageAbbreviations, true);
        $placeholders = implode(',', array_fill(0, count($questionIDs), '?'));
        $stmt = $db->prepare(
            "SELECT q.QuestionID, q.StartVerseID, COALESCE(q.EndVerseID, q.StartVerseID) AS EndVerseID
             FROM Questions q
             INNER JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID
             INNER JOIN Verses startVerse ON startVerse.VerseID = q.StartVerseID
             INNER JOIN Chapters startChapter ON startChapter.ChapterID = startVerse.ChapterID
             INNER JOIN Books startBook ON startBook.BookID = startChapter.BookID
             WHERE q.QuestionID IN ({$placeholders})
               AND q.Type = 'bible-qna-fill' AND q.IsDeleted = 0
               AND q.LanguageID = ? AND startBook.YearID = ?
               AND qb.IsGlobal = 1 AND qb.IsDeleted = 0"
        );
        $stmt->execute(array_merge($questionIDs, [$languageID, $yearID]));
        $ranges = $stmt->fetchAll();
        if (count($ranges) !== count($questionIDs)) {
            return self::failure([
                'Every curated item must be a non-deleted global Bible fill-in for the selected year and language.',
            ], $questionIDs);
        }

        $verses = self::loadOrderedVerses($yearID, $db);
        $evaluation = self::evaluateCoverage($verses, $ranges);
        $errors = $evaluation['errors'];
        if ($isRestricted && $evaluation['distinctVerseCount'] > self::MAX_DISTINCT_VERSES) {
            $errors[] = 'The active set quotes ' . $evaluation['distinctVerseCount']
                . ' distinct verses; the configured maximum is ' . self::MAX_DISTINCT_VERSES . '.';
        }
        if ($isRestricted && $evaluation['completeBooks'] !== []) {
            $errors[] = 'The active set contains every verse of: ' . implode(', ', $evaluation['completeBooks']) . '.';
        }

        return [
            'allowed' => $errors === [],
            'skipped' => !$isRestricted,
            'distinctVerseCount' => $evaluation['distinctVerseCount'],
            'completeBooks' => $evaluation['completeBooks'],
            'errors' => $errors,
            'manualReviewRequired' => true,
            'questionIDs' => $questionIDs,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $orderedVerses
     * @param array<int,array<string,mixed>> $ranges
     * @return array{distinctVerseCount:int,completeBooks:array<int,string>,errors:array<int,string>}
     */
    public static function evaluateCoverage(array $orderedVerses, array $ranges): array
    {
        $positionByVerseID = [];
        $verseCountsByBook = [];
        foreach ($orderedVerses as $position => $verse) {
            $verseID = (int)$verse['VerseID'];
            $bookID = (int)$verse['BookID'];
            $positionByVerseID[$verseID] = $position;
            $verseCountsByBook[$bookID] = ($verseCountsByBook[$bookID] ?? 0) + 1;
        }

        $quotedVerseIDs = [];
        $quotedByBook = [];
        $errors = [];
        foreach ($ranges as $range) {
            $startID = (int)$range['StartVerseID'];
            $endID = (int)$range['EndVerseID'];
            if (!isset($positionByVerseID[$startID], $positionByVerseID[$endID])) {
                $errors[] = 'A curated question references a verse outside the selected year.';
                continue;
            }
            $start = $positionByVerseID[$startID];
            $end = $positionByVerseID[$endID];
            if ($end < $start) {
                $errors[] = 'A curated question has an end verse before its start verse.';
                continue;
            }
            for ($position = $start; $position <= $end; $position++) {
                $verse = $orderedVerses[$position];
                $verseID = (int)$verse['VerseID'];
                $bookID = (int)$verse['BookID'];
                $quotedVerseIDs[$verseID] = true;
                $quotedByBook[$bookID][$verseID] = true;
            }
        }

        $completeBooks = [];
        foreach ($quotedByBook as $bookID => $bookVerses) {
            if (count($bookVerses) === ($verseCountsByBook[$bookID] ?? 0)) {
                foreach ($orderedVerses as $verse) {
                    if ((int)$verse['BookID'] === (int)$bookID) {
                        $completeBooks[] = (string)$verse['BookName'];
                        break;
                    }
                }
            }
        }

        return [
            'distinctVerseCount' => count($quotedVerseIDs),
            'completeBooks' => array_values(array_unique($completeBooks)),
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function loadOrderedVerses(int $yearID, PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT v.VerseID, b.BookID, b.Name AS BookName, b.BibleOrder, c.Number AS ChapterNumber,
                    v.Number AS VerseNumber
             FROM Verses v
             INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
             INNER JOIN Books b ON b.BookID = c.BookID
             WHERE b.YearID = ?
             ORDER BY b.BibleOrder, b.BookID, c.Number, v.Number, v.VerseID'
        );
        $stmt->execute([$yearID]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed> */
    private static function failure(array $errors, array $questionIDs = []): array
    {
        return [
            'allowed' => false,
            'skipped' => false,
            'distinctVerseCount' => 0,
            'completeBooks' => [],
            'errors' => $errors,
            'manualReviewRequired' => true,
            'questionIDs' => $questionIDs,
        ];
    }
}
