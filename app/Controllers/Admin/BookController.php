<?php

namespace App\Controllers\Admin;

use App\Models\Book;
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
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class BookController extends BaseAdminController
{
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
            return new TwigView('admin/books/view-books', compact('books', 'currentYear', 'error'), 'View Books');
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
}
