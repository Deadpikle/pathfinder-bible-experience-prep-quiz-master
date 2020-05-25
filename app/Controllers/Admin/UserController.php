<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use Yamf\Responses\Redirect;

use App\Models\Club;
use App\Models\Conference;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\UserType;
use App\Models\ValidationStatus;
use App\Models\Views\TwigView;
use Yamf\Responses\NotFound;
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
        $clubsByID = Club::loadAllClubsKeyedByID($app->db);
        $conferencesByID = Conference::loadAllConferencesByID($app->db);
        $clubName = User::currentClubName();
        $conferenceName = User::currentConferenceName();
        $currentUserID = User::currentUserID();
        return new TwigView('admin/users/view-users', 
            compact('users', 'displayConferenceName', 'clubsByID', 'conferencesByID', 'clubName', 'conferenceName', 'currentUserID'), 
            'Users');
    }

    private function createOrEditUser(PBEAppConfig $app, Request $request, bool $isCreating, ?User $user, string $error = '') : Response
    {
        $conferencesByID = Conference::loadAllConferencesByID($app->db);
        if ($app->isWebAdmin) {
            $userTypes = UserType::loadAllUserTypes($app->db);
            $clubs = Club::loadAllClubs($app->db);
        } else if ($app->isConferenceAdmin) {
            $userTypes = UserType::loadConferenceAdminEditableUserTypes($app->db);
            // load only those clubs in conference
            $clubs = Club::loadClubsInConference(User::currentConferenceID(), $app->db);
        } else {
            $userTypes = [];
            $clubs = [];
        }

        return new TwigView('admin/users/create-edit-user', compact('isCreating', 'user', 'userTypes', 'clubs', 'conferencesByID', 'error'), $isCreating ? 'Create User' : 'Edit User');
    }

    private function validateUser(PBEAppConfig $app, Request $request, ?User $user) : ValidationStatus
    {
        $username = $request->post['username'] ?? '';
        $userTypeID = (int)$request->post['user-type'] ?? -1;
        $userType = UserType::loadUserTypeByID($userTypeID, $app->db);

        if ($app->isClubAdmin) {
            $clubID = User::currentClubID();
            $userType = UserType::loadUserTypeByName('Pathfinder', $app->db);
        } else {
            $clubID = $request->post['club'] ?? -1;
        }
        $club = Club::loadClubByID($clubID, $app->db);

        $user = new User($user->userID ?? -1, $username);
        $user->type = $userType;
        $user->clubID = $clubID;

        if ($username === null || $username === '') {
            return new ValidationStatus(false, $user, 400, 'Name is required');
        }
        if ($userType === null) {
            return new ValidationStatus(false, $user, 400, 'User type is required');
        }
        if ($club === null) {
            return new ValidationStatus(false, $user, 400, 'Club is required');
        }

        return new ValidationStatus(true, $user);
    }

    public function createUser(PBEAppConfig $app, Request $request) : Response
    {
        return $this->createOrEditUser($app, $request, true, null);
    }

    public function saveCreatedUser(PBEAppConfig $app, Request $request) : Response
    {
        $status = $this->validateUser($app, $request, null);
        $user = $status->output;
        if (!$status->didValidate) {
            return $this->createOrEditUser($app, $request, true, $user, $status->error);
        }
        $user->create($app->db);
        return new Redirect('/admin/users');
    }

    public function editUser(PBEAppConfig $app, Request $request) : Response
    {
        $user = User::loadUserByID($request->routeParams['userID'], $app->db);
        if ($user === null) {
            return new NotFound();
        }
        return $this->createOrEditUser($app, $request, false, $user);
    }

    public function saveEditedUser(PBEAppConfig $app, Request $request) : Response
    {
        $user = User::loadUserByID($request->routeParams['userID'], $app->db);
        if ($user === null) {
            return new NotFound();
        }
        $status = $this->validateUser($app, $request, $user);
        $user = $status->output;
        if (!$status->didValidate) {
            return $this->createOrEditUser($app, $request, false, $user, $status->error);
        }
        $user->update($app->db);
        return new Redirect('/admin/users');
    }

    public function verifyDeleteUser(PBEAppConfig $app, Request $request) : Response
    {
        $user = User::loadUserByID($request->routeParams['userID'], $app->db);
        if ($user === null) {
            return new NotFound();
        }
        return new TwigView('admin/users/verify-delete-user', compact('user'), 'Delete User');
    }

    public function deleteUser(PBEAppConfig $app, Request $request) : Response
    {
        $user = User::loadUserByID($request->routeParams['userID'], $app->db);
        if ($user === null) {
            return new NotFound();
        }
        if (CSRF::verifyToken('delete-user')) {
            $user->delete($app->db);
            return new Redirect('/admin/users');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/users/verify-delete-user', compact('user', 'error'), 'Delete User');
        }
    }
}
