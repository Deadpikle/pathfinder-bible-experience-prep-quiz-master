<?php

namespace App\Controllers\User;

use Yamf\Request;
use Yamf\Responses\ErrorMessage;
use Yamf\Responses\JsonResponse;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Commentary;
use App\Models\Language;
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
use App\Services\QuizGenerator;
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
        $viewFillInTheBlankAnswersInBold = Util::validateBoolean($request->post, 'flash-full-fill-in');
        return new TwigView('user/quiz/take-quiz', compact('quizQuestions', 'userID', 'viewFillInTheBlankAnswersInBold'), 'Take Quiz');
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
        $hasFlagged = UserFlagged::addFlagIfNecessary($request->post['questionID'], $userID, $app->db);
        return new JsonResponse(['status' => $hasFlagged ? 200 : 400]);
    }

    public function viewMatchingQuizPage(PBEAppConfig $app, Request $request): Response
    {
        $currentYear = Year::loadCurrentYear($app->db);
        $questionSets = MatchingQuestionSet::loadAllMatchingSetsForYear($currentYear->yearID, $app->db);
        return new TwigView('user/quiz/matching-quiz', compact('currentYear', 'questionSets'), 'Matching Quiz');
    }

    public function generateMatchingQuiz(PBEAppConfig $app, Request $request): Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->post, 'questionSetID'), $app->db);
        $numQuestions = Util::validateInteger($request->post, 'numberQuestions');
        if ($questionSet === null) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Question set not found'], 404);
        }
        if ($numQuestions < 1) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Number of questions must be greater than 1'], 404);
        }
        if (count($questionSet->questions) < 1) {
            return new JsonStatusCodeResponse(['didSucceed' => false, 'message' => 'Question set has no questions'], 404);
        }
        $numToDisplay = min($numQuestions, $questionSet->questions);
        $questionsToSend = $questionSet->questions;
        shuffle($questionsToSend);
        $questionsToSend = array_slice($questionsToSend, 0, $numToDisplay);
        return new JsonStatusCodeResponse(['didSucceed' => true, 'data' => $questionsToSend], 200);
    }
}
