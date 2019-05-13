<?php

namespace App\Models;

use PDO;

class Club
{
    public $clubID;
    public $name;
    public $url;
    
    public $conferenceID;

    public function __construct(int $clubID, string $name)
    {
        $this->clubID = $clubID;
        $this->name = $name;
    }
}
