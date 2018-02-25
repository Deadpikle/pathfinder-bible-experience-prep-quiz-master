<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    $fromConferenceID = $_POST["from-conference-id"];
    $toConferenceID = $_POST["to-conference-id"];
    copy_home_sections($pdo, $fromConferenceID, $toConferenceID, $_POST["year"]);
    header("Location: $basePath/admin/view-home-sections.php?conferenceID=" . $toConferenceID);
?>