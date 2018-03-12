<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $params = [
            trim(filter_var($_POST["name"], FILTER_SANITIZE_STRING)),
            trim(filter_var($_POST["url"], FILTER_SANITIZE_STRING)),
            trim(filter_var($_POST["contact-name"], FILTER_SANITIZE_STRING)),
            trim(filter_var($_POST["contact-email"], FILTER_SANITIZE_EMAIL))
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE Conferences SET Name = ?, URL = ?, ContactName = ?, ContactEmail = ? WHERE ConferenceID = ?
            ';
            $params[] = $_POST["conference-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO Conferences (Name, URL, ContactName, ContactEmail) VALUES (?, ?, ?, ?)
            ';
        }
        else {
            die("Invalid update type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-conferences.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>