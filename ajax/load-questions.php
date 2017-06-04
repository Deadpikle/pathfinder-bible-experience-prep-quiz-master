<?php
    session_start();

    require_once("../database.php");

    $whereClause = "";
    if (isset($_POST["loadType"])) {
        $loadType = $_POST["loadType"];
        if ($loadType == "recent") {
            $eightDaysAgo = date('Y-m-d 00:00:00', strtotime('-8 days'));
            $whereClause = " WHERE DateCreated >= '" . $eightDaysAgo . "' ";
        }
        else if ($loadType == "flagged") {
            $whereClause = " WHERE IsFlagged = 1";
        }
    }

    $pageSize = 10;
    if (isset($_POST["pageSize"])) {
        $pageSize = $_POST["pageSize"];
    }

    $pageOffset = 0;
    if (isset($_POST["pageOffset"])) {
        $pageOffset = $_POST["pageOffset"];
    }
    $selectPortion = '
        SELECT QuestionID, Question, Answer, NumberPoints, IsFlagged, DateCreated,
            bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
            bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse ';
    $fromPortion = '
        FROM Questions q 
            JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
            JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
            JOIN Books bStart ON bStart.BookID = cStart.BookID

            LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
            LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            ' . $whereClause . '
        ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number';
    $limitClause = '
        LIMIT ' . $pageOffset . ',' . $pageSize;  
    $stmt = $pdo->query($selectPortion . $fromPortion . $limitClause);
    $questions = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT COUNT(*) AS QuestionCount " . $fromPortion);
    $row = $stmt->fetch(); 
    $totalQuestions = $row["QuestionCount"];

    $output = json_encode(array(
        "questions" => $questions,
        "totalQuestions" => $totalQuestions
    ));
    header('Content-Type: application/json; charset=utf-8');
    echo $output;

?>