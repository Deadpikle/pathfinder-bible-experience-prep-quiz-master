<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $sectionID = $_POST["section-id"];
        $lineID = $_POST["line-id"];
        if (!isset($_POST["line-is-link"]) || $_POST["line-is-link"] == NULL) {
            $_POST["line-is-link"] = FALSE;
        }
        else {
            $_POST["line-is-link"] = TRUE;
        }
        $params = [
            $_POST["line-text"],
            $_POST["line-is-link"],
            $_POST["line-url"]
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE HomeInfoItems SET Text = ?, IsLink = ?, URL = ? WHERE HomeInfoItemID = ?
            ';
            $params[] = $_POST["item-id"];
        }
        else if ($_GET["type"] == "create") {
            $params[] = $lineID;
            // find max sort order 
            $stmt = $pdo->prepare("SELECT MAX(SortOrder) AS MaxSort FROM HomeInfoItems WHERE HomeInfoLineID = ?");
            $stmt->execute([$lineID]);
            $row = $stmt->fetch();
            $sortOrder = 1;
            if ($row != NULL) {
                $sortOrder = intval($row["MaxSort"]) + 1;
            }
            $params[] = $sortOrder;
            $query = '
                INSERT INTO HomeInfoItems (Text, IsLink, URL, HomeInfoLineID, SortOrder) VALUES (?, ?, ?, ?, ?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-home-section-items.php?sectionID=$sectionID");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>