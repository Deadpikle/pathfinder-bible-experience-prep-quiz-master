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

class ImportQuestionsController extends BaseAdminController
{
    public function viewImportPage(PBEAppConfig $app, Request $request)
    {
        $conferenceID = User::currentConferenceID();
        return new TwigView('admin/upload-csv', compact('conferenceID'), 'Upload Questions');
    }

    public function saveImportedQuestions(PBEAppConfig $app, Request $request)
    {

    }
}
