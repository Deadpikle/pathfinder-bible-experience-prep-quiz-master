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

    private static function loadSectionData(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, 
                his.Subtitle AS SectionSubtitle, his.SortOrder AS SectionSortOrder, his.YearID, his.ConferenceID,
                hil.HomeInfoLineID AS LineID, hil.Name AS LineName, hil.SortOrder AS LineSortOrder,
                hii.HomeInfoItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
            FROM HomeInfoSections his 
                LEFT JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
                LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
            ' . $whereClause . '
            ORDER BY SectionSortOrder, LineSortOrder, ItemSortOrder';
        $sectionStmt = $db->prepare($query);
        $sectionStmt->execute($whereParams);
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
                $currentSection->yearID = $row['YearID'];
                $currentSection->conferenceID = $row['ConferenceID'];
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
        return HomeInfoSection::loadSectionData($whereClause, $params, $db);
    }

    public static function loadSectionByID(int $sectionID, PDO $db) : ?HomeInfoSection
    {
        $data = HomeInfoSection::loadSectionData(' WHERE his.HomeInfoSectionID = ? ', [ $sectionID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO HomeInfoSections (Name, SortOrder, YearID, ConferenceID, Subtitle) 
            VALUES (?, ?, ?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            (int)$this->sortOrder,
            (int)$this->yearID,
            (int)$this->conferenceID,
            $this->subtitle ?? '',
            (int)$this->homeInfoSectionID
        ]);
        $this->homeInfoSectionID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE HomeInfoSections SET Name = ?, SortOrder = ?, YearID = ?, ConferenceID = ?, Subtitle = ? 
            WHERE HomeInfoSections = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            $this->name,
            (int)$this->sortOrder,
            (int)$this->yearID,
            (int)$this->conferenceID,
            $this->subtitle ?? '',
            $this->homeInfoSectionID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->homeInfoSectionID ]);
    }
}
