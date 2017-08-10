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
    $shouldAvoidPastCorrectAnswers = filter_var($_POST["shouldAvoidPastCorrect"], FILTER_VALIDATE_BOOLEAN);
    
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
    
    $percentFillIn = 30;
    if (isset($_POST["fillInPercent"])) {
        $percentFillIn = filter_var($_POST["fillInPercent"], FILTER_VALIDATE_INT);
    }
    $percentFillIn = $percentFillIn / 100;

    // question type values:
    // both
    // qa-only
    // fill-in-only
    $questionTypes = $_POST["questionTypes"];
    $userWantsNormalQuestions = $_POST["questionTypes"] === "qa-only" || $_POST["questionTypes"] === "both";
    $userWantsFillIn = $_POST["questionTypes"] === "fill-in-only" || $_POST["questionTypes"] === "both";
    $questionOrder = $_POST["questionOrder"];
    $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
    $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";

    // see if user wants to load any possible question or just from a specific chapter of the Bible (or Bible commentary volume)
    if (!isset($_POST["quizItems"])) {
        $quizItems = array();
    }
    else {
        $quizItems = $_POST["quizItems"];
    }
    $chapterIDs = array();
    $volumeNumbers = array();
    if (count($quizItems) > 0) {
        // user wants to load specific things!
        // figure out which chapter IDs and volume numbers they want to load
        foreach ($quizItems as $item) {
            if (strpos($item, 'chapter-') !== false) {
                $text = str_replace('chapter-', '', $item);
                $chapterIDs[] = (int)$text;
            }
            else if (strpos($item, 'commentary-') !== false) {
                $text = str_replace('commentary-', '', $item);
                $volumeNumbers[] = (int)$text;
            }
        }
    }
    $shouldLoadBibleQnA = (count($quizItems) == 0 || count($chapterIDs) > 0) && $userWantsNormalQuestions;
    $shouldLoadCommentaryQnA = (count($quizItems) == 0 || count($volumeNumbers) > 0) && $userWantsNormalQuestions;
    // // // // //
    // load Bible questions
    // // // // //
    $bibleQnA = array();
    $selectPortion = '
        SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
            bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
            bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
            IFNULL(uf.UserFlaggedID, 0) AS IsFlagged ';
    $fromPortion = '
        FROM Questions q 
            JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
            JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
            JOIN Books bStart ON bStart.BookID = cStart.BookID

            LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID 
            LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID 
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID 
            LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID';
    if ($shouldAvoidPastCorrectAnswers) {
        $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
    }
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "bible-qna"';
    if (count($chapterIDs) > 0) {
        $whereClause .= ' AND cStart.ChapterID IN (' . implode(',', $chapterIDs) . ') ';
    }
    if ($shouldAvoidPastCorrectAnswers) {
        $whereClause .= '  AND (ua.UserAnswerID IS NULL 
            OR (ua.UserAnswerID IS NOT NULL AND ua.WasCorrect = 0 AND ua.UserID = ' . $_POST["userID"] . '))'; 
    }
    $orderByPortion = '';
    if ($areRandomQuestionsPulled) {
        $orderByPortion = ' ORDER BY RAND() ';
    }
    else {
        // sequential-sequential
        $orderByPortion = '
            ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number';
    }

    $limitPortion = ' LIMIT ' . $maxQuestions;
    if ($shouldLoadBibleQnA) {
        $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
        $bibleQnA = $stmt->fetchAll();
    }
    // // // // //
    // load Bible fill in the blank questions
    // // // // //
    $bibleFillIn = array();
    $whereClause = str_replace("bible-qna", "bible-qna-fill", $whereClause);
    if ($userWantsFillIn) {
        $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
        $bibleFillIn = $stmt->fetchAll();
    }

    // // // // //
    // load commentary questions
    // // // // //
    $commentaryQnA = array();
    $selectPortion = '
        SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
            IFNULL(uf.UserFlaggedID, 0) AS IsFlagged,
            CommentaryVolume, CommentaryStartPage, CommentaryEndPage ';
    $fromPortion = '
        FROM Questions q 
            LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID';
    if ($shouldAvoidPastCorrectAnswers) {
        $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
    }
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "commentary-qna"';
    if (count($volumeNumbers) > 0) {
        $whereClause .= ' AND CommentaryVolume IN (' . implode(',', $volumeNumbers) . ') ';
    }
    if ($shouldAvoidPastCorrectAnswers) {
        $whereClause .= '  AND (ua.UserAnswerID IS NULL 
            OR (ua.UserAnswerID IS NOT NULL AND ua.WasCorrect = 0 AND ua.UserID = ' . $_POST["userID"] . '))'; 
    }
    if (!$areRandomQuestionsPulled) {
        $orderByPortion = ' ORDER BY CommentaryVolume, CommentaryStartPage, CommentaryEndPage';
    }
    if ($shouldLoadCommentaryQnA) {
        $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
        $commentaryQnA = $stmt->fetchAll();
    }
    // // // // //
    // load commentary fill in the blank questions
    // // // // //
    $commentaryFillIn = array();
    $whereClause = str_replace("commentary-qna", "commentary-qna-fill", $whereClause);
    if ($userWantsFillIn) {
        $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
        $commentaryFillIn = $stmt->fetchAll();
    }
    // // // // //
    // Merge data as needed
    // // // // //
    if ($isOutputSequential) {
        // If things need to be shown sequentially, we need to sort them individually,
        // then re-merge them in a random order (but still sequential within the question types)

        // Sort the arrays
        // https://stackoverflow.com/a/3233009/3938401
        array_multisort(
            array_column($bibleQnA, 'StartBook'), SORT_ASC, 
            array_column($bibleQnA, 'StartChapter'), SORT_ASC, 
            array_column($bibleQnA, 'StartVerse'), SORT_ASC,
            array_column($bibleQnA, 'EndBook'), SORT_ASC,
            array_column($bibleQnA, 'EndChapter'), SORT_ASC,
            array_column($bibleQnA, 'EndVerse'), SORT_ASC,
            $bibleQnA);
        array_multisort(
            array_column($bibleFillIn, 'StartBook'), SORT_ASC, 
            array_column($bibleFillIn, 'StartChapter'), SORT_ASC, 
            array_column($bibleFillIn, 'StartVerse'), SORT_ASC,
            array_column($bibleFillIn, 'EndBook'), SORT_ASC,
            array_column($bibleFillIn, 'EndChapter'), SORT_ASC,
            array_column($bibleFillIn, 'EndVerse'), SORT_ASC,
            $bibleFillIn);
            
        array_multisort(
            array_column($commentaryQnA, 'CommentaryVolume'), SORT_ASC, 
            array_column($commentaryQnA, 'CommentaryStartPage'), SORT_ASC, 
            array_column($commentaryQnA, 'CommentaryEndPage'), SORT_ASC,
            $commentaryQnA);
        array_multisort(
            array_column($commentaryFillIn, 'CommentaryVolume'), SORT_ASC, 
            array_column($commentaryFillIn, 'CommentaryStartPage'), SORT_ASC, 
            array_column($commentaryFillIn, 'CommentaryEndPage'), SORT_ASC,
            $commentaryFillIn);
    }
    
    // Generate final questions array using data we've pulled out of the database
    $output = array();
    $bibleCount = count($bibleQnA);
    $bibleFillInCount = count($bibleFillIn);
    $commentaryCount = count($commentaryQnA);
    $commentaryFillInCount = count($commentaryFillIn);
    $bibleAdded = 0;
    $bibleFillInAdded = 0;
    $commentaryAdded = 0;
    $commentaryFillInAdded = 0;
    //die("bible: " . (int)$bibleCount . "  commentary: " . (int)$commentaryCount);
    $bibleIndex = 0;
    $bibleFillInIndex = 0;
    $commentaryIndex = 0;
    $commentaryFillInIndex = 0;
    for ($i = 0; $i < $maxQuestions; $i++) {
        $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
        $hasBibleFillInLeft = $bibleFillInIndex < $bibleFillInCount;
        $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
        $hasCommentaryFillInQuestionLeft = $commentaryFillInIndex < $bibleFillInCount;

        if (!$hasBibleQuestionLeft && !$hasCommentaryQuestionLeft && 
            !$hasBibleFillInLeft && !$hasCommentaryFillInQuestionLeft) {
            break; // ran out of questions!
        }
        // figure out which arrays have stuff left
        $availableArraysOfQuestions = array();
        if ($hasBibleQuestionLeft) {
            $availableArraysOfQuestions[] = "bible-qna";
        }
        if ($hasBibleFillInLeft) {
            $availableArraysOfQuestions[] = "bible-qna-fill";
        }
        if ($hasCommentaryQuestionLeft) {
            $availableArraysOfQuestions[] = "commentary-qna";
        }
        if ($hasCommentaryFillInQuestionLeft) {
            $availableArraysOfQuestions[] = "commentary-qna-fill";
        }
        // now choose one
        $index = random_int(0, count($availableArraysOfQuestions) - 1);
        $typeToAdd = $availableArraysOfQuestions[$index];
        // add the question to the output
        if ($typeToAdd == "bible-qna") {
            $output[] = $bibleQnA[$bibleIndex];
            $bibleIndex++;
            $bibleAdded++;
        }
        else if ($typeToAdd == "bible-qna-fill") {
            $output[] = $bibleFillIn[$bibleFillInIndex];
            $bibleFillInIndex++;
            $bibleFillInAdded++;
        }
        else if ($typeToAdd == "commentary-qna") {
            $output[] = $commentaryQnA[$commentaryIndex];
            $commentaryIndex++;
            $commentaryAdded++;
        }
        else if ($typeToAdd == "commentary-qna-fill") {
            $output[] = $commentaryFillIn[$commentaryFillInIndex];
            $commentaryFillInIndex++;
            $commentaryFillInAdded++;
        }
    }
    // set questions to output of this little merging algorithm
    $questions = $output;
    
    // TODO: sort/merge with fill in the blank questions

    // Generate output
    $outputQuestions = [];
    $number = 1;
    foreach ($questions as $question) {
        $data = array (
            "type" => $question["Type"],
            "number" => $number,
            "id" => $question["QuestionID"],
            "isFlagged" => $question["IsFlagged"],
            "points" => $question["NumberPoints"],
            "question" => $question["Question"],
            "answer" => $question["Answer"]
        );
        if (is_bible_qna($question["Type"])) {
            // Bible Q&A
            $data["startBook"] = $question["StartBook"] != NULL ? $question["StartBook"] : "";
            $data["startChapter"] = $question["StartChapter"] != NULL ? $question["StartChapter"] : "";
            $data["startVerse"] = $question["StartVerse"] != NULL ? $question["StartVerse"] : "";
            $data["endBook"] = $question["EndBook"] != NULL ? $question["EndBook"] : "";
            $data["endChapter"] = $question["EndChapter"] != NULL ? $question["EndChapter"] : "";
            $data["endVerse"] = $question["EndVerse"] != NULL ? $question["EndVerse"] : "";
        }
        else if (is_commentary_qna($question["Type"])) {
            // commentary Q&A
            $data["volume"] = $question["CommentaryVolume"];
            $data["startPage"] = $question["CommentaryStartPage"];
            $data["endPage"] = $question["CommentaryEndPage"];
        }
        if (is_fill_in($question["Type"])) {
            $fillInData = generate_fill_in_question($question["Question"], $percentFillIn);
            $data["fillInData"] = $fillInData;
            $data["points"] = $fillInData["blank-count"];
        }
        // for fill in the blank, will have text/blank key/value pairs
        $outputQuestions[] = $data;
        $number++;
    }

    $output = [ 
        "bibleQuestions" => $bibleAdded,
        "bibleFillIns" => $bibleFillInAdded,
        "commentaryQuestions" => $commentaryAdded,
        "commentaryFillIns" => $commentaryFillInAdded,
        "totalQuestions" => ($bibleAdded + $bibleFillInAdded + $commentaryAdded + $commentaryFillInAdded),
        "questions" => $outputQuestions 
    ];
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($output);
?>