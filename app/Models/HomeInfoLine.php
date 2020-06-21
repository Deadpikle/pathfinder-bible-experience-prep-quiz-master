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

    public static function loadLinesForSection(int $homeInfoSectionID, PDO $db) : array
    {
        $query = '
            SELECT his.Name AS SectionName,
                hil.Name AS LineName, hil.SortOrder AS LineSortOrder, hil.HomeInfoLineID AS LineID,
                hii.HomeInfoItemID AS ItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
            FROM HomeInfoSections his 
                JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
                LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
            WHERE hil.HomeInfoSectionID = ?
            ORDER BY LineSortOrder, ItemSortOrder';
        $sectionStmt = $db->prepare($query);
        $sectionStmt->execute([
            $homeInfoSectionID
        ]);
        $data = $sectionStmt->fetchAll();
        $output = [];

        $currentLine = null;

        foreach ($data as $row) {
            $lineID = $row['LineID'];
            if (($currentLine === null || $lineID !== $currentLine->homeInfoLineID) 
                && is_numeric($lineID) && $lineID > 0) {
                $currentLine = new HomeInfoLine($lineID, $row['LineName']);
                $currentLine->sortOrder = $row['LineSortOrder'];
                $currentLine->homeInfoSectionID = $homeInfoSectionID;
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
}
