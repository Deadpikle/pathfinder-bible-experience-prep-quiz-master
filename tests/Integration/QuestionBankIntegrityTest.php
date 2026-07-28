<?php
declare(strict_types=1);

use App\Models\Conference;
use App\Models\FlagReason;
use App\Models\QuestionBank;
use App\Models\UserAnswer;
use App\Models\UserFlagged;
use App\Services\QuestionScope;
use PHPUnit\Framework\TestCase;

final class QuestionBankIntegrityTest extends TestCase
{
    private ?PDO $db = null;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_DB_DSN');
        if ($dsn === false || trim($dsn) === '') {
            $this->markTestSkipped('Set TEST_DB_DSN to run MariaDB integration tests.');
        }

        $this->db = new PDO(
            $dsn,
            getenv('TEST_DB_USER') ?: getenv('DB_USER') ?: 'root',
            getenv('TEST_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db?->inTransaction()) {
            $this->db->rollBack();
        }
        $this->db = null;
    }

    public function testSeededUserStateAndQuestionScopeAreEnforced(): void
    {
        $db = $this->db;
        $this->assertInstanceOf(PDO::class, $db);

        $userID = (int)$db->query("
            SELECT UserID FROM Users WHERE Username = 'Test Pathfinder' LIMIT 1
        ")->fetchColumn();
        $questionID = (int)$db->query("
            SELECT QuestionID FROM Questions
            WHERE Question = 'What does the test fixture establish?'
            LIMIT 1
        ")->fetchColumn();
        $conferenceID = (int)$db->query("
            SELECT ConferenceID FROM Conferences WHERE Name = 'Test Conference' LIMIT 1
        ")->fetchColumn();

        $this->assertGreaterThan(0, $userID);
        $this->assertGreaterThan(0, $questionID);
        $this->assertGreaterThan(0, $conferenceID);

        $db->prepare('DELETE FROM UserAnswers WHERE UserID = ? AND QuestionID = ?')
            ->execute([$userID, $questionID]);
        $this->assertTrue(UserAnswer::saveUserAnswersForUser(
            [[
                'questionID' => $questionID,
                'userID' => 999999,
                'dateAnswered' => '1900-01-01 00:00:00',
                'userAnswer' => 'First answer',
                'correct' => false,
            ]],
            $userID,
            $db,
            new DateTimeImmutable('2026-07-28 10:00:00')
        ));
        $this->assertTrue(UserAnswer::saveUserAnswersForUser(
            [[
                'questionID' => $questionID,
                'userAnswer' => 'Updated answer',
                'correct' => true,
            ]],
            $userID,
            $db,
            new DateTimeImmutable('2026-07-28 10:01:00')
        ));

        $answerStmt = $db->prepare('
            SELECT UserID, Answer, WasCorrect, DateAnswered
            FROM UserAnswers WHERE UserID = ? AND QuestionID = ?
        ');
        $answerStmt->execute([$userID, $questionID]);
        $answers = $answerStmt->fetchAll();
        $this->assertCount(1, $answers);
        $this->assertSame($userID, (int)$answers[0]['UserID']);
        $this->assertSame('Updated answer', $answers[0]['Answer']);
        $this->assertSame(1, (int)$answers[0]['WasCorrect']);
        $this->assertSame('2026-07-28 10:01:00', $answers[0]['DateAnswered']);

        $db->prepare('DELETE FROM UserFlagged WHERE UserID = ? AND QuestionID = ?')
            ->execute([$userID, $questionID]);
        $this->assertTrue(UserFlagged::addFlagIfNecessary(
            $questionID,
            $userID,
            FlagReason::NEEDS_PRACTICE,
            $db
        ));
        $this->assertTrue(UserFlagged::addFlagIfNecessary(
            $questionID,
            $userID,
            FlagReason::OTHER,
            $db
        ));
        $flagCount = $db->prepare('
            SELECT COUNT(*) FROM UserFlagged WHERE UserID = ? AND QuestionID = ?
        ');
        $flagCount->execute([$userID, $questionID]);
        $this->assertSame(1, (int)$flagCount->fetchColumn());

        $global = QuestionBank::loadGlobal($db);
        $overlay = QuestionBank::loadConferenceOverlay($conferenceID, $db);
        $this->assertNotNull($global);
        $this->assertNotNull($overlay);

        $pathfinderScope = QuestionScope::forUser($userID, $db, $conferenceID);
        $this->assertTrue($pathfinderScope->canReadBank($global->questionBankID));
        $this->assertTrue($pathfinderScope->canReadBank($overlay->questionBankID));
        $this->assertFalse($pathfinderScope->canWriteBank($overlay->questionBankID));

        $guestScope = QuestionScope::forRole(QuestionScope::ROLE_GUEST, $conferenceID, $db);
        $this->assertTrue($guestScope->canReadBank($global->questionBankID));
        $this->assertFalse($guestScope->canReadBank($overlay->questionBankID));

        $clubAdminTypeID = (int)$db->query("
            SELECT UserTypeID FROM UserTypes WHERE Type = 'ClubAdmin' LIMIT 1
        ")->fetchColumn();
        $db->prepare('UPDATE Users SET UserTypeID = ? WHERE UserID = ?')
            ->execute([$clubAdminTypeID, $userID]);
        $adminScope = QuestionScope::forUser($userID, $db, $conferenceID);
        $this->assertTrue($adminScope->canWriteBank($overlay->questionBankID));
        $this->assertFalse($adminScope->canWriteBank($global->questionBankID));

        $webAdminTypeID = (int)$db->query("
            SELECT UserTypeID FROM UserTypes WHERE Type = 'WebAdmin' LIMIT 1
        ")->fetchColumn();
        $db->prepare('UPDATE Users SET UserTypeID = ? WHERE UserID = ?')
            ->execute([$webAdminTypeID, $userID]);
        $webAdminScope = QuestionScope::forUser($userID, $db, $conferenceID);
        $this->assertTrue($webAdminScope->canReadBank($overlay->questionBankID));
        $this->assertTrue($webAdminScope->canWriteBank($global->questionBankID));
        $this->assertFalse($webAdminScope->canWriteBank($overlay->questionBankID));

        $conference = new Conference(-1, 'Transaction Test Conference');
        $conference->url = 'https://test.invalid/transaction';
        $conference->contactName = 'Transaction Test';
        $conference->contactEmail = 'transaction@example.invalid';
        $conference->create($db);
        $mappingCount = $db->prepare('
            SELECT COUNT(*) FROM ConferenceQuestionBanks WHERE ConferenceID = ?
        ');
        $mappingCount->execute([$conference->conferenceID]);
        $this->assertSame(2, (int)$mappingCount->fetchColumn());
    }
}
