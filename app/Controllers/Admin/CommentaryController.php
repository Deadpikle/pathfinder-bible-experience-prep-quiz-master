<?php

namespace App\Controllers\Admin;

use App\Models\Book;
use App\Models\Chapter;
use Yamf\Request;

use App\Models\Club;
use App\Models\Commentary;
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
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class CommentaryController extends BaseAdminController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     * 
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        $response = parent::validateRequest($app, $request);
        if ($response === null) {
            /** @var PBEAppConfig $app */
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewCommentaries(PBEAppConfig $app, Request $request): Response
    {
        $commentaries = Commentary::loadAllCommentaries($app->db);
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/commentaries/view-commentaries', compact('commentaries', 'currentYear'), 'View Commentaries');
    }

    public function createCommentary(PBEAppConfig $app, Request $request): Response
    {
        $topic = Util::validateString($request->post, 'topic');
        $commentaryNumber = intval($request->post['commentary'] ?? -1);
        $currentYear = Year::loadCurrentYear($app->db);
        $error = '';
        if ($topic === '') {
            $error = 'Book name is required';
        }
        if ($commentaryNumber <= 0) {
            $error = 'Commentary number must be greater than 0';
        }
        if ($error !== '') {
            return new TwigView('admin/commentaries/view-commentaries', compact('commentaries', 'currentYear', 'error', 'commentaryNumber', 'topic'), 'View Commentaries');
        }

        Commentary::createCommentary($commentaryNumber, $topic, $currentYear->yearID, $app->db);
        return new Redirect('/admin/commentaries');
    }

    public function verifyDeleteCommentary(PBEAppConfig $app, Request $request): Response
    {
        $commentary = Commentary::loadCommentaryByID($request->routeParams['commentaryID'], $app->db);
        if ($commentary === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/commentaries/verify-delete-commentary', compact('commentary'), 'Delete Commentary');
    }

    public function deleteCommentary(PBEAppConfig $app, Request $request): Response
    {
        $commentary = Commentary::loadCommentaryByID($request->routeParams['commentaryID'], $app->db);
        if ($commentary === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-commentary')) {
            $commentary->delete($app->db);
            return new Redirect('/admin/commentaries');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/commentaries/verify-delete-commentary', compact('commentary', 'error'), 'Delete Commentary');
        }
    }
}
