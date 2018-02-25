<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    $toConferenceID = $_POST["to-conference-id"];
    copy_home_sections($pdo, get_web_admin_conference_id($pdo), $toConferenceID, $_POST["year"]);
    header("Location: $basePath/admin/view-home-sections.php?conferenceID=" . $toConferenceID);
?>