<?php
    session_start();

    require_once("../database.php");
    require_once("../functions.php");

    if (!isset($_POST["maxQuestions"])) {
        die("maxQuestions is required");
    }
    if (!isset($_POST["maxPoints"])) {
        die("maxPoints is required");
    }
    if (!isset($_POST["questionTypes"])) {
        die("questionTypes is required");
    }
    if (!isset($_POST["questionOrder"])) {
        die("questionOrder is required");
    }
    if (!isset($_POST["shouldAvoidPastCorrect"])) {
        die("shouldAvoidPastCorrect is required");
    }
    if (!isset($_POST["userID"])) {
        die("userID is required");
    }

    $generated = generate_quiz_questions($pdo, $_POST);
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($generated);
?>