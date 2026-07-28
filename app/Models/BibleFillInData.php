<?php

namespace App\Models;

use App\Services\FillInAvailabilityPolicy;
use PDO;

class BibleFillInData
{
    public int $chapterID;
    public int $chapterNumber;
    public string $bookName;
    
    public ?Language $language;
    public int $numberOfQuestions;

    public function __construct()
    {
        $this->chapterID = -1;
        $this->chapterNumber = 0;
        $this->bookName = '';
        $this->language = null;
        $this->numberOfQuestions = 0;
    }

    /** @return array<BibleFillInData> */
    public static function loadFillInData(Year $year, PDO $db): array
    {
        $query = '
            SELECT c.ChapterID, c.Number, b.Name, COUNT(q.QuestionID) AS QuestionCount, q.LanguageID
            FROM Questions q
                JOIN QuestionBanks qb
                    ON qb.QuestionBankID = q.QuestionBankID
                    AND qb.IsGlobal = 1
                    AND qb.IsDeleted = 0
                JOIN Verses v ON q.StartVerseID = v.VerseID
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ?
                AND q.Type = ?
                AND q.IsDeleted = 0
                AND q.IsActive = 1
            GROUP BY c.ChapterID, c.Number, q.LanguageID
            ORDER BY b.bibleOrder, b.Name, c.Number, q.LanguageID';
        
        $data = [];
        $languagesByID = Language::loadAllLanguagesByID($db);
        $stmt = $db->prepare($query);
        $stmt->execute([ 
            $year->yearID, 
            Question::getBibleQnAFillType() 
        ]);
        $bookQuestionData = $stmt->fetchAll();
        foreach ($bookQuestionData as $row) {
            $fillInData = new BibleFillInData();
            $fillInData->chapterID = $row['ChapterID'];
            $fillInData->chapterNumber = $row['Number'];
            $fillInData->bookName = $row['Name'];
            $fillInData->numberOfQuestions = $row['QuestionCount'];
            $fillInData->language = $languagesByID[$row['LanguageID']];
            $data[] = $fillInData;
        }

        return $data;
    }

    public static function deleteFillInsForLanguage(Year $year, int $languageID, PDO $db): void
    {
        FillInAvailabilityPolicy::acquireLock($db);
        try {
            $query = '
                UPDATE Questions
                SET IsActive = 0, IsDeleted = 1
                WHERE QuestionID IN (
                    SELECT scoped.QuestionID
                    FROM (
                        SELECT q.QuestionID
                        FROM Questions q
                        INNER JOIN QuestionBanks qb
                            ON qb.QuestionBankID = q.QuestionBankID
                            AND qb.IsGlobal = 1
                            AND qb.IsDeleted = 0
                        INNER JOIN Verses v ON q.StartVerseID = v.VerseID
                        INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
                        INNER JOIN Books b ON b.BookID = c.BookID
                        WHERE q.Type = ?
                            AND b.YearID = ?
                            AND q.LanguageID = ?
                    ) scoped
                )';
            $stmt = $db->prepare($query);
            $stmt->execute([
                Question::getBibleQnAFillType(),
                $year->yearID,
                $languageID,
            ]);
        } finally {
            FillInAvailabilityPolicy::releaseLock($db);
        }
    }

    public static function deleteFillInsForChapter(Year $year, int $chapterID, int $languageID, PDO $db): void
    {
        FillInAvailabilityPolicy::acquireLock($db);
        try {
            $query = '
                UPDATE Questions
                SET IsActive = 0, IsDeleted = 1
                WHERE QuestionID IN (
                    SELECT scoped.QuestionID
                    FROM (
                        SELECT q.QuestionID
                        FROM Questions q
                        INNER JOIN QuestionBanks qb
                            ON qb.QuestionBankID = q.QuestionBankID
                            AND qb.IsGlobal = 1
                            AND qb.IsDeleted = 0
                        INNER JOIN Verses v ON q.StartVerseID = v.VerseID
                        INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
                        INNER JOIN Books b ON b.BookID = c.BookID
                        WHERE c.ChapterID = ?
                            AND q.Type = ?
                            AND b.YearID = ?
                            AND q.LanguageID = ?
                    ) scoped
                )';
            $stmt = $db->prepare($query);
            $stmt->execute([
                $chapterID,
                Question::getBibleQnAFillType(),
                $year->yearID,
                $languageID,
            ]);
        } finally {
            FillInAvailabilityPolicy::releaseLock($db);
        }
    }
}
