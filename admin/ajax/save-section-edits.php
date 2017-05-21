<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $params = [
            $_POST["section-name"]
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE HomeInfoSections SET Name = ? WHERE HomeInfoSectionID = ?
            ';
            $params[] = $_POST["section-id"];
        }
        else if ($_GET["type"] == "create") {
            // find max sort order 
            $stmt = $pdo->query("SELECT MAX(SortOrder) AS MaxSort FROM HomeInfoSections");
            $row = $stmt->fetch();
            $sortOrder = 1;
            print_r($row);
            if ($row != NULL) {
                $sortOrder = intval($row["MaxSort"]) + 1;
            }
            $params[] = $sortOrder;
            $query = '
                INSERT INTO HomeInfoSections (Name, SortOrder) VALUES (?, ?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-home-sections.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>