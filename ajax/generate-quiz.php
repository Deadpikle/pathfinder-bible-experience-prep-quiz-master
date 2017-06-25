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
    $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
    $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";
    $shouldAvoidPastCorrect = $_POST["shouldAvoidPastCorrect"]; // TODO: extra JOIN and WHERE 

    // Setup query
    $selectPortion = '
        SELECT q.QuestionID, q.Type, Question, Answer, NumberPoints, DateCreated,
            bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
            bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
            IFNULL(uf.UserFlaggedID, 0) AS IsFlagged,
            CommentaryVolume, CommentaryStartPage, CommentaryEndPage ';
    $fromPortion = '
        FROM Questions q 
            LEFT JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
            LEFT JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
            LEFT JOIN Books bStart ON bStart.BookID = cStart.BookID

            LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID 
            LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID 
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID 
            LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID';
    $whereClause = ' 
        WHERE NumberPoints <= ' . $maxPoints;
    $orderByPortion = '';
    if ($areRandomQuestionsPulled) {
        $orderByPortion = ' ORDER BY RAND() ';
    }
    else {
        // sequential-sequential
        // this is less than ideal due to the commentary vs Bible q&a ordering, but 
        // I couldn't think of a better & quick way to handle sequential-sequential with multiple question types
        // I really need to pull these out with 2 queries, but that's problematic due to max questions.
        // Should I just enforce a start/end verse on commentary questions?
        // sequential-sequential is problematic when pulling questions out of two locations...
        $orderByPortion = '
            ORDER BY COALESCE(bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number,
                CommentaryVolume, CommentaryStartPage, CommentaryEndPage)';
    }

    $limitPortion = ' LIMIT ' . $maxQuestions;
    $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
    $questions = $stmt->fetchAll();


    if ($isOutputSequential) {
        // If things need to be shown sequentially, we need to separate the question types, sort them individually,
        // then re-merge them in a random order (but still sequential within the question types)

        // Separate the two question types
        $bibleQnA = array();
        $commentaryQnA = array();
        foreach ($questions as $question) {
            $type = $question["Type"];
            if ($type === "bible-qna") {
                $bibleQnA[] = $question;
            }
            else if ($type === "commentary-qna") {
                $commentaryQnA[] = $question;
            }
        }

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

        // Merge them back together~
        $output = array();
        $bibleCount = count($bibleQnA);
        $commentaryCount = count($commentaryQnA);
        $totalQuestions = $bibleCount + $commentaryCount;
        $bibleIndex = 0;
        $commentaryIndex = 0;
        for ($i = 0; $i < $totalQuestions; $i++) {
            // 0 = Bible question, 1 = commentary question obtained via random_int(0, 1);
            $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
            $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
            if ($hasBibleQuestionLeft && $hasCommentaryQuestionLeft) {
                // pull next one out randomly
                $randomSelection = random_int(0, 1);
                if ($randomSelection == 0) {
                    $output[] = $bibleQnA[$bibleIndex];
                    $bibleIndex++;
                }
                else {
                    $output[] = $commentaryQnA[$commentaryIndex];
                    $commentaryIndex++;
                }
            }
            else if ($hasBibleQuestionLeft) {
                $output[] = $bibleQnA[$bibleIndex];
                $bibleIndex++;
            }
            else {
                // has commentary question left
                $output[] = $commentaryQnA[$commentaryIndex];
                $commentaryIndex++;
            }
        }
        // set questions to output of this algorithm~
        $questions = $output;
    }
    // TODO: pull out fill in the blank questions
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

    $output = [ "questions" => $outputQuestions ];
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($output);
?>