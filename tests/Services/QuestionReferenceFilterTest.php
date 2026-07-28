<?php

use App\Services\QuestionReferenceFilter;
use PHPUnit\Framework\TestCase;

final class QuestionReferenceFilterTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('
            CREATE TABLE Books (BookID INTEGER PRIMARY KEY, YearID INTEGER, BibleOrder INTEGER);
            CREATE TABLE Chapters (ChapterID INTEGER PRIMARY KEY, BookID INTEGER, Number INTEGER);
            CREATE TABLE Verses (VerseID INTEGER PRIMARY KEY, ChapterID INTEGER, Number INTEGER);
            CREATE TABLE Questions (QuestionID INTEGER PRIMARY KEY, StartVerseID INTEGER, EndVerseID INTEGER);
            INSERT INTO Books VALUES (1, 9, 44), (2, 9, 45);
            INSERT INTO Chapters VALUES (10, 1, 1), (11, 1, 2), (12, 2, 1);
            INSERT INTO Verses VALUES (100, 10, 1), (101, 10, 2), (102, 11, 1), (103, 12, 1);
            INSERT INTO Questions VALUES
                (1000, 100, 102),
                (1001, 100, NULL),
                (1002, 103, NULL);
        ');
    }

    public function testResolveRequiresAConsistentBookChapterVerseHierarchy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QuestionReferenceFilter::resolve(9, 1, 12, -1, $this->db);
    }

    public function testInclusiveOverlapMatchesAQuestionWhoseRangeCrossesIntoChapter(): void
    {
        $range = QuestionReferenceFilter::resolve(9, 1, 11, -1, $this->db);
        $predicate = QuestionReferenceFilter::overlapPredicate($range);
        $stmt = $this->db->prepare('
            SELECT q.QuestionID
            FROM Questions q
            INNER JOIN Verses vStart ON vStart.VerseID = q.StartVerseID
            INNER JOIN Chapters cStart ON cStart.ChapterID = vStart.ChapterID
            INNER JOIN Books bStart ON bStart.BookID = cStart.BookID
            LEFT JOIN Verses vEnd ON vEnd.VerseID = q.EndVerseID
            LEFT JOIN Chapters cEnd ON cEnd.ChapterID = vEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            WHERE ' . $predicate['sql'] . '
            ORDER BY q.QuestionID
        ');
        $stmt->execute($predicate['params']);

        $this->assertSame([1000], array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testSingleVerseBoundaryIsExact(): void
    {
        $range = QuestionReferenceFilter::resolve(9, 1, 10, 101, $this->db);
        $this->assertSame($range['start'], $range['end']);
        $this->assertSame(44001002, $range['start']);
    }
}
