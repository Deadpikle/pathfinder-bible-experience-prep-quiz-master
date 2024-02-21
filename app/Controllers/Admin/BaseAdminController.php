<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use Yamf\Responses\Redirect;

use App\Models\PBEAppConfig;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class BaseAdminController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     *
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request) : ?Response
    {
        $canViewAdminPanel = isset($_SESSION['UserType']) && $_SESSION['UserType'] !== 'Pathfinder' && $_SESSION['UserType'] !== 'Guest';
        if (!$canViewAdminPanel) {
            return new Redirect('/');
        }
        return null;
    }
}
