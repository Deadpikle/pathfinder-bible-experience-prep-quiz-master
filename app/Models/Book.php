<?php

namespace App\Models;

use PDO;

class Book
{
    public $bookID;
    public $name;
    public $numberChapters;

    public $yearID;

    public function __construct(int $bookID, string $name)
    {
        $this->bookID = $bookID;
        $this->name = $name;
    }
}
