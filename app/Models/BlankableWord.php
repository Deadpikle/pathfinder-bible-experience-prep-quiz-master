<?php

namespace App\Models;

use PDO;

class BlankableWord
{
    public $wordID;
    public $word;

    public function __construct(int $wordID, string $word)
    {
        $this->wordID = $wordID;
        $this->word = $word;
    }
}
