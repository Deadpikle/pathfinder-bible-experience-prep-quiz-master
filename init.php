<?php

    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__));

    require_once("config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("database.php");
    require_once("functions.php");

    $loggedIn = FALSE;
    $isOnLoginPage = strpos($_SERVER['REQUEST_URI'], 'login.php') !== false;
    $isOnOtherNonLoginPage = strpos($_SERVER['REQUEST_URI'], 'about.php') !== false;
    $isUserIDSessionSet = isset($_SESSION["UserID"]);
    if (!isset($_SESSION["UserID"]) && !$isOnLoginPage && !$isOnOtherNonLoginPage) {
        header("Location: login.php");
    }
    if ($isUserIDSessionSet) {
        $loggedIn = TRUE;
    }
    
    $isGuest = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "Guest";
    $isClubAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "ClubAdmin";
    $isConferenceAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "ConferenceAdmin";
    $isWebAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "WebAdmin";
    $isAdmin = $isClubAdmin || $isWebAdmin || $isConferenceAdmin;
    $isPathfinder = !($isAdmin);

    // init settings
    $settings = get_settings($pdo);
    $contactName = isset($settings['AboutContactName']) ? $settings['AboutContactName'] : '[name redacted]';
    $contactEmail = isset($settings['AboutContactEmail']) ? $settings['AboutContactEmail'] : '[email redacted]';
    $websiteName = isset($settings['WebsiteName']) ? $settings['WebsiteName'] : 'UCC Quiz Engine';
    $websiteTabTitle = isset($settings['WebsiteTabTitle']) ? $settings['WebsiteTabTitle'] : 'UCC PBE';
    $analyticsURL = isset($settings['AnalyticsURL']) ? $settings['AnalyticsURL'] : '';
    $analyticsSiteID = isset($settings['AnalyticsSiteID']) ? $settings['AnalyticsSiteID'] : '1';

    // get active year
    $yearData = get_active_year($pdo);
    $activeYearID = $yearData["YearID"];
    $activeYearNumber = $yearData["Year"];
?>