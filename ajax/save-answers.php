<?php
    session_start();

    require_once("../database.php");
    
    try {
        if (isset($_POST["answers"])) {
            $answers = $_POST["answers"];
            $query = "INSERT INTO UserAnswers (Answer, DateAnswered, WasCorrect, QuestionID, UserID) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            foreach ($answers as $answer) {
                $params = [
                    $answer["userAnswer"],
                    $answer["dateAnswered"],
                    $answer["correct"],
                    $answer["questionID"],
                    $answer["userID"]
                ];
                $stmt->execute($params);
            }   
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