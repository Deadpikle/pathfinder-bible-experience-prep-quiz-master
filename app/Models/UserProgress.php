<?php

namespace App\Models;

use App\Services\QuestionScope;
use PDO;

final class UserProgress
{
    /** @return array<string,mixed> */
    public static function loadForUser(int $userID, PDO $db): array
    {
        $contextStmt = $db->prepare(
            'SELECT u.UserID, u.Username, u.ClubID, u.PreferredLanguageID,
                    c.Name AS ClubName, c.ConferenceID, conf.Name AS ConferenceName
             FROM Users u
             LEFT JOIN Clubs c ON c.ClubID = u.ClubID
             LEFT JOIN Conferences conf ON conf.ConferenceID = c.ConferenceID
             WHERE u.UserID = ? AND u.WasDeleted = 0'
        );
        $contextStmt->execute([$userID]);
        $user = $contextStmt->fetch();
        if (!$user) {
            return [];
        }

        $languageID = (int)($user['PreferredLanguageID'] ?? 0);
        if ($languageID <= 0) {
            $languageID = self::englishLanguageID($db);
        }
        $year = Year::loadCurrentYear($db);
        $scope = QuestionScope::forUser($userID, $db, (int)($user['ConferenceID'] ?? 0));
        $bankPredicate = $scope->readPredicate('q');

        $chapterSql = '
            SELECT b.BookID, b.Name AS BookName, b.BibleOrder,
                   c.ChapterID, c.Number AS ChapterNumber,
                   COUNT(DISTINCT q.QuestionID) AS AccessibleCount,
                   COUNT(DISTINCT CASE
                       WHEN COALESCE(ua.WasCorrect, uqm.IsMastered, 0) = 1 THEN q.QuestionID
                   END) AS MasteredCount
            FROM Questions q
            INNER JOIN Verses v ON v.VerseID = q.StartVerseID
            INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
            INNER JOIN Books b ON b.BookID = c.BookID
            LEFT JOIN UserQuestionMastery uqm
                ON uqm.QuestionID = q.QuestionID AND uqm.UserID = ?
            LEFT JOIN UserAnswers ua
                ON ua.QuestionID = q.QuestionID AND ua.UserID = ?
            WHERE q.IsDeleted = 0
              AND q.IsActive = 1
              AND q.Type IN (?, ?)
              AND q.LanguageID = ?
              AND b.YearID = ?
              AND ' . $bankPredicate['sql'] . '
            GROUP BY b.BookID, b.Name, b.BibleOrder, c.ChapterID, c.Number
            ORDER BY b.BibleOrder, c.Number';
        $chapterStmt = $db->prepare($chapterSql);
        $chapterStmt->execute(array_merge([
            $userID,
            $userID,
            Question::getBibleQnAType(),
            Question::getBibleQnAFillType(),
            $languageID,
            $year->yearID,
        ], $bankPredicate['params']));
        $mastery = self::summarizeChapterMasteryRows($chapterStmt->fetchAll());

        $historyStmt = $db->prepare(
            'SELECT qa.QuizAttemptID, qa.StartedAt, qa.CompletedAt, qa.PointsEarned,
                    qa.PointsPossible, COUNT(qai.QuizAttemptItemID) AS QuestionCount,
                    SUM(CASE WHEN qai.WasCorrect = 1 THEN 1 ELSE 0 END) AS CorrectCount
             FROM QuizAttempts qa
             INNER JOIN QuizAttemptItems qai ON qai.QuizAttemptID = qa.QuizAttemptID
             WHERE qa.UserID = ? AND qa.IsCompleted = 1
             GROUP BY qa.QuizAttemptID, qa.StartedAt, qa.CompletedAt,
                      qa.PointsEarned, qa.PointsPossible
             ORDER BY qa.CompletedAt DESC, qa.QuizAttemptID DESC'
        );
        $historyStmt->execute([$userID]);
        $history = $historyStmt->fetchAll();

        $language = Language::loadLanguageWithID($languageID, $db);
        return [
            'user' => $user,
            'year' => $year,
            'language' => $language,
            'accessibleQuestions' => $mastery['accessibleQuestions'],
            'masteredQuestions' => $mastery['masteredQuestions'],
            'masteryPercent' => $mastery['masteryPercent'],
            'chapterMastery' => $mastery['chapters'],
            'attempts' => $history,
        ];
    }

    /**
     * Convert grouped database rows into view data and a weighted overall
     * percentage. Overall mastery is calculated from question totals, not by
     * averaging chapter percentages, so small chapters cannot skew the result.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{
     *   accessibleQuestions:int,
     *   masteredQuestions:int,
     *   masteryPercent:int,
     *   chapters:array<int,array<string,int|string>>
     * }
     */
    public static function summarizeChapterMasteryRows(array $rows): array
    {
        $chapters = [];
        $accessibleTotal = 0;
        $masteredTotal = 0;

        foreach ($rows as $row) {
            $accessible = max(0, (int)($row['AccessibleCount'] ?? 0));
            $mastered = min($accessible, max(0, (int)($row['MasteredCount'] ?? 0)));
            $accessibleTotal += $accessible;
            $masteredTotal += $mastered;
            $chapters[] = [
                'bookID' => (int)($row['BookID'] ?? 0),
                'bookName' => (string)($row['BookName'] ?? ''),
                'chapterID' => (int)($row['ChapterID'] ?? 0),
                'chapterNumber' => (int)($row['ChapterNumber'] ?? 0),
                'accessibleQuestions' => $accessible,
                'masteredQuestions' => $mastered,
                'masteryPercent' => self::percentage($mastered, $accessible),
            ];
        }

        return [
            'accessibleQuestions' => $accessibleTotal,
            'masteredQuestions' => $masteredTotal,
            'masteryPercent' => self::percentage($masteredTotal, $accessibleTotal),
            'chapters' => $chapters,
        ];
    }

    /**
     * UserAnswers is the canonical current answer state. Attempt mastery is a
     * fallback for future records that may not have a legacy answer row.
     */
    public static function resolveMasteryState(?bool $userAnswerWasCorrect, ?bool $attemptMastery): bool
    {
        return $userAnswerWasCorrect ?? $attemptMastery ?? false;
    }

    public static function isReportablePathfinder(User $user): bool
    {
        return $user->type?->type === 'Pathfinder';
    }

    /** @return array<int,array<string,mixed>> */
    public static function loadUsersForAdmin(PBEAppConfig $app, PDO $db): array
    {
        if ($app->isWebAdmin) {
            $users = User::loadAllUsers($db);
        } elseif ($app->isClubAdmin) {
            $users = User::loadUsersInClub(User::currentClubID(), $db);
        } else {
            return [];
        }

        $summaries = [];
        foreach ($users as $user) {
            if (!self::isReportablePathfinder($user)) {
                continue;
            }
            $progress = self::loadForUser($user->userID, $db);
            if ($progress !== []) {
                $summaries[] = $progress;
            }
        }
        return $summaries;
    }

    public static function canAdminViewUser(PBEAppConfig $app, int $targetUserID, PDO $db): bool
    {
        if ($app->isWebAdmin) {
            $user = User::loadUserByID($targetUserID, $db);
            return $user !== null && self::isReportablePathfinder($user);
        }
        if (!$app->isClubAdmin) {
            return false;
        }
        $stmt = $db->prepare(
            'SELECT 1
             FROM Users u
             INNER JOIN UserTypes ut ON ut.UserTypeID = u.UserTypeID
             WHERE u.UserID = ? AND u.ClubID = ? AND u.WasDeleted = 0
               AND ut.Type = ?
             LIMIT 1'
        );
        $stmt->execute([$targetUserID, User::currentClubID(), 'Pathfinder']);
        return (bool)$stmt->fetchColumn();
    }

    private static function englishLanguageID(PDO $db): int
    {
        $stmt = $db->query("SELECT LanguageID FROM Languages WHERE Abbreviation = 'en' ORDER BY LanguageID LIMIT 1");
        $languageID = (int)$stmt->fetchColumn();
        if ($languageID > 0) {
            return $languageID;
        }
        return (int)$db->query('SELECT LanguageID FROM Languages ORDER BY LanguageID LIMIT 1')->fetchColumn();
    }

    private static function percentage(int $mastered, int $accessible): int
    {
        return $accessible > 0 ? (int)round(($mastered / $accessible) * 100) : 0;
    }
}
