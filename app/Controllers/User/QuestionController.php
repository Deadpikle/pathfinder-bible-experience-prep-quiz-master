<?php

namespace App\Controllers\User;

use Yamf\Request;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Book;
use App\Models\Commentary;
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

class QuestionController
{
    public function viewQuestions(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            return new Redirect('/login');
        }
        
        $currentYear = Year::loadCurrentYear($app->db);
        $languages = Language::loadAllLanguages($app->db);
        $bookData = Book::loadAllBookChapterVerseDataForYear($currentYear, $app->db);
        $volumes = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db);
        $userLanguage = Language::findLanguageWithID($_SESSION['PreferredLanguageID'], $languages);

        return new TwigView('user/questions/view-questions', compact('currentYear', 'languages', 'bookData', 'volumes', 'userLanguage'), 'Questions');
    }

    // TODO: better response instead of echo
    // this is more of a JSON response API
    public function loadQuestions(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            die();
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

    private function loadQuestionEditingData(PBEAppConfig $app)
    {
        $currentYear = Year::loadCurrentYear($app->db);
        $bookData = Book::loadAllBookChapterVerseDataForYear($currentYear, $app->db);
        $commentaries = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db);
        $languages = Language::loadAllLanguages($app->db);
        $userLanguage = Language::findLanguageWithID($_SESSION['PreferredLanguageID'], $languages);
        return compact('bookData', 'currentYear', 'commentaries', 'languages', 'userLanguage');
    }

    public function createNewQuestion(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }

        $editData = $this->loadQuestionEditingData($app);
        $editData['isCreating'] = true;

        return new TwigView('user/questions/create-edit-question', $editData, 'Add Question');
    }

    private function validateQuestionForm(PBEAppConfig $app, Request $request, bool $isCreating) : ValidationStatus
    {
        $totalBibleFillInQuestions = Question::getNumberOfFillInBibleQuestionsForCurrentYear($app->db);

        $questionType = $request->post['question-type'];
        $isFillInTheBlank = Util::validateBoolean($request->post, 'question-is-fill-in-blank');
        $languageID = $request->post['language-select'];

        if ($questionType == Question::getBibleQnAType()) {
            $startVerseID = $request->post['start-verse-id'] ?? null;
            if ($startVerseID == -1) {
                $startVerseID = null;
            }
            $endVerseID = $request->post['end-verse-id'] ?? null;
            if ($endVerseID == -1) {
                $endVerseID = null;
            }
            $commentaryID = null;
            $commentaryStartPage = null;
            $commentaryEndPage = null;
            if ($isFillInTheBlank) {
                $questionType = Question::getBibleQnAFillType();
            }
        } else if ($questionType == Question::getCommentaryQnAType()) {
            $commentaryID = $request->post['commentary-volume'];
            $commentaryStartPage = $request->post['commentary-start'];
            $commentaryEndPage = $request->post['commentary-end'];
            $startVerseID = null;
            $endVerseID = null;
            if ($isFillInTheBlank) {
                $questionType = Question::getCommentaryQnAFillType();
            }
        }

        $question = new Question($request->post['question-id'] ?? -1);
        $question->question = trim($request->post['question-text']);
        $question->answer = isset($request->post['question-answer']) ? $request->post['question-answer'] : '';
        $question->type = $questionType;
        $question->lastEditedByID = User::currentUserID();
        $question->numberPoints = $request->post['number-of-points'];
        $question->startVerseID = $startVerseID;
        $question->endVerseID = $endVerseID;

        $question->commentaryID = $commentaryID;
        $question->commentaryStartPage = $commentaryStartPage;
        $question->commentaryEndPage = $commentaryEndPage;
        $question->languageID = $languageID;
        if ($isCreating) {
            $question->creatorID = User::currentUserID();
        } else {
            $dbQuestion = Question::loadQuestionWithID($request->post['question-id'], $app->db);
            $question->questionID = $dbQuestion->questionID;
            $question->creatorID = $dbQuestion->creatorID;
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
    
    public function saveNewQuestion(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn() || $app->isGuest) {
            return new Redirect('/');
        }
        $validation = $this->validateQuestionForm($app, $request, true);
        if ($validation->didValidate) {
            $question = $validation->output;
            $question->create($app->db);
            return new Redirect('/questions');
        } else {
            $editData = $this->loadQuestionEditingData($app);
            $editData['isCreating'] = true;
            $editData['error'] = $validation->error;
            $editData['question'] = $validation->output;
            return new TwigView('user/questions/create-edit-question', $editData, 'Add Question');
        }
    }
    
    public function editQuestion(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn() || $app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        $editData = $this->loadQuestionEditingData($app);
        $editData['isCreating'] = false;
        $questionID = Util::validateInteger($request->routeParams, 'questionID');
        $editData['question'] = Question::loadQuestionWithID($questionID, $app->db);
        $editData['isFlagged'] = 
            $app->isWebAdmin 
                ? UserFlagged::isFlaggedByAnyUser($questionID, $app->db)
                : UserFlagged::isFlagged($questionID, User::currentUserID(), $app->db);

        return new TwigView('user/questions/create-edit-question', $editData, 'Edit Question');
    }
    
    public function saveQuestionEdits(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn() || $app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        $validation = $this->validateQuestionForm($app, $request, false);
        if ($validation->didValidate) {
            $question = $validation->output;
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
            return new Redirect('/questions');
        } else {
            $editData = $this->loadQuestionEditingData($app);
            $editData['isCreating'] = true;
            $editData['error'] = $validation->error;
            $editData['question'] = $question;
            $editData['isFlagged'] = 
                $app->isWebAdmin 
                    ? UserFlagged::isFlaggedByAnyUser($question->questionID, $app->db)
                    : UserFlagged::isFlagged($question->questionID, User::currentUserID(), $app->db);
            return new TwigView('user/questions/create-edit-question', $editData, 'Edit Question');
        }
    }

    public function verifyDeleteQuestion(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn() || $app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null) {
            return new TwigNotFound();
        }
        return new TwigView('user/questions/verify-delete-question', compact('question'), 'Delete Question');
    }

    public function deleteQuestion(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn() || $app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);
        if ($question === null || $question->questionID != $request->post['question-id']) {
            return new TwigNotFound();
        }
        $question->updateDeletedFlag(true, $app->db);
        return new Redirect('/questions');
    }
}
