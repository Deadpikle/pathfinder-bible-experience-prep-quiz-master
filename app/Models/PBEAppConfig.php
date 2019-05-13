<?php

namespace App\Models;

use Yamf\AppConfig;

class PBEAppConfig extends AppConfig
{
    public function __construct(bool $isLocalHost, string $basePath)
    {
        parent::__construct($isLocalHost, $basePath);
    }
}
