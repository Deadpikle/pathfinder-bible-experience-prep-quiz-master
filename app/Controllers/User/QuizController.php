<?php

namespace App\Controllers\User;

use Yamf\Request;
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
use App\Models\Year;

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
}
