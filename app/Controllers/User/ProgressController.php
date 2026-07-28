<?php

namespace App\Controllers\User;

use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

final class ProgressController implements IRequestValidator
{
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        /** @var PBEAppConfig $app */
        return User::isLoggedIn() && !$app->isGuest ? null : new Redirect('/login');
    }

    public function viewProgress(PBEAppConfig $app, Request $request): Response
    {
        $progress = UserProgress::loadForUser(User::currentUserID(), $app->db);
        $isAdminView = false;
        return new TwigView('user/progress/index', compact('progress', 'isAdminView'), 'My Progress');
    }
}
