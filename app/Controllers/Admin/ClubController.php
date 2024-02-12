<?php

namespace App\Controllers\Admin;

use Yamf\Request;

use App\Models\Club;
use App\Models\Conference;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class ClubController extends BaseAdminController implements IRequestValidator
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
            /** @var PBEAppConfig $app */
            if ($app->isWebAdmin || $app->isConferenceAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewClubs(PBEAppConfig $app, Request $request)
    {
        $currentConferenceID = User::currentConferenceID();
        if ($app->isConferenceAdmin) {
            $clubs = Club::loadClubsInConference($currentConferenceID, $app->db);
        } else { // web admin
            $clubs = Club::loadAllClubs($app->db);
        }
        $conferences = Conference::loadAllNonAdminConferences($app->db);
        $conferencesByID = Conference::loadAllConferencesByID($app->db);
        return new TwigView('admin/clubs/view-clubs', compact('clubs', 'conferences', 'conferencesByID', 'currentConferenceID'), 'Clubs');
    }

    private function showCreateOrEditClub(PBEAppConfig $app, Request $request, bool $isCreating, ?Club $club, string $error = '') : Response
    {
        $conferences = Conference::loadAllNonAdminConferences($app->db);
        return new TwigView('admin/clubs/create-edit-club', compact('isCreating', 'club', 'conferences', 'error'), $isCreating ? 'Create Club' : 'Edit Club');
    }

    private function validateClub(PBEAppConfig $app, Request $request, ?Club $club) : ValidationStatus
    {
        $name = $request->post['club-name'] ?? '';
        $url = $request->post['club-url'] ?? '';
        $conferenceID = (int)$request->post['conference'] ?? -1;

        $club = new Club($club->clubID ?? -1, $name);
        $club->url = $url;
        if ($app->isWebAdmin) {
            $club->conferenceID = $conferenceID;
        } else {
            $userConference = User::currentConferenceID();
            $club->conferenceID = User::currentConferenceID() != -1 ? $userConference : null;
        }
        
        if ($name === null || $name === '') {
            return new ValidationStatus(false, $club, 'Club name is required');
        }

        return new ValidationStatus(true, $club);
    }

    public function createClub(PBEAppConfig $app, Request $request) : Response
    {
        return $this->showCreateOrEditClub($app, $request, true, null);
    }

    public function saveCreatedClub(PBEAppConfig $app, Request $request) : Response
    {
        $status = $this->validateClub($app, $request, null);
        $club = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditClub($app, $request, true, $club, $status->error);
        }
        $club->create($app->db);
        return new Redirect('/admin/clubs');
    }

    public function editClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditClub($app, $request, false, $club);
    }

    public function saveEditedClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new TwigNotFound();
        }
        $status = $this->validateClub($app, $request, $club);
        $club = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditClub($app, $request, false, $club, $status->error);
        }
        $club->update($app->db);
        return new Redirect('/admin/clubs');
    }

    public function verifyDeleteClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/clubs/verify-delete-club', compact('club'), 'Delete Club');
    }

    public function deleteClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-club')) {
            $club->delete($app->db);
            return new Redirect('/admin/clubs');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/clubs/verify-delete-club', compact('club', 'error'), 'Delete Club');
        }
    }
}
