<?php

namespace App\Models;

use PDO;
use PDOException;

class UserFlagged
{
    public $userFlaggedID;
    public $userID;
    public $questionID;
    public $reason;

    public function __construct(int $userFlaggedID, int $userID, int $questionID)
    {
        $this->userFlaggedID = $userFlaggedID;
        $this->userID = $userID;
        $this->questionID = $questionID;
        $this->reason = FlagReason::UNKNOWN;
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

    public static function addFlagIfNecessary(int $questionID, int $userID, string $flagReason, PDO $db) : bool
    {
        try {
            // Make sure it's not in the table already before inserting!
            $params = [
                $questionID,
                $userID
            ];
    
            $query = 'SELECT 1 FROM UserFlagged WHERE QuestionID = ? AND UserID = ?';
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $didFind = count($stmt->fetchAll()) >= 1 ? true : false;
            if (!$didFind) {
                $query = ' INSERT INTO UserFlagged (QuestionID, UserID, Reason) VALUES (?, ?, ?) ';
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $questionID,
                    $userID,
                    $flagReason
                ]);
            }
    
            return true;
        }
        catch (PDOException $e) {
            return false;
        }
    }

    public static function isFlagged(int $questionID, int $userID, PDO $db) : bool
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

    public static function isFlaggedByAnyUser(int $questionID, PDO $db) : bool
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
