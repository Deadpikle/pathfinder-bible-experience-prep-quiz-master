<?php

namespace App\Controllers\Admin;

use App\Models\NonBlankableWord;
use Yamf\Request;

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

class NonBlankableWordController extends BaseAdminController implements IRequestValidator
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

    public function viewNonBlankableWords(PBEAppConfig $app, Request $request)
    {
        $nonBlankableWords = NonBlankableWord::loadAllBlankableWords($app->db);
        return new TwigView('admin/non-blankable-words/view-non-blankable-words', compact('nonBlankableWords'), 'Words');
    }

    private function showCreateOrEditNonBlankableWord(PBEAppConfig $app, Request $request, bool $isCreating, ?NonBlankableWord $nonBlankableWord, string $error = '') : Response
    {
        return new TwigView('admin/non-blankable-words/create-edit-non-blankable-word', compact('isCreating', 'nonBlankableWord', 'error'), $isCreating ? 'Create Non-blankable Word' : 'Edit Non-blankable Word');
    }

    private function validateNonBlankableWord(PBEAppConfig $app, Request $request, ?NonBlankableWord $existingNonBlankableWord) : ValidationStatus
    {
        $word = Util::validateString($request->post, 'blankable-word');

        $nonBlankableWord = new NonBlankableWord($existingNonBlankableWord->wordID ?? -1, $word);
        
        if ($word === null || $word === '') {
            return new ValidationStatus(false, $nonBlankableWord, 'Non-blankable word is required');
        }
        $loadedWord = NonBlankableWord::loadNonBlankableWordByWord($word, $app->db);
        if (($existingNonBlankableWord !== null && $loadedWord !== null && $loadedWord->wordID !== $existingNonBlankableWord->wordID) ||
            ($existingNonBlankableWord == null && $loadedWord !== null)) {
            return new ValidationStatus(false, $nonBlankableWord, 'Non-blankable word already exists');
        }

        return new ValidationStatus(true, $nonBlankableWord);
    }

    public function saveNewNonBlankableWord(PBEAppConfig $app, Request $request) : Response
    {
        $status = $this->validateNonBlankableWord($app, $request, null);
        $nonBlankableWord = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditNonBlankableWord($app, $request, true, $nonBlankableWord, $status->error);
        }
        $nonBlankableWord->create($app->db);
        return new Redirect('/admin/non-blankable-words');
    }

    public function editNonBlankableWord(PBEAppConfig $app, Request $request) : Response
    {
        $nonBlankableWord = NonBlankableWord::loadNonBlankableWordByID($request->routeParams['nonBlankableWordID'], $app->db);
        if ($nonBlankableWord === null) {
            return new NotFound();
        }
        return $this->showCreateOrEditNonBlankableWord($app, $request, false, $nonBlankableWord);
    }

    public function saveEditedNonBlankableWord(PBEAppConfig $app, Request $request) : Response
    {
        $nonBlankableWord = NonBlankableWord::loadNonBlankableWordByID($request->routeParams['nonBlankableWordID'], $app->db);
        if ($nonBlankableWord === null) {
            return new NotFound();
        }
        $status = $this->validateNonBlankableWord($app, $request, $nonBlankableWord);
        $nonBlankableWord = $status->output;
        if (!$status->didValidate) {
            return $this->showCreateOrEditNonBlankableWord($app, $request, false, $nonBlankableWord, $status->error);
        }
        $nonBlankableWord->update($app->db);
        return new Redirect('/admin/non-blankable-words');
    }

    public function verifyDeleteNonBlankableWord(PBEAppConfig $app, Request $request) : Response
    {
        $nonBlankableWord = NonBlankableWord::loadNonBlankableWordByID($request->routeParams['nonBlankableWordID'], $app->db);
        if ($nonBlankableWord === null) {
            return new NotFound();
        }
        return new TwigView('admin/non-blankable-words/verify-delete-non-blankable-word', compact('nonBlankableWord'), 'Delete Non-blankable Word');
    }

    public function deleteNonBlankableWord(PBEAppConfig $app, Request $request) : Response
    {
        $nonBlankableWord = NonBlankableWord::loadNonBlankableWordByID($request->routeParams['nonBlankableWordID'], $app->db);
        if ($nonBlankableWord === null) {
            return new NotFound();
        }
        if (CSRF::verifyToken('delete-non-blankable-word')) {
            $nonBlankableWord->delete($app->db);
            return new Redirect('/admin/non-blankable-words');
        } else {
            $error = 'Unable to validate request. Please try again.';
            return new TwigView('admin/non-blankable-words/verify-delete-non-blankable-word', compact('nonBlankableWord', 'error'), 'Delete Non-blankable Word');
        }
    }
}
