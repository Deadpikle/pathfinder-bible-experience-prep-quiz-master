<?php
    require_once("../config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("../database.php");

    if (!isset($_SESSION["UserID"])) {
        die();
    }
    
        // prepare everything
        // we don't want to add duplicate rows for a question
        // so that generate-quiz has a bit easier of a time.
        // I couldn't figure out an easy/quick way to have multiple rows for an answered question 
        // where some of them had correct = false and one had correct = true. Probably need a GROUP BY and HAVING. 
        // Keeping only one row in the db for this info makes it easier to generate a quiz. :effort:
        // I'm sure there's a better way.
        $query = 'SELECT 1 FROM UserAnswers WHERE QuestionID = ? AND UserID = ?';
        $searchStmt = $pdo->prepare($query);
        $insertQuery = "INSERT INTO UserAnswers (Answer, DateAnswered, WasCorrect, QuestionID, UserID) VALUES (?, ?, ?, ?, ?)";
        $insertStmnt = $pdo->prepare($insertQuery);
        $updateQuery = ' UPDATE UserAnswers SET Answer = ?, DateAnswered = ?, WasCorrect = ? WHERE QuestionID = ? AND UserID = ?';
        $updateStmnt = $pdo->prepare($updateQuery);
    try {
        if (isset($_POST["answers"])) {
            $answers = $_POST["answers"];
            $stmt = $pdo->prepare($query);
            foreach ($answers as $answer) {
                $searchParams = [
                    $answer["questionID"],
                    $answer["userID"]
                ];
                $searchStmt->execute($searchParams);
                $didFind = count($searchStmt->fetchAll()) >= 1 ? true : false;
                $insertUpdateParams = [
                    $answer["userAnswer"],
                    $answer["dateAnswered"],
                    $answer["correct"],
                    $answer["questionID"],
                    $answer["userID"]
                ];
                if (!$didFind) {
                    $insertStmnt->execute($insertUpdateParams);
                }
                else {
                    $updateStmnt->execute($insertUpdateParams);
                }
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