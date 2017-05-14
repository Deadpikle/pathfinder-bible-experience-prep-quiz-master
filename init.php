<?php

    $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__));

    session_start();

    require_once("database.php");

    $logged_in = FALSE;
    if (!isset($_SESSION["UserID"])) {
        header("Location: login.php");
    }
    $logged_in = TRUE;

?>