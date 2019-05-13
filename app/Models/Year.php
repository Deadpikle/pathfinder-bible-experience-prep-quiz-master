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

    private function loadYears(string $whereClause, array $whereParams, PDO $db) : array
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

    public function loadCurrentYear(PDO $db) : ?Year
    {
        $years = Year::loadYears('WHERE IsCurrent = 1', [], $db);
        return count($years) > 0 ? $years[0] : null;
    }
}
