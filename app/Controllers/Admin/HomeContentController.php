<?php

namespace App\Controllers\Admin;

use App\Helpers\Localization;
use App\Models\Conference;
use App\Models\CSRF;
use App\Models\HomeContent;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\User;
use App\Models\Views\TwigNotFound;
use App\Models\Views\TwigView;
use App\Models\Year;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\Response;

class HomeContentController extends BaseAdminController implements IRequestValidator
{
    private const MAX_MARKDOWN_LENGTH = 200000;

    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        $response = parent::validateRequest($app, $request);
        if ($response !== null) {
            return $response;
        }

        /** @var PBEAppConfig $app */
        $conferenceID = (int)($request->routeParams['conferenceID'] ?? 0);
        $conference = Conference::loadConferenceWithID($conferenceID, $app->db);
        if ($conference === null || (!$app->isWebAdmin && !$app->isConferenceAdmin)) {
            return new Redirect('/admin');
        }

        $globalConference = Conference::loadAdminConference($app->db);
        $isGlobal = $globalConference !== null && $conferenceID === $globalConference->conferenceID;
        if (!$app->isWebAdmin && ($isGlobal || $conferenceID !== User::currentConferenceID())) {
            return new Redirect('/admin');
        }
        return null;
    }

    public function edit(PBEAppConfig $app, Request $request): Response
    {
        $selection = $this->selection($app, $request->routeParams, $request->get);
        if ($selection === null) {
            return new TwigNotFound();
        }
        [$conference, $years, $year, $languages, $language, $globalConference] = $selection;
        $content = HomeContent::loadExact($year->yearID, $conference->conferenceID, $language->languageID, $app->db)
            ?? $this->newContent($year->yearID, $conference->conferenceID, $language->languageID);
        $renderedPreview = '';
        $error = '';
        $didSave = isset($request->get['saved']);
        $didPreview = false;
        return new TwigView(
            'admin/home-content/edit',
            compact(
                'conference',
                'years',
                'year',
                'languages',
                'language',
                'globalConference',
                'content',
                'renderedPreview',
                'error',
                'didSave',
                'didPreview'
            ),
            Localization::translate('admin.home_content_title')
        );
    }

    public function save(PBEAppConfig $app, Request $request): Response
    {
        $selection = $this->selection($app, $request->routeParams, $request->post);
        if ($selection === null) {
            return new TwigNotFound();
        }
        [$conference, $years, $year, $languages, $language, $globalConference] = $selection;
        $content = HomeContent::loadExact($year->yearID, $conference->conferenceID, $language->languageID, $app->db)
            ?? $this->newContent($year->yearID, $conference->conferenceID, $language->languageID);
        $content->markdown = trim((string)($request->post['markdown'] ?? ''));
        $content->updatedByUserID = User::currentUserID();
        $renderedPreview = '';
        $didSave = false;
        $didPreview = ($request->post['action'] ?? '') === 'preview';
        $error = '';

        if (!CSRF::verifyToken('home-content')) {
            $error = Localization::translate('settings.invalid_request');
        } elseif (mb_strlen($content->markdown) > self::MAX_MARKDOWN_LENGTH) {
            $error = 'Home content cannot exceed ' . number_format(self::MAX_MARKDOWN_LENGTH) . ' characters.';
        } elseif ($didPreview) {
            $renderedPreview = $content->render($this->placeholders($app, $year));
        } else {
            $content->save($app->db);
            return new Redirect(
                '/admin/home-content/' . $conference->conferenceID
                . '?year=' . $year->yearID
                . '&language=' . $language->languageID
                . '&saved=1'
            );
        }

        return new TwigView(
            'admin/home-content/edit',
            compact(
                'conference',
                'years',
                'year',
                'languages',
                'language',
                'globalConference',
                'content',
                'renderedPreview',
                'error',
                'didSave',
                'didPreview'
            ),
            Localization::translate('admin.home_content_title')
        );
    }

    /**
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $input
     * @return array{Conference, array<Year>, Year, array<Language>, Language, ?Conference}|null
     */
    private function selection(PBEAppConfig $app, array $routeParams, array $input): ?array
    {
        $conferenceID = (int)($routeParams['conferenceID'] ?? 0);
        $conference = Conference::loadConferenceWithID($conferenceID, $app->db);
        $years = Year::loadAllYears($app->db);
        $yearID = (int)($input['year'] ?? 0);
        $year = $yearID > 0 ? Year::loadYearByID($yearID, $app->db) : Year::loadCurrentYear($app->db);
        $languages = array_values(array_filter(
            Language::loadAllLanguages($app->db),
            fn(Language $item): bool => in_array($item->abbreviation, Localization::SUPPORTED_LOCALES, true)
        ));
        $languageID = (int)($input['language'] ?? 0);
        $language = Language::findLanguageWithID($languageID, $languages);
        if ($languageID < 1) {
            foreach ($languages as $availableLanguage) {
                if ($availableLanguage->abbreviation === Localization::DEFAULT_LOCALE) {
                    $language = $availableLanguage;
                    break;
                }
            }
        }
        $globalConference = Conference::loadAdminConference($app->db);

        if ($conference === null || $year === null || $language === null) {
            return null;
        }
        return [$conference, $years, $year, $languages, $language, $globalConference];
    }

    private function newContent(int $yearID, int $conferenceID, int $languageID): HomeContent
    {
        $content = new HomeContent();
        $content->yearID = $yearID;
        $content->conferenceID = $conferenceID;
        $content->uiLanguageID = $languageID;
        return $content;
    }

    /** @return array<string, scalar|null> */
    private function placeholders(PBEAppConfig $app, Year $year): array
    {
        return ['year' => $year->year, 'fillInChapters' => $app->currentFillInChapters];
    }
}
