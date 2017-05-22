<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $params = [
            $_POST["club-name"]
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE Clubs SET Name = ? WHERE ClubID = ?
            ';
            $params[] = $_POST["club-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO Clubs (Name) VALUES (?)
            ';
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