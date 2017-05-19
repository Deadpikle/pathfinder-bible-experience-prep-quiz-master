<?php
?>

<html>
    <head>
        <link rel="stylesheet" href="<?=$basePath?>/css/normalize.css" />

        <!--Import Google Icon Font-->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!--Import materialize.css-->
        <link type="text/css" rel="stylesheet" href="<?=$basePath?>/lib/materialize/css/materialize.min.css"  media="screen,projection"/>
        <!--Let browser know website is optimized for mobile-->
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

        <link rel="stylesheet" href="<?=$basePath?>/css/common.css" />
        <script src="<?=$basePath?>/lib/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="<?=$basePath?>/lib/materialize/js/materialize.min.js"></script>
    </head>
    <body>
        <header class="">
            <nav>
                <div class="nav-wrapper blue lighten-2">
                    <a href="<?=$basePath?>" class="brand-logo" style="margin-left:2em">Pathfinder Quiz Engine 2.0</a>
                    <a href="#" data-activates="mobile-demo" class="button-collapse"><i class="material-icons">menu</i></a>
                    <ul class="right hide-on-med-and-down">
                        <li><a href="<?=$basePath?>/admin">Admin Panel</a></li>
                        <li><a href="<?=$basePath?>/logout.php">Logout</a></li>
                    </ul>
                    <ul class="side-nav" id="mobile-demo">
                        <li><a href="<?=$basePath?>/admin">Admin Panel</a></li>
                        <li><a href="<?=$basePath?>/logout.php">Logout</a></li>
                    </ul>
                </div>
            </nav>
        </header>
            <main>
                <div id="main" class="container">
                    