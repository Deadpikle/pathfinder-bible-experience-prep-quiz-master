<?php
?>

<html>
    <head>
        <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
        <link rel="stylesheet" href="<?=$basePath?>/lib/mdl/material.indigo-blue.min.css" />
        <link rel="stylesheet" href="<?=$basePath?>/css/common.css" />
        <script defer src="<?=$basePath?>/lib/mdl/material.min.js"></script>
        <!--getmdl-select https://github.com/CreativeIT/getmdl-select--> 
        <link rel="stylesheet" href="<?=$basePath?>/lib/mdl-select/getmdl-select.min.css">
        <script defer src="<?=$basePath?>/lib/mdl-select/getmdl-select.min.js"></script>
    </head>
    <body>
        <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
        <header class="mdl-layout__header">
            <div class="mdl-layout__header-row">
                <!-- Title -->
                <span class="mdl-layout-title">Pathfinder Quiz Engine</span>
                <!-- Add spacer, to align navigation to the right -->
                <div class="mdl-layout-spacer"></div>
                <!-- Navigation. We hide it in small screens. -->
                <nav class="mdl-navigation mdl-layout--large-screen-only">
                    <a class="mdl-navigation__link" href="<?= $basePath.'/index.php'?>">Home</a>
                    <?php if ($_SESSION["IsAdmin"]) { ?>
                        <a class="mdl-navigation__link" href="admin/">Admin Panel</a>
                    <?php } ?>
                    <a class="mdl-navigation__link" href="<?= $basePath.'/logout.php'?>">Logout</a>
                </nav>
            </div>
        </header>
            <main class="mdl-layout__content">
                <div class="page-content"><!-- Your content goes here --></div>