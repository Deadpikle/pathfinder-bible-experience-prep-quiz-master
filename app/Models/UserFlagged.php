<?php

namespace App\Models;

use PDO;

class UserFlagged
{
    public $userFlaggedID;
    public $userID;
    public $questionID;

    public function __construct(int $userFlaggedID, int $userID, int $questionID)
    {
        $this->userFlaggedID = $userFlaggedID;
        $this->userID = $userID;
        $this->questionID = $questionID;
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
}
