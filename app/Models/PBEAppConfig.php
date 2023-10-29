<?php

namespace App\Models;

use Yamf\AppConfig;

class PBEAppConfig extends AppConfig
{
    public bool $isWebAdmin;

    public array $settings;
    public string $contactName;
    public string $contactEmail;
    public string $websiteName;
    public string $websiteTabTitle;
    public string $analyticsURL;
    public string $analyticsSiteID;
    public string $footerText;
    
    public string $currentFillInChapters;

    public function __construct(bool $isLocalHost, string $basePath)
    {
        parent::__construct($isLocalHost, $basePath);
        $this->isWebAdmin = false;
        
        $this->settings = [];
        $this->contactName = '';
        $this->contactEmail = '';
        $this->websiteName = '';
        $this->websiteTabTitle = '';
        $this->analyticsURL = '';
        $this->analyticsSiteID = '';
        $this->footerText = '';
        $this->currentFillInChapters = '';
    }
}
