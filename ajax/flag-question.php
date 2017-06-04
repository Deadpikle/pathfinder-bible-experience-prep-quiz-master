<?php
    require_once("../database.php");
    
    try {
        $params = [
            $_POST["questionID"]
        ];
        $query = ' UPDATE Questions SET IsFlagged = 1 WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        header('Content-Type: application/json; charset=utf-8');
        $output = array ( "status" => 200 );
        echo json_encode($output);
    }
    catch (PDOException $e) {
        $output = array ( "status" => 400 );
        echo json_encode($output);
    }
?>