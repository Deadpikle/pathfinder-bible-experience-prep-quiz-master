<?php

namespace App\Controllers\User;

use Yamf\Request;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Book;
use App\Models\Commentary;
use App\Models\CSRF;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\User;
use App\Models\UserFlagged;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class QuestionController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     *
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        if (!User::isLoggedIn()) {
            if ($request->function === 'loadQuestions') {
                return new Response(401);
            }
            return new Redirect('/');
        }
        return null;
    }

    public function viewQuestions(PBEAppConfig $app, Request $request): Response
    {        
        $currentYear = Year::loadCurrentYear($app->db);
        $languages = Language::loadAllLanguages($app->db);
        $bookData = Book::loadAllBookChapterVerseDataForYear($currentYear, $app->db);
        $volumes = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db);
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages);
        $usersByID = User::loadAllUsersByID($app->db);
        return new TwigView('user/questions/view-questions', compact('currentYear', 'languages', 'bookData', 'volumes', 'userLanguage', 'usersByID'), 'Questions');
    }

    // TODO: better response instead of echo
    // this is more of a JSON response API
    public function loadQuestions(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Response(401);
        }

        $questionData = Question::loadQuestionsWithFilters(
            $request->post['questionFilter'],
            $request->post['questionType'],
            $request->post['bookFilter'],
            $request->post['chapterFilter'],
            $request->post['volumeFilter'],
            $request->post['searchText'],
            $request->post['pageSize'],
            $request->post['pageOffset'],
            $request->post['languageID'],
            User::currentUserID(), 
            $app,
            $app->db
        );
        echo json_encode($questionData);
    }

    private function showCreateOrEditQuestion(PBEAppConfig $app, Request $request, bool $isCreating, ?Question $question = null, string $error = ''): Response
    {
        $currentYear = Year::loadCurrentYear($app->db);
        $bookData = Book::loadAllBookChapterVerseDataForYear($currentYear, $app->db);
        $commentaries = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db);
        $languages = Language::loadAllLanguages($app->db);
        $isFlagged = false;
        if ($question !== null) {
            $isFlagged = $app->isWebAdmin 
                ? UserFlagged::isFlaggedByAnyUser($question->questionID, $app->db)
                : UserFlagged::isFlagged($question->questionID, User::currentUserID(), $app->db);
        }
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages);

        return new TwigView('user/questions/create-edit-question', compact('bookData', 'currentYear', 'commentaries', 'languages', 'userLanguage', 'isCreating', 'question', 'error', 'isFlagged'), $isCreating ? 'Add Question' : 'Edit Question');
    }

    public function createNewQuestion(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || $app->isPathfinder) {
            return new Redirect('/');
        }
        return $this->showCreateOrEditQuestion($app, $request, true);
    }

    private function validateQuestionForm(PBEAppConfig $app, Request $request, bool $isCreating): ValidationStatus
    {
        $totalBibleFillInQuestions = Question::getNumberOfFillInBibleQuestionsForCurrentYear($app->db);

        $questionType = Util::validateString($request->post, 'question-type');
        $isFillInTheBlank = Util::validateBoolean($request->post, 'question-is-fill-in-blank');
        $languageID = Util::validateInteger($request->post, 'language-select');

        $isInvalidType = false;
        if ($questionType == Question::getBibleQnAType()) {
            $startVerseID = Util::validateInteger($request->post, 'start-verse-id');
            if ($startVerseID <= 0) {
                $startVerseID = null;
            }
            $endVerseID = Util::validateInteger($request->post, 'end-verse-id');
            if ($endVerseID <= 0) {
                $endVerseID = null;
            }
            $commentaryID = null;
            $commentaryStartPage = null;
            $commentaryEndPage = null;
            if ($isFillInTheBlank) {
                $questionType = Question::getBibleQnAFillType();
            }
        } else if ($questionType == Question::getCommentaryQnAType()) {
            $commentaryID = Util::validateInteger($request->post, 'commentary-volume');
            $commentaryStartPage = Util::validateInteger($request->post, 'commentary-start');
            $commentaryEndPage = Util::validateInteger($request->post, 'commentary-end');
            $startVerseID = null;
            $endVerseID = null;
            if ($isFillInTheBlank) {
                $questionType = Question::getCommentaryQnAFillType();
            }
        } else {
            $isInvalidType = true;
        }

        $question = new Question(Util::validateInteger($request->routeParams, 'questionID'));
        $question->question = Util::validateString($request->post, 'question-text');
        $question->answer = Util::validateString($request->post, 'question-answer');
        $question->type = $questionType;
        $question->lastEditedByID = User::currentUserID();
        $question->numberPoints = Util::validateInteger($request->post, 'number-of-points');
        $question->startVerseID = $startVerseID;
        $question->endVerseID = $endVerseID;

        $question->commentaryID = $commentaryID;
        $question->commentaryStartPage = $commentaryStartPage !== 0 ? $commentaryStartPage : null;
        $question->commentaryEndPage = $commentaryEndPage !== 0 ? $commentaryEndPage : null;
        $question->languageID = $languageID;
        if ($isCreating) {
            $question->creatorID = User::currentUserID();
        } else {
            $questionID = Util::validateInteger($request->routeParams, 'questionID');
            $dbQuestion = Question::loadQuestionWithID($questionID, $app->db);
            $question->questionID = $dbQuestion->questionID;
            $question->creatorID = $dbQuestion->creatorID;
        }
        if ($isInvalidType) {
            return new ValidationStatus(false, $question, 'Invalid question type');
        }
        // validate Bible fill in
        if ($questionType == Question::getBibleQnAFillType() && $totalBibleFillInQuestions >= 500 && $app->ENABLE_NKJV_RESTRICTIONS) {
            if ($isCreating) {
                return new ValidationStatus(false, $question, 'Maximum amount of Bible fill-in questions reached');
            } else if ($dbQuestion === null || $dbQuestion->type !== Question::getBibleQnAFillType()) {
                return new ValidationStatus(false, $question, 'Maximum amount of Bible fill-in questions reached');
            }
        }
        if (!Util::doesTextPassWordFilter($question->question)) {
            return new ValidationStatus(false, $question, 'The question text for this Q&A has invalid text');
        }
        if (!Util::doesTextPassWordFilter($question->answer)) {
            return new ValidationStatus(false, $question, 'The answer text for this Q&A has invalid text');
        }
        return new ValidationStatus(true, $question);
    }
    
    public function saveNewQuestion(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || $app->isPathfinder) {
            return new Redirect('/');
        }
        $validation = $this->validateQuestionForm($app, $request, true);
        if ($validation->didValidate) {
            $question = $validation->output;
            /** @var Question $question */
            $question->create($app->db);
            return new Redirect('/questions' . ($question->isCommentaryQnA() ? '?loadCommentaryFirst=1' : ''));
        }
        return $this->showCreateOrEditQuestion($app, $request, true, $validation->output, $validation->error);
    }
    
    public function editQuestion(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || !$app->isAdmin) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID(Util::validateInteger($request->routeParams, 'questionID'), $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditQuestion($app, $request, false, $question);
    }
    
    public function saveQuestionEdits(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || !$app->isAdmin) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID(Util::validateInteger($request->routeParams, 'questionID'), $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        $validation = $this->validateQuestionForm($app, $request, false);
        if ($validation->didValidate) {
            $question = $validation->output;
            /** @var Question $question */
            $question->update($app->db);
            // check if validated before removing flag!
            $shouldRemoveFlag = Util::validateBoolean($request->post, 'remove-question-flag');
            if ($shouldRemoveFlag) {
                if ($app->isWebAdmin) {
                    UserFlagged::deleteAllFlagsForQuestion($question->questionID, $app->db);
                } else {
                    UserFlagged::deleteFlag($question->questionID, User::currentUserID(), $app->db);
                }
            }
            return new Redirect('/questions' . ($question->isCommentaryQnA() ? '?loadCommentaryFirst=1' : ''));
        }
        return $this->showCreateOrEditQuestion($app, $request, false, $validation->output, $validation->error);
    }

    public function verifyDeleteQuestion(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || !$app->isAdmin) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        $bookDataByVerseID = Book::getBookDataIndexedByVerse(Year::loadCurrentYear($app->db), $app->db);
        $commentariesByID = Commentary::loadAllCommentariesKeyedByID($app->db);
        return new TwigView('user/questions/verify-delete-question', compact('question', 'bookDataByVerseID', 'commentariesByID'), 'Delete Question');
    }

    public function deleteQuestion(PBEAppConfig $app, Request $request): Response
    {
        if ($app->isGuest || !$app->isAdmin) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-question')) {
            $question->updateDeletedFlag(true, $app->db);
            return new Redirect('/questions' . ($question->isCommentaryQnA() ? '?loadCommentaryFirst=1' : ''));
        } else {
            $error = 'Unable to validate request. Please try again.';
            $bookDataByVerseID = Book::getBookDataIndexedByVerse(Year::loadCurrentYear($app->db), $app->db);
            $commentariesByID = Commentary::loadAllCommentariesKeyedByID($app->db);
            return new TwigView('user/questions/verify-delete-question', compact('question', 'error', 'bookDataByVerseID', 'commentariesByID'), 'Delete Question');
        }
    }
}
