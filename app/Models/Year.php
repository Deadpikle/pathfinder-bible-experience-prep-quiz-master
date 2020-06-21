<?php

namespace App\Models;

use PDO;

class Year
{
    public $yearID;
    public $year;
    public $isCurrent;

    public function __construct(int $yearID, int $year)
    {
        $this->yearID = $yearID;
        $this->year = $year;
        $this->isCurrent = false;
    }

    private static function loadYears(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT YearID, Year, IsCurrent
            FROM Years
            ' . $whereClause . '
            ORDER BY Year';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $year = new Year($row['YearID'], $row['Year']);
            $year->isCurrent = $row['IsCurrent'];
            $output[] = $year;
        }
        return $output;
    }

    public static function loadAllYears(PDO $db) : array
    {
        return Year::loadYears('', [], $db);
    }

    public static function loadYearByID(int $yearID, PDO $db) : ?Year
    {
        $data = Year::loadYears(' WHERE YearID = ? ', [ $yearID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadCurrentYear(PDO $db) : ?Year
    {
        $years = Year::loadYears('WHERE IsCurrent = 1', [], $db);
        return count($years) > 0 ? $years[0] : null;
    }

    public static function addYear(int $yearNumber, PDO $db) : bool
    {
        $query = 'SELECT 1 FROM Years WHERE Year = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$yearNumber]);
        $yearData = $stmt->fetchAll();
        if ($yearData === false || count($yearData) > 0) {
            // year already exists; don't add it!
            return false;
        }
        $params = [
            $yearNumber, 
            0
        ];
        $query = '
            INSERT INTO Years (Year, IsCurrent) VALUES (?, ?)
        ';
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return true;
    }

    public static function makeYearCurrentYear(int $yearID, PDO $db)
    {
        // clear current year
        $query = 'UPDATE Years SET IsCurrent = 0;';
        $stmt = $db->prepare($query);
        $stmt->execute([]);
        // set new current year
        $query = 'UPDATE Years SET IsCurrent = 1 WHERE YearID = ?;';
        $stmt = $db->prepare($query);
        $stmt->execute([$yearID]);
    }
}
