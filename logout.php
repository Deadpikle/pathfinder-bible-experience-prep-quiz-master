<?php
    require_once("config.php");
    session_name($SESSION_NAME);
    session_start();
    session_destroy();

    header("Location: index.php");
?>