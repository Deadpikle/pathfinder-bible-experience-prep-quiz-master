<?php

namespace App\Controllers\Admin;

use App\Models\Book;
use App\Models\Chapter;
use Yamf\Request;

use App\Models\Club;
use App\Models\Conference;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\StudyGuide;
use App\Models\User;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use finfo;
use PDOException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class BookController extends BaseAdminController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     * 
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request) : ?Response
    {
        $response = parent::validateRequest($app, $request);
        if ($response === null) {
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewBooks(PBEAppConfig $app, Request $request)
    {
        $books = Book::loadAllBooks($app->db);
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/books/view-books', compact('books', 'currentYear'), 'View Books');
    }

    public function createBook(PBEAppConfig $app, Request $request) : Response
    {
        $bookName = Util::validateString($request->post, 'name');
        $numberOfChapters = Util::validateInteger($request->post, 'number-chapters');
        $bibleOrder = Util::validateInteger($request->post, 'bible-order');
        $currentYear = Year::loadCurrentYear($app->db);
        $error = '';
        if ($bookName === '') {
            $error = 'Book name is required';
        }
        if ($numberOfChapters <= 0) {
            $error = 'Number of chapters must be greater than 0';
        }
        if ($bibleOrder < 1 || $bibleOrder > 66) {
            $error = 'Bible order should be between 1 and 66, inclusive';
        }
        if ($error !== '') {
            return new TwigView('admin/books/view-books', compact('books', 'currentYear', 'error', 'bookName', 'numberOfChapters', 'bibleOrder'), 'View Books');
        }

        Book::createBook($bookName, $numberOfChapters, $currentYear->yearID, $bibleOrder, $app->db);
        return new Redirect('/admin/books');
    }

    public function editBook(PBEAppConfig $app, Request $request): Response
    {
        $book = Book::loadBookByID(Util::validateInteger($request->routeParams, 'bookID'), $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/books/edit-book', compact('book', 'currentYear'), 'Edit Book');
    }

    public function saveBookUpdates(PBEAppConfig $app, Request $request): Response
    {
        $book = Book::loadBookByID(Util::validateInteger($request->routeParams, 'bookID'), $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        // TODO: refactor with creating book, more validation for not duplicating books
        // TODO: allow year selection
        $bookName = Util::validateString($request->post, 'name');
        $numberOfChapters = Util::validateInteger($request->post, 'number-chapters');
        $bibleOrder = Util::validateInteger($request->post, 'bible-order');
        $currentYear = Year::loadCurrentYear($app->db);

        $book->name = $bookName;
        $book->numberChapters = $numberOfChapters;
        $book->bibleOrder = $bibleOrder;

        $error = '';
        if ($bookName === '') {
            $error = 'Book name is required';
        }
        if ($numberOfChapters <= 0) {
            $error = 'Number of chapters must be greater than 0';
        }
        if ($bibleOrder < 1 || $bibleOrder > 66) {
            $error = 'Bible order should be between 1 and 66, inclusive';
        }
        if ($error !== '') {
            return new TwigView('admin/books/edit-book', compact('book', 'currentYear', 'error'), 'View Books');
        }
        $book->update($app->db);
        
        return new Redirect('admin/books');
    }

    public function verifyDeleteBook(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/books/verify-delete-book', compact('book'), 'Delete Book');
    }

    public function deleteBook(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-book')) {
            $book->delete($app->db);
            return new Redirect('/admin/books');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/books/verify-delete-book', compact('book', 'error'), 'Delete Book');
        }
    }

    public function viewBookChapters(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        $chapters = Chapter::loadChaptersByBookID($book->bookID, $app->db);
        return new TwigView('admin/books/view-chapters', compact('book', 'chapters'), 'Book Chapters');
    }

    public function createChapter(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new TwigNotFound();
        }
        $chapterNumber = intval($request->post['chapter-number'] ?? -1);
        $numberOfVerses = intval($request->post['number-verses'] ?? -1);
        $error = '';
        if ($chapterNumber <= 0) {
            $error = 'Chapter number must be greater than 0';
        }
        if ($numberOfVerses <= 0) {
            $error = 'Number of chapters must be greater than 0';
        }
        if ($error !== '') {
            return new TwigView('admin/books/view-chapters', compact('book', 'chapters', 'error', 'chapterNumber', 'numberOfVerses'), 'Book Chapters');
        }
        Chapter::createChapterForBook($chapterNumber, $numberOfVerses, $book->bookID, $app->db);
        return new Redirect('/admin/books/' . $book->bookID . '/chapters');
    }

    public function verifyDeleteChapter(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        if ($book === null || $chapter === null || $chapter->bookID != $book->bookID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/books/verify-delete-chapter', compact('book', 'chapter'), 'Delete Chapter');
    }

    public function deleteChapter(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        if ($book === null || $chapter === null || $chapter->bookID != $book->bookID) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-chapter')) {
            $chapter->delete($app->db);
            return new Redirect('/admin/books/' . $book->bookID . '/chapters');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/books/verify-delete-chapter', compact('book', 'chapter', 'error'), 'Delete Chapter');
        }
    }

    // core logic should be refactored elsewhere...along with an ExcelResponse class...
    public function downloadExcelTemplateFoChapter(PBEAppConfig $app, Request $request)
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        if ($book === null || $chapter === null || $chapter->bookID != $book->bookID) {
            return new TwigNotFound();
        }
        // ok, create a spreadsheet
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $vals = [];
        // setup first row
        $vals[] = [
            'Type',             // A
            'Fill in?',         // B
            'Language',         // C
            'Question',         // D
            'Answer',           // E
            'Points',           // F
            'Start Book',       // G
            'Start Chapter',    // H
            'Start Verse',      // I
            'End Book',         // J
            'End Chapter',      // K
            'End Verse',        // L
        ];
        for ($i = 0; $i < $chapter->numberVerses; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $vals[] = [
                    'Bible', 'No', 'English', '', '', 1, 
                    $book->name, $chapter->number, $i + 1, $book->name, '', ''
                ];
            }
        }
        $sheet->fromArray($vals, null, 'A1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        // hide first three columns as user doesn't need to change those in this case
        $sheet->getColumnDimension('A')->setVisible(false);
        $sheet->getColumnDimension('B')->setVisible(false);
        $sheet->getColumnDimension('C')->setVisible(false);
        // adjust width
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(50);

        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setAutoSize(true);
        $sheet->getColumnDimension('I')->setAutoSize(true);
        $sheet->getColumnDimension('J')->setAutoSize(true);
        $sheet->getColumnDimension('K')->setAutoSize(true);
        $sheet->getColumnDimension('L')->setAutoSize(true);

        // output
        
        $writer = new Xlsx($spreadsheet);
        $fileName = $book->name . '-' . $chapter->number . '-Verses-' . date('Y-m-d-h-i-s') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        ob_end_clean();
        $writer->save('php://output');
    }
}
