<?php

    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__));

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
    $isWebAdmin = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "WebAdmin";
    $isAdmin = $isClubAdmin || $isWebAdmin;
    $isPathfinder = !($isAdmin);

?>