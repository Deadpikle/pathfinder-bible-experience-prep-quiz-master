<?php

namespace App\Models;

use PDO;

use App\Models\Year;

class Chapter
{
    public $chapterID;
    public $number;
    public $numberVerses;

    public $verses; // array of Verse objects

    public $bookID;

    public function __construct(int $chapterID, int $number)
    {
        $this->chapterID = $chapterID;
        $this->number = $number;
        $this->verses = [];
    }

    public static function loadChaptersWithActiveQuestions(Year $year, PDO $db) : array
    {
        $query = '
            SELECT DISTINCT c.BookID, c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses
            FROM Chapters c
                JOIN BOOKS b ON b.BookID = c.BookID
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
}
