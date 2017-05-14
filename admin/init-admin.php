<?php
    require_once("../init.php");

    if (!$_SESSION["IsAdmin"]) {
        header("Location: ../index.php");
    }
?>