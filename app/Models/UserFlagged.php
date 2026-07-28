<?php

namespace App\Models;

use PDO;
use PDOException;

class UserFlagged
{
    public int $userFlaggedID;
    public int $userID;
    public int $questionID;
    public FlagReason $reason;

    public string $dateTimeFlagged;

    public function __construct(int $userFlaggedID, int $userID, int $questionID)
    {
        $this->userFlaggedID = $userFlaggedID;
        $this->userID = $userID;
        $this->questionID = $questionID;
        $this->reason = FlagReason::UNKNOWN;
        $this->dateTimeFlagged = '2000-12-07 10:02:00';
    }

    public static function deleteFlag(int $questionID, int $userID, PDO $db)
    {
        $query = 'DELETE FROM UserFlagged WHERE QuestionID = ? AND UserID = ?';
        $params = [
            $questionID,
            $userID
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    public static function deleteAllFlagsForQuestion(int $questionID, PDO $db)
    {
        $query = 'DELETE FROM UserFlagged WHERE QuestionID = ?';
        $params = [
            $questionID
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    public static function addFlagIfNecessary(int $questionID, int $userID, string $flagReason, PDO $db): bool
    {
        if ($questionID <= 0 || $userID <= 0) {
            return false;
        }

        try {
            // The unique (UserID, QuestionID) key makes this atomic under
            // concurrent requests; the no-op update preserves the first reason.
            $query = '
                INSERT INTO UserFlagged (QuestionID, UserID, Reason)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE UserFlaggedID = UserFlaggedID
            ';
            $stmt = $db->prepare($query);
            $stmt->execute([$questionID, $userID, $flagReason]);
            return true;
        } catch (PDOException $exception) {
            return false;
        }
    }

    public static function addFlagForCurrentUser(
        int $questionID,
        string $flagReason,
        PDO $db
    ): bool {
        return self::addFlagIfNecessary(
            $questionID,
            User::currentUserID(),
            $flagReason,
            $db
        );
    }

    public static function isFlagged(int $questionID, int $userID, PDO $db): bool
    {
        $query = ' SELECT UserFlaggedID FROM UserFlagged WHERE QuestionID = ? AND UserID = ? ';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $questionID,
            $userID
        ]);
        $data = $stmt->fetchAll();
        return count($data) > 0;
    }

    public static function isFlaggedByAnyUser(int $questionID, PDO $db): bool
    {
        $query = ' SELECT UserFlaggedID FROM UserFlagged WHERE QuestionID = ? ';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $questionID,
        ]);
        $data = $stmt->fetchAll();
        return count($data) > 0;
    }
}
