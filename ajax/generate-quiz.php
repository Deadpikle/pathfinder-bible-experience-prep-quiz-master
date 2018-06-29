<?php
    require_once("../config.php");
    session_name($SESSION_NAME);
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

    $DEBUG = false;
    
    if (isset($_POST["enableQuestionDistribution"]) && 
        filter_var($_POST["enableQuestionDistribution"], FILTER_VALIDATE_BOOLEAN) &&
        isset($_POST["quizItems"]) && count($_POST["quizItems"]) > 0) {
        // performing custom question distribution!
        $bibleWeights = [];
        $commentaryWeights = [];
        $allWeights = [];
        foreach ($_POST as $key => $value) {
            if (str_contains("table-input-chapter-", $key)) {
                $bibleWeights[$key] = $value;
                $allWeights[$key] = $value;
            }
            else if (str_contains("table-input-commentary-", $key)) {
                $commentaryWeights[$key] = $value;
                $allWeights[$key] = $value;
            }
        }
        // Make sure submitted percentage not below 0 or above 100
        $totalPercent = 0;
        $hasNegativePercent = false;
        foreach($bibleWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
        }
        foreach($commentaryWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
        }
        if ($hasNegativePercent) {
            die("Invalid weighted question percent given. All percents must be above positive or 0.");
        }
        if ($totalPercent < 0 || $totalPercent > 100) {
            die("Invalid weighted question percent given. Value must be between 0 and 100 inclusive.");
        }
        //print_r($_POST["questionTypes"]);
        // // // //
        // For each quizItems item that has a specific weight set in bibleWeights/commentaryWeights,
        // generate questions.
        $allGenerated = [];
        $quizItems = $_POST["quizItems"];
        $postCopy = $_POST;
        $totalGenerated = 0;
        // get rid of values we will override in the loop
        unset($postCopy["quizItems"]);
        $maxQuestions = (int)$postCopy["maxQuestions"];
        unset($postCopy["maxQuestions"]);
        // first generate questions for those sections that have weights
        for ($i = 0; $i < count($quizItems); $i++) {
            $quizItem = $quizItems[$i];
            $allWeightsKey = "table-input-" . $quizItem;
            if (isset($allWeights[$allWeightsKey]) && (int)$allWeights[$allWeightsKey] > 0) {
                $postCopy["quizItems"] = [ $quizItem ];
                $quizItemCount = count($quizItems); // count every loop iteration due to the unset a few lines down
                if ($i == $quizItemCount - 1 && $quizItemCount == 1) {
                    // if we only have 1 thing left with our weighting system and
                    // this thing is weighted, try to get as many questions out of it as possible
                    $postCopy["maxQuestions"] = $maxQuestions - $totalGenerated;
                }
                else {
                    $postCopy["maxQuestions"] = floor($maxQuestions * ((int)$allWeights[$allWeightsKey] / 100));
                    if ($postCopy["maxQuestions"] == 0) {
                        $postCopy["maxQuestions"] = 1;
                    }
                    if ($postCopy["maxQuestions"] == 1 && $postCopy["maxQuestions"] + $totalGenerated > $maxQuestions) {
                        break;
                    }
                }
                $generatedQuestions = generate_quiz_questions($pdo, $postCopy);
                $allGenerated[] = $generatedQuestions;
                $totalGenerated += (int)$generatedQuestions["totalQuestions"];
                // we don't want to generate questions for this chapter again
                unset($quizItems[$i]);
            }
        }
        //echo "-------" . "\n";
        //echo $totalGenerated . " questions generated \n";
        //echo "-------" . "\n";
        // OK, now that we have generated questions for all sections with specific
        // weights, generate as many questions as possible for the remaining sections
        $questionsLeft = $maxQuestions - $totalGenerated;
        //echo "There are " . $questionsLeft .  " questions left to generate \n";
        
        // need to know how to pull out questions and sort questions at the end.
        $questionOrder = $_POST["questionOrder"];
        $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
        $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";

        if ($questionsLeft > 0) {
            $numberOfQuestionsForEachPortion = round($questionsLeft / count($quizItems));
            if ($numberOfQuestionsForEachPortion == 0) {
                $numberOfQuestionsForEachPortion = 1;
            }
            //echo "There are " . $numberOfQuestionsForEachPortion .  " questions for each item to generate \n";

            // OK INSTEAD ALWAYS GENERATE $QUESTIONSLEFT 
            // THEN SORT BY # GENERATED LEAST TO GREATEST
            // THEN TRY TO GET $numberOfQuestionsForEachPortion FROM EACH ONE
            // IF YOU CAN'T, JUST GRAB AS MANY AS POSSIBLE FROM ONES AS YOU GO ALONG THE ARRAY

            $otherGenerated = [];
            foreach ($quizItems as $quizItem) { // take whatever is left
                $postCopy["quizItems"] = [ $quizItem ];
                $postCopy["maxQuestions"] = $questionsLeft;
                $generatedQuestions = generate_quiz_questions($pdo, $postCopy);
                $otherGenerated[] = $generatedQuestions;
                //echo "Got " . $generatedQuestions["totalQuestions"] . " out\n";
            }
            // sort $otherGenerated by count from least to greatest
            array_multisort(array_column($otherGenerated, 'totalQuestions'), SORT_ASC, $otherGenerated);
            // now that we are sorted, grab questions from each one until we have enough
            // ugh we have to grab random or sequentially depending on what user asked for
            for ($i = 0; $i < count($otherGenerated); $i++) {
                $item = $otherGenerated[$i];
                if ($i == count($otherGenerated) - 1) {
                    $numberOfQuestionsForEachPortion = $questionsLeft; // try to get as many from last one as possible
                }
                if ($item["totalQuestions"] < $numberOfQuestionsForEachPortion) {
                    // we can't get enough questions out of this item. we'll have to adjust
                    // $numberOfQuestionsForEachPortion to account for this
                    $questionsLeft -= $item["totalQuestions"];
                    if ($i != count($otherGenerated) - 1) {
                        $numberOfQuestionsForEachPortion = round($questionsLeft / (count($quizItems) - ($i + 1)));
                        if ($numberOfQuestionsForEachPortion == 0) {
                            $numberOfQuestionsForEachPortion = 1;
                        }
                    }
                    // else we just flat out don't have enough questions. Sorry.
                    // don't have to worry about random selection or in order selection as we are choosing all of them,
                    // so just add these questions to the overall output.
                    $totalGenerated += $item["totalQuestions"];
                    $allGenerated[] = $item;
                    //echo "Added " . $item["totalQuestions"] . " when I did not have enough\n";
                }
                else {
                    // we have enough to fulfill our needs!
                    // pick out $numberOfQuestionsForEachPortion questions
                    $pickedQuestions = [];
                    if ($areRandomQuestionsPulled) {
                        shuffle($item["questions"]);
                        $pickedQuestions = array_slice($item["questions"], 0, $numberOfQuestionsForEachPortion);
                    }
                    else {
                        // pick the first $numberOfQuestionsForEachPortion out of the questions array
                        // as they are already in sequential order
                        $pickedQuestions = array_slice($item["questions"], 0, $numberOfQuestionsForEachPortion);
                    }
                    $questionsLeft -= $numberOfQuestionsForEachPortion;
                    $totalGenerated += $numberOfQuestionsForEachPortion;
                    $bibleQuestions = 0;
                    $bibleFillIns = 0;
                    $commentaryQuestions = 0;
                    $commentaryFillIns = 0;
                    foreach ($pickedQuestions as $question) {
                        if ($question["type"] == "bible-qna") {
                            $bibleQuestions++;
                        }
                        else if ($question["type"] == "bible-qna-fill") {
                            $bibleFillIns++;
                        }
                        else if ($question["type"] == "commentary-qna") {
                            $commentaryQuestions++;
                        }
                        else if ($question["type"] == "commentary-qna-fill") {
                            $commentaryFillIns++;
                        }
                    }
                    $allGenerated[] = [
                        "bibleQuestions" => $bibleQuestions,
                        "bibleFillIns" => $bibleFillIns,
                        "commentaryQuestions" => $commentaryQuestions,
                        "commentaryFillIns" => $commentaryFillIns,
                        "totalQuestions" => $numberOfQuestionsForEachPortion,
                        "questions" => $pickedQuestions
                    ];
                }
                //echo "There are " . $questionsLeft .  " questions left to generate \n";
                if ($questionsLeft <= 0) {
                    break; // just in case...
                }
            }
        }
        // At this point, everything is generated! Huzzah! Collate the questions into 
        // the final output, make sure things are sorted properly, and then return the
        // final data!
        $output = [
            "bibleQuestions" => 0,
            "bibleFillIns" => 0,
            "commentaryQuestions" => 0,
            "commentaryFillIns" => 0,
            "totalQuestions" => 0,
            "questions" => []
        ];
        foreach ($allGenerated as $item) {
            $output["bibleQuestions"] += (int)$item["bibleQuestions"];
            $output["bibleFillIns"] += (int)$item["bibleFillIns"];
            $output["commentaryQuestions"] += (int)$item["commentaryQuestions"];
            $output["commentaryFillIns"] += (int)$item["commentaryFillIns"];
            $output["totalQuestions"] += (int)$item["totalQuestions"];
            $output["questions"] = array_merge($output["questions"], $item["questions"]);
        }
        //echo "-------" . "\n";
        //echo "After all done, " . $totalGenerated . " questions generated \n";
        //echo "After all done, " . count($output["questions"]) . " questions generated \n";
        //echo "There are " . $questionsLeft .  " questions left to generate \n";
        //echo "-------" . "\n";
        
        // ok, everything is generated. However, now we need to resort 
        // everything so that the output is what the user expects!

        if ($isOutputSequential) {
            // have to break questions out into each kind and then sort and then reorder them
            $totalQuestions = count($output["questions"]);
            $bibleQnA = [];
            $bibleFillIn = [];
            $commentaryQnA = [];
            $commentaryFillIn = [];
            foreach ($output["questions"] as $question) {
                if ($question["type"] == "bible-qna") {
                    $bibleQnA[] = $question;
                }
                else if ($question["type"] == "bible-qna-fill") {
                    $bibleFillIn[] = $question;
                }
                else if ($question["type"] == "commentary-qna") {
                    $commentaryQnA[] = $question;
                }
                else if ($question["type"] == "commentary-qna-fill") {
                    $commentaryFillIn[] = $question;
                }
            }
            // sort!
            array_multisort(
                array_column($bibleQnA, 'startBook'), SORT_ASC, 
                array_column($bibleQnA, 'startChapter'), SORT_ASC, 
                array_column($bibleQnA, 'startVerse'), SORT_ASC,
                array_column($bibleQnA, 'endBook'), SORT_ASC,
                array_column($bibleQnA, 'endChapter'), SORT_ASC,
                array_column($bibleQnA, 'endVerse'), SORT_ASC,
                array_column($bibleQnA, 'id'), SORT_ASC,
                $bibleQnA);
            array_multisort(
                array_column($bibleFillIn, 'startBook'), SORT_ASC, 
                array_column($bibleFillIn, 'startChapter'), SORT_ASC, 
                array_column($bibleFillIn, 'startVerse'), SORT_ASC,
                array_column($bibleFillIn, 'endBook'), SORT_ASC,
                array_column($bibleFillIn, 'endChapter'), SORT_ASC,
                array_column($bibleFillIn, 'endVerse'), SORT_ASC,
                array_column($bibleFillIn, 'id'), SORT_ASC,
                $bibleFillIn);
            array_multisort(
                array_column($commentaryQnA, 'number'), SORT_ASC, 
                array_column($commentaryQnA, 'topic'), SORT_ASC, 
                array_column($commentaryQnA, 'startPage'), SORT_ASC, 
                array_column($commentaryQnA, 'endPage'), SORT_ASC,
                array_column($commentaryQnA, 'id'), SORT_ASC,
                $commentaryQnA);
            array_multisort(
                array_column($commentaryFillIn, 'number'), SORT_ASC, 
                array_column($commentaryFillIn, 'topic'), SORT_ASC, 
                array_column($commentaryFillIn, 'startPage'), SORT_ASC, 
                array_column($commentaryFillIn, 'endPage'), SORT_ASC,
                array_column($commentaryFillIn, 'id'), SORT_ASC,
                $commentaryFillIn);
            // now mash them back into a quiz.
            $reorderedQuestions = [];
            $bibleIndex = 0;
            $bibleFillInIndex = 0;
            $commentaryIndex = 0;
            $commentaryFillInIndex = 0;
            $bibleCount = count($bibleQnA);
            $bibleFillInCount = count($bibleFillIn);
            $commentaryCount = count($commentaryQnA);
            $commentaryFillInCount = count($commentaryFillIn);
            for ($i = 0; $i < $totalQuestions; $i++) {
                $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
                $hasBibleFillInLeft = $bibleFillInIndex < $bibleFillInCount;
                $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
                $hasCommentaryFillInQuestionLeft = $commentaryFillInIndex < $commentaryFillInCount;

                $availableArraysOfQuestions = [];
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
                //echo "i = " . $i . "\n";
                //echo "bible = " . $bibleIndex . ", bible fill = " . $bibleFillInIndex . ", commentary = " . $commentaryIndex . ", comm fill = ". $commentaryFillInIndex . "\n";
                $index = random_int(0, count($availableArraysOfQuestions) - 1);
                $typeToAdd = $availableArraysOfQuestions[$index];
                if ($typeToAdd == "bible-qna") {
                    $reorderedQuestions[] = $bibleQnA[$bibleIndex++];
                }
                else if ($typeToAdd == "bible-qna-fill") {
                    $reorderedQuestions[] = $bibleFillIn[$bibleFillInIndex++];
                }
                else if ($typeToAdd == "commentary-qna") {
                    $reorderedQuestions[] = $commentaryQnA[$commentaryIndex++];
                }
                else if ($typeToAdd == "commentary-qna-fill") {
                    $reorderedQuestions[] = $commentaryFillIn[$commentaryFillInIndex++];
                }
            }
            $output["questions"] = $reorderedQuestions;
        }
        else {
            // random output! shuffle 2x (2x because 1x is boring)
            shuffle($output["questions"]);
            shuffle($output["questions"]);
        }
        $generated = $output;
        //print_r($output);
        if ($DEBUG) {
            $thingsUsed = [];
            foreach ($output["questions"] as $question) {
                if (str_contains("bible", $question["type"])) {
                    $key = $question["startBook"] . " " . $question["startChapter"];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                }
                else {
                    $key = "Commentary " . $question["volume"] . " - " . $question["topic"];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                }
            }
            foreach ($thingsUsed as $key => $value) {
                echo $key . " --- " . $value . "\n";
            }
            die();
        }
    }
    else {
        $generated = generate_quiz_questions($pdo, $_POST);
    }
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($generated);
?>