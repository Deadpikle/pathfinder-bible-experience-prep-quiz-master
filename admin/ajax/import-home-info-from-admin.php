<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    copy_home_sections($pdo, get_web_admin_conference_id($pdo), $_SESSION["ConferenceID"], $_POST["year"]);
    header("Location: $basePath/admin/view-home-sections.php");
?>