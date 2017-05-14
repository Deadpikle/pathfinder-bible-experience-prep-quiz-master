<?php

    session_start();

    require_once("database.php");

    $logged_in = FALSE;
    if (!isset($_SESSION["UserID"])) {
        header("Location: login.php");
    }
    $logged_in = TRUE;

?>