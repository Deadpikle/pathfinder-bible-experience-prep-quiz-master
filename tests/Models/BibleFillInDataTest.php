<?php

use App\Models\BibleFillInData;
use App\Models\Year;
use App\Services\FillInAvailabilityPolicy;
use PHPUnit\Framework\TestCase;

final class BibleFillInDataTest extends TestCase
{
    private PDO $db;
    /** @var array<int,array<int,int|string>> */
    private array $lockEvents = [];

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->sqliteCreateFunction('GET_LOCK', function (string $name, int $timeout): int {
            $this->lockEvents[] = ['acquire', $name, $timeout];
            return 1;
        }, 2);
        $this->db->sqliteCreateFunction('RELEASE_LOCK', function (string $name): int {
            $this->lockEvents[] = ['release', $name];
            return 1;
        }, 1);

        $this->db->exec('
            CREATE TABLE Languages (
                LanguageID INTEGER PRIMARY KEY,
                Name TEXT NOT NULL,
                IsDefault INTEGER NOT NULL,
                IsUIEnabled INTEGER NOT NULL,
                AltName TEXT NOT NULL,
                Abbreviation TEXT NOT NULL
            );
            CREATE TABLE QuestionBanks (
                QuestionBankID INTEGER PRIMARY KEY,
                IsGlobal INTEGER NOT NULL,
                IsDeleted INTEGER NOT NULL
            );
            CREATE TABLE Books (
                BookID INTEGER PRIMARY KEY,
                Name TEXT NOT NULL,
                YearID INTEGER NOT NULL,
                BibleOrder INTEGER NOT NULL
            );
            CREATE TABLE Chapters (
                ChapterID INTEGER PRIMARY KEY,
                Number INTEGER NOT NULL,
                BookID INTEGER NOT NULL
            );
            CREATE TABLE Verses (
                VerseID INTEGER PRIMARY KEY,
                Number INTEGER NOT NULL,
                ChapterID INTEGER NOT NULL
            );
            CREATE TABLE Questions (
                QuestionID INTEGER PRIMARY KEY,
                QuestionBankID INTEGER NOT NULL,
                Type TEXT NOT NULL,
                LanguageID INTEGER NOT NULL,
                StartVerseID INTEGER,
                IsDeleted INTEGER NOT NULL,
                IsActive INTEGER NOT NULL
            );

            INSERT INTO Languages VALUES
                (1, "English", 1, 1, "", "en"),
                (2, "French", 0, 0, "Français", "fr");
            INSERT INTO QuestionBanks VALUES
                (1, 1, 0),
                (2, 0, 0),
                (3, 1, 1);
            INSERT INTO Books VALUES
                (10, "Acts", 7, 44),
                (11, "Romans", 8, 45);
            INSERT INTO Chapters VALUES
                (20, 1, 10),
                (21, 2, 10),
                (30, 1, 11);
            INSERT INTO Verses VALUES
                (100, 1, 20),
                (101, 1, 21),
                (200, 1, 30);
            INSERT INTO Questions VALUES
                (1, 1, "bible-qna-fill", 1, 100, 0, 1),
                (2, 2, "bible-qna-fill", 1, 100, 0, 1),
                (3, 3, "bible-qna-fill", 1, 100, 0, 1),
                (4, 1, "bible-qna",      1, 100, 0, 1),
                (5, 1, "bible-qna-fill", 2, 100, 0, 1),
                (6, 1, "bible-qna-fill", 1, 200, 0, 1),
                (7, 1, "bible-qna-fill", 1, 101, 0, 1),
                (8, 1, "bible-qna-fill", 1, 100, 1, 0);
        ');
    }

    public function testLoadCountsOnlyActiveNondeletedFillInsFromAnActiveGlobalBank(): void
    {
        $data = BibleFillInData::loadFillInData(new Year(7, 2026), $this->db);

        $this->assertCount(3, $data);
        $this->assertSame([20, 20, 21], array_column($data, 'chapterID'));
        $this->assertSame([1, 2, 1], array_map(
            static fn(BibleFillInData $row): int => $row->language->languageID,
            $data
        ));
        $this->assertSame([1, 1, 1], array_column($data, 'numberOfQuestions'));
    }

    public function testDeleteForLanguageLocksAndTouchesOnlyGlobalFillInsInScope(): void
    {
        BibleFillInData::deleteFillInsForLanguage(new Year(7, 2026), 1, $this->db);

        $states = $this->questionStates();
        foreach ([1, 7, 8] as $questionID) {
            $this->assertSame(['active' => 0, 'deleted' => 1], $states[$questionID]);
        }
        foreach ([2, 3, 4, 5, 6] as $questionID) {
            $this->assertSame(['active' => 1, 'deleted' => 0], $states[$questionID]);
        }
        $this->assertSame($this->expectedLockEvents(), $this->lockEvents);
    }

    public function testDeleteForChapterLocksAndLeavesOtherGlobalChaptersUntouched(): void
    {
        BibleFillInData::deleteFillInsForChapter(new Year(7, 2026), 20, 1, $this->db);

        $states = $this->questionStates();
        $this->assertSame(['active' => 0, 'deleted' => 1], $states[1]);
        $this->assertSame(['active' => 0, 'deleted' => 1], $states[8]);
        $this->assertSame(['active' => 1, 'deleted' => 0], $states[7]);
        $this->assertSame(['active' => 1, 'deleted' => 0], $states[2]);
        $this->assertSame($this->expectedLockEvents(), $this->lockEvents);
    }

    public function testDeleteReleasesPolicyLockWhenTheMutationFails(): void
    {
        $this->db->exec('DROP TABLE Books');

        try {
            BibleFillInData::deleteFillInsForLanguage(new Year(7, 2026), 1, $this->db);
            $this->fail('The invalid maintenance query should fail.');
        } catch (PDOException) {
            $this->assertSame($this->expectedLockEvents(), $this->lockEvents);
        }
    }

    /** @return array<int,array{active:int,deleted:int}> */
    private function questionStates(): array
    {
        $states = [];
        foreach ($this->db->query(
            'SELECT QuestionID, IsActive, IsDeleted FROM Questions ORDER BY QuestionID'
        )->fetchAll() as $row) {
            $states[(int)$row['QuestionID']] = [
                'active' => (int)$row['IsActive'],
                'deleted' => (int)$row['IsDeleted'],
            ];
        }
        return $states;
    }

    /** @return array<int,array<int,int|string>> */
    private function expectedLockEvents(): array
    {
        return [
            ['acquire', FillInAvailabilityPolicy::LOCK_NAME, 10],
            ['release', FillInAvailabilityPolicy::LOCK_NAME],
        ];
    }
}
