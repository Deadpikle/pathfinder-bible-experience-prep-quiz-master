<?php

namespace App\Models;

use PDO;

class MatchingQuestionSet
{
    public $matchingQuestionSetID;
    public $name;
    public $description;
    public $isDeleted;
    public $languageID;
    public $yearID;

    /** @var array<MatchingQuestionItem> $questions */
    public $questions;

    public function __construct(int $matchingQuestionSetID, string $name)
    {
        $this->matchingQuestionSetID = $matchingQuestionSetID;
        $this->name = $name;
        $this->isDeleted = false;
        $this->questions = [];
    }

    private static function loadMatchingSets(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT MatchingQuestionSetID, Name, Description, IsDeleted, LanguageID, YearID
            FROM MatchingQuestionSets
            ' . $whereClause . '
            ORDER BY lower(Name)';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        $setIDs = [];
        $setIDsToSets = [];
        /** @var array<int, MatchingQuestionSet> $setIDsToSets */
        foreach ($data as $row) {
            $set = new MatchingQuestionSet($row['MatchingQuestionSetID'], $row['Name']);
            $setIDs[] = $set->matchingQuestionSetID;
            $setIDsToSets[$set->matchingQuestionSetID] = $set;
            $set->description = $row['Description'];
            $set->languageID = $row['LanguageID'];
            $set->isDeleted = $row['IsDeleted'];
            $set->yearID = $row['YearID'];
            $output[] = $set;
        }
        // load questions
        $questionItems = MatchingQuestionItem::loadQuestionItemsInSets($setIDs, $db);
        foreach ($questionItems as $item) {
            /** @var MatchingQuestionItem $item */
            $setIDsToSets[$item->matchingQuestionSetID]->questions[] = $item;
        }
        return $output;
    }

    public static function loadAllMatchingSets(PDO $db): array
    {
        return self::loadMatchingSets(' WHERE IsDeleted = 0 ', [], $db);
    }

    public static function loadAllMatchingSetsForYear(int $yearID, PDO $db): array
    {
        return self::loadMatchingSets(' WHERE IsDeleted = 0 AND YearID = ? ', [ $yearID ], $db);
    }

    public static function loadMatchingSetByID(int $matchingSetID, PDO $db): ?MatchingQuestionSet
    {
        $data = self::loadMatchingSets(' WHERE MatchingQuestionSetID = ? AND IsDeleted = 0 ', [ $matchingSetID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO MatchingQuestionSets (Name, Description, LanguageID, YearID, IsDeleted) 
            VALUES (?, ?, ?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->description,
            $this->languageID,
            $this->yearID,
            (int)$this->isDeleted,
        ]);
        $this->matchingQuestionSetID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE MatchingQuestionSets SET Name = ?, Description = ?, LanguageID = ?, YearID = ?, IsDeleted = ?
            WHERE MatchingQuestionSetID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            $this->description,
            $this->languageID,
            $this->yearID,
            (int)$this->isDeleted,
            $this->matchingQuestionSetID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'UPDATE MatchingQuestionSets SET IsDeleted = 1 WHERE MatchingQuestionSetID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->matchingQuestionSetID ]);
    }
}
