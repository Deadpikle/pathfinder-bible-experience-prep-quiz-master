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
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserFlagged;
use App\Models\Util;
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
        $booksByBookID = [];
        foreach ($books as $book) {
            $booksByBookID[$book->bookID] = $book;
        }
        $chapters = Chapter::loadChaptersWithActiveQuestions($currentYear, $app->db);

        return new View('user/quiz/quiz-setup', compact('currentYear', 'languages', 'userLanguage', 'chapters', 'commentaries', 'booksByBookID'), 'Quiz Setup');
    }

    public function checkBeforeRemovingAnswers(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/');
        }
        return new View('user/quiz/verify-delete-user-answers', null, 'Delete Answers');
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
                $request->post['max-questions'],
                $request->post['max-points'],
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
        return new View('user/quiz/take-quiz', compact('quizQuestions', 'userID'), 'Quiz');
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
}
