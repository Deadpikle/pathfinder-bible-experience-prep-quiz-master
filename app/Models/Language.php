<?php

namespace App\Models;

use PDO;

class Language
{
    public $languageID;
    public $name;
    public $isDefault;

    public function __construct(int $languageID, string $name)
    {
        $this->languageID = $languageID;
        $this->name = $name;
        $this->isDefault = false;
    }
}
