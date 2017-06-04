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

    $stmt = $pdo->query('
        SELECT QuestionID, Question, Answer, NumberPoints, IsFlagged, DateCreated,
            bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
            bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse
        FROM Questions q 
            JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
            JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
            JOIN Books bStart ON bStart.BookID = cStart.BookID

            LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
            LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            ' . $whereClause . '
        ORDER BY Question, Answer, NumberPoints
    ');
    $questions = $stmt->fetchAll();
    $output = json_encode($questions);
    echo $output;

?>