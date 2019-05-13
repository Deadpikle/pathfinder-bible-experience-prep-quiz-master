<?php

namespace App\Models;

use PDO;

class HomeInfoSection
{
    public $homeInfoSectionID;
    public $name;
    public $sortOrder;
    
    public $yearID;
    public $conferenceID;

    public function __construct(int $homeInfoSectionID, string $name)
    {
        $this->homeInfoSectionID = $homeInfoSectionID;
        $this->name = $name;
    }
}
