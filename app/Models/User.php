<?php

namespace App\Models;

use PDO;

class User
{
    public $userID;
    public $username;
    public $entryCode;
    public $password; // not used
    public $lastLoginDate;
    
    public $type; // of type UserType

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
        return $_SESSION['UserID'] ?? -1;
    }

    public static function currentClubID() : int
    {
        return $_SESSION['ClubID'] ?? -1;
    }

    public static function currentClubName() : string
    {
        return $_SESSION['ClubName'] ?? '';
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

    public static function currentConferenceName() : string
    {
        return $_SESSION['ConferenceName'] ?? '';
    }

    public static function currentPreferredLanguageID() : int
    {
        return $_SESSION['PreferredLanguageID'] ?? -1;
    }

    private function loadUsers(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT UserID, Username, EntryCode, ut.UserTypeID, ut.Type, ut.DisplayName AS UserTypeDisplayName, 
                    u.ClubID, u.LastLoginDate
            FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
                LEFT JOIN Clubs c ON u.ClubID = c.ClubID 
            ' . $whereClause . '
            ORDER BY Username';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $user = new User($row['UserID'], $row['Username']);
            $user->entryCode = $row['EntryCode'];
            $user->lastLoginDate = $row['LastLoginDate'];
            $user->type = new UserType($row['UserTypeID'], $row['Type']);
            $user->type->displayName = $row['UserTypeDisplayName'];
            $user->clubID = $row['ClubID'];
            $output[] = $user;
        }
        return $output;
    }

    public function loadAllUsers(PDO $db) : array
    {
        return User::loadUsers('', [], $db);
    }

    public function loadUsersInClub(int $clubID, PDO $db) : array
    {
        return User::loadUsers(' WHERE u.ClubID = ? AND Type = "Pathfinder" ', [ $clubID ], $db);
    }

    public function loadUsersInConference(int $conferenceID, PDO $db) : array
    {
        return User::loadUsers(' WHERE c.ConferenceID = ? AND Type <> "ConferenceAdmin" AND Type <> "WebAdmin" ', [ $conferenceID ], $db);
    }
}
