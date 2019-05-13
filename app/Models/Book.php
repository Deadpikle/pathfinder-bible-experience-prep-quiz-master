<?php

namespace App\Models;

use PDO;

use App\Models\Year;

class Book
{
    public $bookID;
    public $name;
    public $numberChapters;

    public $chapters; // array of Chapter objects

    public $yearID;

    public function __construct(int $bookID, string $name)
    {
        $this->bookID = $bookID;
        $this->name = $name;
    }

    public function loadAllBookChapterVerseDataForYear(Year $year, PDO $db) : array
    {
        $query = '
            SELECT b.BookID, b.Name, b.NumberChapters,
                c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses,
                v.VerseID, v.Number AS VerseNumber, v.VerseText
            FROM Books b 
                JOIN Chapters c ON b.BookID = c.BookID
                LEFT JOIN Verses v ON c.ChapterID = v.ChapterID
            WHERE b.YearID = ' . $year->yearID . '
            ORDER BY b.Name, ChapterNumber, VerseNumber';
        $stmt = $db->prepare($query);
        $stmt->execute([]);
        $data = $stmt->fetchAll();

        $lastBookID = -1;
        $lastChapterID = -1;
        $books = array();
        $book = NULL;
        $chapter = NULL;
        foreach ($data as $row) {
            if ($row['BookID'] != $lastBookID) {
                $lastBookID = $row['BookID'];
                if ($chapter != NULL) {
                    $book->chapters[] = $chapter;
                }
                if ($book != NULL) {
                    $books[] = $book;
                }
                $book = new Book($row['BookID'], $row['Name']);
                $book->numberChapters = $row['NumberChapters'];
                $book->chapters = [];
                $chapter = null;
            }
            if ($row['ChapterID'] != $lastChapterID) {
                $lastChapterID = $row['ChapterID'];
                if ($chapter != NULL) {
                    $book->chapters[] = $chapter;
                }
                $chapter = new Chapter($row['ChapterID'], $row['ChapterNumber']);
                $chapter->numberVerses = $row['NumberVerses'];
                $chapter->bookID = $book->bookID;
                $chapter->verses = [];
            }
            // create verse
            $verse = new Verse($row['VerseID'], $row['VerseNumber']);
            $verse->text = $row['VerseText'];
            $verse->chapterID = $chapter->chapterID;
            $chapter->verses[] = $verse;
        }
        // wrap it up
        if ($chapter != NULL && $book != NULL) {
            $book->chapters[] = $chapter;
            $books[] = $book;
        }
        return $books;
    }
}
