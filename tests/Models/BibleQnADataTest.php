<?php

use App\Controllers\Admin\BibleQnAController;
use App\Models\BibleQnAData;
use App\Models\PBEAppConfig;
use App\Models\Year;
use PHPUnit\Framework\TestCase;
use Yamf\Request;
use Yamf\Responses\Redirect;

final class BibleQnADataTest extends TestCase
{
    private PDO $db;
    private bool $hadPost;
    private bool $hadSession;
    private array $savedPost = [];
    private array $savedSession = [];

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $this->hadPost = isset($GLOBALS['_POST']);
        $this->hadSession = isset($GLOBALS['_SESSION']);
        $this->savedPost = $_POST ?? [];
        $this->savedSession = $_SESSION ?? [];
        $_POST = [];
        $_SESSION = [];

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('
            CREATE TABLE Years (
                YearID INTEGER PRIMARY KEY,
                Year INTEGER NOT NULL,
                IsCurrent INTEGER NOT NULL
            );
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

            INSERT INTO Years VALUES (7, 2026, 1), (8, 2027, 0);
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
                (1, 1, "bible-qna",      1, 100, 0, 1),
                (2, 2, "bible-qna",      1, 100, 0, 1),
                (3, 1, "bible-qna",      1, 100, 1, 1),
                (4, 1, "bible-qna",      1, 100, 0, 0),
                (5, 1, "bible-qna-fill", 1, 100, 0, 1),
                (6, 3, "bible-qna",      1, 100, 0, 1),
                (7, 1, "bible-qna",      2, 100, 0, 1),
                (8, 1, "bible-qna",      1, 200, 0, 1),
                (9, 1, "bible-qna",      1, 101, 0, 1);
        ');
    }

    protected function tearDown(): void
    {
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($GLOBALS['_POST']);
        }
        if ($this->hadSession) {
            $_SESSION = $this->savedSession;
        } else {
            unset($GLOBALS['_SESSION']);
        }
    }

    public function testLoadCountsOnlyActiveNondeletedQuestionsInTheGlobalBank(): void
    {
        $data = BibleQnAData::loadQnAData(new Year(7, 2026), $this->db);

        $this->assertCount(3, $data);
        $this->assertSame([20, 20, 21], array_column($data, 'chapterID'));
        $this->assertSame([1, 2, 1], array_map(
            static fn(BibleQnAData $row): int => $row->language->languageID,
            $data
        ));
        $this->assertSame([1, 1, 1], array_column($data, 'numberOfQuestions'));
    }

    public function testDeleteForLanguageTouchesOnlyGlobalQnAInTheSelectedYearAndLanguage(): void
    {
        BibleQnAData::deleteQnAForLanguage(new Year(7, 2026), 1, $this->db);

        $this->assertSame([2, 5, 6, 7, 8], $this->remainingQuestionIDs());
    }

    public function testDeleteForChapterTouchesOnlyGlobalQnAInTheSelectedChapter(): void
    {
        BibleQnAData::deleteQnAForChapter(new Year(7, 2026), 20, 1, $this->db);

        $this->assertSame([2, 5, 6, 7, 8, 9], $this->remainingQuestionIDs());
    }

    public function testControllerLanguageDeleteDispatchesToQnADeletionNotFillIns(): void
    {
        $_SESSION['hmac_key_token'] = 'controller-regression-key';
        $_POST['token'] = hash_hmac(
            'sha256',
            'delete-language-bible-qna',
            $_SESSION['hmac_key_token']
        );

        $app = new PBEAppConfig(true, '');
        $app->db = $this->db;
        $request = new Request();
        $request->routeParams['languageID'] = 1;

        $response = (new BibleQnAController())->deleteQnAQuestionsForLanguage($app, $request);

        $this->assertInstanceOf(Redirect::class, $response);
        $this->assertSame('/admin/bible-qna-questions', $response->redirectPath);
        $this->assertSame([2, 5, 6, 7, 8], $this->remainingQuestionIDs());
        $this->assertSame(1, (int)$this->db->query(
            "SELECT COUNT(*) FROM Questions WHERE QuestionID = 5 AND Type = 'bible-qna-fill'"
        )->fetchColumn());
    }

    /** @return array<int,int> */
    private function remainingQuestionIDs(): array
    {
        return array_map(
            'intval',
            $this->db->query('SELECT QuestionID FROM Questions ORDER BY QuestionID')
                ->fetchAll(PDO::FETCH_COLUMN)
        );
    }
}
