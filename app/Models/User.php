<?php

namespace App\Models;

use PDO;

class User
{
    public $userID;
    public $username;
    public $entryCode;
    public $password;
    public $lastLoginDate;
    
    public $userType;

    public $clubID;
    public $createdByID;
    public $defaultLanguageID;

    public function __construct(int $userID, string $username)
    {
        $this->userID = $userID;
        $this->username = $username;
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION['UserID']);
    }

    public static function currentUserID() : int
    {
        return isset($_SESSION['UserID']) ? $_SESSION['UserID'] : -1;
    }

    public static function updatePreferredLanguage(int $userID, int $languageID, PDO $db)
    {
        $query = 'UPDATE Users SET PreferredLanguageID = ? WHERE UserID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $languageID, 
            $userID
        ]);
    }

    public static function currentConferenceID() : int
    {
        return $_SESSION['ConferenceID'] ?? -1;
    }
}
