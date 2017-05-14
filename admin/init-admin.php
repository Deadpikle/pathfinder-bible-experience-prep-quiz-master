<?php
    require_once(dirname(__FILE__)."/../init.php");

    if (!$_SESSION["IsAdmin"]) {
        header("Location: $basePath/index.php");
    }
?>