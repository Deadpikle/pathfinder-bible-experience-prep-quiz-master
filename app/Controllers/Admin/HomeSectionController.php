<?php

namespace App\Controllers\Admin;

use App\Models\Conference;
use App\Models\CSRF;
use App\Models\HomeInfoItem;
use App\Models\HomeInfoLine;
use App\Models\HomeInfoSection;
use App\Models\PBEAppConfig;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

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
            $currentConferenceID = $request->routeParams['conferenceID'];
            $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
            if ($conference === null) {
                return new Redirect('/admin');
            }
            if (!$app->isWebAdmin && !$app->isConferenceAdmin) {
                return new Redirect('/admin');
            }
        }
        return $response;
    }

    public function viewHomeSections(PBEAppConfig $app, Request $request) : Response
    {
        $years = Year::loadAllYears($app->db);
        $conferences = Conference::loadAllConferences($app->db);
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        $sections = HomeInfoSection::loadSections(Year::loadCurrentYear($app->db), $currentConferenceID, $app->db);
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/home-sections/view-home-sections', compact('years', 'conferences', 'conference', 'currentConferenceID', 'sections', 'currentYear'), 'Home Section Info');
    }

    public function copyFromYear(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        // copying from current conference to same conference in the given year
        $year = Year::loadYearByID($request->post['year'], $app->db);
        HomeInfoSection::copyHomeSections($currentConferenceID, $currentConferenceID, $year->yearID, $app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections');
    }

    public function copyFromAdmin(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        // copying from admin to current conference in the given year
        $year = Year::loadYearByID($request->post['year'], $app->db);
        $adminConference = Conference::loadAdminConference($app->db);
        HomeInfoSection::copyHomeSections($adminConference->conferenceID, $currentConferenceID, $year->yearID, $app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections');
    }

    private function validateSection(PBEAppConfig $app, Request $request, Conference $conference, ?HomeInfoSection $existingSection) : ValidationStatus
    {
        $name = Util::validateString($request->post, 'section-name');
        $subtitle = Util::validateString($request->post, 'section-subtitle');

        $year = Year::loadCurrentYear($app->db);
        $section = new HomeInfoSection($existingSection->homeInfoSectionID ?? -1, $name);
        $section->subtitle = $subtitle;
        $section->sortOrder = $existingSection === null ? HomeInfoSection::getSortOrderForConferenceInYear($conference->conferenceID, $year->yearID, $app->db) : $existingSection->sortOrder;
        $section->yearID = $year->yearID;
        $section->conferenceID = $conference->conferenceID;

        if ($name === null || $name === '') {
            return new ValidationStatus(false, $section, 'Section name is required');
        }
        return new ValidationStatus(true, $section);
    }

    private function showCreateOrEditSection(PBEAppConfig $app, Request $request, bool $isCreating, Conference $conference, HomeInfoSection $section, string $error = '') : Response
    {
        return new TwigView('admin/home-sections/create-edit-section', compact('isCreating', 'conference', 'section', 'error'), $isCreating ? 'Create Section' : 'Edit Section');
    }

    public function createHomeSection(PBEAppConfig $app, Request $request) : Response
    {
        // POST request from main section listing for conference
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        $status = $this->validateSection($app, $request, $conference, null);
        $section = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditSection($app, $request, true, $conference, $section, $status->error);
        }
        $section->create($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections');
    }

    public function changeHomeSectionConference(PBEAppConfig $app, Request $request) : Response
    {
        // validate current URL data
        echo '?';
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        if ($conference === null) {
            return new TwigNotFound();
        }
        // redirect
        $goToConferenceID = Util::validateInteger($request->post, 'changeToConferenceID');
        if ($goToConferenceID > 0) {
            return new Redirect('/admin/home-sections/' . $goToConferenceID . '/sections');
        }
        return new Redirect('/admin/home-sections/' . $currentConferenceID . '/sections');
    }

    public function saveSectionSorting(PBEAppConfig $app, Request $request) : Response
    {
        $data = json_decode($_POST['json'], true);
        if (HomeInfoSection::saveSortOrder($data, $app->db)) {
            return new Response(200);
        }
        return new Response(400);
    }

    public function editHomeSection(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        if ($conference === null || $section === null || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditSection($app, $request, false, $conference, $section);
    }

    public function saveHomeSectionUpdates(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        if ($conference === null || $section === null || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        $status = $this->validateSection($app, $request, $conference, $section);
        $section = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditSection($app, $request, false, $conference, $section, $status->error);
        }
        $section->update($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections');
    }

    public function verifyDeleteHomeSection(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        if ($conference === null || $section === null || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/home-sections/verify-delete-section', compact('conference', 'section'), 'Delete Section');
    }

    public function deleteHomeSection(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        if ($conference === null || $section === null || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-section')) {
            $section->delete($app->db);
            return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/home-sections/verify-delete-section', compact('conference', 'section', 'error'), 'Delete Section');
        }
    }

    public function viewLines(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $sectionID = $request->routeParams['sectionID'];
        $section = HomeInfoSection::loadSectionByID($sectionID, $app->db);
        if ($conference === null || $section === null) {
            return new TwigNotFound();
        }
        $lines = HomeInfoLine::loadLinesForSection($sectionID, $app->db);
        return new TwigView('admin/home-sections/view-lines-for-section', compact('sectionID', 'conference', 'lines', 'section'), 'Home Section - Lines');
    }

    public function saveLineSorting(PBEAppConfig $app, Request $request) : Response
    {
        $data = json_decode($request->post['json'], true);
        $didSucceed = HomeInfoLine::saveSorting($data, $app->db);
        return new Response($didSucceed ? 200 : 400);
    }

    public function createNewLine(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        if ($conference === null || $section === null || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        $sortOrder = HomeInfoLine::getSortOrderForSection($section->homeInfoSectionID, $app->db);
        $line = new HomeInfoLine(-1, '');
        $line->sortOrder = $sortOrder;
        $line->homeInfoSectionID = $section->homeInfoSectionID;
        $line->create($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
    }

    public function verifyDeleteLine(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID
            || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/home-sections/verify-delete-line', compact('conference', 'section', 'line'), 'Delete Line');
    }

    public function deleteLine(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID  
            || $section->conferenceID !== $conference->conferenceID) {
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

    public function createLineItem(PBEAppConfig $app, Request $request) : ?Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditLineItem($app, $request, true, $conference, $section, $line, null);
    }

    public function saveNewLineItem(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        if ($conference === null || $section === null || $line === null || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        $status = $this->validateLineItem($app, $request, $line, null);
        $item = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditLineItem($app, $request, true, $conference, $section, $line, $item, $status->error);
        }
        $item->create($app->db);
        return new Redirect('/admin/home-sections/' . $conference->conferenceID . '/sections/' . $section->homeInfoSectionID . '/lines');
    }

    public function editLineItem(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditLineItem($app, $request, false, $conference, $section, $line, $item);
    }

    public function saveLineItemUpdates(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
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

    public function verifyDeleteLineItem(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
            return new TwigNotFound();
        }
        return new TwigView('admin/home-sections/verify-delete-line-item', compact('conference', 'section', 'line', 'item'), 'Delete Line Item');
    }

    public function deleteLineItem(PBEAppConfig $app, Request $request) : Response
    {
        $currentConferenceID = $request->routeParams['conferenceID'];
        $conference = Conference::loadConferenceWithID($currentConferenceID, $app->db);
        $section = HomeInfoSection::loadSectionByID($request->routeParams['sectionID'], $app->db);
        $line = HomeInfoLine::loadLineByID($request->routeParams['lineID'], $app->db);
        $item = HomeInfoItem::loadItemByID($request->routeParams['itemID'], $app->db);
        if ($conference === null || $section === null || $line === null || $item === null ||
            $item->homeInfoLineID !== $line->homeInfoLineID || $line->homeInfoSectionID !== $section->homeInfoSectionID 
            || $section->conferenceID !== $conference->conferenceID) {
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
