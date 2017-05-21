<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $sectionID = $_POST["section-id"];
        // get max line number for sorting so we can go one above that
        $stmt = $pdo->query("SELECT MAX(SortOrder) AS MaxSort FROM HomeInfoLines");
        $row = $stmt->fetch();
        $sortOrder = 1;
        if ($row != NULL) {
            $sortOrder = intval($row["MaxSort"]) + 1;
        }
        $params = [
            '',
            $sortOrder,
            $sectionID
        ];
        $query = '
            INSERT INTO HomeInfoLines (Name, SortOrder, HomeInfoSectionID) VALUES (?, ?, ?)
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-home-section-items.php?sectionID=$sectionID");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>