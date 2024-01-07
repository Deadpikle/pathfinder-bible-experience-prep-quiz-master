<?php

namespace App\Controllers\Admin;

use App\Models\BibleFillInData;
use App\Models\Book;
use App\Models\Chapter;
use Yamf\Request;

use App\Models\Conference;
use App\Models\CSRF;
use App\Models\Language;
use App\Models\NonBlankableWord;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigPDFView;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class BibleFillInController extends BaseAdminController
{
    public function viewBibleFillIns(PBEAppConfig $app, Request $request)
    {
        $fillInData = BibleFillInData::loadFillInData(Year::loadCurrentYear($app->db), $app->db);
        $totalQuestions = 0;
        foreach ($fillInData as $data) {
            $totalQuestions += $data->numberOfQuestions;
        }
        $languages = Language::loadAllLanguages($app->db);
        $languagesByID = [];
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }
        $totalsByLanguage = [];
        foreach ($fillInData as $data) {
            if (!isset($totalsByLanguage[$data->language->languageID])) {
                $totalsByLanguage[$data->language->languageID] = 0;
            }
            $totalsByLanguage[$data->language->languageID] += $data->numberOfQuestions;
        }
        return new TwigView('admin/bible-fill-ins/view-bible-fill-ins', compact('fillInData', 'totalQuestions', 'totalsByLanguage', 'languages'), 'Bible Fill In Questions');
    }

    public function verifyDeleteFillInsForChapter(PBEAppConfig $app, Request $request)
    {
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        $book = Book::loadBookByID($chapter->bookID, $app->db);
        if ($chapter === null || $language === null || $book === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/bible-fill-ins/verify-delete-chapter-fill-ins', compact('chapter', 'language', 'book'), 'Delete Bible Fill In Questions');
    }

    public function deleteFillInsForChapter(PBEAppConfig $app, Request $request)
    {
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        $book = Book::loadBookByID($chapter->bookID, $app->db);
        if ($chapter === null || $language === null || $book === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-chapter-bible-fill-ins')) {
            BibleFillInData::deleteFillInsForChapter(Year::loadCurrentYear($app->db), $chapter->chapterID, $language->languageID, $app->db);
            return new Redirect('/admin/bible-fill-ins');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/bible-fill-ins/verify-delete-chapter-fill-ins', compact('chapter', 'language', 'book', 'error'), 'Delete Bible Fill In Questions');
        }
    }

    public function verifyDeleteFillInsForLanguage(PBEAppConfig $app, Request $request)
    {
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        if ($language === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/bible-fill-ins/verify-delete-language-fill-ins', compact('language'), 'Delete Bible Fill In Questions');
    }

    public function deleteFillInsForLanguage(PBEAppConfig $app, Request $request)
    {
        $language = Language::loadLanguageWithID($request->routeParams['languageID'], $app->db);
        if ($language === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-language-bible-fill-ins')) {
            BibleFillInData::deleteFillInsForLanguage(Year::loadCurrentYear($app->db), $language->languageID, $app->db);
            return new Redirect('/admin/bible-fill-ins');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/bible-fill-ins/verify-delete-language-fill-ins', compact('language', 'error'), 'Delete Bible Fill In Questions');
        }
    }

    public function createLetterPDF(PBEAppConfig $app, Request $request)
    {
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'] ?? 0, $app->db);
        $language = Language::loadLanguageWithID($request->routeParams['languageID'] ?? 0, $app->db);
        $book = Book::loadBookByID($chapter->bookID ?? 0, $app->db);
        if ($chapter === null || $language === null || $book === null) {
            return new TwigNotFound();
        }
        $questions = Question::loadFillInsForChapterAndLanguage($chapter->chapterID, $language->languageID, $app->db);
        $lines = [];
        foreach ($questions as $questionData) {
            $wordData = NonBlankableWord::generateFillInQuestion($questionData['question'], 0, []);
            $str = '';
            foreach ($wordData['data'] as $item) {
                $firstChar = mb_substr($item['word'], 0, 1);
                if ($str !== '') {
                    $str .= ' ';
                }
                $str .= trim($item['before'] . $firstChar . $item['after']);
            }
            $lines[] = $str;
        }
        // create PDF
        $chapterNumber = $chapter->number;
        return new TwigPDFView('pdf/fill-in-first-letters', compact('lines', 'chapterNumber', 'language'), $book->name . ' ' . 
            $chapter->number, $book->name . ' ' . $chapter->number . ' First Letters [' . $language->name . '].pdf');
    }
}
