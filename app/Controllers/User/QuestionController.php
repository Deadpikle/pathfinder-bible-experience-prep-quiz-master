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

        return new View('user/questions/view-questions', compact('currentYear', 'languages', 'bookData', 'volumes', 'userLanguage'), 'Questions');
    }

    // TODO: better response instead of echo
    public function loadQuestions(PBEAppConfig $app, Request $request)
    {
        if (!User::isLoggedIn()) {
            die();
        }

        $questionData = Question::loadQuestionsWithFilters(
            $request->post['questionFilter'],
            $request->post['questionType'],
            $request->post['bookFilter'],
            $request->post['volumeFilter'],
            $request->post['searchText'],
            $request->post['pageSize'],
            $request->post['pageOffset'],
            $request->post['languageID'],
            $_SESSION['UserID'],
            $app->db
        );
        echo $questionData;
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

        return new View('user/questions/create-edit-question', $editData, 'Add Question');
    }
    
    public function saveNewQuestion(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }
    }
    
    public function editQuestion(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);;
        if ($question === null) {
            return new NotFound();
        }
        $editData = $this->loadQuestionEditingData($app);
        $editData['isCreating'] = false;
        $editData['question'] = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);

        return new View('user/questions/create-edit-question', $editData, 'Add Question');
    }
    
    public function saveQuestionEdits(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);;
        if ($question === null) {
            return new NotFound();
        }
    }

    public function verifyDeleteQuestion(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);;
        if ($question === null) {
            return new NotFound();
        }
        return new View('user/questions/verify-delete-question', compact('question'), 'Delete Question');
    }

    public function deleteQuestion(PBEAppConfig $app, Request $request)
    {
        if ($app->isGuest) {
            return new Redirect('/');
        }
        $question = Question::loadQuestionWithID($request->routeParams['questionID'], $app->db);;
        if ($question === null || $question->questionID != $request->post['question-id']) {
            return new NotFound();
        }
        $question->updateDeletedFlag(true, $app->db);
        return new Redirect('/questions');
    }
}
