<?php

namespace App\Models;

use PDO;

class Chapter
{
    public $chapterID;
    public $number;
    public $numberVerses;

    public $verses; // array of Verse objects

    public $bookID;

    public function __construct(int $chapterID, int $number)
    {
        $this->chapterID = $chapterID;
        $this->number = $number;
    }
}
