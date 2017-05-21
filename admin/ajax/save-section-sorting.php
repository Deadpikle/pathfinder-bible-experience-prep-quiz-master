<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    $data = json_decode($_POST["json"], true);
    $sqlStatements = "";
    foreach ($data as $section) {
        $sqlStatements .= " UPDATE HomeInfoSections SET SortOrder = " . $section["index"] . " WHERE HomeInfoSectionID = " . $section["id"] . "; ";
    }
    try {
        $pdo->exec($sqlStatements);
    }
    catch (PDOException $e) {
        echo $e->getMessage();
        die();
    }
    echo 'success';
?>