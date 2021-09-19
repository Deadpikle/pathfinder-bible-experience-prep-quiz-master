<?php

namespace App\Controllers\Admin;

use App\Models\BibleFillInData;
use App\Models\BibleQnAData;
use App\Models\Book;
use App\Models\Chapter;
use Yamf\Request;

use App\Models\Conference;
use App\Models\CSRF;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class BibleQnAController extends BaseAdminController
{
    public function viewBibleQuestions(PBEAppConfig $app, Request $request)
    {
        $qnaData = BibleQnAData::loadQnAData(Year::loadCurrentYear($app->db), $app->db);
        $totalQuestions = 0;
        foreach ($qnaData as $data) {
            $totalQuestions += $data->numberOfQuestions;
        }
        $languages = Language::loadAllLanguages($app->db);
        $languagesByID = [];
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }
        $totalsByLanguage = [];
        foreach ($qnaData as $data) {
            if (!isset($totalsByLanguage[$data->language->languageID])) {
                $totalsByLanguage[$data->language->languageID] = 0;
            }
            $totalsByLanguage[$data->language->languageID] += $data->numberOfQuestions;
        }
        return new TwigView('admin/bible-qna-questions/view-bible-qna-questions', compact('qnaData', 'totalQuestions', 'totalsByLanguage', 'languages'), 'Bible Q&A Questions');
    }

    public function verifyDeleteQnAQuestionsForChapter(PBEAppConfig $app, Request $request)
    {
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        $book = Book::loadBookByID($chapter->bookID, $app->db);
        if ($chapter === null || $language === null || $book === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/bible-qna-questions/verify-delete-chapter-qna', compact('chapter', 'language', 'book'), 'Delete Bible Q&A Questions');
    }

    public function deleteQnAQuestionsForChapter(PBEAppConfig $app, Request $request)
    {
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        $book = Book::loadBookByID($chapter->bookID, $app->db);
        if ($chapter === null || $language === null || $book === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-chapter-bible-qna')) {
            BibleQnAData::deleteQnAForChapter(Year::loadCurrentYear($app->db), $chapter->chapterID, $language->languageID, $app->db);
            return new Redirect('/admin/bible-qna-questions');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/bible-qna-questions/verify-delete-chapter-qna', compact('chapter', 'language', 'book', 'error'), 'Delete Bible Q&A Questions');
        }
    }

    public function verifyDeleteQnAQuestionsForLanguage(PBEAppConfig $app, Request $request)
    {
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        if ($language === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/bible-qna-questions/verify-delete-language-qna', compact('language'), 'Delete Bible Q&A Questions');
    }

    public function deleteQnAQuestionsForLanguage(PBEAppConfig $app, Request $request)
    {
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        if ($language === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-language-bible-qna')) {
            BibleFillInData::deleteFillInsForLanguage(Year::loadCurrentYear($app->db), $language->languageID, $app->db);
            return new Redirect('/admin/bible-qna-questions');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/bible-qna-questions/verify-delete-language-qna', compact('language', 'error'), 'Delete Bible Q&A Questions');
        }
    }

}
