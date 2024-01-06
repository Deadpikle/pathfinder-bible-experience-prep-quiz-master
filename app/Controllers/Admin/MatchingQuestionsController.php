<?php

namespace App\Controllers\Admin;

use App\Models\NonBlankableWord;
use Yamf\Request;

use App\Models\CSRF;
use App\Models\Language;
use App\Models\MatchingQuestionItem;
use App\Models\MatchingQuestionSet;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\NotFound;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class MatchingQuestionsController extends BaseAdminController implements IRequestValidator
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
            /** @var PBEAppConfig $app */
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewMatchingQuestionSets(PBEAppConfig $app, Request $request): Response
    {
        $years = Year::loadAllYears($app->db);
        $yearsByID = [];
        foreach ($years as $year) {
            $yearsByID[$year->yearID] = $year;
        }
        $languages = Language::loadAllLanguages($app->db);
        $languagesByID = [];
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }
        $questionSets = MatchingQuestionSet::loadAllMatchingSets($app->db);
        return new TwigView('admin/matching-questions/view-question-sets', compact('questionSets', 'years', 'yearsByID', 'languages', 'languagesByID'), 'Matching Question Sets');
    }

    private function showCreateOrEditMatchingQuestionSet(PBEAppConfig $app, Request $request, bool $isCreating, ?MatchingQuestionSet $questionSet, string $error = '') : Response
    {
        $years = Year::loadAllYears($app->db);
        $languages = Language::loadAllLanguages($app->db);
        $currentYear = Year::loadCurrentYear($app->db);
        return new TwigView('admin/matching-questions/create-edit-question-set', compact('isCreating', 'questionSet', 'years', 'currentYear', 'languages', 'error'), $isCreating ? 'Create Matching Question Set' : 'Edit Matching Question Set');
    }

    private function validateMatchingQuestionSet(PBEAppConfig $app, Request $request, ?MatchingQuestionSet $questionSet) : ValidationStatus
    {
        $name = Util::validateString($request->post, 'name');
        $description = Util::validateString($request->post, 'description');
        $languageID = Util::validateInteger($request->post, 'langauge');
        $yearID = Util::validateInteger($request->post, 'year');

        $questionSet = new MatchingQuestionSet($questionSet->matchingQuestionSetID ?? -1, $name);
        $questionSet->description = $description;
        $questionSet->languageID = $languageID;
        $questionSet->yearID = $yearID;
        
        if ($name === null || $name === '') {
            return new ValidationStatus(false, $questionSet, 'Name is required');
        }
        $language = Language::loadLanguageWithID($languageID, $app->db);
        $year = Year::loadYearByID($yearID, $app->db);
        if ($language === null) {
            $questionSet->languageID = null;
        }
        if ($year === null) {
            return new ValidationStatus(false, $questionSet, 'You must choose a valid year');
        }

        return new ValidationStatus(true, $questionSet);
    }

    public function createMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        return $this->showCreateOrEditMatchingQuestionSet($app, $request, true, null);
    }

    // getHexChar from http://forums.devshed.com/php-development-5/comparing-hex-values-comprising-string-249095.html
    private function getHexChar($hexCode): string
    {
        return chr(hexdec($hexCode));
    }

    public function saveCreatedMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        $status = $this->validateMatchingQuestionSet($app, $request, null);
        $questionSet = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditMatchingQuestionSet($app, $request, true, $questionSet, $status->error);
        }
        /** @var MatchingQuestionSet $questionSet */
        $questionSet->create($app->db);
        $numCreated = 0;
        if (isset($request->files['csv']) && isset($request->files['csv']['tmp_name'])) {
            // user wants to import questions
            $tmpName = $request->files['csv']['tmp_name'];
            $contents = file_get_contents($tmpName);
            // check if UTF-8 encoded file
            if ($contents[0] == $this->getHexChar('EF') && $contents[1] == $this->getHexChar('BB') && $contents[2] == $this->getHexChar('BF')) {
                $contents = substr($contents, 3);
            }
            // split file by items
            $rows = explode("\r", $contents);
            // get csv data
            $csv = array_map('str_getcsv', $rows);
            // import data
            foreach ($csv as $row) {
                /** @var array $row */
                if (count($row) > 1) {
                    $question = trim($row[0]);
                    $answer = trim($row[1]);
                    if ($question === '' && $answer === '') {
                        break; // must be done, encountered blank row
                    }
                    if ($question === '' || $answer === '') {
                        continue;
                    }
                    $questionItem = new MatchingQuestionItem(-1, $question, $answer);
                    $questionItem->creatorID = User::currentUserID();
                    $questionItem->lastEditedByID = User::currentUserID();
                    $questionItem->dateCreated = date('Y-m-d H:i:s');
                    $questionItem->dateModified = date('Y-m-d H:i:s');
                    $questionItem->matchingQuestionSetID = $questionSet->matchingQuestionSetID;
                    $questionItem->create($app->db);
                    $numCreated++;
                } else {
                    break;
                }
            }
        }

        return new Redirect('/admin/matching-question-sets' . ($numCreated > 0 ? '?created=' . $numCreated : ''));
    }

    public function editMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        if ($questionSet === null) {
            return new TwigNotFound();
        }
        return $this->showCreateOrEditMatchingQuestionSet($app, $request, false, $questionSet);
    }

    public function saveEditedMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        if ($questionSet === null) {
            return new TwigNotFound();
        }
        $status = $this->validateMatchingQuestionSet($app, $request, $questionSet);
        $questionSet = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditMatchingQuestionSet($app, $request, false, $questionSet, $status->error);
        }
        $questionSet->update($app->db);
        return new Redirect('/admin/matching-question-sets');
    }

    public function verifyDeleteMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        if ($questionSet === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/matching-questions/verify-delete-question-set', compact('questionSet'), 'Delete Matching Question Set');
    }

    public function deleteMatchingQuestionSet(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        if ($questionSet === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-matching-question-set')) {
            $questionSet->delete($app->db);
            return new Redirect('/admin/matching-question-sets');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/matching-questions/verify-delete-question-set', compact('questionSet', 'error'), 'Delete Matching Question Set');
        }
    }

    // questions in set
    public function viewMatchingQuestionsForSet(PBEAppConfig $app, Request $request): Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        if ($questionSet === null) {
            return new TwigNotFound();
        }
        $years = Year::loadAllYears($app->db);
        $languages = Language::loadAllLanguages($app->db);
        return new TwigView('admin/matching-questions/view-questions-in-set', compact('questionSet', 'years', 'languages'), 'View Questions in Set');
    }

    public function verifyDeleteMatchingQuestion(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        $question = MatchingQuestionItem::loadQuestionItemByID(Util::validateInteger($request->routeParams, 'matchingQuestionItemID'), $app->db);
        if ($questionSet === null || $question === null) {
            return new TwigNotFound();
        }
        return new TwigView('admin/matching-questions/verify-delete-question', compact('questionSet', 'question'), 'Delete Matching Question');
    }

    public function deleteMatchingQuestion(PBEAppConfig $app, Request $request) : Response
    {
        $questionSet = MatchingQuestionSet::loadMatchingSetByID(Util::validateInteger($request->routeParams, 'matchingQuestionSetID'), $app->db);
        $question = MatchingQuestionItem::loadQuestionItemByID(Util::validateInteger($request->routeParams, 'matchingQuestionItemID'), $app->db);
        if ($questionSet === null || $question === null) {
            return new TwigNotFound();
        }
        if (CSRF::verifyToken('delete-matching-question')) {
            $question->delete($app->db);
            return new Redirect('/admin/matching-question-sets/' . $questionSet->matchingQuestionSetID . '/questions');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/matching-questions/verify-delete-question', compact('questionSet', 'question', 'error'), 'Delete Matching Question');
        }
    }
}
