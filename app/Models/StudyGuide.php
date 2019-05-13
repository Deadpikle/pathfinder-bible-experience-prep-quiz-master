<?php

namespace App\Models;

use PDO;

class StudyGuide
{
    public $studyGuideID;
    public $displayName;
    public $fileName;
    
    public $yearID;

    public function __construct(int $studyGuideID, string $displayName)
    {
        $this->studyGuideID = $studyGuideID;
        $this->displayName = $displayName;
    }
}
