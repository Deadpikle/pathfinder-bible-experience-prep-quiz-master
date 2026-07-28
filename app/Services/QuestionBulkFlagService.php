<?php

namespace App\Services;

use App\Models\FlagReason;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\Year;
use InvalidArgumentException;
use PDO;
use Throwable;

final class QuestionBulkFlagService
{
    /**
     * Flags only the explicitly selected active Bible questions after proving
     * that each one belongs to the user's readable scope and overlaps the
     * selected book/chapter/verse range.
     *
     * @param array<string,mixed> $filters
     */
    public static function flag(
        array $filters,
        int $userID,
        string $reason,
        PBEAppConfig $app,
        PDO $db,
        ?QuestionScope $scope = null
    ): int
    {
        if ($userID <= 0 || $app->isGuest) {
            throw new InvalidArgumentException('Guests cannot flag questions.');
        }
        $reason = FlagReason::validateReason($reason);
        if ($reason === FlagReason::UNKNOWN) {
            throw new InvalidArgumentException('A valid flag reason is required.');
        }
        $selectedQuestionIDs = self::positiveIDs($filters['questionIDs'] ?? null);
        if ($selectedQuestionIDs === []) {
            throw new InvalidArgumentException('Select at least one matching question to flag.');
        }
        if (count($selectedQuestionIDs) > 10000) {
            throw new InvalidArgumentException('Too many questions were selected.');
        }

        $year = Year::loadCurrentYear($db);
        $bookID = self::positiveInt($filters['bookFilter'] ?? -1);
        if ($year === null || $bookID <= 0) {
            throw new InvalidArgumentException('Select at least a book before bulk flagging.');
        }
        $range = QuestionReferenceFilter::resolve(
            $year->yearID,
            $bookID,
            self::positiveInt($filters['chapterFilter'] ?? -1),
            self::positiveInt($filters['verseFilter'] ?? -1),
            $db
        );
        $rangePredicate = QuestionReferenceFilter::overlapPredicate($range);

        $scope ??= QuestionScope::forApp($app, $db);
        $bankID = self::positiveInt($filters['questionBankID'] ?? -1);
        if ($bankID > 0) {
            if (!$scope->canReadBank($bankID)) {
                throw new InvalidArgumentException('The selected question bank is not available.');
            }
            $bankPredicate = ['sql' => 'q.QuestionBankID = ?', 'params' => [$bankID]];
        } else {
            $bankPredicate = $scope->readPredicate('q');
        }

        $where = [
            'q.IsDeleted = 0',
            'q.IsActive = 1',
            'q.Type IN (?, ?)',
            'bStart.YearID = ?',
            '(q.EndVerseID IS NULL OR bEnd.YearID = ?)',
            $bankPredicate['sql'],
            $rangePredicate['sql'],
        ];
        $params = [
            Question::getBibleQnAType(),
            Question::getBibleQnAFillType(),
            $year->yearID,
            $year->yearID,
            ...$bankPredicate['params'],
            ...$rangePredicate['params'],
        ];
        $languageID = self::positiveInt($filters['languageID'] ?? -1);
        if ($languageID > 0) {
            $where[] = 'q.LanguageID = ?';
            $params[] = $languageID;
        }
        $where[] = 'q.QuestionID IN (' . implode(', ', array_fill(0, count($selectedQuestionIDs), '?')) . ')';
        array_push($params, ...$selectedQuestionIDs);

        $stmt = $db->prepare('
            SELECT DISTINCT q.QuestionID
            FROM Questions q
            INNER JOIN Verses vStart ON vStart.VerseID = q.StartVerseID
            INNER JOIN Chapters cStart ON cStart.ChapterID = vStart.ChapterID
            INNER JOIN Books bStart ON bStart.BookID = cStart.BookID
            LEFT JOIN Verses vEnd ON vEnd.VerseID = q.EndVerseID
            LEFT JOIN Chapters cEnd ON cEnd.ChapterID = vEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $questionIDs = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($questionIDs);
        $expectedQuestionIDs = $selectedQuestionIDs;
        sort($expectedQuestionIDs);
        if ($questionIDs !== $expectedQuestionIDs) {
            throw new InvalidArgumentException('One or more selected questions are outside the chosen range or scope.');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $upsertSQL = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? '
                    INSERT INTO UserFlagged (QuestionID, UserID, Reason)
                    VALUES (?, ?, ?)
                    ON CONFLICT(UserID, QuestionID) DO UPDATE SET
                        Reason = excluded.Reason,
                        DateTimeFlagged = CURRENT_TIMESTAMP
                '
                : '
                    INSERT INTO UserFlagged (QuestionID, UserID, Reason)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        Reason = VALUES(Reason),
                        DateTimeFlagged = CURRENT_TIMESTAMP
                ';
            $upsert = $db->prepare($upsertSQL);
            foreach ($questionIDs as $questionID) {
                $upsert->execute([$questionID, $userID, $reason]);
            }
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        return count($questionIDs);
    }

    private static function positiveInt(mixed $value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value !== false && $value > 0 ? $value : -1;
    }

    /** @return list<int> */
    private static function positiveIDs(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $ids = [];
        foreach ($values as $value) {
            $id = self::positiveInt($value);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}
