<?php

namespace App\Controllers\Admin;

use App\Models\PBEAppConfig;
use App\Models\UserProgress;
use App\Models\Util;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

final class ProgressController extends BaseAdminController
{
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        $response = parent::validateRequest($app, $request);
        if ($response !== null) {
            return $response;
        }
        /** @var PBEAppConfig $app */
        // Conference-wide learner reporting needs a separate privacy decision.
        return ($app->isWebAdmin || $app->isClubAdmin) ? null : new Redirect('/admin');
    }

    public function index(PBEAppConfig $app, Request $request): Response
    {
        $progressSummaries = UserProgress::loadUsersForAdmin($app, $app->db);
        return new TwigView('admin/progress/index', compact('progressSummaries'), 'Learner Progress');
    }

    public function viewUser(PBEAppConfig $app, Request $request): Response
    {
        $userID = Util::validateInteger($request->routeParams, 'userID');
        if ($userID <= 0 || !UserProgress::canAdminViewUser($app, $userID, $app->db)) {
            return new TwigNotFound();
        }
        $progress = UserProgress::loadForUser($userID, $app->db);
        $isAdminView = true;
        return new TwigView('user/progress/index', compact('progress', 'isAdminView'), 'Learner Progress');
    }
}
