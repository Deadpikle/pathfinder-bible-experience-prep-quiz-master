<?php

namespace App\Controllers\User;

use App\Models\BibleFillInData;
use Yamf\Request;
use Yamf\Responses\ErrorMessage;
use Yamf\Responses\JsonResponse;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Commentary;
use App\Models\FlagReason;
use App\Models\Language;
use App\Models\MatchingQuestionItem;
use App\Models\MatchingQuestionSet;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserFlagged;
use App\Models\Util;
use App\Models\Views\JsonStatusCodeResponse;
use App\Models\Views\TwigView;
use App\Models\Year;
use App\Services\PDFGenerator;
use App\Services\PowerPointGenerator;
use App\Services\QuizGenerator;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use Yamf\Responses\Response;

class QuizController
{
    public function setupQuiz(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/');
        }
        $currentYear = Year::loadCurrentYear($app->db);
        $commentaries = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db); // TODO: need to only load ones with active questions!
        $languages = Language::loadAllLanguages($app->db);
        $userLanguage = Language::findLanguageWithID($_SESSION['PreferredLanguageID'], $languages);

        $books = Book::loadBooksForYear($currentYear, $app->db);
        if ($app->isGuest) {
            if (count($books) > 0) {
                $books = [$books[0]]; // guests only get first book
            }
            if (count($commentaries) > 0) {
                $commentaries = [$commentaries[0]]; // guests only get first commentary.
            }
        }
        $booksByBookID = [];
        foreach ($books as $book) {
            $booksByBookID[$book->bookID] = $book;
        }
        $chapters = Chapter::loadChaptersWithActiveQuestions($currentYear, $app->db);
        if ($app->isGuest) {
            // guests only get up to 2 chapters
            if (count($chapters) > 1) {
                $chapters = [$chapters[0], $chapters[1]];
            } else if (count($chapters) > 0) {
                $chapters = [$chapters[0]];
            }
        }

        return new TwigView('user/quiz/quiz-setup', compact('currentYear', 'languages', 'userLanguage', 'chapters', 'commentaries', 'booksByBookID'), 'Quiz Setup');
    }

    public function checkBeforeRemovingAnswers(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/');
        }
        return new TwigView('user/quiz/verify-delete-user-answers', null, 'Delete Answers');
    }

    public function removeAnswers(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/');
        }
        UserAnswer::deleteUserAnswers(User::currentUserID(), $app->db);
        return new Redirect('/quiz/setup');
    }

    private function getQuizQuestions(PBEAppConfig $app, Request $request, bool $isFlashCardQuiz, bool $isFrontBackFlashCards)
    {
        $enableQuestionDistribution = Util::validateBoolean($request->post, 'enable-question-distribution');
        $year = Year::loadCurrentYear($app->db);
        $userID = User::currentUserID();
        if ($app->isGuest && ($request->post['quiz-items'] === null || count($request->post['quiz-items']) === 0)) {
            $chapters = Chapter::loadChaptersWithActiveQuestions($year, $app->db);
            // guests only get up to 2 chapters
            if (count($chapters) > 1) {
                $request->post['quiz-items'] = [
                    'chapter-' . $chapters[0]->chapterID,
                    'chapter-' . $chapters[1]->chapterID
                ];
            } else if (count($chapters) > 0) {
                $request->post['quiz-items'] = [
                    'chapter-' . $chapters[0]->chapterID,
                ];
            }
        }
        if ($enableQuestionDistribution && 
            isset($request->post["quiz-items"]) && 
            count($request->post["quiz-items"]) > 0) {
            // ok, safe to do weighted question distribution
            $bibleWeights = [];
            $commentaryWeights = [];
            foreach ($request->post as $key => $value) {
                if (Util::str_contains("table-input-chapter-", $key)) {
                    $bibleWeights[$key] = $value;
                } else if (Util::str_contains("table-input-commentary-", $key)) {
                    $commentaryWeights[$key] = $value;
                }
            }
            $quizQuestions = QuizGenerator::generateWeightedQuiz(
                $year,
                $request->post['no-questions-answered-correct'] ?? false,
                $app->isGuest ? min($request->post['max-questions'], 100) : $request->post['max-questions'] ?? 30,
                $request->post['max-points'] ?? 10,
                $request->post['fill-in-percent'] ?? 30, // defaults to 30
                $request->post['question-types'], // qa-only, fill-in-only, or both
                $request->post['order'],
                !$isFlashCardQuiz ? false : Util::validateBoolean($request->post, 'flash-show-recently-added'), 
                !$isFlashCardQuiz ? 0 : ($request->post['flash-recently-added-days'] ?? 30), 
                $request->post['language-select'],
                $userID,
                $bibleWeights,
                $commentaryWeights,
                $request->post['quiz-items'] ?? [],
                $app->db
            );

        } else {
            $quizQuestions = QuizGenerator::generateQuiz(
                $year,
                $request->post['no-questions-answered-correct'] ?? false,
                $request->post['max-questions'],
                $request->post['max-points'],
                $request->post['fill-in-percent'] ?? 30, // defaults to 30
                $request->post['question-types'], // qa-only, fill-in-only, or both
                $request->post['order'],
                !$isFlashCardQuiz ? false : Util::validateBoolean($request->post, 'flash-show-recently-added'), 
                !$isFlashCardQuiz ? 0 : ($request->post['flash-recently-added-days'] ?? 30), 
                $request->post['language-select'],
                $userID,
                $request->post['quiz-items'] ?? [],
                $app->db
            );
        }
        return $quizQuestions;
    }

    public function takeQuiz(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/');
        }
        if (!isset($request->post["max-questions"])) {
            return new ErrorMessage("max-questions is required");
        }
        if (!isset($request->post["max-points"])) {
            return new ErrorMessage("max-points is required");
        }
        if (!isset($request->post["question-types"])) {
            return new ErrorMessage("question-types is required");
        }
        if (!isset($request->post["order"])) {
            return new ErrorMessage("order is required");
        }

        $quizQuestions = $this->getQuizQuestions($app, $request, false, false);
        $userID = User::currentUserID();
        $disableQuestionTimer = Util::validateBoolean($request->post, 'disable-question-timer');
        $disableAutoShowAnswer = Util::validateBoolean($request->post, 'autoshow-answer');
        $viewFillInTheBlankAnswersInBold = Util::validateBoolean($request->post, 'flash-full-fill-in');
        return new TwigView('user/quiz/take-quiz', compact('quizQuestions', 'userID', 'disableQuestionTimer', 'disableAutoShowAnswer', 'viewFillInTheBlankAnswersInBold'), 'Take Quiz');
    }

    public function generateLeftRightFlashCards(PBEAppConfig $app, Request $request)
    {
        // TODO: errors if not enough data sent
        $quizQuestions = $this->getQuizQuestions($app, $request, true, false);
        $viewFillInTheBlankAnswersInBold = Util::validateBoolean($request->post, 'flash-full-fill-in');
        $pdf = PDFGenerator::generatePDF($quizQuestions, false, $viewFillInTheBlankAnswersInBold);
        $pdf->Output();
    }

    public function generateFrontBackFlashCards(PBEAppConfig $app, Request $request)
    {
        // TODO: errors if not enough data sent
        $viewFillInTheBlankAnswersInBold = Util::validateBoolean($request->post, 'flash-full-fill-in');
        $quizQuestions = $this->getQuizQuestions($app, $request, true, false);
        $pdf = PDFGenerator::generatePDF($quizQuestions, true, $viewFillInTheBlankAnswersInBold);
        $pdf->Output();
    }

    public function generatePresentation(PBEAppConfig $app, Request $request)
    {
        $quizQuestionData = $this->getQuizQuestions($app, $request, true, false);
        $viewFillInTheBlankAnswersInBold = Util::validateBoolean($request->post, 'flash-full-fill-in');
        $generator = new PowerPointGenerator();
        $generator->outputPowerPoint($quizQuestionData, $viewFillInTheBlankAnswersInBold);
        return new Response(200);
    }

    public function saveQuizAnswers(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }

        $answers = $request->post['answers'] ?? [];
        $didSave = UserAnswer::saveUserAnswers($answers, $app->db);
        return new JsonResponse(['status' => $didSave ? 200 : 400]);
    }

    public function flagQuestion(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }
        $userID = User::currentUserID();
        $flagReason =FlagReason::validateReason(Util::validateString($request->post, 'reason'));
        $hasFlagged = UserFlagged::addFlagIfNecessary($request->post['questionID'], $userID, $flagReason, $app->db);
        return new JsonResponse(['status' => $hasFlagged ? 200 : 400]);
    }

    public function viewMatchingQuizPage(PBEAppConfig $app, Request $request): Response
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }
        $currentYear = Year::loadCurrentYear($app->db);
        $questionSets = MatchingQuestionSet::loadAllMatchingSetsForYear($currentYear->yearID, $app->db);

        // other sets, dynamically generated
        $fillInData = BibleFillInData::loadFillInData(Year::loadCurrentYear($app->db), $app->db);

        return new TwigView('user/quiz/matching-quiz', compact('currentYear', 'questionSets', 'fillInData'), 'Matching Quiz');
    }

    public function generateMatchingQuiz(PBEAppConfig $app, Request $request): Response
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }
        $questionSetData = Util::validateString($request->post, 'questionSet');
        $parts = explode('|', $questionSetData);
        if (count($parts) < 2) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Invalid question set information'], 400);            
        }
        $numQuestions = Util::validateInteger($request->post, 'numberQuestions');
        $numSets = Util::validateInteger($request->post, 'numberSets');
        if ($numQuestions < 1) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Number of questions must be greater than 1'], 404);
        }
        if ($parts[0] === 'set') {
            $questionSet = MatchingQuestionSet::loadMatchingSetByID(intval($parts[1]), $app->db);
        } else if ($parts[0] === 'fill') {
            if (count($parts) !== 3) {
                return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Invalid question set information'], 400);            
            }
            // create based on chapter ID
            $chapterID = intval($parts[1]);
            $languageID = intval($parts[2]);
            $language = Language::loadLanguageWithID($languageID, $app->db);
            if ($language === null) {
                return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Language not found'], 404);
            }
            $questions = Question::loadMatchingFillInQuestionsForChapterAndLanguage($chapterID, $language->languageID, $app->db);
            $questionSet = new MatchingQuestionSet(-1, $chapterID);
            $questionSet->languageID = $language->languageID;
            $questionSet->questions = $questions;
        }
        if ($questionSet === null) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Question set not found'], 404);
        }
        if (count($questionSet->questions) < 1) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Question set has no questions'], 404);
        }
        if ($numSets < 1) {
            $numSets = 1;
        }
        $sets = [];
        $questionsToSend = $questionSet->questions;
        for ($i = 0; $i < $numSets; $i++) {
            if (count($questionsToSend) === 0) {
                break;
            }
            $numToDisplay = min($numQuestions, count($questionsToSend));
            shuffle($questionsToSend);
            $sets[] = array_slice($questionsToSend, 0, $numToDisplay);
            $questionsToSend = array_slice($questionsToSend, $numToDisplay);
        }
        return new JsonStatusCodeResponse(['didSucceed' => true, 'sets' => $sets], 200);
    }
}
