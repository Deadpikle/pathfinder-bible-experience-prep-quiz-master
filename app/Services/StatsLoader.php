<?php

namespace App\Services;

use App\Models\Question;
use PDO;

class StatsLoader
{
    // this should really return a nicer object, but we are in rush mode while we get
    // the site ready for usage again
    public static function loadQnAQuestionsByChapterInYear(int $yearID, PDO $db, ?QuestionScope $scope = null): array
    {
        $bankPredicate = $scope?->readPredicate('q') ?? ['sql' => '1 = 1', 'params' => []];
        $query = '
            SELECT l.LanguageID, b.Name, c.Number, COUNT(q.QuestionID) AS Count
            FROM Books b
                INNER JOIN Chapters c ON c.BookID = b.BookID
                CROSS JOIN Languages l
                LEFT JOIN Verses v ON v.ChapterID = c.ChapterID
                LEFT JOIN Questions q ON q.StartVerseID = v.VerseID
                    AND q.LanguageID = l.LanguageID
                    AND q.Type = ?
                    AND q.IsDeleted = 0
                    AND q.IsActive = 1
                    AND ' . $bankPredicate['sql'] . '
            WHERE b.YearID = ?
            GROUP BY l.LanguageID, b.BookID, b.Name, b.BibleOrder, c.ChapterID, c.Number
            ORDER BY l.LanguageID, b.BibleOrder, c.Number';
        $stmt = $db->prepare($query);
        $stmt->execute([
            Question::getBibleQnAType(),
            ...$bankPredicate['params'],
            $yearID
        ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $output[] = [
                'language' => $row['LanguageID'],
                'book' => $row['Name'],
                'chapter' => $row['Number'],
                'count' => $row['Count']
            ];
        }
        return $output;
    }

    public static function loadCommentaryQuestionsByYear(int $yearID, PDO $db, ?QuestionScope $scope = null): array
    {
        $bankPredicate = $scope?->readPredicate('q') ?? ['sql' => '1 = 1', 'params' => []];
        $query = '
            SELECT l.LanguageID, c.TopicName, c.Number, COUNT(q.QuestionID) AS Count
            FROM Commentaries c
                CROSS JOIN Languages l
                LEFT JOIN Questions q ON q.CommentaryID = c.CommentaryID
                    AND q.LanguageID = l.LanguageID
                    AND q.Type = ?
                    AND q.IsDeleted = 0
                    AND q.IsActive = 1
                    AND ' . $bankPredicate['sql'] . '
            WHERE c.YearID = ?
            GROUP BY l.LanguageID, c.CommentaryID, c.TopicName, c.Number
            ORDER BY l.LanguageID, c.Number, c.TopicName';
        $stmt = $db->prepare($query);
        $stmt->execute([
            Question::getCommentaryQnAType(),
            ...$bankPredicate['params'],
            $yearID
        ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $output[] = [
                'language' => $row['LanguageID'],
                'topic' => $row['TopicName'],
                'number' => $row['Number'],
                'count' => $row['Count']
            ];
        }
        return $output;
    }

    public static function loadQnAQuestionsByChapterAndVerseInYear(int $yearID, PDO $db, ?QuestionScope $scope = null): array
    {
        $bankPredicate = $scope?->readPredicate('q') ?? ['sql' => '1 = 1', 'params' => []];
        $query = '
            SELECT l.LanguageID, b.Name, c.Number AS Chapter, v.Number AS Verse,
                COUNT(q.QuestionID) AS Count
            FROM Books b
                INNER JOIN Chapters c ON c.BookID = b.BookID
                INNER JOIN Verses v ON v.ChapterID = c.ChapterID
                CROSS JOIN Languages l
                LEFT JOIN Questions q ON q.StartVerseID = v.VerseID
                    AND q.LanguageID = l.LanguageID
                    AND q.Type = ?
                    AND q.IsDeleted = 0
                    AND q.IsActive = 1
                    AND ' . $bankPredicate['sql'] . '
            WHERE b.YearID = ?
            GROUP BY l.LanguageID, b.BookID, b.Name, b.BibleOrder, c.ChapterID, c.Number, v.VerseID, v.Number
            ORDER BY l.LanguageID, b.BibleOrder, c.Number, v.Number';
        $stmt = $db->prepare($query);
        $stmt->execute([
            Question::getBibleQnAType(),
            ...$bankPredicate['params'],
            $yearID
        ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $output[] = [
                'language' => $row['LanguageID'],
                'book' => $row['Name'],
                'chapter' => $row['Chapter'],
                'verse' => $row['Verse'],
                'count' => $row['Count']
            ];
        }
        return $output;
    }
}
