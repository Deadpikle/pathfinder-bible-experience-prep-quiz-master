<?php
    session_start();

    require_once("../database.php");

    $query = '
        SELECT UserID, FirstName, LastName, ut.Type AS UserType, c.Name AS ClubName
        FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
            LEFT JOIN Clubs c ON u.ClubID = c.ClubID
        WHERE EntryCode = ?';
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
        $_SESSION["UserType"] = $row["UserType"];
        $_SESSION["ClubName"] = $row["ClubName"];
        header("Location: ../index.php");
    }
    else {
        header("Location: ../login.php?error=Invalid access code");
    }
?>