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
    $params = [
        $_POST["question-type"], // either bible-qna or commentary-qna right now
        $_POST["question-text"],
        $_POST["question-answer"],
        $_POST["number-of-points"],
        $_SESSION["UserID"],
        $startVerseID,
        $endVerseID,
        $_POST["commentary-volume"],
        $_POST["commentary-start"],
        $_POST["commentary-end"]
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