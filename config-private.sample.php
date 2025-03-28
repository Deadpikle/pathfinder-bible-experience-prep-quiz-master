<?php

// In this file, save any app settings that you do NOT want to be saved
// to your repository. These are settings that you don't want shared
// with others, such as public/private keys, database connection details, etc.

// Make sure to update the sample file for a template of keys to fill out
// on your own website!! That way, when you set up a new instance of your
// site, you know which keys need to be filled out. :)

// Some examples:
// $app->recaptchaPrivateKey = "[key here]";
// $app->recaptchaPublicKey = "[key here]";
// $app->mapsAPIKey = "[key here]";

if (!isset($app)) {
    $app = new stdClass;
}

/* 
// Uncomment these lines if you want there to be a database connection available through $app->db
try {
    $host = '127.0.0.1';
    $db   = 'db-name';
    $user = 'username';
    $pass = 'password';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $app->db = new PDO($dsn, $user, $pass, $opt);
}
catch (Exception $e) {
    echo "DB connection details are invalid: " . $e->getMessage();
}*/

// shouldShowErrorOnExceptionThrown: whether or not to show the error page on an exception 
// being thrown from a controller. You probably want this set to true while developing,
// and you probably want this set to false on a production server.

use App\Models\PBEAppConfig;

/** @var PBEAppConfig $app */
$app->shouldShowErrorOnExceptionThrown = false;
$app->ENABLE_NKJV_RESTRICTIONS = true; // DO NOT DISABLE THIS UNLESS YOU HAVE THE LEGAL RIGHT TO DO SO. YOU HAVE BEEN WARNED.
$app->sessionName = 'pbe';
$app->showCookieConsent = false;
$app->cookieDomain = 'example.com';
$app->headerForAnalytics = ''; // e.g. <script data-goatcounter="" async src="//mysite.com/count.js"></script>

$app->contactFromEmail = '';
$app->contactToEmail = '';
$app->contactSubjectPrefix = '';

$app->recaptchaType = 'google'; // 'google' or 'cloudflare' (no option for 'none')
$app->recaptchaExpectedDomain = '';
$app->recaptchaPrivateKey = '';
$app->recaptchaPublicKey = '';