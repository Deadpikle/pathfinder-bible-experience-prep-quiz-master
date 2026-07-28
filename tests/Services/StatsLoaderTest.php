<?php

use App\Services\StatsLoader;
use App\Services\QuestionScope;
use PHPUnit\Framework\TestCase;

final class StatsLoaderTest extends TestCase
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
            CREATE TABLE Languages (LanguageID INTEGER PRIMARY KEY);
            CREATE TABLE Books (BookID INTEGER PRIMARY KEY, Name TEXT, YearID INTEGER, BibleOrder INTEGER);
            CREATE TABLE Chapters (ChapterID INTEGER PRIMARY KEY, BookID INTEGER, Number INTEGER);
            CREATE TABLE Verses (VerseID INTEGER PRIMARY KEY, ChapterID INTEGER, Number INTEGER);
            CREATE TABLE Commentaries (CommentaryID INTEGER PRIMARY KEY, TopicName TEXT, Number INTEGER, YearID INTEGER);
            CREATE TABLE Questions (
                QuestionID INTEGER PRIMARY KEY,
                LanguageID INTEGER,
                StartVerseID INTEGER,
                CommentaryID INTEGER,
                Type TEXT,
                IsDeleted INTEGER,
                IsActive INTEGER,
                QuestionBankID INTEGER
            );
            INSERT INTO Languages VALUES (1), (2);
            INSERT INTO Books VALUES (10, "Acts", 5, 44);
            INSERT INTO Chapters VALUES (20, 10, 1), (21, 10, 2);
            INSERT INTO Verses VALUES (30, 20, 1), (31, 21, 1);
            INSERT INTO Commentaries VALUES (40, "Acts", 6, 5);
            INSERT INTO Questions VALUES
                (50, 1, 30, NULL, "bible-qna", 0, 1, 1),
                (51, 1, 31, NULL, "bible-qna", 1, 1, 1),
                (52, 1, NULL, 40, "commentary-qna", 1, 1, 1),
                (54, 1, 30, NULL, "bible-qna", 0, 0, 1);
        ');
    }

    public function testChapterAndVerseReportsIncludeZeroRowsAndExcludeDeletedQuestions(): void
    {
        $chapterStats = StatsLoader::loadQnAQuestionsByChapterInYear(5, $this->db);
        $this->assertCount(4, $chapterStats);
        $this->assertSame([1, 0, 0, 0], array_map('intval', array_column($chapterStats, 'count')));

        $verseStats = StatsLoader::loadQnAQuestionsByChapterAndVerseInYear(5, $this->db);
        $this->assertCount(4, $verseStats);
        $this->assertSame([1, 0, 0, 0], array_map('intval', array_column($verseStats, 'count')));
    }

    public function testCommentaryReportIncludesZeroRowsForEveryLanguage(): void
    {
        $stats = StatsLoader::loadCommentaryQuestionsByYear(5, $this->db);
        $this->assertCount(2, $stats);
        $this->assertSame([0, 0], array_map('intval', array_column($stats, 'count')));
    }

    public function testReportOnlyCountsBanksVisibleInTheProvidedScope(): void
    {
        $this->db->exec('INSERT INTO Questions VALUES (53, 1, 30, NULL, "bible-qna", 0, 1, 2)');
        $scope = QuestionScope::fromBankIDs([1]);

        $stats = StatsLoader::loadQnAQuestionsByChapterInYear(5, $this->db, $scope);
        $this->assertSame([1, 0, 0, 0], array_map('intval', array_column($stats, 'count')));
    }
}
