<?php

// TODO: this class queued up for deletion as it is no longer used

namespace App\Models;

use PDO;

class HomeInfoItem
{
    public $homeInfoItemID;
    public $isLink;
    public $text;
    public $url;
    public $sortOrder;

    public $homeInfoLineID;

    public function __construct(int $homeInfoItemID)
    {
        $this->homeInfoItemID = $homeInfoItemID;
    }

    private static function loadItems(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT HomeInfoItemID, IsLink, Text, URL, SortOrder, HomeInfoLineID
            FROM HomeInfoItems
            ' . $whereClause . '
            ORDER BY SortOrder';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $item = new HomeInfoItem($row['HomeInfoItemID']);
            $item->isLink = $row['IsLink'];
            $item->text = $row['Text'];
            $item->url = $row['URL'];
            $item->sortOrder = $row['SortOrder'];
            $item->homeInfoLineID = $row['HomeInfoLineID'];
            $output[] = $item;
        }
        return $output;
    }

    public static function loadItemByID(int $homeInfoItemID, PDO $db) : ?HomeInfoItem
    {
        $data = HomeInfoItem::loadItems(' WHERE HomeInfoItemID = ? ', [ $homeInfoItemID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO HomeInfoItems (IsLink, Text, URL, SortOrder, HomeInfoLineID) 
            VALUES (?, ?, ?, ?, ?)';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            (int)$this->isLink,
            $this->text ?? '',
            $this->url ?? '',
            (int)$this->sortOrder,
            (int)$this->homeInfoLineID
        ]);
        $this->homeInfoItemID = intval($db->lastInsertId());
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE HomeInfoItems SET IsLink = ?, Text = ?, URL = ?, SortOrder = ?, HomeInfoLineID = ?
            WHERE HomeInfoItemID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([
            (int)$this->isLink,
            $this->text ?? '',
            $this->url ?? '',
            (int)$this->sortOrder,
            (int)$this->homeInfoLineID,
            $this->homeInfoItemID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM HomeInfoItems WHERE HomeInfoItemID = ?';
        $stmnt = $db->prepare($query);
        $stmnt->execute([ $this->homeInfoItemID ]);
    }

    public static function getSortOrder(int $lineID, PDO $db) : int
    {
        $stmt = $db->prepare("SELECT MAX(SortOrder) AS MaxSort FROM HomeInfoItems WHERE HomeInfoLineID = ?");
        $stmt->execute([$lineID]);
        $row = $stmt->fetch();
        $sortOrder = 1;
        if ($row != null) {
            $sortOrder = intval($row['MaxSort']) + 1;
        }
        return $sortOrder;
    }
}
