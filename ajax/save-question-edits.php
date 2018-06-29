<?php
    require_once(dirname(__FILE__)."/../init.php");
    
    $totalBibleFillInQuestions = get_total_number_of_bible_fill_questions_for_current_year($pdo);
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
    $commentaryID = $_POST["commentary-volume"];
    $commentaryStartPage = $_POST["commentary-start"];
    $commentaryEndPage = $_POST["commentary-end"];
    $questionType = $_POST["question-type"];
    // see if fill in the blank
    if (!isset($_POST["question-is-fill-in-blank"]) || $_POST["question-is-fill-in-blank"] == NULL) {
        $isFillInTheBlank = FALSE;
    }
    else {
        $isFillInTheBlank = TRUE;
    }
    $formType = $_GET["type"];
    if ($questionType == "bible-qna") {
        $commentaryID = NULL;
        $commentaryStartPage = NULL;
        $commentaryEndPage = NULL;
        if ($isFillInTheBlank) {
            $questionType = "bible-qna-fill";
            if ($totalBibleFillInQuestions >= 500 && $ENABLE_NKJV_RESTRICTIONS) {
                if ($formType == "create") {
                    die("Maximum amount of Bible questions reached");
                }
                else if ($formType == "update") { 
                    // it's only OK if the current question is already a bible-qna-fill
                    $query = 'SELECT Type FROM Questions WHERE QuestionID = ?';
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$_POST["question-id"]]);
                    $questionData = $stmt->fetch();
                    if ($questionData !== NULL && $questionData['Type'] !== 'bible-qna-fill') {
                        die("Maximum amount of Bible questions reached (changing question type)");
                    }
                }
            }
        }
    }
    else if ($questionType == "commentary-qna") {
        $startVerseID = NULL;
        $endVerseID = NULL;
        if ($isFillInTheBlank) {
            $questionType = "commentary-qna-fill";
        }
    }

    $params = [
        $questionType, // either bible-qna or bible-qna-fill or commentary-qna or commentary-qna-fill right now
        trim($_POST["question-text"]),
        isset($_POST["question-answer"]) ? trim($_POST["question-answer"]) : "",
        $_POST["number-of-points"],
        $_SESSION["UserID"],
        $startVerseID,
        $endVerseID,
        $commentaryID,
        $commentaryStartPage,
        $commentaryEndPage
    ];
    
    if ($formType == "update") {
        $query = '
            UPDATE Questions SET Type = ?, Question = ?, Answer = ?, NumberPoints = ?, LastEditedByID = ?, StartVerseID = ?, EndVerseID = ?,
            CommentaryID = ?, CommentaryStartPage = ?, CommentaryEndPage = ?';
        $query .= ' WHERE QuestionID = ?';
        $params[] = $_POST["question-id"];
    }
    else if ($formType == "create") {
        $params[] = $_SESSION["UserID"];
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryID, CommentaryStartPage, CommentaryEndPage, CreatorID) 
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