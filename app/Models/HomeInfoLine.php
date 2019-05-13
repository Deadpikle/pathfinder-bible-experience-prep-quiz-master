<?php

namespace App\Models;

use PDO;

class HomeInfoLine
{
    public $homeInfoLineID;
    public $name;
    public $sortOrder;

    public $homeInfoSectionID;

    public function __construct(int $homeInfoLineID, string $name)
    {
        $this->homeInfoLineID = $homeInfoLineID;
        $this->name = $name;
    }
}
