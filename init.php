<?php

    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__));

    session_start();

    require_once("database.php");
    require_once("functions.php");

    $loggedIn = FALSE;
    $isOnLoginPage = strpos($_SERVER['REQUEST_URI'], 'login.php') !== false;
    if (!isset($_SESSION["UserID"]) && !$isOnLoginPage) {
        header("Location: login.php");
    }
    if (!$isOnLoginPage) {
        $loggedIn = TRUE;
    }

?>