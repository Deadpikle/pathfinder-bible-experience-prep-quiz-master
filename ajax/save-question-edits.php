<?php
    require_once(dirname(__FILE__)."/../init.php");
    
    $startVerseID = isset($_POST["start-verse-id"]) ? $_POST["start-verse-id"] : NULL;
    if ($_POST["start-verse-id"] == -1 || $_POST["start-verse-id"] == NULL) {
        $startVerseID = NULL;
    }
    $endVerseID = isset($_POST["end-verse-id"]) ? $_POST["end-verse-id"] : NULL;
    if ($_POST["end-verse-id"] == -1 || $_POST["end-verse-id"] == NULL) {
        $endVerseID = NULL;
    }
    $shouldRemoveFlag = FALSE;
    if (isset($_POST["remove-question-flag"]) && $_POST["remove-question-flag"] != NULL) {
        $shouldRemoveFlag = TRUE;
    }
    $commentaryVolume = $_POST["commentary-volume"];
    $commentaryStartPage = $_POST["commentary-start"];
    $commentaryEndPage = $_POST["commentary-end"];
    $questionType = $_POST["question-type"];
    if ($questionType == "bible-qna") {
        $commentaryVolume = NULL;
        $commentaryStartPage = NULL;
        $commentaryEndPage = NULL;
    }
    else if ($questionType == "commentary-qna") {
        $startVerseID = NULL;
        $endVerseID = NULL;
    }
    $params = [
        $questionType, // either bible-qna or commentary-qna right now (we don't support fill in the blank quite yet)
        $_POST["question-text"],
        $_POST["question-answer"],
        $_POST["number-of-points"],
        $_SESSION["UserID"],
        $startVerseID,
        $endVerseID,
        $commentaryVolume,
        $commentaryStartPage,
        $commentaryEndPage
    ];
    if ($_GET["type"] == "update") {
        $query = '
            UPDATE Questions SET Type = ?, Question = ?, Answer = ?, NumberPoints = ?, LastEditedByID = ?, StartVerseID = ?, EndVerseID = ?,
            CommentaryVolume = ?, CommentaryStartPage = ?, CommentaryEndPage = ?';
        $query .= ' WHERE QuestionID = ?';
        $params[] = $_POST["question-id"];
    }
    else if ($_GET["type"] == "create") {
        $params[] = $_SESSION["UserID"];
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryVolume, CommentaryStartPage, CommentaryEndPage, CreatorID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
    }
    else {
        die("Invalid type");
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    if ($shouldRemoveFlag) {
        $query = ' DELETE FROM UserFlagged WHERE QuestionID = ? AND UserID = ?';
        $params = [
            $_POST["question-id"],
            $_SESSION["UserID"]
        ];
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }

    header("Location: $basePath/view-questions.php");
?>