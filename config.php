<?php

// Set up any session/config parameters you need in for your website that are safe
// to add to your repo, such as session logic, etc. 
// Add the info to the $app class if you want it to automatically 
// be available to your controllers and views. This file contains 
// some YAMF configuration parameters, too.

// DO NOT save any private info to this file, including database credentials, etc.
// Sharing such credentials in any form is dangerous for a multitude of reasons.

// Over time, YAMF may have new config parameters. Any of those parameters will show up
// BELOW all other parameters, so, we suggest that you add any of your own, custom
// ones above all YAMF built-in options. Each section will be preceded by the YAMF
// version number that the setting was first introduced in. Make sure to read
// release notes when updating YAMF versions so that you're aware of any changes that
// have been made!

// First, load private config so that we have a db connection if we need one for any initialization.

$appConfigClass = 'App\Models\PBEAppConfig';

$whitelist = [
    '127.0.0.1',
    '::1'
];

$app = new $appConfigClass(
    in_array($_SERVER['REMOTE_ADDR'], $whitelist), 
    str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__)
));

if (file_exists('config-private.php')) {
    require_once 'config-private.php';
}

// // // // // // // Session Settings // // // // // // //

// What follows is some very basic session logic that you can use to start your PHP session logic.
// It's not guaranteed to be very good, but should get you started. (Open to pull request improvements!)
$sessionTime = 3600 * 24;
ini_set('session.gc_maxlifetime', $sessionTime);
session_name('pbe');
session_start();

// https://stackoverflow.com/a/1270960/3938401
// 3600 * 24 => 24 hours
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $sessionTime)) {
    // last request was more than 24 hrs ago
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

// // // // // // // User Settings // // // // // // //

// Examples of settings or other config parameters you might want:
// $app->isAdmin = isset($_SESSION['UserType']) && $_SESSION['UserType'] === 'WebAdmin';

require_once("functions.php");

$app->loggedIn = false;
$app->isOnLoginPage = strpos($_SERVER['REQUEST_URI'], 'login.php') !== false;
$app->isOnOtherNonLoginPage = strpos($_SERVER['REQUEST_URI'], 'about.php') !== false;
$app->isUserIDSessionSet = isset($_SESSION["UserID"]);
if (!isset($_SESSION["UserID"]) && !$app->isOnLoginPage && !$app->isOnOtherNonLoginPage) {
    header("Location: login.php");
}
if ($app->isUserIDSessionSet) {
    $app->loggedIn = true;
}

//$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

$app->isGuest = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "Guest";
$app->isClubAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "ClubAdmin";
$app->isConferenceAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "ConferenceAdmin";
$app->isWebAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "WebAdmin";
$app->isAdmin = $app->isClubAdmin || $app->isWebAdmin || $app->isConferenceAdmin;
$app->isPathfinder = !($app->isAdmin);

// init settings
// TODO: refactor to an actual object!
$app->settings = get_settings($app->db);
$app->contactName = isset($app->settings['AboutContactName']) ? $app->settings['AboutContactName'] : '[name redacted]';
$app->contactEmail = isset($app->settings['AboutContactEmail']) ? $app->settings['AboutContactEmail'] : '[email redacted]';
$app->websiteName = isset($app->settings['WebsiteName']) ? $app->settings['WebsiteName'] : 'UCC Quiz Engine';
$app->websiteTabTitle = isset($app->settings['WebsiteTabTitle']) ? $app->settings['WebsiteTabTitle'] : 'UCC PBE';
$app->analyticsURL = isset($app->settings['AnalyticsURL']) ? $app->settings['AnalyticsURL'] : '';
$app->analyticsSiteID = isset($app->settings['AnalyticsSiteID']) ? $app->settings['AnalyticsSiteID'] : '1';
$app->footerText = isset($app->settings['FooterText']) ? $app->settings['FooterText'] : '';

// get active year
$app->yearData = \App\Models\Year::loadCurrentYear($app->db);
$app->activeYearID = $app->yearData->yearID;
$app->activeYearNumber = $app->yearData->year;

if (!isset($app->ENABLE_NKJV_RESTRICTIONS)) {
    $app->ENABLE_NKJV_RESTRICTIONS = true;
}

// // // // // YAMF Settings v1.0 // // // // //

/* Change isShortURLEnabled to true if you want to enable routing
    logic for shortened URLs. You'll want a table with the following
    schema available in the db with the a PDO $app->db connection:
    CREATE TABLE `ShortURLs` (
    `ShortURLID` int(11) NOT null,
    `Slug` varchar(1000) NOT null,
    `Destination` varchar(7500) NOT null,
    `DateCreated` datetime NOT null DEFAULT CURRENT_TIMESTAMP,
    `DateLastUsed` datetime DEFAULT null,
    `TimesUsed` int(11) NOT null DEFAULT '0'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8; */

$app->isShortURLEnabled = false;    

// Headers and Footers //

// If you want headers and footers to not be used at all for the following items,
//  set the value to null.

$app->defaultHeaderName = 'header'; // change this value if you want a different default header
$app->defaultFooterName = 'footer'; // change this value if you want a different default header

$app->staticPageHeaderName = 'header'; // change this value if you want a different header for static pages
$app->staticPageFooterName = 'footer'; // change this value if you want a different footer for static pages

$app->notFoundHeaderName = 'header'; // change this value if you want a different 404 header to be used by the router
$app->notFoundViewName = '404'; // change this value if you want a different 404 page to be used by the router
$app->notFoundFooterName = 'footer'; // change this value if you want a different 404 footer to be used by the router

$app->viewsFolderName = 'views/'; // this is the folder path (including trailing slash) from the root dir to the views directory
$app->staticViewsFolderName = 'views/static/'; // this is the folder path (including trailing slash) from the root dir to the static views directory

$app->viewExtension = '.php'; // change this value if you want to use a different file extension for your views
$app->staticViewExtension = '.php'; // change this value if you want to use a different file extension for your static views

$app->router = 'App\Helpers\TwigRouter';