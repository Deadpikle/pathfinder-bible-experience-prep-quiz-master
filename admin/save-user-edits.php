<?php
    require_once("init-admin.php");

    if ($_GET["type"] == "update") {
        $query = '
            UPDATE Users SET FirstName = ?, LastName = ?, EntryCode = ?, IsAdmin = ? WHERE UserID = ?
        ';
        $stmt = $pdo->prepare($query);
        $params = [
            $_POST["first-name"],
            $_POST["last-name"],
            $_POST["entry-code"],
            $_POST["is-admin"],
            $_POST["user-id"]
        ];
        $stmt->execute($params);
        header("Location: view-users.php");
    }
    else if ($_GET["type"] == "create") {

    }
?>