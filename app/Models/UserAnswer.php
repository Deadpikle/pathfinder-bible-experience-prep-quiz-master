<?php

namespace App\Models;

use PDO;

class UserAnswer
{
    public $userAnswerID;
    public $answer;
    public $dateAnswered;
    public $wasCorrect;
    
    public $questionID;
    public $userID;

    public function __construct(int $userAnswerID, string $answer)
    {
        $this->userAnswerID = $userAnswerID;
        $this->answer = $answer;
    }
}
