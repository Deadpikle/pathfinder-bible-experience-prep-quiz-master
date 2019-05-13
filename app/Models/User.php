<?php

namespace App\Models;

use PDO;

class User
{
    public $userID;
    public $username;
    public $entryCode;
    public $password;
    public $lastLoginDate;
    
    public $userType;

    public $clubID;
    public $createdByID;
    public $defaultLanguageID;

    public function __construct(int $userID, string $username)
    {
        $this->userID = $userID;
        $this->username = $username;
    }
}
