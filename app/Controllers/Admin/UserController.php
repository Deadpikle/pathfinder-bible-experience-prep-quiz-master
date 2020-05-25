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
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class UserController extends BaseAdminController
{
    public function viewUsers(PBEAppConfig $app, Request $request)
    {
        $displayConferenceName = true;
        if ($app->isClubAdmin) {
            $displayConferenceName = false;
            $users = User::loadUsersInClub(User::currentClubID(), $app->db);
        } else if ($app->isConferenceAdmin) {
            $users = User::loadUsersInConference(User::currentConferenceID(), $app->db);
        } else {
            $users = User::loadAllUsers($app->db);
        }
        $clubs = Club::loadAllClubs($app->db);
        $clubsByID = [];
        foreach ($clubs as $club) {
            $clubsByID[$club->clubID] = $club;
        }
        $conferences = Conference::loadAllConferences($app->db);
        $conferencesByID = [];
        foreach ($conferences as $conference) {
            $conferencesByID[$conference->conferenceID] = $conference;
        }
        $clubName = User::currentClubName();
        $conferenceName = User::currentConferenceName();
        $currentUserID = User::currentUserID();
        return new TwigView('admin/view-users', 
            compact('users', 'displayConferenceName', 'clubsByID', 'conferencesByID', 'clubName', 'conferenceName', 'currentUserID'), 
            'Users');
    }
}
