<?php
    $canViewAdminPanel = isset($_SESSION["UserType"]) && $_SESSION["UserType"] !== "Pathfinder";
    $isLoggedIn = $loggedIn;

    $localHostList = array(
        '127.0.0.1',
        '::1'
    );
    $isLocalHost = TRUE;
    if(!in_array($_SERVER['REMOTE_ADDR'], $localHostList)){
        // not valid
        $isLocalHost = FALSE;
    }
?>

<html>
    <head>
        <title>UCC PBE</title>
        <link rel="stylesheet" href="<?=$basePath?>/css/normalize.css" />

        <!--Import Google Icon Font-->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!--Import materialize.css-->
        <link type="text/css" rel="stylesheet" href="<?=$basePath?>/lib/materialize/css/materialize.min.css?v=20170813b" media="screen,projection"/>
        <!--Let browser know website is optimized for mobile-->
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

        <link rel="stylesheet" href="<?=$basePath?>/css/common.css?v=20170927" />
        <script src="<?=$basePath?>/lib/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="<?=$basePath?>/lib/materialize/js/materialize.min.js"></script>
        <script type="text/javascript" src="<?=$basePath?>/lib/html.sortable.min.js"></script> <!-- https://github.com/lukasoppermann/html5sortable -->
        <script type="text/javascript" src="<?=$basePath?>/lib/autosize.min.js"></script>
        <script src="<?=$basePath?>/js/common.js?v=20170927"></script>
        <?php if (!$isLocalHost) { ?>
            <!-- Piwik -->
            <script type="text/javascript">
                var _paq = _paq || [];
                /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
                _paq.push(['trackPageView']);
                _paq.push(['enableLinkTracking']);
                (function() {
                    var u="//babien.co/analytics/";
                    _paq.push(['setTrackerUrl', u+'piwik.php']);
                    _paq.push(['setSiteId', '2']);
                    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
                    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
                })();
            </script>
            <!-- End Piwik Code -->
        <?php } ?>
    </head>
    <body>
        <header>
            <nav>
                <div class="nav-wrapper teal lighten-2">
                    <div class="container">
                        <a href="<?=$basePath?>" class="brand-logo">UCC Quiz Engine</a>
                        <a href="#" data-activates="mobile-demo" class="button-collapse"><i class="material-icons">menu</i></a>
                        <ul class="right hide-on-med-and-down">
                            <?php if ($isLoggedIn) { ?>
                                <li><a href="<?=$basePath?>">Home</a></li>
                                <!--li><a href="<?=$basePath?>/view-questions.php">View Questions</a></li-->
                            <?php } ?>
                            <li><a href="<?=$basePath?>/about.php">About</a></li>
                            <?php if ($isLoggedIn) { ?>
                                <?php if ($canViewAdminPanel) { ?>
                                    <li><a href="<?=$basePath?>/admin">Admin Panel</a></li>
                                <?php } ?>
                                <li><a href="<?=$basePath?>/logout.php">Logout</a></li>
                            <?php } ?>
                        </ul>
                        <ul class="side-nav" id="mobile-demo">
                            <?php if ($isLoggedIn) { ?>
                                <li><a href="<?=$basePath?>">Home</a></li>
                                <!--li><a href="<?=$basePath?>/view-questions.php">View Questions</a></li-->
                            <?php } ?>
                            <li><a href="<?=$basePath?>/about.php">About</a></li>
                            <?php if ($isLoggedIn) { ?>
                                <?php if ($canViewAdminPanel) { ?>
                                    <li><a href="<?=$basePath?>/admin">Admin Panel</a></li>
                                <?php } ?>
                                <li><a href="<?=$basePath?>/logout.php">Logout</a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
            <main>
                <div id="main" class="container">
                    