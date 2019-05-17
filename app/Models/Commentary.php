<?php

namespace App\Models;

use PDO;

class Commentary
{
    public $commentaryID;
    public $number;
    public $topicName;

    public $yearID;

    public function __construct(int $commentaryID, int $number)
    {
        $this->commentaryID = $commentaryID;
        $this->number = $number;
    }

    public function getName()
    {
        return 'SDA Commentary Volume ' . $this->number;
    }

    public function getDisplayValue()
    {
        return $this->getName() . ' - ' . $this->topicName;
    }

    private function loadCommentaries(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT DISTINCT CommentaryID, Number, TopicName, YearID
            FROM Commentaries 
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
            $output[] = $commentary;
        }
        return $output;
    }

    public function loadCommentariesForYear(int $yearID, PDO $db) : array
    {
        return Commentary::loadCommentaries('WHERE YearID = ?', [$yearID], $db);
    }

    public function loadCommentariesWithActiveQuestions(int $yearID, PDO $db) : array
    {
        $query = '
            SELECT DISTINCT c.CommentaryID, Number, TopicName
            FROM Commentaries c 
                JOIN Questions q ON c.CommentaryID = q.CommentaryID
            WHERE YearID = ? AND q.IsDeleted = 0
            ORDER BY Number';
        $stmt = $db->prepare($query);
        $stmt->execute([ $yearID ]);
        $data = $commentaryStmt->fetchAll();

        $output = [];
        foreach ($data as $row) {
            $commentary = new Commentary($row['CommentaryID'], $row['Number']);
            $commentary->topicName = $row['TopicName'];
            $commentary->yearID = $yearID;
            $output[] = $commentary;
        }
        return $output;
    }
}
