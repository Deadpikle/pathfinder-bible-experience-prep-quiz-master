<?php

namespace App\Models;

use PDO;

class Verse
{
    public $verseID;
    public $number;
    public $text;
    
    public $chapterID;

    public function __construct(int $verseID, int $number)
    {
        $this->verseID = $verseID;
        $this->number = $number;
    }
}
