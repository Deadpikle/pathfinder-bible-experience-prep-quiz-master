<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Club;
use App\Models\Conference;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\StudyGuide;
use App\Models\User;
use App\Models\Views\TwigView;
use App\Models\Year;

class AdminController
{
    public function index(PBEAppConfig $app, Request $request)
    {
        $canViewAdminPanel = isset($_SESSION["UserType"]) && $_SESSION["UserType"] !== "Pathfinder" && $_SESSION["UserType"] !== "Guest";
        if (!$canViewAdminPanel) {
            return new Redirect('/');
        }
        $title = 'Admin Home';

        return new TwigView('admin/index');
    }
}
