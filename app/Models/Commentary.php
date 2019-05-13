<?php

namespace App\Models;

use PDO;

class Commentary
{
    public $commentaryID;
    public $number;
    public $topicName;

    public $yearID;

    public function __construct(int $commentaryID, int $number)
    {
        $this->commentaryID = $commentaryID;
        $this->number = $number;
    }
}
