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
    public $year;

    public function __construct(int $studyGuideID, string $displayName)
    {
        $this->studyGuideID = $studyGuideID;
        $this->displayName = $displayName;
    }

    private function loadStudyGuides(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT StudyGuideID, DisplayName, FileName, StudyGuides.YearID, Years.Year
            FROM StudyGuides
                JOIN Years ON StudyGuides.YearID = Years.YearID
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
            $guide->year = $row['Year'];
            $output[] = $guide;
        }
        return $output;
    }

    public static function loadCurrentStudyGuides(Year $year, PDO $db) : array
    {
        return StudyGuide::loadStudyGuides(' WHERE YearID = ? ', [ $year->yearID ], $db);
    }

    public static function loadAllStudyGuides(PDO $db) : array
    {
        return StudyGuide::loadStudyGuides('', [ ], $db);
    }

    public static function loadStudyGuideByID(int $studyGuideID, PDO $db) : ?StudyGuide
    {
        $data = StudyGuide::loadStudyGuides(' WHERE StudyGuideID = ? ', [ $studyGuideID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function createStudyGuide(string $fileName, string $displayName, int $yearID, PDO $db)
    {
        $query = 'INSERT INTO StudyGuides (FileName, DisplayName, YearID) VALUES (?, ?, ?)';
        $stmt = $db->prepare($query);
        $stmt->execute([
            'uploads/' . $fileName,
            trim($displayName),
            $yearID
        ]);
    }

    public static function renameStudyGuide(int $studyGuideID, string $displayName, PDO $db)
    {
        $query = 'UPDATE StudyGuides SET DisplayName = ? WHERE StudyGuideID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            trim($displayName),
            $studyGuideID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM StudyGuides WHERE StudyGuideID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->studyGuideID
        ]);
        if (file_exists($this->fileName)) {
            unlink($this->fileName);
        }
    }
}
