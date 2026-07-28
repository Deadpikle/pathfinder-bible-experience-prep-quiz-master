<?php

namespace App\Models;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class QuizAttempt
{
    /**
     * Persist the immutable question list before a live quiz is shown.
     * The generated quiz array is enriched with attempt item IDs for completion.
     */
    public static function start(
        int $userID,
        int $yearID,
        ?int $languageID,
        array &$quizData,
        PDO $db
    ): int {
        $questions = $quizData['questions'] ?? [];
        if ($userID <= 0 || count($questions) === 0) {
            return -1;
        }

        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $attemptStmt = $db->prepare(
                'INSERT INTO QuizAttempts (UserID, YearID, LanguageID, StartedAt, DateModified)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $now = self::now();
            $attemptStmt->execute([$userID, $yearID, $languageID > 0 ? $languageID : null, $now, $now]);
            $attemptID = (int)$db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO QuizAttemptItems
                    (QuizAttemptID, QuestionID, SortOrder, QuestionType, QuestionSnapshot,
                     AnswerSnapshot, ReferenceSnapshot, PointsPossible)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $pointsPossible = 0;
            foreach ($questions as $index => $question) {
                $possible = max(0, (int)($question['points'] ?? 0));
                $itemStmt->execute([
                    $attemptID,
                    (int)($question['id'] ?? 0) ?: null,
                    $index,
                    (string)($question['type'] ?? ''),
                    trim((string)($question['question'] ?? '')),
                    trim((string)($question['answer'] ?? '')),
                    self::referenceForQuestion($question),
                    $possible,
                ]);
                $quizData['questions'][$index]['attemptItemID'] = (int)$db->lastInsertId();
                $pointsPossible += $possible;
            }

            $db->prepare('UPDATE QuizAttempts SET PointsPossible = ? WHERE QuizAttemptID = ?')
                ->execute([$pointsPossible, $attemptID]);
            if ($startedTransaction) {
                $db->commit();
            }
            return $attemptID;
        } catch (Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Complete an attempt once. Repeating the same request is idempotent; a changed
     * request for an already completed attempt produces a conflict.
     *
     * @return array{status:string,attempt:array<string,mixed>}
     */
    public static function complete(int $attemptID, int $userID, array $submittedAnswers, PDO $db): array
    {
        $db->beginTransaction();
        try {
            $attemptStmt = $db->prepare(
                'SELECT QuizAttemptID, UserID, IsCompleted, CompletionHash
                 FROM QuizAttempts WHERE QuizAttemptID = ? FOR UPDATE'
            );
            $attemptStmt->execute([$attemptID]);
            $attempt = $attemptStmt->fetch();
            if (!$attempt || (int)$attempt['UserID'] !== $userID) {
                throw new RuntimeException('Attempt not found or not owned by the current user.', 404);
            }

            $itemsStmt = $db->prepare(
                'SELECT QuizAttemptItemID, QuestionID, PointsPossible
                 FROM QuizAttemptItems WHERE QuizAttemptID = ? ORDER BY SortOrder'
            );
            $itemsStmt->execute([$attemptID]);
            $items = $itemsStmt->fetchAll();
            $normalised = self::normaliseAnswers($submittedAnswers, $items);
            $hash = self::completionHash($normalised);

            if ((int)$attempt['IsCompleted'] === 1) {
                if (!hash_equals((string)$attempt['CompletionHash'], $hash)) {
                    throw new RuntimeException('This attempt was already completed with different answers.', 409);
                }
                $db->commit();
                return ['status' => 'already-completed', 'attempt' => self::loadSummary($attemptID, $db)];
            }

            $updateItem = $db->prepare(
                'UPDATE QuizAttemptItems
                 SET UserAnswer = ?, WasCorrect = ?, PointsEarned = ?, AnsweredAt = ?
                 WHERE QuizAttemptItemID = ? AND QuizAttemptID = ?'
            );
            $upsertMastery = $db->prepare(
                'INSERT INTO UserQuestionMastery
                    (UserID, QuestionID, LastQuizAttemptItemID, TimesAttempted, TimesCorrect,
                     BestPointsEarned, LastPointsEarned, LastPointsPossible, IsMastered, LastAnsweredAt)
                 VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    LastQuizAttemptItemID = VALUES(LastQuizAttemptItemID),
                    TimesAttempted = TimesAttempted + 1,
                    TimesCorrect = TimesCorrect + VALUES(TimesCorrect),
                    BestPointsEarned = GREATEST(BestPointsEarned, VALUES(BestPointsEarned)),
                    LastPointsEarned = VALUES(LastPointsEarned),
                    LastPointsPossible = VALUES(LastPointsPossible),
                    IsMastered = VALUES(IsMastered),
                    LastAnsweredAt = VALUES(LastAnsweredAt)'
            );
            $upsertLegacy = $db->prepare(
                'INSERT INTO UserAnswers (Answer, DateAnswered, WasCorrect, QuestionID, UserID)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    Answer = VALUES(Answer), DateAnswered = VALUES(DateAnswered), WasCorrect = VALUES(WasCorrect)'
            );

            $answeredAt = self::now();
            $pointsEarned = 0;
            foreach ($normalised as $answer) {
                $updateItem->execute([
                    $answer['userAnswer'],
                    $answer['correct'] ? 1 : 0,
                    $answer['pointsEarned'],
                    $answeredAt,
                    $answer['attemptItemID'],
                    $attemptID,
                ]);
                $pointsEarned += $answer['pointsEarned'];

                if ($answer['questionID'] !== null) {
                    $upsertMastery->execute([
                        $userID,
                        $answer['questionID'],
                        $answer['attemptItemID'],
                        $answer['correct'] ? 1 : 0,
                        $answer['pointsEarned'],
                        $answer['pointsEarned'],
                        $answer['pointsPossible'],
                        $answer['correct'] ? 1 : 0,
                        $answeredAt,
                    ]);
                    $upsertLegacy->execute([
                        mb_substr($answer['userAnswer'], 0, 5000),
                        $answeredAt,
                        $answer['correct'] ? 1 : 0,
                        $answer['questionID'],
                        $userID,
                    ]);
                }
            }

            $finish = $db->prepare(
                'UPDATE QuizAttempts
                 SET IsCompleted = 1, CompletedAt = ?, PointsEarned = ?, CompletionHash = ?, DateModified = ?
                 WHERE QuizAttemptID = ? AND IsCompleted = 0'
            );
            $finish->execute([$answeredAt, $pointsEarned, $hash, $answeredAt, $attemptID]);
            if ($finish->rowCount() !== 1) {
                throw new RuntimeException('Attempt completion conflicted with another request.', 409);
            }

            $db->commit();
            return ['status' => 'completed', 'attempt' => self::loadSummary($attemptID, $db)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function normaliseAnswers(array $submittedAnswers, array $attemptItems): array
    {
        $itemsByID = [];
        foreach ($attemptItems as $item) {
            $itemsByID[(int)$item['QuizAttemptItemID']] = $item;
        }
        if (count($submittedAnswers) !== count($itemsByID)) {
            throw new RuntimeException('Every attempt item must be submitted exactly once.', 422);
        }

        $normalised = [];
        foreach ($submittedAnswers as $submitted) {
            if (!is_array($submitted)) {
                throw new RuntimeException('Each submitted answer must be an object.', 422);
            }
            $itemID = (int)($submitted['attemptItemID'] ?? 0);
            if ($itemID <= 0 || !isset($itemsByID[$itemID]) || isset($normalised[$itemID])) {
                throw new RuntimeException('The completion payload contains an invalid or duplicate item.', 422);
            }
            $item = $itemsByID[$itemID];
            $possible = max(0, (int)$item['PointsPossible']);
            $earned = (int)floor((float)($submitted['pointsEarned'] ?? 0));
            $earned = max(0, min($possible, $earned));
            $normalised[$itemID] = [
                'attemptItemID' => $itemID,
                'questionID' => isset($item['QuestionID']) ? (int)$item['QuestionID'] : null,
                'pointsPossible' => $possible,
                'pointsEarned' => $earned,
                'correct' => filter_var($submitted['correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'userAnswer' => mb_substr(trim((string)($submitted['userAnswer'] ?? '')), 0, 5000),
            ];
        }
        ksort($normalised, SORT_NUMERIC);
        return array_values($normalised);
    }

    public static function completionHash(array $normalisedAnswers): string
    {
        $hashable = array_map(
            static fn(array $answer): array => [
                'attemptItemID' => (int)$answer['attemptItemID'],
                'pointsEarned' => (int)$answer['pointsEarned'],
                'correct' => (bool)$answer['correct'],
                'userAnswer' => (string)$answer['userAnswer'],
            ],
            $normalisedAnswers
        );
        usort($hashable, static fn(array $a, array $b): int => $a['attemptItemID'] <=> $b['attemptItemID']);
        return hash('sha256', json_encode($hashable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    public static function loadSummary(int $attemptID, PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT qa.QuizAttemptID, qa.UserID, qa.YearID, qa.LanguageID, qa.StartedAt,
                    qa.CompletedAt, qa.IsCompleted, qa.PointsEarned, qa.PointsPossible,
                    COUNT(qai.QuizAttemptItemID) AS QuestionCount
             FROM QuizAttempts qa
             LEFT JOIN QuizAttemptItems qai ON qai.QuizAttemptID = qa.QuizAttemptID
             WHERE qa.QuizAttemptID = ?
             GROUP BY qa.QuizAttemptID, qa.UserID, qa.YearID, qa.LanguageID, qa.StartedAt,
                      qa.CompletedAt, qa.IsCompleted, qa.PointsEarned, qa.PointsPossible'
        );
        $stmt->execute([$attemptID]);
        return $stmt->fetch() ?: [];
    }

    public static function purgeIncomplete(int $days, PDO $db): int
    {
        $days = max(1, $days);
        $stmt = $db->prepare(
            'DELETE FROM QuizAttempts
             WHERE IsCompleted = 0 AND StartedAt < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? DAY)'
        );
        $stmt->bindValue(1, $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private static function referenceForQuestion(array $question): string
    {
        if (Question::isTypeBibleQnA((string)($question['type'] ?? ''))) {
            $reference = trim((string)($question['startBook'] ?? '')) . ' '
                . (string)($question['startChapter'] ?? '') . ':' . (string)($question['startVerse'] ?? '');
            if (($question['endVerse'] ?? '') !== '' && ($question['endVerse'] ?? '') != ($question['startVerse'] ?? '')) {
                $reference .= '-';
                if (($question['endChapter'] ?? '') != ($question['startChapter'] ?? '')) {
                    $reference .= (string)$question['endChapter'] . ':';
                }
                $reference .= (string)$question['endVerse'];
            }
            return trim($reference);
        }
        $topic = trim((string)($question['topic'] ?? ''));
        $pages = (string)($question['startPage'] ?? '');
        if (($question['endPage'] ?? '') !== '' && ($question['endPage'] ?? '') != ($question['startPage'] ?? '')) {
            $pages .= '-' . (string)$question['endPage'];
        }
        return trim($topic . ($pages !== '' ? ', pp. ' . $pages : ''));
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}
