<?php
    require_once(dirname(__FILE__)."/../init.php");
    
    $endVerseID = isset($_POST["end-verse-id"]) ? $_POST["end-verse-id"] : NULL;
    if ($_POST["end-verse-id"] == -1 || $_POST["end-verse-id"] == NULL) {
        $endVerseID = NULL;
    }
    $params = [
        $_POST["question-text"],
        $_POST["question-answer"],
        $_POST["number-of-points"],
        $_SESSION["UserID"],
        $_POST["start-verse-id"],
        $endVerseID
    ];
    if ($_GET["type"] == "update") {
        $query = '
            UPDATE Questions SET Question = ?, Answer = ?, NumberPoints = ?, LastEditedByID = ?, StartVerseID = ?, EndVerseID = ? WHERE QuestionID = ?
        ';
        $params[] = $_POST["question-id"];
    }
    else if ($_GET["type"] == "create") {
        $params[] = $_SESSION["UserID"];
        $query = '
            INSERT INTO Questions (Question, Answer, NumberPoints, LastEditedByID, StartVerseID, EndVerseID, CreatorID) VALUES (?, ?, ?, ?, ?, ?, ?)
        ';
    }
    else {
        die("Invalid type");
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    header("Location: $basePath/view-questions.php");
?>