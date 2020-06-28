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
use App\Models\ValidationStatus;
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
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/home-sections/verify-delete-line', compact('conference', 'section', 'line'), 'Delete Line');
    }

    public function deleteLine(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-line')) {
            $line->delete($app->db);
            return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/home-sections/verify-delete-line', compact('conference', 'section', 'line', 'error'), 'Delete Line');
        }
    }

    private function validateLineItem(PBEAppConfig $app, Request $request, HomeInfoLine $line, ?HomeInfoItem $existingLineItem) : ValidationStatus
    {
        $lineText = Util::validateString($request->post, 'line-text');
        $isLink = Util::validateBoolean($request->post, 'line-is-link');
        $lineURL = Util::validateURL($request->post, 'line-url');

        $lineItem = new HomeInfoItem($existingLineItem->homeInfoItemID ?? -1);
        $lineItem->text = $lineText;
        $lineItem->isLink = $isLink;
        $lineItem->url = $lineURL;
        $lineItem->homeInfoLineID = $line->homeInfoLineID;
        $lineItem->sortOrder = $existingLineItem === null ? HomeInfoItem::getSortOrder($line->homeInfoLineID, $app->db) : $existingLineItem->sortOrder;

        if ($lineText === null || $lineText === '') {
            return new ValidationStatus(false, $lineItem, 'Item text is required');
        }
        return new ValidationStatus(true, $lineItem);
    }

    private function showCreateOrEditLineItem(PBEAppConfig $app, Request $request, bool $isCreating, Conference $conference, HomeInfoSection $section, HomeInfoLine $line, ?HomeInfoItem $item, string $error = '') : Response
    {
        return new TwigView('admin/home-sections/create-edit-line-item', compact('isCreating', 'conference', 'item', 'error', 'section', 'line'), $isCreating ? 'Create Line Item' : 'Edit Line Item');
    }

    public function createLineItem(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditLineItem($app, $request, true, $conference, $section, $line, null);
    }

    public function saveNewLineItem(AppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID) {
            return new TwigNotFound();
        }
        $status = $this->validateLineItem($app, $request, $line, null);
        $item = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditLineItem($app, $request, true, $conference, $section, $line, $item);
        }
        $item->create($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
    }

    public function editLineItem(AppConfig $app, Request $request) : ?Response
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
        return $this->showCreateOrEditLineItem($app, $request, false, $conference, $section, $line, $item);
    }

    public function saveLineItemUpdates(AppConfig $app, Request $request) : ?Response
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
        $status = $this->validateLineItem($app, $request, $line, $item);
        $item = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditLineItem($app, $request, false, $conference, $section, $line, $item, $status->error);
        }
        $item->update($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
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
