<?php

namespace App\Models;

use PDO;

use App\Models\Year;

class Book
{
    public $bookID;
    public $name;
    public $numberChapters;
    public $bibleOrder;

    public $chapters; // array of Chapter objects

    public $yearID;
    public $year;

    public function __construct(int $bookID, string $name)
    {
        $this->bookID = $bookID;
        $this->name = $name;
    }

    private static function loadBooks(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT BookID, Name, NumberChapters, Books.YearID, Years.Year, BibleOrder
            FROM Books JOIN Years ON Books.YearID = Years.YearID
            ' . $whereClause . '
            ORDER BY Name, NumberChapters';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $book = new Book($row['BookID'], $row['Name']);
            $book->bibleOrder = $row['BibleOrder'];
            $book->numberChapters = $row['NumberChapters'];
            $book->yearID = $row['YearID'];
            $book->year = $row['Year'];
            $output[] = $book;
        }
        return $output;
    }

    /**
     * @return array<Book>
     */
    public static function loadAllBooks(PDO $db): array
    {
        return Book::loadBooks('', [], $db);
    }

    public static function loadBooksForYear(Year $year, PDO $db) : array
    {
        return Book::loadBooks(' WHERE Books.YearID = ? ', [ $year->yearID ], $db);
    }

    public static function loadBookByID(int $bookID, PDO $db) : ?Book
    {
        $data = Book::loadBooks(' WHERE Books.BookID = ? ', [ $bookID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadAllBookChapterVerseDataForYear(Year $year, PDO $db) : array
    {
        $query = '
            SELECT b.BookID, b.Name, b.NumberChapters, b.BibleOrder,
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
        $books = [];
        $book = null;
        $chapter = null;
        foreach ($data as $row) {
            if ($row['BookID'] != $lastBookID) {
                $lastBookID = $row['BookID'];
                if ($chapter != null) {
                    /** @var Book $book */
                    $book->chapters[] = $chapter;
                }
                if ($book != null) {
                    $books[] = $book;
                }
                $book = new Book($row['BookID'], $row['Name']);
                $book->bibleOrder = $row['BibleOrder'];
                $book->numberChapters = $row['NumberChapters'];
                $book->chapters = [];
                $book->yearID = $year->yearID;
                $book->year = $year->year;
                $chapter = null;
            }
            if ($row['ChapterID'] != $lastChapterID) {
                $lastChapterID = $row['ChapterID'];
                if ($chapter != null) {
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
        if ($chapter != null && $book != null) {
            $book->chapters[] = $chapter;
            $books[] = $book;
        }
        return $books;
    }

    public static function createBook(string $name, int $numberOfChapters, int $yearID, int $bibleOrder, PDO $db)
    {
        $query = 'SELECT 1 FROM Books WHERE Name = ? AND YearID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $name, 
            $yearID
        ]);
        $bookData = $stmt->fetchAll();
        // make sure book doesn't exist
        if ($bookData !== true && count($bookData) == 0) {
            $query = '
                INSERT INTO Books (Name, NumberChapters, YearID, BibleOrder) VALUES (?, ?, ?, ?)
            ';
            $stmt = $db->prepare($query);
            $stmt->execute([
                trim($name),
                $numberOfChapters, 
                $yearID,
                $bibleOrder
            ]);
        }
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE Books SET Name = ?, NumberChapters = ?, YearID = ?, BibleOrder = ?
            WHERE BookID = ?
        ';
        $stmt = $db->prepare($query);
        $stmt->execute([
            trim($this->name),
            $this->numberChapters,
            $this->yearID,
            $this->bibleOrder,
            $this->bookID
        ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM Books WHERE BookID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $this->bookID
        ]);
    }
}
