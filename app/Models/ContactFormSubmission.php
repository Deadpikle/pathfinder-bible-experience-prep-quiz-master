<?php

namespace App\Models;

use PDO;

class ContactFormSubmission
{
    public int $contactFormSubmissionID;
    public string $title;
    public string $personName;
    public string $email;
    public string $message;
    public string $dateTimeSubmitted;

    public string $club;
    public string $conference;
    public string $type;

    public function __construct(int $contactFormSubmissionID, string $title)
    {
        $this->contactFormSubmissionID = $contactFormSubmissionID;
        $this->title = $title;
        $this->personName = '';
        $this->email = '';
        $this->message = '';
        $this->dateTimeSubmitted = '';
        $this->club = '';
        $this->conference = '';
        $this->type = '';
    }

    /** @return array<ContactFormSubmission> */
    private static function loadSubmissions(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT ContactFormSubmissionID, Title, PersonName, Email, Message, DateTimeSubmitted,
                Club, Conference, Type
            FROM ContactFormSubmissions
            ' . $whereClause . '
            ORDER BY DateTimeSubmitted DESC';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $submission = new ContactFormSubmission($row['ContactFormSubmissionID'], $row['Title']);
            $submission->personName = $row['PersonName'];
            $submission->email = $row['Email'];
            $submission->message = $row['Message'];
            $submission->dateTimeSubmitted = $row['DateTimeSubmitted'];
            $submission->club = $row['Club'];
            $submission->conference = $row['Conference'];
            $submission->type = $row['Type'];
            $output[] = $submission;
        }
        return $output;
    }

    /** @return array<ContactFormSubmission> */
    public static function loadAllSubmissions(PDO $db): array
    {
        return self::loadSubmissions('', [], $db);
    }

    public static function loadSubmissionByID(int $contactFormSubmissionID, PDO $db): ?ContactFormSubmission
    {
        $data = self::loadSubmissions(' WHERE ContactFormSubmissionID = ? ', [ $contactFormSubmissionID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO ContactFormSubmissions (Title, PersonName, Email, Message, DateTimeSubmitted,
                Club, Conference, Type) 
            VALUES (?, ?, ?, ?, ?,
                ?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->title,
            $this->personName,
            $this->email,
            $this->message,
            date('Y-m-d H:i:s'),
            $this->club,
            $this->conference,
            $this->type,
        ]);
        $this->contactFormSubmissionID = intval($db->lastInsertId());
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE ContactFormSubmissions SET 
                Title = ?, PersonName = ?, Email = ?, Message = ?, DateTimeSubmitted = ?
            WHERE ContactFormSubmissionID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->title,
            $this->personName,
            $this->email,
            $this->message,
            $this->dateTimeSubmitted
        ]);
    }

    public function delete(PDO $db): void
    {
        $query = 'DELETE FROM ContactFormSubmissions WHERE ContactFormSubmissionID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->contactFormSubmissionID
        ]);
    }
}
