<?php

namespace App\Models;

use PDO;

class BibleQnAData
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

    /** @return array<BibleQnAData> */
    public static function loadQnAData(Year $year, PDO $db): array
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
            ORDER BY b.Name, c.Number, q.LanguageID';
        
        $data = [];
        $languages = Language::loadAllLanguages($db);
        $languagesByID = [];
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }
        $stmt = $db->prepare($query);
        $stmt->execute([ 
            $year->yearID, 
            Question::getBibleQnAType() 
        ]);
        $bookQuestionData = $stmt->fetchAll();
        foreach ($bookQuestionData as $row) {
            $qnaData = new BibleQnAData();
            $qnaData->chapterID = $row['ChapterID'];
            $qnaData->chapterNumber = $row['Number'];
            $qnaData->bookName = $row['Name'];
            $qnaData->numberOfQuestions = $row['QuestionCount'];
            $qnaData->language = $languagesByID[$row['LanguageID']];
            $data[] = $qnaData;
        }
        return $data;
    }

    public static function deleteQnAForLanguage(Year $year, int $languageID, PDO $db): void
    {
        // The extra derived table keeps this compatible with MariaDB's
        // restriction on selecting directly from a table being deleted.
        $query = '
            DELETE FROM Questions
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
                        AND q.LanguageID = ?
                        AND b.YearID = ?
                ) scoped
            )';
        $stmt = $db->prepare($query);
        $stmt->execute([
            Question::getBibleQnAType(),
            $languageID,
            $year->yearID,
        ]);
    }

    public static function deleteQnAForChapter(Year $year, int $chapterID, int $languageID, PDO $db): void
    {
        $query = '
            DELETE FROM Questions
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
                        AND q.LanguageID = ?
                        AND c.ChapterID = ?
                        AND b.YearID = ?
                ) scoped
            )';
        $stmt = $db->prepare($query);
        $stmt->execute([
            Question::getBibleQnAType(),
            $languageID,
            $chapterID,
            $year->yearID,
        ]);
    }
}
