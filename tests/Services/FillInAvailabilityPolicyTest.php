<?php

namespace Tests\Services;

use App\Services\FillInAvailabilityPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

final class FillInAvailabilityPolicyTest extends TestCase
{
    public function testRangeExpansionCountsDistinctVersesOnlyOnce(): void
    {
        $verses = $this->verses();
        $result = FillInAvailabilityPolicy::evaluateCoverage($verses, [
            ['StartVerseID' => 1, 'EndVerseID' => 3],
            ['StartVerseID' => 2, 'EndVerseID' => 4],
        ]);

        self::assertSame(4, $result['distinctVerseCount']);
        self::assertSame(['Genesis'], $result['completeBooks']);
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $result = FillInAvailabilityPolicy::evaluateCoverage($this->verses(), [[
            'StartVerseID' => 4,
            'EndVerseID' => 1,
        ]]);

        self::assertSame(0, $result['distinctVerseCount']);
        self::assertNotEmpty($result['errors']);
    }

    public function testUnrestrictedRotationStillRequiresValidGlobalFillInMembership(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec(<<<'SQL'
            CREATE TABLE Languages (LanguageID INTEGER PRIMARY KEY, Abbreviation TEXT);
            CREATE TABLE QuestionBanks (QuestionBankID INTEGER PRIMARY KEY, IsGlobal INTEGER, IsDeleted INTEGER);
            CREATE TABLE Books (BookID INTEGER PRIMARY KEY, Name TEXT, YearID INTEGER, BibleOrder INTEGER);
            CREATE TABLE Chapters (ChapterID INTEGER PRIMARY KEY, BookID INTEGER, Number INTEGER);
            CREATE TABLE Verses (VerseID INTEGER PRIMARY KEY, ChapterID INTEGER, Number INTEGER);
            CREATE TABLE Questions (
                QuestionID INTEGER PRIMARY KEY, QuestionBankID INTEGER, Type TEXT,
                IsDeleted INTEGER, LanguageID INTEGER, StartVerseID INTEGER, EndVerseID INTEGER
            );
            INSERT INTO Languages VALUES (2, 'es');
            INSERT INTO QuestionBanks VALUES (1, 1, 0), (2, 0, 0);
            INSERT INTO Books VALUES (1, 'Genesis', 9, 1);
            INSERT INTO Chapters VALUES (1, 1, 1);
            INSERT INTO Verses VALUES (1, 1, 1), (2, 1, 2);
            INSERT INTO Questions VALUES
                (10, 1, 'bible-qna', 0, 2, 1, 1),
                (11, 2, 'bible-qna-fill', 0, 2, 1, 1),
                (12, 1, 'bible-qna-fill', 0, 2, 1, 1);
            SQL);

        $policy = new FillInAvailabilityPolicy();
        self::assertFalse($policy->auditQuestionIDs(9, 2, [], $db)['allowed']);
        self::assertFalse($policy->auditQuestionIDs(9, 2, [10], $db)['allowed']);
        self::assertFalse($policy->auditQuestionIDs(9, 2, [11], $db)['allowed']);
        $valid = $policy->auditQuestionIDs(9, 2, [12], $db);
        self::assertTrue($valid['allowed']);
        self::assertTrue($valid['skipped']);
    }

    /** @return array<int,array<string,mixed>> */
    private function verses(): array
    {
        return [
            ['VerseID' => 1, 'BookID' => 10, 'BookName' => 'Genesis'],
            ['VerseID' => 2, 'BookID' => 10, 'BookName' => 'Genesis'],
            ['VerseID' => 3, 'BookID' => 10, 'BookName' => 'Genesis'],
            ['VerseID' => 4, 'BookID' => 10, 'BookName' => 'Genesis'],
            ['VerseID' => 5, 'BookID' => 11, 'BookName' => 'Exodus'],
        ];
    }
}
