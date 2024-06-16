<?php

namespace App\Models;

use Yamf\AppConfig;

class PBEAppConfig extends AppConfig
{
    public bool $isWebAdmin;
    public bool $isConferenceAdmin;
    public bool $isClubAdmin;
    /**
     * isWebAdmin || isConferenceAdmin || isClubAdmin
     */
    public bool $isAdmin;
    public bool $isPathfinder;
    public bool $isGuest;

    public array $settings;
    public string $contactName;
    public string $contactEmail;
    public string $websiteName;
    public string $websiteTabTitle;
    public string $analyticsURL;
    public string $analyticsSiteID;
    public string $footerText;
    
    public string $currentFillInChapters;

    public bool $loggedIn;
    public bool $ENABLE_NKJV_RESTRICTIONS;

    public string $contactToEmail;
    public string $contactFromEmail;
    public string $contactSubjectPrefix;

    public string $recaptchaType;
    public string $recaptchaExpectedDomain;
    public string $recaptchaPrivateKey;
    public string $recaptchaPublicKey;

    public string $sessionName;
    public bool $showCookieConsent;
    public string $cookieDomain;
    public string $headerForAnalytics;

    public string $bannerMessage;
    public bool $bannerIsShown;

    public function __construct(bool $isLocalHost, string $basePath)
    {
        parent::__construct($isLocalHost, $basePath);
        $this->isWebAdmin = false;
        $this->isConferenceAdmin = false;
        $this->isClubAdmin = false;
        $this->isAdmin = false;
        $this->isPathfinder = false;
        $this->isGuest = false;
        
        $this->settings = [];
        $this->contactName = '';
        $this->contactEmail = '';
        $this->websiteName = '';
        $this->websiteTabTitle = '';
        $this->analyticsURL = '';
        $this->analyticsSiteID = '';
        $this->footerText = '';
        $this->currentFillInChapters = '';
        $this->ENABLE_NKJV_RESTRICTIONS = false;
        $this->isGuest = true;
        $this->loggedIn = false;
        $this->contactToEmail = '';
        $this->contactFromEmail = '';
        $this->contactSubjectPrefix = '';
        $this->recaptchaType = '';
        $this->recaptchaExpectedDomain = '';
        $this->recaptchaPrivateKey = '';
        $this->recaptchaPublicKey = '';

        $this->sessionName = '';
        $this->showCookieConsent = true;
        $this->cookieDomain = '';
        $this->headerForAnalytics = '';

        $this->bannerMessage = '';
        $this->bannerIsShown = false;
    }
}
