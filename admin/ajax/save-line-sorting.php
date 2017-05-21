<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    $data = json_decode($_POST["json"], true);
    //print_r($data);
    $sqlStatements = "";
    foreach ($data as $line) {
        $sqlStatements .= " UPDATE HomeInfoLines SET SortOrder = " . $line["index"] . " WHERE HomeInfoLineID = " . $line["id"] . "; ";
        foreach ($line["items"] as $item) {
            $sqlStatements .= " UPDATE HomeInfoItems SET SortOrder = " . $item["index"] . " WHERE HomeInfoItemID = " . $item["id"] . "; ";
        }
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