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

    public $wasDeleted;

    public function __construct(int $userID, string $username)
    {
        $this->userID = $userID;
        $this->username = $username;
        $this->wasDeleted = false;
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

    private static function loadUsers(string $whereClause, array $whereParams, PDO $db) : array
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

    public static function loadAllUsers(PDO $db) : array
    {
        return User::loadUsers(' WHERE WasDeleted = 0 ', [], $db);
    }

    public static function loadUsersInClub(int $clubID, PDO $db) : array
    {
        return User::loadUsers(' WHERE u.ClubID = ? AND Type = "Pathfinder" AND WasDeleted = 0 ', [ $clubID ], $db);
    }

    public static function loadUsersInConference(int $conferenceID, PDO $db) : array
    {
        return User::loadUsers(' WHERE c.ConferenceID = ? AND Type <> "ConferenceAdmin" AND Type <> "WebAdmin" AND WasDeleted = 0 ', [ $conferenceID ], $db);
    }

    public static function loadUserByID(int $userID, PDO $db) : ?User
    {
        $data = User::loadUsers(' WHERE UserID = ? AND WasDeleted = 0 ', [ $userID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }


    // http://stackoverflow.com/a/31107425/3938401
    // Note: may want to upgrade to https://github.com/ircmaxell/RandomLib at some point
    /**
    * Generate a random string, using a cryptographically secure 
    * pseudorandom number generator (random_int)
    * 
    * For PHP 7, random_int is a PHP core function
    * For PHP 5.x, depends on https://github.com/paragonie/random_compat
    * 
    * @param int $length      How many characters do we want?
    * @param string $keyspace A string of all possible characters
    *                         to select from
    * @return string
    */
    private function random_str($length, $keyspace = '23456789abcdefghjkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ') {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    private function generateEntryCode(PDO $db) {
        $didFindNewCode = false;
        // pre-create the sql statement for faster queries in the db
        $entryCodeQuery = 'SELECT 1 FROM Users WHERE EntryCode = ?';
        $entryCodeStmt = $db->prepare($entryCodeQuery);
        $entryCode = "";
        while (!$didFindNewCode) { // this seems dangerous, but given that there are 42 billion possible entry codes, we should be OK...
            $entryCode = $this->random_str(6);
            // Make sure entry code doesn't already exist in the db
            $entryCodeStmt->execute([$entryCode]);
            $didFindNewCode = count($entryCodeStmt->fetchAll()) == 1 ? false : true;
        }
        return $entryCode;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO Users (Username, UserTypeID, ClubID, EntryCode, CreatedByID, Password, LastLoginDate, WasDeleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $this->entryCode = $this->generateEntryCode($db);
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->username,
            $this->type->userTypeID,
            $this->clubID,
            $this->entryCode,
            User::currentUserID(),
            '',
            '1989-12-25 00:00:00', // default, not-yet-logged-in date
            (bool)$this->wasDeleted
        ]);
        $this->userID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE Users SET Username = ?, UserTypeID = ?, ClubID = ? 
            WHERE UserID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->username,
            $this->type->userTypeID,
            $this->clubID,
            $this->userID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'UPDATE Users SET WasDeleted = 1 WHERE UserID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->userID ]);
    }

    public static function updateLastLoginDateForUserID(int $userID, PDO $db)
    {
        $updateQuery = 'UPDATE Users SET LastLoginDate = ? WHERE UserID = ?';
        $statement = $db->prepare($updateQuery);
        $params = [
            date('Y-m-d H:i:s'),
            $userID
        ];
        $statement->execute($params);
    }
}
