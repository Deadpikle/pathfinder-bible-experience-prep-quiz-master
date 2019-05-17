<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $params = [
            trim($_POST["club-name"]),
            trim($_POST["club-url"])
        ];
        if ($_GET["type"] == "update") {
            $conferenceIDText = $isWebAdmin ? ', ConferenceID = ?' : '';
            $query = '
                UPDATE Clubs SET Name = ?, URL = ? ' . $conferenceIDText . ' WHERE ClubID = ?
            ';
            if ($isWebAdmin) {
                $params[] = $_POST["conference"];
            }
            $params[] = $_POST["club-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO Clubs (Name, URL, ConferenceID) VALUES (?, ?, ?)
            ';
            if ($isWebAdmin) {
                $params[] = $_POST["conference"];
            }
            else {
                $params[] = $_SESSION["ConferenceID"] != -1 ? $_SESSION["ConferenceID"] : null;
            }
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-clubs.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>