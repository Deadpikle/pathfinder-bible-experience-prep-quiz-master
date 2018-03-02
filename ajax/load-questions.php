<?php

    require_once("../config.php");
    session_name($SESSION_NAME);
    session_start();

    require_once("../database.php");
    require_once("../functions.php");

    try {
        $whereClause = "";
        $isFlagged = FALSE;
        $flaggedJoinClause = "";
        $questionType = "bible-qna";
        if (isset($_POST["questionFilter"])) {
            $questionFilter = $_POST["questionFilter"];
            if ($questionFilter == "recent") {
                $eightDaysAgo = date('Y-m-d 00:00:00', strtotime('-8 days'));
                $whereClause = " WHERE DateCreated >= '" . $eightDaysAgo . "' ";
            }
            else if ($questionFilter == "flagged") {
                $isFlagged = TRUE;
                $flaggedJoinClause =  " JOIN UserFlagged uf ON q.QuestionID = uf.QuestionID ";
                $whereClause = " WHERE UserID = " . $_SESSION["UserID"];
            }
        }
        if (isset($_POST["questionType"])) {
            $questionType = $_POST["questionType"];
        }
        if ($whereClause == "") {
            $whereClause = " WHERE (Type = '" . $questionType . "' OR Type = '" . $questionType . "-fill') ";
        }
        else {
            $whereClause .= " AND (Type = '" . $questionType . "' OR Type = '" . $questionType . "-fill') ";
        }
        // TODO: why are these if/else things in two separate sections exactly?
        if (strpos($questionType, 'bible') !== false) {
            if (isset($_POST["bookFilter"]) && is_numeric($_POST["bookFilter"]) && $_POST["bookFilter"] != -1) {
                $whereClause .= " AND bStart.BookID = " . $_POST["bookFilter"];
            }
            if (isset($_POST["chapterFilter"]) && is_numeric($_POST["chapterFilter"]) && $_POST["chapterFilter"] != -1) {
                $whereClause .= " AND cStart.ChapterID = " . $_POST["chapterFilter"];
            }
        }
        else if (strpos($questionType, 'commentary') !== false) {
            if (isset($_POST["volumeFilter"]) && is_numeric($_POST["volumeFilter"]) && $_POST["volumeFilter"] != -1) {
                $whereClause .= " AND comm.Number = " . $_POST["volumeFilter"];
            }
        }

        $currentYear = get_active_year($pdo)["YearID"];

        if ($questionType == "bible-qna" || $questionType == "bible-qna-fill") {
            $orderByClause = " ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number ";
            $whereClause .= " AND IsDeleted = 0 AND bStart.YearID = " . $currentYear . " AND (q.EndVerseID IS NULL OR bEnd.YearID = " . $currentYear . ")";
        }
        else if ($questionType == "commentary-qna" || $questionType == "commentary-qna-fill") {
            $orderByClause = " ORDER BY comm.Number, CommentaryStartPage, CommentaryEndPage ";
            $whereClause .= " AND IsDeleted = 0 AND comm.YearID = " . $currentYear;
        }
        else {
            $orderByClause = "";
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
            SELECT q.QuestionID, Question, Answer, NumberPoints, DateCreated,
                bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                Type, comm.Number AS CommentaryVolume, comm.TopicName, CommentaryStartPage, CommentaryEndPage ';
        $fromPortion = '
            FROM Questions q 
                LEFT JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
                LEFT JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
                LEFT JOIN Books bStart ON bStart.BookID = cStart.BookID

                LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
                LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID
                LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
                LEFT JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID 
                ' . $flaggedJoinClause . '
                ' . $whereClause . '
                ' . $orderByClause;
        $limitClause = '
            LIMIT ' . $pageOffset . ',' . $pageSize;  
        $fullQuery = $selectPortion . $fromPortion . $limitClause;
        $stmt = $pdo->query($fullQuery);
        $questions = $stmt->fetchAll();

        if (isset($_POST["searchText"]) && $_POST["searchText"] !== "") {
            $tmpQuestions = [];
            $searchText = $_POST["searchText"];
            foreach ($questions as $question) {
                // see if it matches the search text
                $startBook = $question["StartBook"];
                $startVerse = $question["StartChapter"] . ":" . $question["StartVerse"];
                // TODO: finish up the custom search
                /*var startVerse = question.StartBook + " " + question.StartChapter + ":" + question.StartVerse;
                var endVerse = "";
                if (typeof question.EndVerse !== 'undefined' && question.EndVerse != null && question.EndVerse != "") {
                    endVerse = question.EndBook + " " + question.EndChapter + ":" + question.EndVerse;
                }*/
            }
        }

        $stmt = $pdo->query("SELECT COUNT(*) AS QuestionCount " . $fromPortion);
        $row = $stmt->fetch(); 
        $totalQuestions = $row["QuestionCount"];

        $output = json_encode(array(
            "questions" => $questions,
            "totalQuestions" => $totalQuestions
        ));
        header('Content-Type: application/json; charset=utf-8');
        echo $output;
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
    catch (Exception $e) {
        print_r($e);
        die();
    }

?>