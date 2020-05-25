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

    private function loadClubs(string $whereClause, array $whereParams, PDO $db) : array
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

    public static function loadClub(int $clubID, PDO $db) : array
    {
        return Club::loadClubs(' WHERE ClubID = ? ', [$clubID], $db);
    }

    public static function loadClubsInConference(int $conferenceID, PDO $db) : array
    {
        return Club::loadClubs(' WHERE ConferenceID = ? ', [ $conferenceID ], $db);
    }

    public function loadAllClubsKeyedByID(PDO $db) : array
    {
        $clubs = Club::loadAllClubs($db);
        $clubsByID = [];
        foreach ($clubs as $club) {
            $clubsByID[$club->clubID] = $club;
        }
        return $clubsByID;
    }

    public function loadRecentlyActiveClubs(PDO $db) : array
    {
        // https://stackoverflow.com/a/26044915/3938401 -- 30 days ago
        $thirtyDaysAgo = date('Y-m-d 00:00:00', strtotime('-31 days'));
        $query = '
            SELECT c.ClubID, Name, URL, ConferenceID
            FROM Clubs c JOIN Users u ON c.ClubID = u.ClubID
            WHERE u.LastLoginDate > ?
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
}
