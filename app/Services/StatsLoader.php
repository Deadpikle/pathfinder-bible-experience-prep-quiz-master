<?php

namespace App\Services;

use App\Models\Question;
use PDO;

class StatsLoader
{
    // this should really return a nicer object, but we are in rush mode while we get
    // the site ready for usage again
    public static function loadQnAQuestionsByChapterInYear(int $yearID, PDO $db): array
    {
        $query = '
            SELECT q.LanguageID, b.Name, c.Number, COUNT(*) AS Count
            FROM Questions q 
                JOIN Verses v ON q.StartVerseID = v.VerseID
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? AND q.Type = ?
            GROUP BY q.LanguageID, b.Name, c.Number
            ORDER BY q.LanguageID, b.Name, c.Number';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $yearID, 
            Question::getBibleQnAType()
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

    public static function loadCommentaryQuestionsByYear(int $yearID, PDO $db): array
    {
        $query = '
            SELECT q.LanguageID, c.TopicName, c.Number, COUNT(*) AS Count
            FROM Questions q 
                JOIN Commentaries c ON c.CommentaryID = q.CommentaryID
            WHERE c.YearID = ? AND q.Type = ?
            GROUP BY q.LanguageID, c.TopicName, c.Number
            ORDER BY q.LanguageID, c.TopicName, c.Number';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $yearID,
            Question::getCommentaryQnAType()
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

    public static function loadQnAQuestionsByChapterAndVerseInYear(int $yearID, PDO $db): array
    {
        $query = '
            SELECT q.LanguageID, b.Name, c.Number AS Chapter, v.Number AS Verse, COUNT(*) AS Count
            FROM Questions q 
                JOIN Verses v ON q.StartVerseID = v.VerseID
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? AND q.Type = ?
            GROUP BY q.LanguageID, b.Name, c.Number, v.Number
            ORDER BY q.LanguageID, b.Name, c.Number, v.Number';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $yearID,
            Question::getBibleQnAType()
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
