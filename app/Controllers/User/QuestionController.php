<?php

namespace App\Controllers\User;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Book;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\Year;

class QuestionController
{
    public function viewQuestions(PBEAppConfig $app, Request $request)
    {
        $currentYear = Year::loadCurrentYear($app->db);
        $languages = Language::loadAllLanguages($app->db);
        $bookData = Book::loadAllBookChapterVerseDataForYear($currentYear, $app->db);

        return new View('user/questions/view-questions', compact('currentYear', 'languages', 'bookData'), 'Questions');
    }

    public function loadQuestions(PBEAppConfig $app, Request $request)
    {
        $questionData = Question::loadQuestions(
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
}
