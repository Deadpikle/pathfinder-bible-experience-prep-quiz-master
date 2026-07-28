<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOException;
use Throwable;

class UserAnswer
{
    public int $userAnswerID;
    public string $answer;
    public string $dateAnswered;
    public bool $wasCorrect;
    
    public int $questionID;
    public int $userID;

    public function __construct(int $userAnswerID, string $answer)
    {
        $this->userAnswerID = $userAnswerID;
        $this->answer = $answer;
        $this->dateAnswered = '';
        $this->wasCorrect = false;
        $this->questionID = -1;
        $this->userID = -1;
    }

    public static function deleteUserAnswers(int $userID, PDO $db)
    {
        $query = 'DELETE FROM UserAnswers WHERE UserID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$userID]);
    }

    /**
     * Save current mastery state for the authenticated user.
     *
     * Client-provided user IDs and timestamps are intentionally ignored. Full
     * attempt history is stored separately; this table remains one row per
     * user/question for fast quiz generation.
     */
    public static function saveUserAnswers(array $answers, PDO $db): bool
    {
        return self::saveUserAnswersForUser(
            $answers,
            User::currentUserID(),
            $db
        );
    }

    /**
     * Server-side entry point for controllers, workers, and attempt completion.
     *
     * @param array<array{questionID: mixed, userAnswer?: mixed, correct?: mixed}> $answers
     */
    public static function saveUserAnswersForUser(
        array $answers,
        int $userID,
        PDO $db,
        ?DateTimeInterface $answeredAt = null
    ): bool {
        if ($userID <= 0) {
            return false;
        }

        $answeredAt ??= new DateTimeImmutable();
        $dateAnswered = $answeredAt->format('Y-m-d H:i:s');
        $ownsTransaction = !$db->inTransaction();

        try {
            $upsert = $db->prepare('
                INSERT INTO UserAnswers
                    (Answer, DateAnswered, WasCorrect, QuestionID, UserID)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    Answer = VALUES(Answer),
                    DateAnswered = VALUES(DateAnswered),
                    WasCorrect = VALUES(WasCorrect)
            ');
            if ($ownsTransaction) {
                $db->beginTransaction();
            }

            foreach ($answers as $answer) {
                if (!is_array($answer)) {
                    throw new PDOException('Each answer must be an object.');
                }
                $questionID = filter_var(
                    $answer['questionID'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if ($questionID === false) {
                    throw new PDOException('A valid question ID is required.');
                }

                $rawAnswer = $answer['userAnswer'] ?? '';
                if (!is_scalar($rawAnswer) && $rawAnswer !== null) {
                    throw new PDOException('The answer must be text.');
                }

                $wasCorrect = filter_var(
                    $answer['correct'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

                $upsert->execute([
                    (string)($rawAnswer ?? ''),
                    $dateAnswered,
                    (int)$wasCorrect,
                    (int)$questionID,
                    $userID,
                ]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
            return true;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }
}
