<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    $toConferenceID = $_POST["to-conference-id"];
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
            if ($row != NULL) {
                $sortOrder = intval($row["MaxSort"]) + 1;
            }
            $params[] = $sortOrder;
            $params[] = get_active_year($pdo)["YearID"];
            $params[] = $_POST["to-conference-id"];
            $query = '
                INSERT INTO HomeInfoSections (Name, SortOrder, YearID, ConferenceID) VALUES (?, ?, ?, ?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-home-sections.php?conferenceID=" . $toConferenceID);
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>