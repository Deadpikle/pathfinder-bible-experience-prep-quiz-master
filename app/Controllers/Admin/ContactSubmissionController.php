<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use Yamf\Responses\Redirect;

use App\Models\ContactFormSubmission;
use App\Models\PBEAppConfig;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class ContactSubmissionController extends BaseAdminController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     * 
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        $response = parent::validateRequest($app, $request);
        if ($response === null) {
            /** @var PBEAppConfig $app */
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewContactSubmissions(PBEAppConfig $app, Request $request): Response
    {
        $submissions = ContactFormSubmission::loadAllSubmissions($app->db);
        return new TwigView('admin/contact-submissions/view-contact-submissions', compact('submissions'), 'Contact Submissions');
    }
}
