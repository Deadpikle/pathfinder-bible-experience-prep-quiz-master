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
use App\Models\Views\TwigView;
use App\Models\Year;
use finfo;
use PDOException;
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
        $numberOfChapters = intval($request->post['number-chapters'] ?? -1);
        $currentYear = Year::loadCurrentYear($app->db);
        $error = '';
        if ($bookName === '') {
            $error = 'Book name is required';
        }
        if ($numberOfChapters <= 0) {
            $error = 'Number of chapters must be greater than 0';
        }
        if ($error !== '') {
            return new TwigView('admin/books/view-books', compact('books', 'currentYear', 'error', 'bookName', 'numberOfChapters'), 'View Books');
        }

        Book::createBook($bookName, $numberOfChapters, $currentYear->yearID, $app->db);
        return new Redirect('/admin/books');
    }

    public function verifyDeleteBook(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new NotFound();
        }
        return new TwigView('admin/books/verify-delete-book', compact('book'), 'Delete Book');
    }

    public function deleteBook(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new NotFound();
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
            return new NotFound();
        }
        $chapters = Chapter::loadChaptersByBookID($book->bookID, $app->db);
        return new TwigView('admin/books/view-chapters', compact('book', 'chapters'), 'Book Chapters');
    }

    public function createChapter(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        if ($book === null) {
            return new NotFound();
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
            return new NotFound();
        }
        return new TwigView('admin/books/verify-delete-chapter', compact('book', 'chapter'), 'Delete Chapter');
    }

    public function deleteChapter(PBEAppConfig $app, Request $request) : Response
    {
        $book = Book::loadBookByID($request->routeParams['bookID'], $app->db);
        $chapter = Chapter::loadChapterByID($request->routeParams['chapterID'], $app->db);
        if ($book === null || $chapter === null || $chapter->bookID != $book->bookID) {
            return new NotFound();
        }
        if (CSRF::verifyToken('delete-chapter')) {
            $chapter->delete($app->db);
            return new Redirect('/admin/books/' . $book->bookID . '/chapters');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/books/verify-delete-chapter', compact('book', 'chapter', 'error'), 'Delete Chapter');
        }
    }
}
