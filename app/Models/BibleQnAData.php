<?php

namespace App\Models;

use PDO;

class BibleQnAData
{
    public $chapterID;
    public $chapterNumber;
    public $bookName;
    
    public $language;
    public $numberOfQuestions;

    public static function loadQnAData(Year $year, PDO $db) : array
    {
        $query = '
            SELECT c.ChapterID, c.Number, b.Name, COUNT(q.QuestionID) AS QuestionCount, q.LanguageID
            FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ?
                AND q.Type = ?
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

    public static function deleteQnAForLanguage(Year $year, int $languageID, PDO $db)
    {
        $qnaType = Question::getBibleQnAType();
        // the weird subquery SELECT * was due to a workaround
        // for the error discussed here: https://stackoverflow.com/q/44970574/3938401
        $query = 'DELETE FROM Questions
                    WHERE QuestionID IN (
                    SELECT q.QuestionID 
                    FROM (SELECT QuestionID, Type, LanguageID, StartVerseID FROM Questions WHERE Type = "' . $qnaType . '" AND LanguageID = ?) q 
                        JOIN Verses v ON q.StartVerseID = v.VerseID
                        JOIN Chapters c ON c.ChapterID = v.ChapterID
                        JOIN Books b ON b.BookID = c.BookID
                    WHERE q.Type = "' . $qnaType . '"
                        AND b.YearID = ?
                        AND q.LanguageID = ?)';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $languageID,
            $year->yearID,
            $languageID
        ]);
    }

    public static function deleteQnAForChapter(Year $year, int $chapterID, int $languageID, PDO $db)
    {
        // the weird subquery SELECT * was due to a workaround
        // for the error discussed here: https://stackoverflow.com/q/44970574/3938401
        $qnaType = Question::getBibleQnAType();
        $query = 'DELETE FROM Questions 
                      WHERE QuestionID IN (
                        SELECT q.QuestionID 
                        FROM (SELECT QuestionID, Type, LanguageID, StartVerseID FROM Questions WHERE Type = "' . $qnaType . '" AND LanguageID = ?) q 
                            JOIN Verses v ON q.StartVerseID = v.VerseID
                            JOIN Chapters c ON c.ChapterID = v.ChapterID
                            JOIN Books b ON b.BookID = c.BookID
                        WHERE c.ChapterID = ? AND q.Type = "' . $qnaType . '"
                            AND b.YearID = ? AND q.LanguageID = ?)';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $languageID,
            $chapterID,
            $year->yearID,
            $languageID
        ]);
    }
}
