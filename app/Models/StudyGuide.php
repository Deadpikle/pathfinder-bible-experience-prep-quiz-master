<?php

namespace App\Models;

use PDO;

use App\Models\Year;

class StudyGuide
{
    public $studyGuideID;
    public $displayName;
    public $fileName;
    
    public $yearID;

    public function __construct(int $studyGuideID, string $displayName)
    {
        $this->studyGuideID = $studyGuideID;
        $this->displayName = $displayName;
    }

    private function loadStudyGuides(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT StudyGuideID, DisplayName, FileName, YearID
            FROM StudyGuides
            ' . $whereClause . '
            ORDER BY DisplayName';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $guide = new StudyGuide($row['StudyGuideID'], $row['DisplayName']);
            $guide->fileName = $row['FileName'];
            $guide->yearID = $row['YearID'];
            $output[] = $guide;
        }
        return $output;
    }

    public function loadCurrentStudyGuides(Year $year, PDO $db) : array
    {
        return StudyGuide::loadStudyGuides(' WHERE YearID = ? ', [ $year->yearID ], $db);
    }
}
