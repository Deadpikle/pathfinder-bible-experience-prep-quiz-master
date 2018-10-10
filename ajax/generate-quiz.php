<?php
    require_once("../config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("../database.php");
    require_once("../functions.php");

    if (!isset($_SESSION["UserID"])) {
        die();
    }

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

    if (isset($_POST["enableQuestionDistribution"]) && 
        filter_var($_POST["enableQuestionDistribution"], FILTER_VALIDATE_BOOLEAN) &&
        isset($_POST["quizItems"]) && count($_POST["quizItems"]) > 0) {
        $generated = generate_weighted_quiz_questions($pdo, $_POST);
    }
    else {
        $generated = generate_quiz_questions($pdo, $_POST);
    }
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($generated);
?>