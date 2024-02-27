<?php

namespace App\Models;

use PDO;

use App\Models\Year;

class Chapter
{
    public int $chapterID;
    public int $number;
    public int $numberVerses;

    /** @var array<Verse> $verses */
    public array $verses;

    public int $bookID;

    public function __construct(int $chapterID, int $number)
    {
        $this->chapterID = $chapterID;
        $this->number = $number;
        $this->numberVerses = 0;
        $this->verses = [];
        $this->bookID = -1;
    }

    /** @return array<Chapter> */
    private static function loadChapters(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT ChapterID, Number, NumberVerses, BookID
            FROM Chapters
            ' . $whereClause . '
            ORDER BY Number, NumberVerses';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $chapter = new Chapter($row['ChapterID'], $row['Number']);
            $chapter->numberVerses = $row['NumberVerses'];
            $chapter->bookID = $row['BookID'];
            $output[] = $chapter;
        }
        return $output;
    }

    /** @return array<Chapter> */
    public static function loadAllChapters(PDO $db): array
    {
        return Chapter::loadChapters('', [], $db);
    }

    public static function loadChapterByID(int $chapterID, PDO $db): ?Chapter
    {
        $data = Chapter::loadChapters(' WHERE ChapterID = ? ', [ $chapterID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    /** @return array<Chapter> */
    public static function loadChaptersByBookID(int $bookID, PDO $db): array
    {
        return Chapter::loadChapters(' WHERE BookID = ? ', [ $bookID ], $db);
    }

    /** @return array<Chapter> */
    public static function loadChaptersWithActiveQuestions(Year $year, PDO $db): array
    {
        $query = '
            SELECT DISTINCT b.Name, c.BookID, c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses
            FROM Chapters c
                JOIN Books b ON b.BookID = c.BookID
                JOIN Verses v ON c.ChapterID = v.ChapterID
                JOIN Questions q ON v.VerseID = q.StartVerseID
            WHERE b.YearID = ? AND q.IsDeleted = 0
            ORDER BY b.Name, ChapterNumber';
        $stmt = $db->prepare($query);
        $stmt->execute([ $year->yearID ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $chapter = new Chapter($row['ChapterID'], $row['ChapterNumber']);
            $chapter->numberVerses = $row['NumberVerses'];
            $chapter->bookID = $row['BookID'];
            $output[] = $chapter;
        }
        return $output;
    }

    public static function createChapterForBook(int $number, int $numberVerses, int $bookID, PDO $db)
    {
        $query = 'SELECT 1 FROM Chapters WHERE Number = ? AND NumberVerses = ? AND BookID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $number,
            $numberVerses,
            $bookID
        ]);
        $bookData = $stmt->fetchAll();
        // make sure chapter doesn't already exist
        if ($bookData !== true && count($bookData) == 0) {
            $query = '
                INSERT INTO Chapters (Number, NumberVerses, BookID) VALUES (?, ?, ?)
            ';
            $stmt = $db->prepare($query);
            $stmt->execute([
                $number,
                $numberVerses,
                $bookID
            ]);
            // now insert verses into the db for that chapter based on the number of verses
            $chapterID = intval($db->lastInsertId());
            Verse::createAllVersesForChapter($chapterID, $numberVerses, $db);
        }
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Chapters WHERE ChapterID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->chapterID
        ]);
    }
}
