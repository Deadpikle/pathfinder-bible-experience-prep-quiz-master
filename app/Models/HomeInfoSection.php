<?php

namespace App\Models;

use PDO;

class HomeInfoSection
{
    public $homeInfoSectionID;
    public $name;
    public $subtitle;
    public $sortOrder;
    
    public $yearID;
    public $conferenceID;

    public $lines;

    public function __construct(int $homeInfoSectionID, string $name)
    {
        $this->homeInfoSectionID = $homeInfoSectionID;
        $this->name = $name;
        $this->lines = [];
    }

    public static function loadSections(Year $year, int $conferenceID, PDO $db) : array
    {
        $params = [
            $year->yearID
        ];
        $whereClause = " WHERE his.YearID = ? ";
        if ($conferenceID > 0) {
            $whereClause .= " AND his.ConferenceID = ? ";
            $params[] = $conferenceID;
        }
        $query = '
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, 
                his.Subtitle AS SectionSubtitle, his.SortOrder AS SectionSortOrder,
                hil.HomeInfoLineID AS LineID, hil.Name AS LineName, hil.SortOrder AS LineSortOrder,
                hii.HomeInfoItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
            FROM HomeInfoSections his 
                LEFT JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
                LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
            ' . $whereClause . '
            ORDER BY SectionSortOrder, LineSortOrder, ItemSortOrder';
        $sectionStmt = $db->prepare($query);
        $sectionStmt->execute($params);
        $data = $sectionStmt->fetchAll();
        $output = [];

        $currentSection = null;
        $currentLine = null;

        foreach ($data as $row) {
            $sectionID = $row['SectionID'];
            if ($currentSection === null || $sectionID !== $currentSection->homeInfoSectionID) {
                // on a new section
                $currentSection = new HomeInfoSection($sectionID, $row['SectionName']);
                $currentSection->sortOrder = $row['SectionSortOrder'];
                $currentSection->subtitle = $row['SectionSubtitle'];
                $currentSection->yearID = $year->yearID;
                $currentSection->conferenceID = $conferenceID;
                $currentLine = null;
                $output[] = $currentSection;
            }
            $lineID = $row['LineID'];
            if (($currentLine === null || $lineID !== $currentLine->homeInfoLineID) 
                && is_numeric($lineID) && $lineID > 0) {
                $currentLine = new HomeInfoLine($lineID, $row['LineName']);
                $currentLine->sortOrder = $row['LineSortOrder'];
                $currentSection->lines[] = $currentLine;
            }
            $itemID = $row['HomeInfoItemID'];
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
