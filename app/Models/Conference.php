<?php

namespace App\Models;

use PDO;
use Throwable;

class Conference
{
    public int $conferenceID;
    public string $name;
    public string $url;
    public string $contactName;
    public string $contactEmail;

    public function __construct(int $conferenceID, string $name)
    {
        $this->conferenceID = $conferenceID;
        $this->name = $name;
        $this->url = '';
        $this->contactName = '';
        $this->contactEmail = '';
    }

    /** @return array<Conference> */
    private static function loadConferences(string $whereClause, array $whereParams, PDO $db): array
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

    /** @return array<Conference> */
    public static function loadAllConferences(PDO $db): array
    {
        return Conference::loadConferences('', [], $db);
    }

    /** @return array<Conference> */
    public static function loadAllNonAdminConferences(PDO $db): array
    {
        return Conference::loadConferences(' WHERE Name <> "Website Administrators"', [], $db);
    }

    /** @return array<Conference> */
    public static function loadNonWebsiteConferences(PDO $db): array
    {
        return Conference::loadConferences('WHERE Name NOT LIKE "%Website%"', [], $db);
    }

    public static function loadConferenceWithID(int $conferenceID, PDO $db): ?Conference
    {
        $data = Conference::loadConferences('WHERE ConferenceID = ?', [ $conferenceID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadAdminConference(PDO $db): ?Conference
    {
        $data = Conference::loadConferences(' WHERE Name = "Website Administrators"', [], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    /**
     * Returns array of conferences with keys being the ConferenceID and values being the Conference
     * 
     * @return array<int,Conference>
     */
    public static function loadAllConferencesByID(PDO $db): array
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
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
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
            $this->conferenceID = intval($db->lastInsertId());
            QuestionBank::ensureConferenceBanks(
                $this->conferenceID,
                $this->name,
                $db
            );

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function update(PDO $db)
    {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
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
            $db->prepare('
                UPDATE QuestionBanks qb
                INNER JOIN ConferenceQuestionBanks cqb
                    ON cqb.QuestionBankID = qb.QuestionBankID AND cqb.IsOverlay = 1
                SET qb.Name = ?, qb.DateModified = CURRENT_TIMESTAMP
                WHERE cqb.ConferenceID = ?
            ')->execute([trim($this->name) . ' Private', $this->conferenceID]);
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(PDO $db)
    {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $overlay = QuestionBank::loadConferenceOverlay($this->conferenceID, $db);
            $query = 'DELETE FROM Conferences WHERE ConferenceID = ?';
            $stmnt = $db->prepare($query);
            $stmnt->execute([ $this->conferenceID ]);
            if ($overlay !== null) {
                $db->prepare('
                    UPDATE QuestionBanks
                    SET IsDeleted = 1, DateModified = CURRENT_TIMESTAMP
                    WHERE QuestionBankID = ? AND IsGlobal = 0
                ')->execute([$overlay->questionBankID]);
            }
            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}
