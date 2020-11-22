<?php

namespace App\Models;

use PDO;

class Verse
{
    public $verseID;
    public $number;
    public $text;
    
    public $chapterID;

    public function __construct(?int $verseID, ?int $number)
    {
        $this->verseID = $verseID;
        $this->number = $number;
    }

    public static function createAllVersesForChapter(int $chapterID, int $numberVerses, PDO $db)
    {
        $insertQuery = ' INSERT INTO Verses (Number, VerseText, ChapterID) VALUES (?, ?, ?)';
        $insertStmt = $db->prepare($insertQuery);
        for ($i = 0; $i < $numberVerses; $i++) {
            $params = [
                ($i+1),
                '',
                $chapterID
            ];
            $insertStmt->execute($params);
        }
    }
}
