<?php

namespace App\Models;

use PDO;

class HomeInfoLine
{
    public $homeInfoLineID;
    public $name;
    public $sortOrder;

    public $homeInfoSectionID;

    public $items;

    public function __construct(int $homeInfoLineID, string $name)
    {
        $this->homeInfoLineID = $homeInfoLineID;
        $this->name = $name;
    }

    private static function loadLines(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT his.Name AS SectionName, hil.HomeInfoSectionID,
                hil.Name AS LineName, hil.SortOrder AS LineSortOrder, hil.HomeInfoLineID AS LineID,
                hii.HomeInfoItemID AS ItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
            FROM HomeInfoSections his 
                JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
                LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
            ' . $whereClause . '
            ORDER BY LineSortOrder, ItemSortOrder';
        $sectionStmt = $db->prepare($query);
        $sectionStmt->execute($whereParams);
        $data = $sectionStmt->fetchAll();
        $output = [];

        $currentLine = null;

        foreach ($data as $row) {
            $lineID = $row['LineID'];
            if (($currentLine === null || $lineID !== $currentLine->homeInfoLineID) 
                && is_numeric($lineID) && $lineID > 0) {
                $currentLine = new HomeInfoLine($lineID, $row['LineName']);
                $currentLine->sortOrder = $row['LineSortOrder'];
                $currentLine->homeInfoSectionID = $row['HomeInfoSectionID'];
                $output[] = $currentLine;
            }
            $itemID = $row['ItemID'];
            if ($itemID !== null && is_numeric($itemID) && $itemID > 0) {
                $item = new HomeInfoItem($itemID);
                $item->text = $row['Text'];
                $item->isLink = $row['IsLink'];
                $item->url = $row['URL'];
                $item->sortOrder = $row['ItemSortOrder'];
                $currentLine->items[] = $item;
            }
        }
        return $output;
    }

    public static function loadLinesForSection(int $homeInfoSectionID, PDO $db) : array
    {
        return HomeInfoLine::loadLines(' WHERE hil.HomeInfoSectionID = ? ', [ $homeInfoSectionID ], $db);
    }

    public static function loadLineByID(int $homeInfoLineID, PDO $db) : ?HomeInfoLine
    {
        $data = HomeInfoLine::loadLines(' WHERE hil.HomeInfoLineID = ? ', [ $homeInfoLineID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO HomeInfoLines (Name, SortOrder, HomeInfoSectionID) 
            VALUES (?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            (int)$this->sortOrder,
            (int)$this->homeInfoSectionID
        ]);
        $this->homeInfoLineID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE HomeInfoLines SET Name = ?, SortOrder = ?, HomeInfoSectionID = ?
            WHERE HomeInfoLineID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            (int)$this->sortOrder,
            (int)$this->homeInfoSectionID,
            $this->homeInfoLineID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM HomeInfoLines WHERE HomeInfoLineID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->homeInfoLineID ]);
    }
}
