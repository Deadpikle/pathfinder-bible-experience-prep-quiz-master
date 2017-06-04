<?php
    session_start();

    require_once("../database.php");

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
    
    $maxQuestions = $_POST["maxQuestions"];
    if ($maxQuestions > 500) {
        $maxQuestions = 500;
    }
    else if ($maxQuestions <= 0) {
        $maxQuestions = 10;
    }
    $maxPoints = $_POST["maxPoints"];
    if ($maxPoints > 500) {
        $maxPoints = 500;
    }
    else if ($maxPoints <= 0) {
        $maxPoints = 1;
    }
    // question type values:
    // both
    // qa-only
    // fill-in-only
    $questionTypes = $_POST["questionTypes"]; // TODO: fill in the blank
    $questionOrder = $_POST["questionOrder"];
    $isRandomOrder = $questionOrder == "random-sequential" || $questionOrder == "random-random";
    $mustSortAfterQuery = $questionOrder == "random-sequential";
    $shouldAvoidPastCorrect = $_POST["shouldAvoidPastCorrect"]; // TODO: extra JOIN and WHERE 

    // Setup query
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
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID';
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints;
    $orderByPortion = '';
    if ($isRandomOrder) {
        $orderByPortion = ' ORDER BY RAND() ';
    }
    else {
        // sequential-sequential
        $orderByPortion = '
            ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number';
    }

    $limitPortion = ' LIMIT ' . $maxQuestions;
    $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
    $questions = $stmt->fetchAll();
    if ($mustSortAfterQuery) {
        // https://stackoverflow.com/a/3233009/3938401
        array_multisort(
            array_column($questions, 'StartBook'), SORT_ASC, 
            array_column($questions, 'StartChapter'), SORT_ASC, 
            array_column($questions, 'StartVerse'), SORT_ASC,
            array_column($questions, 'EndBook'), SORT_ASC,
            array_column($questions, 'EndChapter'), SORT_ASC,
            array_column($questions, 'EndVerse'), SORT_ASC,
            $questions);
    }
    // TODO: sort/merge with fill in the blank questions

    // Generate output
    $outputQuestions = [];
    $number = 1;
    foreach ($questions as $question) {
        $data = array (
            "type" => "qa", // todo: fill in the blank
            "number" => $number,
            "id" => $question["QuestionID"],
            "points" => $question["NumberPoints"],
            "start-book" => $question["StartBook"],
            "start-chapter" => $question["StartChapter"],
            "start-verse" => $question["StartVerse"],
            "end-book" => $question["EndBook"] != NULL ? $question["EndBook"] : "",
            "end-chapter" => $question["EndChapter"] != NULL ? $question["EndChapter"] : "",
            "end-verse" => $question["EndVerse"] != NULL ? $question["EndVerse"] : "",
            //
            "question" => $question["Question"],
            "answer" => $question["Answer"]
            // for fill in the blank, will have text/blank key/value pairs
        );
        $outputQuestions[] = $data;
        $number++;
    }

    $output = [ "questions" => $outputQuestions ];
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($output);
?>