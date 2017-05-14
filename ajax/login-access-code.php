<?php
    session_start();

    require_once("../database.php");

    $query = 'SELECT UserID, FirstName, LastName, IsAdmin FROM Users WHERE EntryCode = ?';
    $stmt = $pdo->prepare($query);
    $params = [
        $_POST["access-code"]
    ];
    $stmt->execute($params);
    if ($row = $stmt->fetch()) {
        // Login success!
        $_SESSION["UserID"] = $row["UserID"];
        $_SESSION["FirstName"] = $row["FirstName"];
        $_SESSION["LastName"] = $row["LastName"];
        $_SESSION["IsAdmin"] = $row["IsAdmin"];
        header("Location: ../index.php");
    }
    else {
        header("Location: ../login.php?error=Invalid access code");
    }
?>