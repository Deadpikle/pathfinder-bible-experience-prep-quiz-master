<?php

namespace App\Controllers\Admin;

use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\Util;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class YearController extends BaseAdminController implements IRequestValidator
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

    public function viewYears(AppConfig $app, Request $request): Response
    {
        $years = Year::loadAllYears($app->db);
        return new TwigView('/admin/years/view-years', compact('years'), 'Years');
    }

    public function addYear(AppConfig $app, Request $request): Response
    {
        Year::addYear(Util::validateInteger($request->post, 'year'), $app->db);
        return new Redirect('/admin/years');
    }

    public function verifyMakeYearCurrent(AppConfig $app, Request $request): Response
    {
        $year = Year::loadYearByID($request->routeParams['yearID'], $app->db);
        if ($year === null) {
            return new TwigNotFound();
        }
        return new TwigView('/admin/years/verify-make-year-current', compact('year'), 'Change Current Year');
    }

    public function makeYearCurrent(AppConfig $app, Request $request): Response
    {
        $year = Year::loadYearByID($request->routeParams['yearID'], $app->db);
        if ($year === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('make-year-current')) {
            Year::makeYearCurrentYear($year->yearID, $app->db);
            return new Redirect('/admin/years');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('/admin/years/verify-make-year-current', compact('year', 'error'), 'Change Current Year');
        }
    }
}
