<?php

namespace App\Controllers\Admin;

use App\Models\Conference;
use App\Models\CSRF;
use App\Models\HomeInfoItem;
use App\Models\HomeInfoLine;
use App\Models\HomeInfoSection;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\PBEAppConfig;
use App\Models\Setting;
use App\Models\User;
use App\Models\Util;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class HomeSectionController extends BaseAdminController implements IRequestValidator
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
            // TODO: verify conference
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewHomeSections(AppConfig $app, Request $request) : ?Response
    {
        $years = Year::loadAllYears($app->db);
        $conferences = Conference::loadAllConferences($app->db);
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        $sections = HomeInfoSection::loadSections(Year::loadCurrentYear($app->db), $currentConferenceID, $app->db);

        return new TwigView('admin/home-sections/view-home-sections', compact('years', 'conferences', 'currentConferenceID', 'sections'), 'Home Info');
    }

    public function createHomeSection(AppConfig $app, Request $request) : ?Response
    {
    }

    public function changeHomeSectionConference(AppConfig $app, Request $request) : ?Response
    {
    }

    public function saveSectionSorting(AppConfig $app, Request $request) : ?Response
    {
    }

    public function editHomeSection(AppConfig $app, Request $request) : ?Response
    {
    }

    public function verifyDeleteHomeSection(AppConfig $app, Request $request) : ?Response
    {
    }

    public function deleteHomeSection(AppConfig $app, Request $request) : ?Response
    {
    }

    public function viewLines(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $sectionID = $request->routeParams['sectionID'];
        $section = HomeInfoSection::loadSectionByID($sectionID, $app->db);
        if ($conference === null || $section === null) {
            return new TwigNotFound();
        }
        // TODO: finish editing links in view
        $lines = HomeInfoLine::loadLinesForSection($sectionID, $app->db);
        return new TwigView('admin/home-sections/view-lines-for-section', compact('sectionID', 'conference', 'lines', 'section'), 'Home Section - Lines');
    }

    public function saveLineSorting(AppConfig $app, Request $request) : ?Response
    {
    }

    public function createLine(AppConfig $app, Request $request) : ?Response
    {
    }

    public function saveNewLine(AppConfig $app, Request $request) : ?Response
    {
    }

    public function verifyDeleteLine(AppConfig $app, Request $request) : ?Response
    {
    }

    public function deleteLine(AppConfig $app, Request $request) : ?Response
    {
    }

    public function createLineItem(AppConfig $app, Request $request) : ?Response
    {
    }

    public function saveNewLineItem(AppConfig $app, Request $request) : ?Response
    {
    }

    public function editLineItem(AppConfig $app, Request $request) : ?Response
    {
    }

    public function saveLineItemUpdates(AppConfig $app, Request $request) : ?Response
    {
    }

    public function verifyDeleteLineItem(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/home-sections/verify-delete-line-item', compact('conference', 'section', 'line', 'item'), 'Delete Line Item');
    }

    public function deleteLineItem(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-line-item')) {
            $item->delete($app->db);
            return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/home-sections/verify-delete-line-item', compact('conference', 'section', 'line', 'item', 'error'), 'Delete Line Item');
        }
    }
}
