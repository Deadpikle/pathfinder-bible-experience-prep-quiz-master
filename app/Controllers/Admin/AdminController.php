<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use Yamf\Responses\Response;

class AdminController extends BaseAdminController
{
    public function index(PBEAppConfig $app, Request $request)
    {
        $conferenceID = User::currentConferenceID();
        return new TwigView('admin/index', compact('conferenceID'), 'Admin Home');
    }

    public function help(PBEAppConfig $app, Request $request) : Response
    {
        if (!$app->isWebAdmin) {
            return new TwigNotFound();
        }
        return new TwigView('admin/help', [], 'Admin Help');
    }
}
