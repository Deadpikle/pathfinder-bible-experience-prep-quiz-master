<?php

namespace App\Controllers\Admin;

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

class StudyGuideController extends BaseAdminController
{
    public function viewStudyGuides(PBEAppConfig $app, Request $request)
    {
        if ($app->isWebAdmin) {
            $studyGuides = StudyGuide::loadAllStudyGuides($app->db);
        } else {
            $studyGuides = StudyGuide::loadCurrentStudyGuides(Year::loadCurrentYear($app->db), $app->db);
        }
        return new TwigView('admin/study-guides/view-study-guides', compact('studyGuides'), 'Study Guides');
    }

    private function showViewStudyGuideUpload(PBEAppConfig $app, Request $request, string $error = '', string $displayName = '') : Response
    {
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/study-guides/upload-study-guide', compact('currentYear', 'error', 'displayName'), 'Upload Study Guide');
    }

    public function viewStudyGuideUpload(PBEAppConfig $app, Request $request) : Response
    {
        return $this->showViewStudyGuideUpload($app, $request, '');
    }

    public function uploadStudyGuide(PBEAppConfig $app, Request $request) : Response
    {
        // TODO: refactor to a model or service or something
        // A bunch of code here is from http://php.net/manual/en/features.file-upload.php
        $errorMessage = "";
        $displayName = trim(Util::validateString($request->post, 'display-name'));
        if ($displayName === '') {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: display name is required', $displayName);
        }
        if (!isset($_FILES['file-upload'])) {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: file is required', $displayName);
        }
        // Undefined | Multiple Files | $_FILES Corruption Attack
        // If this request falls under any of them, treat it invalid.
        if (!isset($_FILES['file-upload']['error']) || is_array($_FILES['file-upload']['error'])) {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: ' . $_FILES['file-upload']['error'], $displayName);
        }

        // Check $_FILES['file-upload']['error'] value.
        switch ($_FILES['file-upload']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "No file sent";
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "Exceeded filesize limit";
                break;
            default:
                $errorMessage = "Unknown error";
                break;
        }

        if ($errorMessage !== "") {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: ' . $errorMessage);
        }

        // Max upload size is 10 MB
        if ($_FILES['file-upload']['size'] > 10 * 1024 * 1024 * 1024) {
            $errorMessage = "Exceeded filesize limit";
            return $this->showViewStudyGuideUpload($app, $request, 'Error: Exceeded filesize limit');
        }

        // DO NOT TRUST $_FILES['file-upload']['mime'] VALUE !!
        // Check MIME Type by yourself.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (false === $ext = array_search($finfo->file($_FILES['file-upload']['tmp_name']), array('pdf' => 'application/pdf'), true)) {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: Invalid file type', $displayName);
        }

        // DO NOT USE $_FILES['file-upload']['name'] WITHOUT ANY VALIDATION!!
        // On this example, obtain safe unique name from its binary data.
        $fileName = Util::generateUUID() . '.pdf';
        $uploadsFolder = 'uploads';
        $filePath = 'uploads/' . $fileName;
        if (!file_exists($uploadsFolder)) {
            if (!mkdir($uploadsFolder)) {
                return $this->showViewStudyGuideUpload($app, $request, 'Error: Unable to create upload directory', $displayName);
            }
        }
        if (!move_uploaded_file($_FILES['file-upload']['tmp_name'], $filePath)) {
            return $this->showViewStudyGuideUpload($app, $request, 'Error: Failed to move uploaded file', $displayName);
        }

        // upload success? insert information into the database
        // use try/catch to make sure we can delete the file if this fails
        $currentYear = Year::loadCurrentYear($app->db);
        try {
            StudyGuide::createStudyGuide($fileName, $displayName, $currentYear->yearID, $app->db);
        }
        catch (PDOException $e) {
            unlink($filePath);
            return $this->showViewStudyGuideUpload($app, $request, 'Error inserting database information: ' . $e->getMessage(), $displayName);
        }

        // if we get here, we did everything right!
        return new Redirect('/admin/study-guides');
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
            return new ValidationStatus(false, $club, 400, 'Club name is required');
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
            return new NotFound();
        }
        return $this->showCreateOrEditClub($app, $request, false, $club);
    }

    public function saveEditedClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new NotFound();
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
            return new NotFound();
        }
        return new TwigView('admin/clubs/verify-delete-club', compact('club'), 'Delete Club');
    }

    public function deleteClub(PBEAppConfig $app, Request $request) : Response
    {
        $club = Club::loadClubByID($request->routeParams['clubID'], $app->db);
        if ($club === null) {
            return new NotFound();
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
