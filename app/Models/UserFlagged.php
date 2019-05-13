<?php

namespace App\Models;

use PDO;

class UserFlagged
{
    public $userFlaggedID;
    public $userID;
    public $questionID;

    public function __construct(int $userFlaggedID, int $userID, int $questionID)
    {
        $this->userFlaggedID = $userFlaggedID;
        $this->userID = $userID;
        $this->questionID = $questionID;
    }
}
