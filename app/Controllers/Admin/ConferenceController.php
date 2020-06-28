<?php

namespace App\Controllers\Admin;

use Yamf\Request;

use App\Models\Conference;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class ConferenceController extends BaseAdminController implements IRequestValidator
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
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewConferences(PBEAppConfig $app, Request $request)
    {
        $conferences = Conference::loadAllNonAdminConferences($app->db);
        return new TwigView('admin/conferences/view-conferences', compact('conferences'), 'Conferences');
    }

    private function showCreateOrEditConference(PBEAppConfig $app, Request $request, bool $isCreating, ?Conference $conference, string $error = '') : Response
    {
        return new TwigView('admin/conferences/create-edit-conference', compact('isCreating', 'conference', 'error'), $isCreating ? 'Create Conference' : 'Edit Conference');
    }

    private function validateConference(PBEAppConfig $app, Request $request, ?Conference $conference) : ValidationStatus
    {
        $name = $request->post['name'] ?? '';
        $url = $request->post['url'] ?? '';
        $contactName = Util::validateString($request->post, 'contact-name');
        $contactEmail = Util::validateEmail($request->post, 'contact-email');

        $conference = new Conference($conference->conferenceID ?? -1, $name);
        $conference->url = $url;
        $conference->contactName = $contactName;
        $conference->contactEmail = $contactEmail;
        
        if ($name === null || $name === '') {
            return new ValidationStatus(false, $conference, 'Conference name is required');
        }
        if ($url === null || $url === '') {
            return new ValidationStatus(false, $conference, 'Conference URL is required');
        }
        if ($contactName === null || $contactName === '') {
            return new ValidationStatus(false, $conference, 'Conference contact name is required (use N/A if unavailable)');
        }
        if ($contactEmail === null || $contactEmail === '') {
            return new ValidationStatus(false, $conference, 'Conference contact email is required (use somebody@nowhere.com if unavailable)');
        }

        return new ValidationStatus(true, $conference);
    }

    public function createConference(PBEAppConfig $app, Request $request) : Response
    {
        return $this->showCreateOrEditConference($app, $request, true, null);
    }

    public function saveCreatedConference(PBEAppConfig $app, Request $request) : Response
    {
        $status = $this->validateConference($app, $request, null);
        $conference = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditConference($app, $request, true, $conference, $status->error);
        }
        $conference->create($app->db);
        return new Redirect('/admin/conferences');
    }

    public function editConference(PBEAppConfig $app, Request $request) : Response
    {
        $conference = Conference::loadConferenceWithID($request->routeParams['conferenceID'], $app->db);
        if ($conference === null) {
            return new NotFound();
        }
        return $this->showCreateOrEditConference($app, $request, false, $conference);
    }

    public function saveEditedConference(PBEAppConfig $app, Request $request) : Response
    {
        $conference = Conference::loadConferenceWithID($request->routeParams['conferenceID'], $app->db);
        if ($conference === null) {
            return new NotFound();
        }
        $status = $this->validateConference($app, $request, $conference);
        $conference = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditConference($app, $request, false, $conference, $status->error);
        }
        $conference->update($app->db);
        return new Redirect('/admin/conferences');
    }

    public function verifyDeleteConference(PBEAppConfig $app, Request $request) : Response
    {
        $conference = Conference::loadConferenceWithID($request->routeParams['conferenceID'], $app->db);
        if ($conference === null) {
            return new NotFound();
        }
        return new TwigView('admin/conferences/verify-delete-conference', compact('conference'), 'Delete Conference');
    }

    public function deleteConference(PBEAppConfig $app, Request $request) : Response
    {
        $conference = Conference::loadConferenceWithID($request->routeParams['conferenceID'], $app->db);
        if ($conference === null) {
            return new NotFound();
        }
        if (CSRF::verifyToken('delete-conference')) {
            $conference->delete($app->db);
            return new Redirect('/admin/conferences');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/conferences/verify-delete-conference', compact('conference', 'error'), 'Delete Conference');
        }
    }
}
