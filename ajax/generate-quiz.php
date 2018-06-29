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
        foreach($bibleWeights as $key => $value) {
            $totalPercent += (int)$value;
        }
        foreach($commentaryWeights as $key => $value) {
            $totalPercent += (int)$value;
        }
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
                }
                $generatedQuestions = generate_quiz_questions($pdo, $postCopy);
                $allGenerated[] = $generatedQuestions;
                $totalGenerated += (int)$generatedQuestions["totalQuestions"];
                // we don't want to generate questions for this chapter again
                unset($quizItems[$i]);
            }
        }
        echo "-------" . "\n";
        echo $totalGenerated . " questions generated \n";
        echo "-------" . "\n";
        // OK, now that we have generated questions for all sections with specific
        // weights, generate as many questions as possible for the remaining sections
        $questionsLeft = $maxQuestions - $totalGenerated;
        echo "There are " . $questionsLeft .  " questions left to generate \n";
        
        // need to know how to pull out questions and sort questions at the end.
        $questionOrder = $_POST["questionOrder"];
        $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
        $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";

        if ($questionsLeft > 0) {
            $numberOfQuestionsForEachPortion = round($questionsLeft / count($quizItems));
            echo "There are " . $numberOfQuestionsForEachPortion .  " questions for each item to generate \n";

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
                    }
                    // else we just flat out don't have enough questions. Sorry.
                    // don't have to worry about random selection or in order selection as we are choosing all of them,
                    // so just add these questions to the overall output.
                    $totalGenerated += $item["totalQuestions"];
                    $allGenerated[] = $item;
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
                echo "There are " . $questionsLeft .  " questions left to generate \n";
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
        echo "-------" . "\n";
        echo "After all done, " . $totalGenerated . " questions generated \n";
        echo "After all done, " . count($output["questions"]) . " questions generated \n";
        echo "There are " . $questionsLeft .  " questions left to generate \n";
        echo "-------" . "\n";
        die();
        // ok, everything is generated. However, now we need to resort 
        // everything so that the output is what the user expects!
        // TODO: see quiz func in functions.php and refactor to avoid duplicate code
        // if random output, shuffle questions array 2x (2x just for fun) and be done with it
        // if sequential output, need to sort first then grab questions in sequential order
        // like we do in the quiz engine

        $generated = $allGenerated;
    }
    else {
        $generated = generate_quiz_questions($pdo, $_POST);
    }
    
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($generated);
?>