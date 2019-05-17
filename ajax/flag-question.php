<?php
    require_once("../config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("../database.php");

    if (!isset($_SESSION["UserID"])) {
        die();
    }
    
    try {
        // Make sure it's not in the table already!
        $params = [
            $_POST["questionID"],
            $_SESSION["UserID"]
        ];

        $query = 'SELECT 1 FROM UserFlagged WHERE QuestionID = ? AND UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $didFind = count($stmt->fetchAll()) >= 1 ? true : false;
        if (!$didFind) {
            $query = ' INSERT INTO UserFlagged (QuestionID, UserID) VALUES (?, ?) ';
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }

        header('Content-Type: application/json; charset=utf-8');
        $output = array ( "status" => 200 );
        echo json_encode($output);
    }
    catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        $output = array ( "status" => 400 );
        echo json_encode($output);
    }
?>