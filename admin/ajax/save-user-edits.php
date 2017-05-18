<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        if (!isset($_POST["is-admin"]) || $_POST["is-admin"] == NULL) {
            $_POST["is-admin"] = FALSE;
        }
        else {
            $_POST["is-admin"] = TRUE;
        }
        $params = [
            $_POST["first-name"],
            $_POST["last-name"],
            $_POST["entry-code"],
            $_POST["is-admin"]
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE Users SET FirstName = ?, LastName = ?, EntryCode = ?, IsAdmin = ? WHERE UserID = ?
            ';
            $params[] = $_POST["user-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO Users (FirstName, LastName, EntryCode, IsAdmin) VALUES (?, ?, ?, ?)
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