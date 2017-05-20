<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {

        $params = [
            $_POST["first-name"],
            $_POST["last-name"],
            $_POST["entry-code"]
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE Users SET FirstName = ?, LastName = ?, UserTypeID = ?, ClubID = ? WHERE UserID = ?
            ';
            $params[] = $_POST["user-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO Users (FirstName, LastName, EntryCode, UserTypeID, ClubID) VALUES (?, ?, ?, ?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-users.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>