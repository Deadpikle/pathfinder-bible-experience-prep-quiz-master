<?php
    require_once("../config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("../database.php");

    $query = '
        SELECT UserID, Username, ut.Type AS UserType, c.ClubID AS ClubID, c.Name AS ClubName
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
        // Update the database
        $updateQuery = 'UPDATE Users SET LastLoginDate = ? WHERE UserID = ' . $row["UserID"];
        $statement = $pdo->prepare($updateQuery);
        $params = [
            date("Y-m-d H:i:s")
        ];
        $statement->execute($params);
        // Update the session
        $_SESSION["UserID"] = $row["UserID"];
        $_SESSION["Username"] = $row["Username"];
        $_SESSION["UserType"] = $row["UserType"];
        if ($row["ClubID"] != NULL) {
            $_SESSION["ClubID"] = $row["ClubID"];
        }
        else {
            $_SESSION["ClubID"] = -1;
        }
        $_SESSION["ClubName"] = $row["ClubName"];
        header("Location: ../index.php");
    }
    else {
        header("Location: ../login.php?error=Invalid access code");
    }
?>