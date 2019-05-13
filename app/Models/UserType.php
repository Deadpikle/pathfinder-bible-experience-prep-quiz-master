<?php

namespace App\Models;

use PDO;

class UserType
{
    public $userTypeID;
    public $type;
    public $displayName;

    public function __construct(int $userTypeID, string $type)
    {
        $this->userTypeID = $userTypeID;
        $this->type = $type;
    }
}
