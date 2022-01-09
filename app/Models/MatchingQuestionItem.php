<?php

namespace App\Models;

use PDO;

class MatchingQuestionItem
{
    public $matchingQuestionItemID;
    public $question;
    public $answer;
    public $dateCreated;
    public $dateModified;
    public $isDeleted;
    public $startVerseID;
    public $endVerseID;
    public $creatorID;
    public $lastEditedByID;
    public $matchingQuestionSetID;

    public function __construct(int $matchingQuestionItemID, string $question, string $answer)
    {
        $this->matchingQuestionItemID = $matchingQuestionItemID;
        $this->question = $question;
        $this->answer = $answer;
        $this->isDeleted = false;
        $this->startVerseID = null;
        $this->endVerseID = null;
    }

    private static function loadMatchingSets(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT MatchingQuestionItemID, Question, Answer, DateCreated, DateModified,
                IsDeleted, StartVerseID, EndVerseID, CreatorID, LastEditedByID, MatchingQuestionSetID
            FROM MatchingQuestionItems
            ' . $whereClause . '
            ORDER BY lower(Question), lower(Answer)';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $item = new MatchingQuestionItem($row['MatchingQuestionItemID'], $row['Question'], $row['Answer']);
            $item->dateCreated = $row['DateCreated'];
            $item->dateModified = $row['DateModified'];
            $item->isDeleted = $row['IsDeleted'];
            $item->startVerseID = $row['StartVerseID'];
            $item->endVerseID = $row['EndVerseID'];
            $item->creatorID = $row['CreatorID'];
            $item->lastEditedByID = $row['LastEditedByID'];
            $item->matchingQuestionSetID = $row['MatchingQuestionSetID'];
            $output[] = $item;
        }
        return $output;
    }

    public static function loadAllQuestionsForSet(int $matchingQuestionSetID, PDO $db): array
    {
        return self::loadMatchingSets(' WHERE MatchingQuestionSetID = ? AND IsDeleted = 0 ', [ $matchingQuestionSetID ], $db);
    }

    public static function loadQuestionItemByID(int $questionItemID, PDO $db): ?MatchingQuestionItem
    {
        $data = self::loadMatchingSets(' WHERE MatchingQuestionItemID = ? AND IsDeleted = 0 ', [ $questionItemID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadQuestionItemsInSets(array $matchingQuestionSetIDs, PDO $db): array
    {
        if (count($matchingQuestionSetIDs) === 0) {
            return [];
        }
        return self::loadMatchingSets(' WHERE MatchingQuestionSetID IN (' . implode(',', $matchingQuestionSetIDs) . ') AND IsDeleted = 0 ', [], $db);
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO MatchingQuestionItems (
                Question, Answer, DateCreated, DateModified,
                IsDeleted, StartVerseID, EndVerseID, CreatorID, 
                LastEditedByID, MatchingQuestionSetID) 
            VALUES (?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->question,
            $this->answer,
            $this->dateCreated,
            $this->dateModified,
            (int)$this->isDeleted,
            $this->startVerseID,
            $this->endVerseID,
            $this->creatorID,
            $this->lastEditedByID,
            $this->matchingQuestionSetID
        ]);
        $this->matchingQuestionSetID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE MatchingQuestionItems 
            SET Question = ?, Answer = ?, DateCreated = ?, DateModified = ?,
            IsDeleted = ?, StartVerseID = ?, EndVerseID = ?, CreatorID = ?, 
            LastEditedByID = ?, MatchingQuestionSetID = ?
            WHERE MatchingQuestionItemID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->question,
            $this->answer,
            $this->dateCreated,
            $this->dateModified,
            (int)$this->isDeleted,
            $this->startVerseID,
            $this->endVerseID,
            $this->creatorID,
            $this->lastEditedByID,
            $this->matchingQuestionSetID,
            $this->matchingQuestionItemID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'UPDATE MatchingQuestionItems SET IsDeleted = 1 WHERE MatchingQuestionItemID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->matchingQuestionItemID ]);
    }
}
