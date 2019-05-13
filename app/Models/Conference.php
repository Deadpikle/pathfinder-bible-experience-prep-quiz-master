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

    public function loadConferences(PDO $db) : array
    {
        $conferences = [];
        $query = '
            SELECT ConferenceID, Name, URL, ContactName, ContactEmail
            FROM Conferences
            WHERE Name NOT LIKE "%Website%"
            ORDER BY Name';
        $stmt = $db->prepare($query);
        $stmt->execute([]);
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
}
