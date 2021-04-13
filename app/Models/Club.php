<?php

namespace App\Models;

use PDO;

class Club
{
    public $clubID;
    public $name;
    public $url;
    
    public $conferenceID;

    public function __construct(int $clubID, string $name)
    {
        $this->clubID = $clubID;
        $this->name = $name;
    }

    private static function loadClubs(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT ClubID, Name, URL, ConferenceID 
            FROM Clubs
            ' . $whereClause . '
            ORDER BY Name';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $club = new Club($row['ClubID'], $row['Name']);
            $club->url = $row['URL'];
            $club->conferenceID = $row['ConferenceID'];
            $output[] = $club;
        }
        return $output;
    }

    public static function loadAllClubs(PDO $db) : array
    {
        return Club::loadClubs('', [], $db);
    }

    public static function loadClubByID(int $clubID, PDO $db) : ?Club
    {
        $data = Club::loadClubs(' WHERE ClubID = ? ', [$clubID], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadClubsInConference(int $conferenceID, PDO $db) : array
    {
        return Club::loadClubs(' WHERE ConferenceID = ? ', [ $conferenceID ], $db);
    }

    public static function loadAllClubsKeyedByID(PDO $db) : array
    {
        $clubs = Club::loadAllClubs($db);
        $clubsByID = [];
        foreach ($clubs as $club) {
            $clubsByID[$club->clubID] = $club;
        }
        return $clubsByID;
    }

    public static function loadRecentlyActiveClubs(PDO $db) : array
    {
        // https://stackoverflow.com/a/26044915/3938401 -- 30 days ago
        $thirtyDaysAgo = date('Y-m-d 00:00:00', strtotime('-31 days'));
        $query = '
            SELECT c.ClubID, Name, URL, ConferenceID
            FROM Clubs c JOIN Users u ON c.ClubID = u.ClubID
            WHERE u.LastLoginDate > ?
            GROUP BY c.ClubID
            ORDER BY c.Name';
        $stmt = $db->prepare($query);
        $stmt->execute([$thirtyDaysAgo]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $club = new Club($row['ClubID'], $row['Name']);
            $club->url = $row['URL'];
            $club->conferenceID = $row['ConferenceID'];
            $output[] = $club;
        }
        return $output;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO Clubs (Name, URL, ConferenceID) 
            VALUES (?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->url,
            $this->conferenceID
        ]);
        $this->clubID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE Clubs SET Name = ?, URL = ?, ConferenceID = ?
            WHERE ClubID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->url,
            $this->conferenceID,
            $this->clubID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Clubs WHERE ClubID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->clubID ]);
    }
}
