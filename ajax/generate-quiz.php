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
    // question type values:
    // both
    // qa-only
    // fill-in-only
    $questionTypes = $_POST["questionTypes"]; // TODO: fill in the blank
    $questionOrder = $_POST["questionOrder"];
    $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
    $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";
    $shouldAvoidPastCorrect = $_POST["shouldAvoidPastCorrect"]; // TODO: extra JOIN and WHERE 

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
    $shouldLoadBibleQnA = count($quizItems) == 0 || count($chapterIDs) > 0;
    $shouldLoadCommentaryQnA = count($quizItems) == 0 || count($volumeNumbers) > 0;
    // load Bible questions
    $bibleQnA = array();
    $selectPortion = '
        SELECT q.QuestionID, q.Type, Question, Answer, NumberPoints, DateCreated,
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
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "bible-qna"';
    if (count($chapterIDs) > 0) {
        $whereClause .= ' AND cStart.ChapterID IN (' . implode(',', $chapterIDs) . ') ';
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

    $commentaryQnA = array();
    // load commentary questions
    $selectPortion = '
        SELECT q.QuestionID, q.Type, Question, Answer, NumberPoints, DateCreated,
            IFNULL(uf.UserFlaggedID, 0) AS IsFlagged,
            CommentaryVolume, CommentaryStartPage, CommentaryEndPage ';
    $fromPortion = '
        FROM Questions q 
            LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID';
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "commentary-qna"';
    if (count($volumeNumbers) > 0) {
        $whereClause .= ' AND CommentaryVolume IN (' . implode(',', $volumeNumbers) . ') ';
    }
    if (!$areRandomQuestionsPulled) {
        $orderByPortion = ' ORDER BY CommentaryVolume, CommentaryStartPage, CommentaryEndPage';
    }
    if ($shouldLoadCommentaryQnA) {
        $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
        $commentaryQnA = $stmt->fetchAll();
    }
    // TODO: pull out fill in the blank questions
    // Merge data as needed
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
            array_column($commentaryQnA, 'CommentaryVolume'), SORT_ASC, 
            array_column($commentaryQnA, 'CommentaryStartPage'), SORT_ASC, 
            array_column($commentaryQnA, 'CommentaryEndPage'), SORT_ASC,
            $commentaryQnA);
    }
    
    // Generate final questions array using data we've pulled out of the database
    $output = array();
    $bibleCount = count($bibleQnA);
    $commentaryCount = count($commentaryQnA);
    $bibleAdded = 0;
    $commentaryAdded = 0;
    //die("bible: " . (int)$bibleCount . "  commentary: " . (int)$commentaryCount);
    $totalQuestions = $bibleCount + $commentaryCount;
    $bibleIndex = 0;
    $commentaryIndex = 0;
    for ($i = 0; $i < $maxQuestions; $i++) {
        // even = Bible question, odd = commentary question obtained via random_int(0, 100);
        $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
        $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
        if (!$hasBibleQuestionLeft && !$hasCommentaryQuestionLeft) {
            break; // ran out of questions!
        }
        if ($hasBibleQuestionLeft && $hasCommentaryQuestionLeft) {
            // pull next one out randomly
            $randomSelection = random_int(0, 100);
            if ($randomSelection % 2 == 0) {
                $output[] = $bibleQnA[$bibleIndex];
                $bibleIndex++;
                $bibleAdded++;
            }
            else {
                $output[] = $commentaryQnA[$commentaryIndex];
                $commentaryIndex++;
                $commentaryAdded++;
            }
        }
        else if ($hasBibleQuestionLeft) {
            $output[] = $bibleQnA[$bibleIndex];
            $bibleIndex++;
            $bibleAdded++;
        }
        else if ($hasCommentaryQuestionLeft) {
            // has commentary question left
            $output[] = $commentaryQnA[$commentaryIndex];
            $commentaryIndex++;
            $commentaryAdded++;
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
            "type" => $question["Type"], // todo: fill in the blank
            "number" => $number,
            "id" => $question["QuestionID"],
            "isFlagged" => $question["IsFlagged"],
            "points" => $question["NumberPoints"],
            "question" => $question["Question"],
            "answer" => $question["Answer"]
        );
        if ($question["Type"] == "bible-qna") {
            // Bible Q&A
            $data["startBook"] = $question["StartBook"] != NULL ? $question["StartBook"] : "";
            $data["startChapter"] = $question["StartChapter"] != NULL ? $question["StartChapter"] : "";
            $data["startVerse"] = $question["StartVerse"] != NULL ? $question["StartVerse"] : "";
            $data["endBook"] = $question["EndBook"] != NULL ? $question["EndBook"] : "";
            $data["endChapter"] = $question["EndChapter"] != NULL ? $question["EndChapter"] : "";
            $data["endVerse"] = $question["EndVerse"] != NULL ? $question["EndVerse"] : "";
        }
        else if ($question["Type"] == "commentary-qna") {
            // commentary Q&A
            $data["volume"] = $question["CommentaryVolume"];
            $data["startPage"] = $question["CommentaryStartPage"];
            $data["endPage"] = $question["CommentaryEndPage"];
        }
        // for fill in the blank, will have text/blank key/value pairs
        $outputQuestions[] = $data;
        $number++;
    }

    $output = [ 
        "bibleQuestions" => $bibleAdded,
        "commentaryQuestions" => $commentaryAdded,
        "questions" => $outputQuestions 
    ];
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($output);
?>