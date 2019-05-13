<?php

namespace App\Models;

use PDO;

class HomeInfoItem
{
    public $homeInfoItemID;
    public $isLink;
    public $text;
    public $url;
    public $sortOrder;

    public $homeInfoLineID;

    public function __construct(int $homeInfoItemID)
    {
        $this->homeInfoItemID = $homeInfoItemID;
    }
}
