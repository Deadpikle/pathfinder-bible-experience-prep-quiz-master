<?php
    $canViewAdminPanel = isset($_SESSION["UserType"]) && $_SESSION["UserType"] !== "Pathfinder" 
        && $_SESSION["UserType"] !== "Guest";
    $headerIsGuest = isset($_SESSION["UserType"]) && $_SESSION["UserType"] === "Guest";
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

    $homePath = $basePath == "" ? "/" : $basePath;

    $htmlTitle = isset($title) ? $title . ' - ' . $websiteTabTitle : $websiteTabTitle;
?>

<!DOCTYPE html>
<html>
    <head>
        <title><?= $htmlTitle ?></title>
        <link rel="stylesheet" href="<?=$basePath?>/css/normalize.css" />

        <!-- Favicon Items -->
        <link rel="apple-touch-icon" sizes="180x180" href="<?=$basePath?>/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="<?=$basePath?>/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="<?=$basePath?>/favicon-16x16.png">
        <link rel="manifest" href="<?=$basePath?>/site.webmanifest">
        <link rel="mask-icon" href="<?=$basePath?>/safari-pinned-tab.svg" color="#5bbad5">
        <meta name="msapplication-TileColor" content="#00aba9">
        <meta name="theme-color" content="#ffffff">

        <!--Import Google Icon Font-->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!--Import materialize.css-->
        <link type="text/css" rel="stylesheet" href="<?=$basePath?>/lib/materialize/css/materialize.min.css?v=20171204a" media="screen,projection"/>
        <!--Let browser know website is optimized for mobile-->
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

        <link rel="stylesheet" href="<?=$basePath?>/css/common.css?<?= filemtime(dirname(__FILE__) . "/css/common.css") ?>" />
        <script src="<?=$basePath?>/lib/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="<?=$basePath?>/lib/materialize/js/materialize.min.js"></script>
        <script type="text/javascript" src="<?=$basePath?>/lib/html.sortable.min.js"></script> <!-- https://github.com/lukasoppermann/html5sortable -->
        <script type="text/javascript" src="<?=$basePath?>/lib/autosize.min.js"></script>
        <script src="<?=$basePath?>/js/common.js?<?= filemtime(dirname(__FILE__) . "/js/common.js") ?>"></script>
        <?php if (!$isLocalHost && !$headerIsGuest && $analyticsURL !== '') { ?>
            <!-- Piwik -->
            <script type="text/javascript">
                var _paq = _paq || [];
                /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
                _paq.push(['trackPageView']);
                _paq.push(['enableLinkTracking']);
                (function() {
                    var u = "<?= $analyticsURL ?>";
                    _paq.push(['setTrackerUrl', u+'piwik.php']);
                    _paq.push(['setSiteId', "<?= $analyticsSiteID ?>"]);
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
                        <a href="<?=$homePath?>" class="brand-logo"><?= $websiteName ?></a>
                        <a href="#" data-activates="mobile-demo" class="button-collapse"><i class="material-icons">menu</i></a>
                        <ul class="right hide-on-med-and-down">
                            <?php if ($isLoggedIn) { ?>
                                <li><a href="<?=$homePath?>">Home</a></li>
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
                        <ul class="side-nav teal darken-1" id="mobile-demo">
                            <li><a class="center-align white-text" id="side-nav-title" href="<?=$homePath?>"><?= $websiteName ?></a></li>
                            <?php if ($isLoggedIn) { ?>
                                <li><a class="white-text" href="<?=$homePath?>">Home</a></li>
                                <!--li><a href="<?=$basePath?>/view-questions.php">View Questions</a></li-->
                            <?php } ?>
                            <li><a class="white-text" href="<?=$basePath?>/about.php">About</a></li>
                            <?php if ($isLoggedIn) { ?>
                                <?php if ($canViewAdminPanel) { ?>
                                    <li><a class="white-text" href="<?=$basePath?>/admin">Admin Panel</a></li>
                                <?php } ?>
                                <li><a class="white-text" href="<?=$basePath?>/logout.php">Logout</a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
            <main>
                <div id="main" class="container">
                    