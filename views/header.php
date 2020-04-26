<?php

use Yamf\Util;

$homePath = $app->basePath == "" ? "/" : $app->basePath;

    $htmlTitle = isset($title) ? $title . ' - ' . $app->websiteTabTitle : $app->websiteTabTitle;

    $currentRequest = str_replace($app->basePath, '', $_SERVER['REQUEST_URI']);
    if (Util::strEndsWith($currentRequest, '/')) {
        $currentRequest = substr($currentRequest, 0, -1);
    }
    $currentRequest = str_replace("/", "", $currentRequest);
    
    $homeHeaderActiveStatus = str_contains("home", $currentRequest) || $currentRequest == "" ? "active" : "";
    $aboutHeaderActiveStatus = str_contains("about", $currentRequest) ? "active" : "";
    $adminHeaderActiveStatus = str_contains("admin", $currentRequest) ? "active" : "";
    if ($aboutHeaderActiveStatus === "" && $adminHeaderActiveStatus === "") {
        $homeHeaderActiveStatus = "active";
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title><?= $htmlTitle ?></title>
        <link rel="stylesheet" href="<?= $app->yurl('/css/normalize.css') ?>" />

        <!-- Favicon Items -->
        <link rel="apple-touch-icon" sizes="180x180" href="<?= $app->yurl('/files/apple-touch-icon.png') ?>">
        <link rel="icon" type="image/png" sizes="32x32" href="<?= $app->yurl('/files/favicon-32x32.png') ?>">
        <link rel="icon" type="image/png" sizes="16x16" href="<?= $app->yurl('/files/favicon-16x16.png') ?>">
        <link rel="manifest" href="<?= $app->yurl('/files/site.webmanifest') ?>">
        <link rel="mask-icon" href="<?= $app->yurl('/files/safari-pinned-tab.svg') ?>" color="#5bbad5">
        <meta name="msapplication-TileColor" content="#00aba9">
        <meta name="theme-color" content="#ffffff">

        <!--Import Google Icon Font-->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!--Import materialize.css-->
        <link type="text/css" rel="stylesheet" href="<?= $app->yurl('/lib/materialize/css/materialize.min.css?v=20171204a') ?>" media="screen,projection"/>
        <!--Let browser know website is optimized for mobile-->
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

        <link rel="stylesheet" href="<?= $app->yurl('/css/common.css') ?>?<?= filemtime("css/common.css") ?>" />
        <script src="<?= $app->yurl('/lib/jquery-3.2.1.min.js') ?>"></script>
        <script type="text/javascript" src="<?= $app->yurl('/lib/materialize/js/materialize.min.js') ?>"></script>
        <script type="text/javascript" src="<?= $app->yurl('/lib/autosize.min.js') ?>"></script>

        <!-- For admin pages -->
        <script type="text/javascript" src="<?= $app->yurl('/lib/html.sortable.min.js') ?>"></script> <!-- https://github.com/lukasoppermann/html5sortable -->
        <!-- tablesorter.js -->
        <?php if ($adminHeaderActiveStatus === "active") { ?>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.29.5/css/theme.materialize.min.css" integrity="sha256-jUiCvjE6E8l+KScSvjq5Sq28mU+/yFJNhxqcFPyvKJc=" crossorigin="anonymous" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.29.5/css/filter.formatter.min.css" integrity="sha256-mMTF3msZrX36jof9tDumliTFETqw3pw6Cygt+ZiLN1o=" crossorigin="anonymous" />
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.29.5/js/jquery.tablesorter.min.js" integrity="sha256-5nivqdfmHxGs901RSMulMFGroDjG/qvWK5n8x+S/Wr4=" crossorigin="anonymous"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.29.5/js/jquery.tablesorter.widgets.js" integrity="sha256-ntYf/f8FwONqwWYyCSyuPnkKBvh58KcFZkem8x15dOI=" crossorigin="anonymous"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.29.5/js/widgets/widget-filter-formatter-html5.min.js" integrity="sha256-tP9/Kjrq6K2IGcLqni6LTTPGTthgtxb7omdC3RNyQW8=" crossorigin="anonymous"></script>
        <?php } ?>

        <script src="<?= $app->yurl('/js/common.js') ?>?<?= filemtime("js/common.js") ?>"></script>
        <?php if (!$app->isLocalHost && !$app->isGuest && $analyticsURL !== '') { ?>
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
                        <a href="<?=$homePath?>" class="brand-logo"><?= $app->websiteName ?></a>
                        <a href="#" data-activates="mobile-demo" class="button-collapse"><i class="material-icons">menu</i></a>
                        <ul class="right hide-on-med-and-down">
                            <?php if ($app->loggedIn) { ?>
                                <li class="<?= $homeHeaderActiveStatus ?>"><a href="<?=$homePath?>">Home</a></li>
                            <?php } ?>
                            <li class="<?= $aboutHeaderActiveStatus ?>"><a href="<?= $app->yurl('/about') ?>">About</a></li>
                            <?php if ($app->loggedIn) { ?>
                                <?php if ($app->isAdmin) { ?>
                                    <li class="<?= $adminHeaderActiveStatus ?>"><a href="<?= $app->yurl('/admin') ?>">Admin Panel</a></li>
                                <?php } ?>
                                <li><a href="<?= $app->yurl('/logout') ?>">Logout</a></li>
                            <?php } ?>
                        </ul>
                        <ul class="side-nav teal darken-1" id="mobile-demo">
                            <li><a class="center-align white-text" id="side-nav-title" href="<?=$homePath?>"><?= $app->websiteName ?></a></li>
                            <?php if ($app->loggedIn) { ?>
                                <li class="<?= $homeHeaderActiveStatus ?>"><a class="white-text" href="<?=$homePath?>">Home</a></li>
                            <?php } ?>
                            <li class="<?= $aboutHeaderActiveStatus ?>"><a class="white-text" href="<?= $app->yurl('/about') ?>">About</a></li>
                            <?php if ($app->loggedIn) { ?>
                                <?php if ($app->isAdmin) { ?>
                                    <li class="<?= $adminHeaderActiveStatus ?>"><a class="white-text" href="<?= $app->yurl('/admin') ?>">Admin Panel</a></li>
                                <?php } ?>
                                <li><a class="white-text" href="<?= $app->yurl('/logout') ?>">Logout</a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
            <main>
                <div id="main" class="container">
                    