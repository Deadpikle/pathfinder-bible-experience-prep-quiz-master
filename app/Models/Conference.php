<?php

namespace App\Models;

use PDO;

class Conference
{
    public $conferenceID;
    public $name;
    public $url;
    public $contactName;
    public $contactEmail;

    public function __construct(int $conferenceID, string $name)
    {
        $this->conferenceID = $conferenceID;
        $this->name = $name;
    }

    private static function loadConferences(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT ConferenceID, Name, URL, ContactName, ContactEmail
            FROM Conferences
            ' . $whereClause . '
            ORDER BY Name';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $conference = new Conference($row['ConferenceID'], $row['Name']);
            $conference->url = $row['URL'];
            $conference->contactName = $row['ContactName'];
            $conference->contactEmail = $row['ContactEmail'];
            $output[] = $conference;
        }
        return $output;
    }

    public static function loadAllConferences(PDO $db) : array
    {
        return Conference::loadConferences('', [], $db);
    }

    public static function loadAllNonAdminConferences(PDO $db) : array
    {
        return Conference::loadConferences(' WHERE Name <> "Website Administrators"', [], $db);
    }

    public static function loadNonWebsiteConferences(PDO $db) : array
    {
        return Conference::loadConferences('WHERE Name NOT LIKE "%Website%"', [], $db);
    }

    public static function loadConferenceWithID(int $conferenceID, PDO $db) : ?Conference
    {
        $data = Conference::loadConferences('WHERE ConferenceID = ?', [ $conferenceID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadAdminConference(PDO $db) : ?Conference
    {
        $data = Conference::loadConferences(' WHERE Name = "Website Administrators"', [], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    /**
     * Returns array of conferences with keys being the ConferenceID and values being the Conference
     */
    public static function loadAllConferencesByID(PDO $db) : array
    {
        $conferences = Conference::loadConferences('', [], $db);
        $output = [];
        foreach ($conferences as $conference) {
            $output[$conference->conferenceID] = $conference;
        }
        return $output;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO Conferences (Name, URL, ContactName, ContactEmail) 
            VALUES (?, ?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->url,
            $this->contactName,
            $this->contactEmail
        ]);
        $this->conferenceID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE Conferences SET Name = ?, URL = ?, ContactName = ?, ContactEmail = ?
            WHERE ConferenceID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->url,
            $this->contactName,
            $this->contactEmail,
            $this->conferenceID,
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Conferences WHERE ConferenceID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->conferenceID ]);
    }
}
