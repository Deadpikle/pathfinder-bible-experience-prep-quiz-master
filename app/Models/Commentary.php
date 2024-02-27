<?php

namespace App\Models;

use PDO;

class Commentary
{
    public int $commentaryID;
    public int $number;
    public string $topicName;

    public int $yearID;
    public int $year; // shortcut to year number via year ID

    public string $displayName; // set once on load for easier JS usage (TODO: use serializable PHP stuff to add this to serialized data)

    public function __construct(int $commentaryID, int $number)
    {
        $this->commentaryID = $commentaryID;
        $this->number = $number;
        $this->topicName = '';
        $this->yearID = -1;
        $this->year = 0;
        $this->displayName = '';
    }

    public function getName(): string
    {
        return 'SDA Commentary Volume ' . $this->number;
    }

    public function getDisplayValue(): string
    {
        return $this->getName() . ' - ' . $this->topicName;
    }

    /** @return array<Commentary> */
    private static function loadCommentaries(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT DISTINCT CommentaryID, Number, TopicName, Years.YearID, Years.Year
            FROM Commentaries JOIN Years ON Commentaries.YearID = Years.YearID
            ' . $whereClause . '
            ORDER BY Number';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();

        $output = [];
        foreach ($data as $row) {
            $commentary = new Commentary($row['CommentaryID'], $row['Number']);
            $commentary->topicName = $row['TopicName'];
            $commentary->yearID = $row['YearID'];
            $commentary->displayName = $commentary->getDisplayValue();
            $commentary->year = $row['Year'];
            $output[] = $commentary;
        }
        return $output;
    }

    /** @return array<Commentary> */
    public static function loadAllCommentaries(PDO $db): array
    {
        return Commentary::loadCommentaries('', [], $db);
    }

    /** @return array<Commentary> */
    public static function loadAllCommentariesKeyedByID(PDO $db): array
    {
        $commentaries = self::loadAllCommentaries($db);
        $commentariesByID = [];
        foreach ($commentaries as $commentary) {
            $commentariesByID[$commentary->commentaryID] = $commentary;
        }
        return $commentariesByID;
    }

    /** @return array<Commentary> */
    public static function loadCommentariesForYear(int $yearID, PDO $db): array
    {
        return Commentary::loadCommentaries('WHERE Years.YearID = ?', [$yearID], $db);
    }

    public static function loadCommentaryByID(int $commentaryID, PDO $db): ?Commentary
    {
        $data = Commentary::loadCommentaries(' WHERE CommentaryID = ? ', [ $commentaryID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    /** @return array<Commentary> */
    public static function loadCommentariesWithActiveQuestions(int $yearID, PDO $db): array
    {
        $query = '
            SELECT DISTINCT c.CommentaryID, Number, TopicName, Years.Year
            FROM Commentaries c 
                JOIN Questions q ON c.CommentaryID = q.CommentaryID
                JOIN Years ON Commentaries.YearID = Years.YearID
            WHERE Years.YearID = ? AND q.IsDeleted = 0
            ORDER BY Number';
        $stmt = $db->prepare($query);
        $stmt->execute([ $yearID ]);
        $data = $stmt->fetchAll();

        $output = [];
        foreach ($data as $row) {
            $commentary = new Commentary($row['CommentaryID'], $row['Number']);
            $commentary->topicName = $row['TopicName'];
            $commentary->yearID = $yearID;
            $commentary->year = $row['Year'];
            $output[] = $commentary;
        }
        return $output;
    }

    public static function createCommentary(int $number, string $topic, int $yearID, PDO $db)
    {
        $topic = trim($topic);
        $query = 'SELECT 1 FROM Commentaries WHERE Number = ? AND TopicName = ? AND YearID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $number,
            $topic,
            $yearID
        ]);
        $data = $stmt->fetchAll();
        // make sure book doesn't exist
        if ($data !== true && count($data) == 0) {
            $query = '
                INSERT INTO Commentaries (Number, TopicName, YearID) VALUES (?, ?, ?)
            ';
            $stmt = $db->prepare($query);
            $stmt->execute([
                $number,
                $topic,
                $yearID
            ]);
        }
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Commentaries WHERE CommentaryID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->commentaryID
        ]);
    }
}
