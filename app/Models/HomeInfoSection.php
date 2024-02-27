<?php

// TODO: this class queued up for deletion as it is no longer used

namespace App\Models;

use PDO;
use PDOException;

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
        $whereClause = ' WHERE his.YearID = ? ';
        if ($conferenceID > 0) {
            $whereClause .= ' AND his.ConferenceID = ? ';
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
            $this->subtitle ?? ''
        ]);
        $this->homeInfoSectionID = intval($db->lastInsertId());
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE HomeInfoSections SET Name = ?, SortOrder = ?, YearID = ?, ConferenceID = ?, Subtitle = ? 
            WHERE HomeInfoSectionID = ?';
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

    public static function getSortOrderForConferenceInYear(int $conferenceID, int $yeariD, PDO $db) : int
    {
        $stmt = $db->prepare('SELECT MAX(SortOrder) AS MaxSort FROM HomeInfoSections WHERE ConferenceID = ? AND YearID = ?');
        $stmt->execute([ $conferenceID, $yeariD ]);
        $row = $stmt->fetch();
        $sortOrder = 1;
        if ($row != null) {
            $sortOrder = intval($row['MaxSort']) + 1;
        }
        return $sortOrder;
    }

    public static function saveSortOrder(array $data, PDO $db) : bool
    {
        $sqlStatements = '';
        foreach ($data as $section) {
            $sqlStatements .= ' UPDATE HomeInfoSections SET SortOrder = ' . $section['index'] . ' WHERE HomeInfoSectionID = ' . $section['id'] . '; ';
        }
        try {
            echo $sqlStatements;
            $db->exec($sqlStatements);
        }
        catch (PDOException $e) {
            return false;
        }
        return true;
    }

    public static function copyHomeSections(int $fromConferenceID, int $toConferenceID, int $fromYearID, PDO $db) {
        $currentYear = Year::loadCurrentYear($db);
        $toYearID = $currentYear->yearID;
        // load all sections from other conference and year
        $sectionQuery = '
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, his.Subtitle AS SectionSubtitle
            FROM HomeInfoSections his 
            WHERE ConferenceID = ? AND YearID = ?
            ORDER BY SortOrder';
        $sectionParams = [
            $fromConferenceID, 
            $fromYearID
        ];
        $sectionStmt = $db->prepare($sectionQuery);
        $sectionStmt->execute($sectionParams);
        // prepare other queries so things go fast
        // need to check for a pre-existing section with that name
        $sectionNameQuery = 'SELECT HomeInfoSectionID FROM HomeInfoSections WHERE Name = ? AND ConferenceID = ? AND YearID = ?';
        $sectionNameStmnt = $db->prepare($sectionNameQuery);

        $sectionMaxSortOrderQuery = '
            SELECT MAX(SortOrder) AS MaxSortOrder 
            FROM HomeInfoSections 
            WHERE ConferenceID = ? AND YearID = ?';
        $sectionMaxParams = [
            $toConferenceID,
            $toYearID
        ];
        $sectionMaxSortOrderStmnt = $db->prepare($sectionMaxSortOrderQuery);
        $sectionMaxSortOrderStmnt->execute($sectionMaxParams);
        $nextSectionSortOrder = 0;
        $maxSorts = $sectionMaxSortOrderStmnt->fetchAll();
        if (count($maxSorts) > 0) {
            $nextSectionSortOrder = ((int)$maxSorts[0]['MaxSortOrder']) + 1;
        }
        //die('order = ' .$nextSectionSortOrder);

        $insertSection = 'INSERT INTO HomeInfoSections (Name, Subtitle, SortOrder, YearID, ConferenceID) VALUES (?, ?, ?, ?, ?)';
        $insertSectionStmnt = $db->prepare($insertSection);
        // --
        $lineMaxSortOrderQuery = '
            SELECT MAX(SortOrder) AS MaxSortOrder 
            FROM HomeInfoLines
            WHERE HomeInfoSectionID = ?';
        $lineMaxSortOrderStmnt = $db->prepare($lineMaxSortOrderQuery);

        $lineQuery = '
            SELECT HomeInfoLineID, Name
            FROM HomeInfoLines
            WHERE HomeInfoSectionID = ?
            ORDER BY SortOrder
        ';
        $lineQueryStmnt = $db->prepare($lineQuery);

        $insertLine = 'INSERT INTO HomeInfoLines (Name, SortOrder, HomeInfoSectionID) VALUES (?, ?, ?)';
        $insertLineStmnt = $db->prepare($insertLine);
        // --
        $itemQuery = '
            SELECT IsLink, Text, URL, SortOrder
            FROM HomeInfoItems
            WHERE HomeInfoLineID = ?
            ORDER BY SortOrder
        ';
        $itemQueryStmnt = $db->prepare($itemQuery);
        $insertItem = 'INSERT INTO HomeInfoItems (IsLink, Text, URL, SortOrder, HomeInfoLineID) VALUES (?, ?, ?, ?, ?)';
        $insertItemStmnt = $db->prepare($insertItem);
        // start looping through the sections
        foreach ($sectionStmt as $section) {
            // check to see if a section with this name already exists
            $sectionNameCheckParams = [
                $section['SectionName'],
                $toConferenceID, 
                $toYearID
            ];
            $sectionNameStmnt->execute($sectionNameCheckParams);
            $sectionsWithThatName = $sectionNameStmnt->fetchAll();
            if (count($sectionsWithThatName) > 0) {
                $createdSectionID = $sectionsWithThatName[0]['HomeInfoSectionID'];
            }
            else {
                // insert it into the HomeInfoSections table for the given year and conference
                $insertSectionParams = [
                    $section['SectionName'],
                    $section['SectionSubtitle'] ? $section['SectionSubtitle'] : '',
                    $nextSectionSortOrder++,
                    $toYearID,
                    $toConferenceID
                ];
                $insertSectionStmnt->execute($insertSectionParams);
                $createdSectionID = intval($db->lastInsertId());
            }
            $lineParams = [ $section['SectionID'] ];
            // load the max sort order for the lines for this home info section
            $lineMaxSortOrderStmnt->execute($lineParams);
            $nextLineSortOrder = 0;
            $maxLineSorts = $lineMaxSortOrderStmnt->fetchAll();
            if (count($maxLineSorts) > 0) {
                $nextLineSortOrder = ((int)$maxLineSorts[0]['MaxSortOrder']) + 1;
            }
            // load all the lines for this home info section
            $lineQueryStmnt->execute($lineParams);
            foreach ($lineQueryStmnt as $line) {
                // insert it into the HomeInfoLines table for the given just-created section
                $insertLineParams = [
                    $line['Name'],
                    $nextLineSortOrder++,
                    $createdSectionID
                ];
                $insertLineStmnt->execute($insertLineParams);
                $createdLineID = intval($db->lastInsertId());
                // load all the items for this line
                $itemParams = [ $line['HomeInfoLineID'] ];
                $itemQueryStmnt->execute($itemParams);
                foreach ($itemQueryStmnt as $item) {
                    // insert the new line item
                    $insertItemParams = [
                        $item['IsLink'],
                        $item['Text'],
                        $item['URL'],
                        $item['SortOrder'],
                        $createdLineID
                    ];
                    $insertItemStmnt->execute($insertItemParams);
                }
            }
        }
        // all done :3
    }
}
