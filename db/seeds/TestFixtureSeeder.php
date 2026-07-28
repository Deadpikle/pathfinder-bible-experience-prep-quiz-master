<?php
declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class TestFixtureSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['DefaultSeeder'];
    }

    public function run(): void
    {
        $db = $this->getAdapter()->getConnection();

        $yearID = (int)$db->query('
            SELECT YearID FROM Years WHERE IsCurrent = 1 ORDER BY YearID LIMIT 1
        ')->fetchColumn();
        $languageID = (int)$db->query('
            SELECT LanguageID FROM Languages WHERE IsDefault = 1 ORDER BY LanguageID LIMIT 1
        ')->fetchColumn();
        $pathfinderTypeID = (int)$db->query("
            SELECT UserTypeID FROM UserTypes WHERE Type = 'Pathfinder' LIMIT 1
        ")->fetchColumn();
        $globalBankID = (int)$db->query('
            SELECT QuestionBankID FROM QuestionBanks
            WHERE IsGlobal = 1 AND IsDeleted = 0
            ORDER BY QuestionBankID LIMIT 1
        ')->fetchColumn();

        if ($yearID <= 0 || $languageID <= 0 || $pathfinderTypeID <= 0 || $globalBankID <= 0) {
            throw new RuntimeException('Default seed data and all migrations are required before test fixtures.');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $insertConference = $db->prepare('
                INSERT INTO Conferences (Name, URL, ContactName, ContactEmail)
                VALUES (?, ?, ?, ?)
            ');
            $insertConference->execute([
                'Test Conference',
                'https://test.invalid/conference',
                'Test Contact',
                'test-contact@example.invalid',
            ]);
            $conferenceID = (int)$db->lastInsertId();

            $insertBank = $db->prepare('
                INSERT INTO QuestionBanks (Name, IsGlobal, IsDeleted)
                VALUES (?, 0, 0)
            ');
            $insertBank->execute(['Test Conference Private']);
            $overlayBankID = (int)$db->lastInsertId();

            $mapBank = $db->prepare('
                INSERT INTO ConferenceQuestionBanks
                    (ConferenceID, QuestionBankID, IsOverlay)
                VALUES (?, ?, ?)
            ');
            $mapBank->execute([$conferenceID, $globalBankID, 0]);
            $mapBank->execute([$conferenceID, $overlayBankID, 1]);

            $insertClub = $db->prepare('
                INSERT INTO Clubs (Name, URL, ConferenceID)
                VALUES (?, ?, ?)
            ');
            $insertClub->execute([
                'Test Pathfinder Club',
                'https://test.invalid/club',
                $conferenceID,
            ]);
            $clubID = (int)$db->lastInsertId();

            $insertUser = $db->prepare('
                INSERT INTO Users
                    (Username, EntryCode, Password, LastLoginDate, UserTypeID,
                     ClubID, CreatedByID, PreferredLanguageID, UILanguageID,
                     PrefersDarkMode, WasDeleted)
                VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, 0, 0)
            ');
            $insertUser->execute([
                'Test Pathfinder',
                'testp1',
                '',
                '2000-01-01 00:00:00',
                $pathfinderTypeID,
                $clubID,
                $languageID,
                $languageID,
            ]);
            $userID = (int)$db->lastInsertId();

            $insertBook = $db->prepare('
                INSERT INTO Books (Name, NumberChapters, YearID, BibleOrder)
                VALUES (?, 1, ?, 1)
            ');
            $insertBook->execute(['Test Book', $yearID]);
            $bookID = (int)$db->lastInsertId();

            $insertChapter = $db->prepare('
                INSERT INTO Chapters (Number, NumberVerses, BookID)
                VALUES (1, 2, ?)
            ');
            $insertChapter->execute([$bookID]);
            $chapterID = (int)$db->lastInsertId();

            $insertVerse = $db->prepare('
                INSERT INTO Verses (Number, VerseText, ChapterID)
                VALUES (?, ?, ?)
            ');
            $insertVerse->execute([1, 'In the beginning of the test fixture.', $chapterID]);
            $startVerseID = (int)$db->lastInsertId();
            $insertVerse->execute([2, 'The fixture continued safely.', $chapterID]);
            $endVerseID = (int)$db->lastInsertId();

            $insertQuestion = $db->prepare('
                INSERT INTO Questions
                    (QuestionBankID, Question, Answer, NumberPoints, Type,
                     CreatorID, LastEditedByID, StartVerseID, EndVerseID,
                     LanguageID, IsDeleted, IsActive)
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 0, 1)
            ');
            $insertQuestion->execute([
                $globalBankID,
                'What does the test fixture establish?',
                'A scoped global question.',
                'bible-qna',
                $userID,
                $userID,
                $startVerseID,
                $endVerseID,
                $languageID,
            ]);

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}
