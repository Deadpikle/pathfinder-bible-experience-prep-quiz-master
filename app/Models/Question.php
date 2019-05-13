<?php

namespace App\Models;

use PDO;

class Question
{
    public $questionID;
    public $question;
    public $answer;
    public $numberPoints;
    public $dateCreated;
    public $dateModified;
    public $isFlagged;
    public $type;
    public $commentaryStartPage;
    public $commentaryEndPage;
    public $isDeleted;
    
    public $creatorID;
    public $lastEditedByID;
    public $startVerseID;
    public $endVerseID;
    public $commentaryID;
    public $languageID;

    public function __construct(int $questionID)
    {
        $this->questionID = $questionID;
    }
}
