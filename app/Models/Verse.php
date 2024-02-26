<?php

namespace App\Models;

use PDO;

class Verse
{
    public int $verseID;
    public int $number;
    public string $text;
    
    public int $chapterID;

    public function __construct(?int $verseID, ?int $number)
    {
        $this->verseID = $verseID;
        $this->number = $number;
        $this->text = '';
        $this->chapterID = -1;
    }

    /** @return array<Verse> */
    private static function loadVerses(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT VerseID, Number, VerseText, ChapterID
            FROM Verses
            ' . $whereClause . '
            ORDER BY Number';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $verse = new Verse($row['VerseID'], $row['Number']);
            $verse->text = $row['VerseText'];
            $verse->chapterID = $row['ChapterID'];
            $output[] = $verse;
        }
        return $output;
    }

    /** @return array<Verse> */
    public static function loadVersesForChapter(int $chapterID, PDO $db): array
    {
        return self::loadVerses(' WHERE ChapterID = ? ', [ $chapterID ], $db);
    }

    public static function loadVerseByID(int $verseID, PDO $db): ?Verse
    {
        $data = self::loadVerses(' WHERE VerseID = ? ', [ $verseID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadVerseByNumberInChapter(int $chapterID, int $verseNumber, PDO $db): ?Verse
    {
        $data = self::loadVerses(' WHERE Number = ? AND ChapterID = ? ', [ $verseNumber, $chapterID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Verses WHERE VerseID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->verseID
        ]);
    }

    public static function insertVerseIntoChapter(int $chapterID, int $verseNumber, PDO $db)
    {
        $insertQuery = ' INSERT INTO Verses (Number, VerseText, ChapterID) VALUES (?, ?, ?)';
        $insertStmt = $db->prepare($insertQuery);
        $params = [
            $verseNumber,
            '',
            $chapterID
        ];
        $insertStmt->execute($params);
    }

    public static function createAllVersesForChapter(int $chapterID, int $numberVerses, PDO $db)
    {
        $insertQuery = ' INSERT INTO Verses (Number, VerseText, ChapterID) VALUES (?, ?, ?)';
        $insertStmt = $db->prepare($insertQuery);
        for ($i = 0; $i < $numberVerses; $i++) {
            $params = [
                ($i+1),
                '',
                $chapterID
            ];
            $insertStmt->execute($params);
        }
    }
}
