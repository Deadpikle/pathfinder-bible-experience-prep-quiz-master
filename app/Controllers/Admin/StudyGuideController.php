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
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class StudyGuideController extends BaseAdminController implements IRequestValidator
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
            if ($app->isWebAdmin || $app->isConferenceAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

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

    public function editStudyGuide(PBEAppConfig $app, Request $request) : Response
    {
        $studyGuide = StudyGuide::loadStudyGuideByID($request->routeParams['studyGuideID'], $app->db);
        if ($studyGuide === null) {
            return new NotFound();
        }
        return new TwigView('admin/study-guides/rename-study-guide', compact('studyGuide'), 'Rename Study Guide');
    }

    public function saveEditedStudyGuide(PBEAppConfig $app, Request $request) : Response
    {
        $studyGuide = StudyGuide::loadStudyGuideByID($request->routeParams['studyGuideID'], $app->db);
        if ($studyGuide === null) {
            return new NotFound();
        }
        $displayName = Util::validateString($request->post, 'display-name');
        if ($displayName === '') {
            $error = 'Display name is required';
            return new TwigView('admin/study-guides/rename-study-guide', compact('studyGuide', 'error'), 'Rename Study Guide');
        }
        StudyGuide::renameStudyGuide($studyGuide->studyGuideID, $displayName, $app->db);
        return new Redirect('/admin/study-guides');
    }

    public function verifyDeleteStudyGuide(PBEAppConfig $app, Request $request) : Response
    {
        $studyGuide = StudyGuide::loadStudyGuideByID($request->routeParams['studyGuideID'], $app->db);
        if ($studyGuide === null) {
            return new NotFound();
        }
        return new TwigView('admin/study-guides/verify-delete-study-guide', compact('studyGuide'), 'Delete Study Guide');
    }

    public function deleteStudyGuide(PBEAppConfig $app, Request $request) : Response
    {
        $studyGuide = StudyGuide::loadStudyGuideByID($request->routeParams['studyGuideID'], $app->db);
        if ($studyGuide === null) {
            return new NotFound();
        }
        if (CSRF::verifyToken('delete-study-guide')) {
            $studyGuide->delete($app->db);
            return new Redirect('/admin/study-guides');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/study-guides/verify-delete-study-guide', compact('studyGuide', 'error'), 'Delete Study Guide');
        }
    }
}
