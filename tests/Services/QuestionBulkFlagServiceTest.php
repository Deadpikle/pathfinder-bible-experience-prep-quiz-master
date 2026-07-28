<?php
declare(strict_types=1);

use App\Models\FlagReason;
use App\Models\PBEAppConfig;
use App\Services\QuestionBulkFlagService;
use App\Services\QuestionScope;
use PHPUnit\Framework\TestCase;

final class QuestionBulkFlagServiceTest extends TestCase
{
    private PDO $db;
    private PBEAppConfig $app;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('
            CREATE TABLE Years (YearID INTEGER PRIMARY KEY, Year INTEGER, IsCurrent INTEGER);
            CREATE TABLE Books (BookID INTEGER PRIMARY KEY, YearID INTEGER, BibleOrder INTEGER);
            CREATE TABLE Chapters (ChapterID INTEGER PRIMARY KEY, BookID INTEGER, Number INTEGER);
            CREATE TABLE Verses (VerseID INTEGER PRIMARY KEY, ChapterID INTEGER, Number INTEGER);
            CREATE TABLE Questions (
                QuestionID INTEGER PRIMARY KEY,
                QuestionBankID INTEGER,
                LanguageID INTEGER,
                StartVerseID INTEGER,
                EndVerseID INTEGER,
                Type TEXT,
                IsDeleted INTEGER,
                IsActive INTEGER
            );
            CREATE TABLE UserFlagged (
                UserFlaggedID INTEGER PRIMARY KEY AUTOINCREMENT,
                QuestionID INTEGER,
                UserID INTEGER,
                Reason TEXT,
                DateTimeFlagged TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (UserID, QuestionID)
            );

            INSERT INTO Years VALUES (1, 2026, 1);
            INSERT INTO Books VALUES (10, 1, 44);
            INSERT INTO Chapters VALUES (20, 10, 1);
            INSERT INTO Verses VALUES (30, 20, 1), (31, 20, 2), (32, 20, 3);
            INSERT INTO Questions VALUES
                (100, 1, 1, 30, 31, "bible-qna", 0, 1),
                (101, 1, 1, 32, NULL, "bible-qna", 0, 1),
                (102, 2, 1, 30, NULL, "bible-qna", 0, 1),
                (103, 1, 1, 30, NULL, "bible-qna", 0, 0);
        ');
        $this->app = new PBEAppConfig(true, '');
        $this->app->isGuest = false;
    }

    public function testItFlagsOnlyTheSelectedQuestionWithinRangeAndScope(): void
    {
        $count = QuestionBulkFlagService::flag(
            $this->filters([100]),
            7,
            FlagReason::NEEDS_PRACTICE,
            $this->app,
            $this->db,
            QuestionScope::fromBankIDs([1])
        );

        $this->assertSame(1, $count);
        $this->assertSame(
            [['QuestionID' => 100, 'Reason' => FlagReason::NEEDS_PRACTICE]],
            $this->db->query('SELECT QuestionID, Reason FROM UserFlagged')->fetchAll()
        );
    }

    /** @dataProvider invalidSelectionProvider */
    public function testItRejectsTamperedOrIneligibleSelections(array $questionIDs): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            QuestionBulkFlagService::flag(
                $this->filters($questionIDs),
                7,
                FlagReason::OTHER,
                $this->app,
                $this->db,
                QuestionScope::fromBankIDs([1])
            );
        }
        finally {
            $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM UserFlagged')->fetchColumn());
        }
    }

    public static function invalidSelectionProvider(): array
    {
        return [
            'no explicit selection' => [[]],
            'question outside readable bank' => [[102]],
            'inactive question' => [[103]],
            'question outside selected verse' => [[101]],
            'mixed valid and invalid is atomic' => [[100, 102]],
        ];
    }

    /** @return array<string,mixed> */
    private function filters(array $questionIDs): array
    {
        return [
            'bookFilter' => 10,
            'chapterFilter' => 20,
            'verseFilter' => 30,
            'languageID' => 1,
            'questionBankID' => -1,
            'questionIDs' => $questionIDs,
        ];
    }
}
