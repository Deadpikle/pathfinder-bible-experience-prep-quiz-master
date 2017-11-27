<?php
    require_once(dirname(__FILE__)."/../init.php");

    $canViewAdminPanel = isset($_SESSION["UserType"]) && $_SESSION["UserType"] !== "Pathfinder" && $_SESSION["UserType"] !== "Guest";
    if (!$canViewAdminPanel) {
        header("Location: $basePath/index.php");
    }
?>