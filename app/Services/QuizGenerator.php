<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Language;
use App\Models\NonBlankableWord;
use App\Models\Question;
use App\Models\Util;
use PDO;

use App\Models\Year;

class QuizGenerator
{
    // TODO: generate objects instead of key/value arrays...
    public static function generateQuiz(
        Year $year,
        bool $shouldAvoidPastCorrectAnswers,
        int $maxQuestions,
        int $maxPoints,
        int $fillInPercent, // defaults to 30
        string $questionTypes, // qa-only, fill-in-only, or both
        string $questionOrder, // sequential-sequential, random-sequential, or random-random
        bool $showOnlyRecentOnFlashCards,
        int $showOnlyRecentOnFlashCardsAmount,
        int $languageID,
        int $userID,
        array $itemsForQuiz,
        PDO $db,
        int $intPercentOfQuestionsFillInTheBlank = 10 // should be >= 0 and <= 100
        )
    {
        if ($maxQuestions > 500) {
            $maxQuestions = 500;
        } else if ($maxQuestions <= 0) {
            $maxQuestions = 10;
        }
        if ($maxPoints > 500) {
            $maxPoints = 500;
        } else if ($maxPoints <= 0) {
            $maxPoints = 1;
        }
        
        $percentBlanksInFillIn = isset($fillInPercent) ? filter_var($fillInPercent, FILTER_VALIDATE_INT) : 30;
        $percentBlanksInFillIn = $percentBlanksInFillIn / 100.0;
        // $floatPercentOfFIBQuestionsInOutput >= 0 && <= 1
        $floatPercentOfFIBQuestionsInOutput = $intPercentOfQuestionsFillInTheBlank / 100.0;

        $shouldShowOnlyRecentlyAdded = isset($showOnlyRecentOnFlashCards) ? filter_var($showOnlyRecentOnFlashCards, FILTER_VALIDATE_BOOLEAN) : false;
        $recentlyAddedAmount = isset($showOnlyRecentOnFlashCardsAmount) ? filter_var($showOnlyRecentOnFlashCardsAmount, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 30,
            'min_range' => 1,
            'max_range' => 31
        ))) : 30;

        $languagesByID = Language::loadAllLanguagesByID($db);

        // question type values:
        // both
        // qa-only
        // fill-in-only
        if ($shouldShowOnlyRecentlyAdded) { // override all user settings and load recent questions instead
            $questionTypes = 'both';
            $questionOrder = 'sequential-sequential';
            unset($itemsForQuiz);
            $shouldAvoidPastCorrectAnswers = false;
            $recentDayAmount = date('Y-m-d 00:00:00', strtotime('-' . $recentlyAddedAmount . ' days'));
        }
        $userWantsNormalQuestions = $questionTypes === 'qa-only' || $questionTypes === 'both';
        $userWantsFillIn = $questionTypes === 'fill-in-only' || $questionTypes === 'both';
        $areRandomQuestionsPulled = $questionOrder == 'random-sequential' || $questionOrder == 'random-random';
        $isOutputSequential = $questionOrder == 'random-sequential' || $questionOrder == 'sequential-sequential';
        if ($questionTypes === 'fill-in-only') {
            $floatPercentOfFIBQuestionsInOutput = 1;
        } else if ($questionTypes === 'qa-only') {
            $floatPercentOfFIBQuestionsInOutput = 0;
        }

        // see if user wants to load any possible question or just from a specific chapter of the Bible (or Bible commentary volume)
        if (!isset($itemsForQuiz)) {
            $quizItems = [];
        } else {
            $quizItems = $itemsForQuiz;
        }
        $chapterIDs = [];
        $commentaryIDs = [];
        if (count($quizItems) > 0) {
            // user wants to load specific things!
            // figure out which chapter IDs and volume numbers they want to load
            foreach ($quizItems as $item) {
                if (strpos($item, 'chapter-') !== false) {
                    $text = str_replace('chapter-', '', $item);
                    $chapterIDs[] = (int)$text;
                } else if (strpos($item, 'commentary-') !== false) {
                    $text = str_replace('commentary-', '', $item);
                    $commentaryIDs[] = (int)$text;
                }
            }
        }
        $shouldLoadBibleQnA = (count($quizItems) == 0 || count($chapterIDs) > 0) && $userWantsNormalQuestions;
        $shouldLoadCommentaryQnA = (count($quizItems) == 0 || count($commentaryIDs) > 0) && $userWantsNormalQuestions;
        $disableBibleFillInLoading = false;
        $disableCommentaryFillInLoading = false;
        if (count($chapterIDs) > 0 && count($commentaryIDs) == 0) {
            $shouldLoadCommentaryQnA = false;
            $disableCommentaryFillInLoading = true;
        }
        if (count($commentaryIDs) > 0 && count($chapterIDs) == 0) {
            $shouldLoadBibleQnA = false;
            $disableBibleFillInLoading = true;
        }
        // // // // //
        // load Bible questions
        // // // // //
        $currentYear = $year->yearID;
        $bibleQnA = [];
        $selectPortion = '
            SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
                bStart.Name AS StartBook, bStart.BibleOrder AS StartBibleOrder, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                bEnd.Name AS EndBook, bEnd.BibleOrder AS EndBibleOrder, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                IFnull(uf.UserFlaggedID, 0) AS IsFlagged, q.LanguageID ';
        $fromPortion = '
            FROM Questions q 
                JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
                JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
                JOIN Books bStart ON bStart.BookID = cStart.BookID
                JOIN Languages l ON q.LanguageID = l.LanguageID

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
            $whereClause .= '  AND (ua.UserAnswerID IS null 
                OR (ua.UserAnswerID IS NOT null AND ua.WasCorrect = 0 AND ua.UserID = ' . $userID . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "bible-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }

        $whereClause .= ' AND IsDeleted = 0 AND bStart.YearID = ' . $currentYear . ' AND (q.EndVerseID IS null OR bEnd.YearID = ' . $currentYear . ')';

        if ($languageID != -1) {
            $whereClause .= ' AND l.LanguageID = ' . $languageID;
        }

        $orderByPortion = '';
        if ($areRandomQuestionsPulled) {
            $orderByPortion = ' ORDER BY RAND() ';
        } else {
            // sequential-sequential
            $orderByPortion = '
                ORDER BY bStart.BibleOrder, cStart.Number, vStart.Number, bEnd.BibleOrder, bEnd.Name, cEnd.Number, vEnd.Number';
        }

        $limitPortion = ' LIMIT ' . $maxQuestions;
        if ($shouldLoadBibleQnA) {
            $stmt = $db->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $bibleQnA = $stmt->fetchAll();
        }
        // // // // //
        // load Bible fill in the blank questions
        // // // // //
        $bibleFillIn = [];
        $whereClause = str_replace('bible-qna', 'bible-qna-fill', $whereClause);
        if ($userWantsFillIn && !$disableBibleFillInLoading) {
            $stmt = $db->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $bibleFillIn = $stmt->fetchAll();
        }

        // // // // //
        // load commentary questions
        // // // // //
        $commentaryQnA = [];
        $selectPortion = '
            SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
                IFnull(uf.UserFlaggedID, 0) AS IsFlagged,
                comm.Number AS CommentaryNumber, CommentaryStartPage, CommentaryEndPage, comm.TopicName AS CommentaryTopic,
                q.LanguageID ';
        $fromPortion = '
            FROM Questions q 
                LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID
                JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID
                JOIN Languages l ON q.LanguageID = l.LanguageID';
        if ($shouldAvoidPastCorrectAnswers) {
            $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
        }
        $whereClause = ' 
            WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "commentary-qna"';
        if (count($commentaryIDs) > 0) {
            $whereClause .= ' AND comm.CommentaryID IN (' . implode(',', $commentaryIDs) . ') ';
        }
        if ($shouldAvoidPastCorrectAnswers) {
            $whereClause .= '  AND (ua.UserAnswerID IS null 
                OR (ua.UserAnswerID IS NOT null AND ua.WasCorrect = 0 AND ua.UserID = ' . $userID . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "commentary-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }
        $whereClause .= ' AND IsDeleted = 0 AND comm.YearID = ' . $currentYear;

        if ($languageID != -1) {
            $whereClause .= " AND l.LanguageID = " . $languageID;
        }

        if (!$areRandomQuestionsPulled) {
            $orderByPortion = ' ORDER BY CommentaryNumber, CommentaryStartPage, CommentaryEndPage';
        }
        if ($shouldLoadCommentaryQnA) {
            $stmt = $db->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $commentaryQnA = $stmt->fetchAll();
        }
        // // // // //
        // load commentary fill in the blank questions
        // // // // //
        $commentaryFillIn = [];
        $whereClause = str_replace('commentary-qna', 'commentary-qna-fill', $whereClause);
        if ($userWantsFillIn && !$disableCommentaryFillInLoading) {
            $stmt = $db->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $commentaryFillIn = $stmt->fetchAll();
        }
        // // // // //
        // Merge data as needed to get final quiz
        // // // // //
        
        // Generate final questions array using data we've pulled out of the database
        $output = [];
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
        $numberFillInTheBlankUsed = 0;
        $maxFIBQuestions = ceil($maxQuestions * $floatPercentOfFIBQuestionsInOutput);
        //$totalQuestionsAvailable = $bibleCount + $bibleFillInCount + $commentaryCount + $commentaryFillInCount;
        //echo 'total B: ' . $bibleCount . '<br>';
        //echo 'total B FIB: ' . $bibleFillInCount . '<br>';
        //echo 'total C: ' . $commentaryCount . '<br>';
        //echo 'total CFIB: ' . $commentaryFillInCount . '<br>';
        //echo 'total ALL: ' . $totalQuestionsAvailable . '<br>';
        //die('out of ' . $maxQuestions . ' we are aiming for ' . $maxFIBQuestions . ' fib questions which is ' .($maxFIBQuestions / $maxQuestions));
        for ($i = 0; $i < $maxQuestions; $i++) {
            $canContinueToUseFIB = $numberFillInTheBlankUsed < $maxFIBQuestions;
            $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
            $hasBibleFillInLeft = 
                $bibleFillInIndex < $bibleFillInCount && $canContinueToUseFIB;
            $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
            $hasCommentaryFillInQuestionLeft = 
                $commentaryFillInIndex < $commentaryFillInCount && $canContinueToUseFIB;
            
            if (!$hasBibleQuestionLeft && !$hasCommentaryQuestionLeft &&
                !$canContinueToUseFIB && $userWantsFillIn) {
                // if we've run out of questions by hitting the max FIB limit BUT
                // we still need more questions, allow FIB to continue to be used 
                // so user gets the # of questions they want
                $canContinueToUseFIB = true;
                $hasBibleFillInLeft = $bibleFillInIndex < $bibleFillInCount;
                $hasCommentaryFillInQuestionLeft = $commentaryFillInIndex < $commentaryFillInCount;
            }

            if (!$hasBibleQuestionLeft && !$hasCommentaryQuestionLeft && 
                !$hasBibleFillInLeft && !$hasCommentaryFillInQuestionLeft) {
                break; // ran out of questions!
            }
            // figure out which arrays have stuff left
            $availableArraysOfQuestions = [];
            if ($hasBibleQuestionLeft) {
                $availableArraysOfQuestions[] = Question::getBibleQnAType();
            }
            if ($hasBibleFillInLeft && $canContinueToUseFIB) {
                $availableArraysOfQuestions[] = Question::getBibleQnAFillType();
            }
            if ($hasCommentaryQuestionLeft) {
                $availableArraysOfQuestions[] = Question::getCommentaryQnAType();
            }
            if ($hasCommentaryFillInQuestionLeft && $canContinueToUseFIB) {
                $availableArraysOfQuestions[] = Question::getCommentaryQnAFillType();
            }
            // now choose one
            $index = random_int(0, count($availableArraysOfQuestions) - 1);
            $typeToAdd = $availableArraysOfQuestions[$index];
            // add the question to the output
            if ($typeToAdd == Question::getBibleQnAType()) {
                $output[] = $bibleQnA[$bibleIndex];
                $bibleIndex++;
                $bibleAdded++;
            } else if ($typeToAdd == Question::getBibleQnAFillType()) {
                $output[] = $bibleFillIn[$bibleFillInIndex];
                $bibleFillInIndex++;
                $bibleFillInAdded++;
                $numberFillInTheBlankUsed++;
            } else if ($typeToAdd == Question::getCommentaryQnAType()) {
                $output[] = $commentaryQnA[$commentaryIndex];
                $commentaryIndex++;
                $commentaryAdded++;
            } else if ($typeToAdd == Question::getCommentaryQnAFillType()) {
                $output[] = $commentaryFillIn[$commentaryFillInIndex];
                $commentaryFillInIndex++;
                $commentaryFillInAdded++;
                $numberFillInTheBlankUsed++;
            }
        }
        // set questions to output of this little merging algorithm
        $questions = $output;
        
        // TODO: sort/merge with fill in the blank questions?
        // load non-blankable words
        $words = NonBlankableWord::loadAllBlankableWords($db);
        // Generate output
        $outputQuestions = [];
        $number = 1;
        foreach ($questions as $question) {
            $data = array (
                'type' => $question['Type'],
                'number' => $number,
                'id' => $question['QuestionID'],
                'isFlagged' => $question['IsFlagged'],
                'points' => $question['NumberPoints'],
                'question' => trim($question['Question']),
                'answer' => trim($question['Answer']),
                //
                'volume' => -1, // probably not right; used for sequential sorting at the end
                'topic' => '',
                'startPage' => -1,
                'endPage' => -1,
                'language' => isset($languagesByID[$question['LanguageID']]) 
                    ? $languagesByID[$question['LanguageID']]
                    : null
            );
            if (Question::isTypeBibleQnA($question['Type'])) {
                // Bible Q&A
                $data['startBook'] = $question['StartBook'] ?? '';
                $data['startBibleOrder'] = $question['StartBibleOrder'] ?? '';
                $data['startChapter'] = $question['StartChapter'] ?? '';
                $data['startVerse'] = $question['StartVerse'] ?? '';
                $data['endBibleOrder'] = $question['EndBibleOrder'] ?? '';
                $data['endBook'] = $question['EndBook'] ?? '';
                $data['endChapter'] = $question['EndChapter'] ?? '';
                $data['endVerse'] = $question['EndVerse'] ?? '';
            } else if (Question::isTypeCommentaryQnA($question['Type'])) {
                // commentary Q&A
                $data['volume'] = $question['CommentaryNumber'];
                $data['topic'] = $question['CommentaryTopic'];
                $data['startPage'] = $question['CommentaryStartPage'];
                $data['endPage'] = $question['CommentaryEndPage'];
            }
            if (Question::isTypeFillIn($question['Type'])) {
                $fillInData = NonBlankableWord::generateFillInQuestion(trim($question['Question']), $percentBlanksInFillIn, $words);
                $data['fillInData'] = $fillInData['data'];
                $data['points'] = $fillInData['blank-count'];
            }
            // for fill in the blank, will have text/blank key/value pairs
            $outputQuestions[] = $data;
            $number++;
        }
        if ($isOutputSequential) {
            $outputQuestions = self::sortQuestionsSequentially($outputQuestions, $db);
        }

        $output = [ 
            'bibleQuestions' => $bibleAdded,
            'bibleFillIns' => $bibleFillInAdded,
            'commentaryQuestions' => $commentaryAdded,
            'commentaryFillIns' => $commentaryFillInAdded,
            'totalQuestions' => ($bibleAdded + $bibleFillInAdded + $commentaryAdded + $commentaryFillInAdded),
            'questions' => $outputQuestions 
        ];

        return $output;
    }

    public static function generateWeightedQuiz(
        Year $year,
        bool $shouldAvoidPastCorrectAnswers,
        int $maxQuestions,
        int $maxPoints,
        int $fillInPercent, // defaults to 30
        string $questionTypes, // qa-only, fill-in-only, or both
        string $questionOrder, // sequential-sequential, random-sequential, or random-random
        bool $showOnlyRecentOnFlashCards,
        int $showOnlyRecentOnFlashCardsAmount,
        int $languageID,
        int $userID,
        array $bibleWeights, // key/value pairs: chapter ID -> weight; post['table-input-chapter-{}']
        array $commentaryWeights, // key/value pairs: commentary ID -> weight; post['table-input-commentary-{}']
        array $quizItems, // array of ; post['quiz-items']
        PDO $db,
        int $intPercentOfQuestionsFillInTheBlank = 10 // should be >= 0 and <= 100
    )
    {
        $DEBUG = false;
        // performing custom question distribution!
        //$bibleWeights = [];
        //$commentaryWeights = [];
        $allWeights = [];
        /*foreach ($params as $key => $value) {
            if (str_contains("table-input-chapter-", $key)) {
                $bibleWeights[$key] = $value;
                $allWeights[$key] = $value;
            } else if (str_contains("table-input-commentary-", $key)) {
                $commentaryWeights[$key] = $value;
                $allWeights[$key] = $value;
            }
        }*/
        // Make sure submitted percentage not below 0 or above 100
        $totalPercent = 0;
        $hasNegativePercent = false;
        foreach ($bibleWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
            $allWeights[$key] = $value;
        }
        foreach ($commentaryWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
            $allWeights[$key] = $value;
        }
        if ($hasNegativePercent) { // TODO: return error of some kind instead of dying
            die('Invalid weighted question percent given. All percents must be above positive or 0.');
        }
        if ($totalPercent < 0 || $totalPercent > 100) {
            die('Invalid weighted question percent given. Value must be between 0 and 100 inclusive.');
        }
        //print_r($params["questionTypes"]);
        // // // //
        // For each quizItems item that has a specific weight set in bibleWeights/commentaryWeights,
        // generate questions.
        $allGenerated = [];
        $totalGenerated = 0;
        // get rid of values we will override in the loop
        //unset($postCopy["quizItems"]);
        //unset($postCopy["maxQuestions"]);
        // first generate questions for those sections that have weights
        for ($i = 0; $i < count($quizItems); $i++) {
            $quizItemsForQuizGeneration = [];
            $maxQuestionsForQuizGeneration = 0;
            $quizItem = $quizItems[$i];
            $allWeightsKey = 'table-input-' . $quizItem;
            if (isset($allWeights[$allWeightsKey]) && (int)$allWeights[$allWeightsKey] > 0) {
                $quizItemsForQuizGeneration = [ $quizItem ];
                $quizItemCount = count($quizItems); // count every loop iteration due to the unset a few lines down
                if ($i == $quizItemCount - 1 && $quizItemCount == 1) {
                    // if we only have 1 thing left with our weighting system and
                    // this thing is weighted, try to get as many questions out of it as possible
                    $maxQuestionsForQuizGeneration = $maxQuestions - $totalGenerated;
                }
                else {
                    $maxQuestionsForQuizGeneration = floor($maxQuestions * ((int)$allWeights[$allWeightsKey] / 100));
                    if ($maxQuestionsForQuizGeneration == 0) {
                        $maxQuestionsForQuizGeneration = 1;
                    }
                    if ($maxQuestionsForQuizGeneration == 1 && $maxQuestionsForQuizGeneration + $totalGenerated > $maxQuestions) {
                        break;
                    }
                }
                $generatedQuestions = QuizGenerator::generateQuiz(
                    $year,
                    $shouldAvoidPastCorrectAnswers,
                    $maxQuestionsForQuizGeneration,
                    $maxPoints,
                    $fillInPercent,
                    $questionTypes,
                    $questionOrder,
                    $showOnlyRecentOnFlashCards,
                    $showOnlyRecentOnFlashCardsAmount,
                    $languageID,
                    $userID,
                    $quizItemsForQuizGeneration,
                    $db,
                    $intPercentOfQuestionsFillInTheBlank
                );
                $allGenerated[] = $generatedQuestions;
                $totalGenerated += (int)$generatedQuestions['totalQuestions'];
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
        $areRandomQuestionsPulled = $questionOrder == 'random-sequential' || $questionOrder == 'random-random';
        $isOutputSequential = $questionOrder == 'random-sequential' || $questionOrder == 'sequential-sequential';

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
                $generatedQuestions = QuizGenerator::generateQuiz(
                    $year,
                    $shouldAvoidPastCorrectAnswers,
                    $questionsLeft,
                    $maxPoints,
                    $fillInPercent,
                    $questionTypes,
                    $questionOrder,
                    $showOnlyRecentOnFlashCards,
                    $showOnlyRecentOnFlashCardsAmount,
                    $languageID,
                    $userID,
                    [ $quizItem ],
                    $db,
                    $intPercentOfQuestionsFillInTheBlank
                );
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
                if ($item['totalQuestions'] < $numberOfQuestionsForEachPortion) {
                    // we can't get enough questions out of this item. we'll have to adjust
                    // $numberOfQuestionsForEachPortion to account for this
                    $questionsLeft -= $item['totalQuestions'];
                    if ($i != count($otherGenerated) - 1) {
                        $numberOfQuestionsForEachPortion = round($questionsLeft / (count($quizItems) - ($i + 1)));
                        if ($numberOfQuestionsForEachPortion == 0) {
                            $numberOfQuestionsForEachPortion = 1;
                        }
                    }
                    // else we just flat out don't have enough questions. Sorry.
                    // don't have to worry about random selection or in order selection as we are choosing all of them,
                    // so just add these questions to the overall output.
                    $totalGenerated += $item['totalQuestions'];
                    $allGenerated[] = $item;
                    //echo "Added " . $item["totalQuestions"] . " when I did not have enough\n";
                }
                else {
                    // we have enough to fulfill our needs!
                    // pick out $numberOfQuestionsForEachPortion questions
                    $pickedQuestions = [];
                    if ($areRandomQuestionsPulled) {
                        shuffle($item['questions']);
                        $pickedQuestions = array_slice($item['questions'], 0, $numberOfQuestionsForEachPortion);
                    }
                    else {
                        // pick the first $numberOfQuestionsForEachPortion out of the questions array
                        // as they are already in sequential order
                        $pickedQuestions = array_slice($item['questions'], 0, $numberOfQuestionsForEachPortion);
                    }
                    $questionsLeft -= $numberOfQuestionsForEachPortion;
                    $totalGenerated += $numberOfQuestionsForEachPortion;
                    $bibleQuestions = 0;
                    $bibleFillIns = 0;
                    $commentaryQuestions = 0;
                    $commentaryFillIns = 0;
                    foreach ($pickedQuestions as $question) {
                        if ($question['type'] == 'bible-qna') {
                            $bibleQuestions++;
                        }
                        else if ($question['type'] == 'bible-qna-fill') {
                            $bibleFillIns++;
                        }
                        else if ($question['type'] == 'commentary-qna') {
                            $commentaryQuestions++;
                        }
                        else if ($question['type'] == 'commentary-qna-fill') {
                            $commentaryFillIns++;
                        }
                    }
                    $allGenerated[] = [
                        'bibleQuestions' => $bibleQuestions,
                        'bibleFillIns' => $bibleFillIns,
                        'commentaryQuestions' => $commentaryQuestions,
                        'commentaryFillIns' => $commentaryFillIns,
                        'totalQuestions' => $numberOfQuestionsForEachPortion,
                        'questions' => $pickedQuestions
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
            'bibleQuestions' => 0,
            'bibleFillIns' => 0,
            'commentaryQuestions' => 0,
            'commentaryFillIns' => 0,
            'totalQuestions' => 0,
            'questions' => []
        ];
        foreach ($allGenerated as $item) {
            $output['bibleQuestions'] += (int)$item['bibleQuestions'];
            $output['bibleFillIns'] += (int)$item['bibleFillIns'];
            $output['commentaryQuestions'] += (int)$item['commentaryQuestions'];
            $output['commentaryFillIns'] += (int)$item['commentaryFillIns'];
            $output['totalQuestions'] += (int)$item['totalQuestions'];
            $output['questions'] = array_merge($output['questions'], $item['questions']);
        }
        //echo "-------" . "\n";
        //echo "After all done, " . $totalGenerated . " questions generated \n";
        //echo "After all done, " . count($output['questions']) . " questions generated \n";
        //echo "There are " . $questionsLeft .  " questions left to generate \n";
        //echo "-------" . "\n";
        
        // ok, everything is generated. However, now we need to resort 
        // everything so that the output is what the user expects!

        if ($isOutputSequential) {
            $output['questions'] = self::sortQuestionsSequentially($output['questions'], $db);
        } else {
            // random output! shuffle 2x (2x because 1x is boring)
            shuffle($output['questions']);
            shuffle($output['questions']);
        }
        //print_r($output);
        if ($DEBUG) {
            $thingsUsed = [];
            foreach ($output['questions'] as $question) {
                if (Util::str_contains('bible', $question['type'])) {
                    $key = $question['startBook'] . ' ' . $question['startChapter'];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                } else {
                    $key = 'Commentary ' . $question['volume'] . ' - ' . $question['topic'];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                }
            }
            foreach ($thingsUsed as $key => $value) {
                echo $key . ' --- ' . $value . "\n";
            }
            die();
        }
        return $output;
    }

    private static function sortQuestionsSequentially(array $questions, PDO $db): array
    {
        // have to break questions out into each kind and then sort and then reorder them
        static $allBooks = null;
        if ($allBooks === null) { // 1x load
            $allBooks = Book::loadAllBooks($db);
        }
        $bibleQs = [];
        $commentaryQs = [];
        foreach ($questions as $question) {
            if (Question::isTypeBibleQnA($question['type'])) {
                $bibleQs[] = $question;
            } else {
                $commentaryQs[] = $question;
            }
        }
        // sort Bible questions, then insert commentary questions randomly into the Bible questions
        // to get a final sorted output.
        // usort with comparisons between Bible and commentary questions didn't work (probably due to
        // things getting compared improperly)
        usort($bibleQs, function(array $qa, array $qb) use ($allBooks) {
            $comparisonKeys = ['startBibleOrder', 'startChapter', 'startVerse', 'endBibleOrder', 'endChapter', 'endVerse'];
            foreach ($comparisonKeys as $key) {
                if ($qa[$key] < $qb[$key]) {
                    return -1;
                } else if ($qb[$key] < $qa[$key]) {
                    return 1;
                }
            }
            return 0;
        });
        usort($commentaryQs, function(array $qa, array $qb) use ($allBooks) {
            $comparisonKeys = ['number', 'topic', 'startPage', 'endPage'];
            foreach ($comparisonKeys as $key) {
                if ($qa[$key] < $qb[$key]) {
                    return -1;
                } else if ($qb[$key] < $qa[$key]) {
                    return 1;
                }
            }
            // if we get here, questions are for the same books.
            return 0;
        });
        // short-circuit more work if not needed to merge the two arrays
        if (count($bibleQs) === 0) {
            return $commentaryQs;
        } else if (count($commentaryQs) === 0) {
            return $bibleQs;
        }

        // separate out questions by book (book sort order)
        $bibleQsBySortOrder = [];
        foreach ($bibleQs as $bibleQ) {
            if (!isset($bibleQsBySortOrder[$bibleQ['startBibleOrder']])) {
                $bibleQsBySortOrder[$bibleQ['startBibleOrder']] = [];
            }
            $bibleQsBySortOrder[$bibleQ['startBibleOrder']][] = $bibleQ;
        }
        $commentaryQsBySortOrder = [];
        foreach ($commentaryQs as $commentaryQ) {
            $commentarySortOrder = 99;// doesn't match Bible book? comes last after all Bible questions
            $topic = mb_strtolower($commentaryQ['topic']);
            foreach ($allBooks as $book) {
                if (mb_strtolower($book->name) == $topic) {
                    $commentarySortOrder = $book->bibleOrder;
                    break;
                }
            }
            if (!isset($commentaryQsBySortOrder[$commentarySortOrder])) {
                $commentaryQsBySortOrder[$commentarySortOrder] = [];
            }
            $commentaryQsBySortOrder[$commentarySortOrder][] = $commentaryQ;
        }

        // get all sort orders
        $sortOrders = array_unique(array_merge(array_keys($bibleQsBySortOrder), array_keys($commentaryQsBySortOrder)));
        sort($sortOrders); // put in numerical order
        // now pull in questions
        $output = [];
        foreach ($sortOrders as $sortOrder) {
            if (isset($commentaryQsBySortOrder[$sortOrder])) {
                // make the order of commentary questions random
                shuffle($commentaryQsBySortOrder[$sortOrder]);
            }
            $bIndex = 0;
            $cIndex = 0;
            $bCount = isset($bibleQsBySortOrder[$sortOrder]) ? count($bibleQsBySortOrder[$sortOrder]) : 0;
            $cCount = isset($commentaryQsBySortOrder[$sortOrder]) ? count($commentaryQsBySortOrder[$sortOrder]) : 0;
            $totalCount = $bCount + $cCount;
            for ($i = 0; $i < $totalCount; $i++) {
                $hasBibleQuestionLeft = $bIndex < $bCount;
                $hasCommentaryQuestionLeft = $cIndex < $cCount;
                $availableArraysOfQuestions = [];
                if ($hasBibleQuestionLeft) {
                    $availableArraysOfQuestions[] = 'B';
                }
                if ($hasCommentaryQuestionLeft) {
                    $availableArraysOfQuestions[] = 'C';
                }
                //echo 'on sort order ' . $sortOrder . ', out of ' . $totalCount . ', we are at bindex of ' . $bIndex . ' of ' . $bCount . ' and cindex of ' . $cIndex . ' of ' . $cCount . '<br>';
                if ($hasBibleQuestionLeft && $hasCommentaryQuestionLeft) {
                    // 5% chance of commentary question -- this helps distribute them somewhat more evenly 
                    // since generally speaking there are fewer commentary questions than Bible questions
                    $randomInt = random_int(0, 100);
                    if ($randomInt <= 5) {
                        $output[] = $commentaryQsBySortOrder[$sortOrder][$cIndex++];
                    } else {
                        $output[] = $bibleQsBySortOrder[$sortOrder][$bIndex++];
                    }
                } else if ($hasBibleQuestionLeft) {
                    $output[] = $bibleQsBySortOrder[$sortOrder][$bIndex++];
                } else {
                    $output[] = $commentaryQsBySortOrder[$sortOrder][$cIndex++];
                }
            }
        }
        //foreach ($output as $question) {
        //    if (Question::isTypeBibleQnA($question['type'])) {
        //        echo $question['startBook'] . ' ' . $question['startChapter'] . ':' . $question['startVerse'] . '<br>';
        //    } else {
        //        echo $question['topic'] . '<br>';
        //    }
        //}
        //die();
        return $output;
    }
}
