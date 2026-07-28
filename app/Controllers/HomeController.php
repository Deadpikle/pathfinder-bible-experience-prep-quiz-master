<?php

namespace App\Controllers;

use App\Helpers\Localization;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Club;
use App\Models\Conference;
use App\Models\ContactFormSubmission;
use App\Models\CSRF;
use App\Models\HomeContent;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\StudyGuide;
use App\Models\User;
use App\Models\Util;
use App\Models\ValidationStatus;
use App\Models\Views\TwigErrorMessage;
use App\Models\Views\TwigView;
use App\Models\Year;
use App\Services\StatsLoader;
use App\Services\QuestionScope;
use ReCaptcha\ReCaptcha;
use Yamf\Responses\Response;

class HomeController
{
    public function index(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $globalConference = Conference::loadAdminConference($app->db);
        $conferenceID = User::currentConferenceID();
        $year = Year::loadCurrentYear($app->db);
        $uiLanguage = User::getUILanguage($app->db);
        $englishLanguage = Language::loadEnglishLanguage($app->db);
        $homeContents = [];
        if ($globalConference !== null && $year !== null) {
            $homeContents = HomeContent::loadForHome(
                $year->yearID,
                $conferenceID,
                $globalConference->conferenceID,
                $uiLanguage->languageID,
                $englishLanguage->languageID,
                $app->db
            );
        }

        if ($homeContents === []) {
            $defaultContent = new HomeContent();
            $defaultContent->markdown = HomeContent::defaultEnglishMarkdown();
            $defaultContent->isGlobal = true;
            $defaultContent->isLanguageFallback = $uiLanguage->abbreviation !== 'en';
            $homeContents[] = $defaultContent;
        }

        $renderedHomeContents = [];
        foreach ($homeContents as $content) {
            $renderedHomeContents[] = [
                'html' => $content->render([
                    'year' => $year?->year ?? $app->activeYearNumber,
                    'fillInChapters' => $app->currentFillInChapters,
                ]),
                'isGlobal' => $content->isGlobal,
                'isLanguageFallback' => $content->isLanguageFallback,
            ];
        }
        return new TwigView('home/index', compact('renderedHomeContents'), Localization::translate('home.title'));
    }

    public function message(PBEAppConfig $app, Request $request)
    {
        return new TwigView('home/message', [], 'Message to Parents/Club Leaders');
    }

    public function showLoginScreen(PBEAppConfig $app, Request $request)
    {
        if ($app->loggedIn) {
            return new Redirect('/');
        }
        return new TwigView('home/login', [], 'Login');
    }

    public function attemptLogin(PBEAppConfig $app, Request $request): Response
    {
        if ($app->loggedIn) {
            return new Redirect('/');
        }
        // TODO: this code needs to be refactored to models
        $query = '
            SELECT UserID, Username, ut.Type AS UserType, c.ClubID AS ClubID, c.Name AS ClubName,
                conf.ConferenceID, conf.Name AS ConferenceName, u.PreferredLanguageID, u.UILanguageID
            FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
                LEFT JOIN Clubs c ON u.ClubID = c.ClubID
                LEFT JOIN Conferences conf ON c.ConferenceID = conf.ConferenceID
            WHERE EntryCode = ? AND u.WasDeleted = 0 ';
        $stmt = $app->db->prepare($query);
        $params = [
            $request->post['access-code']
        ];
        $stmt->execute($params);
        if ($row = $stmt->fetch()) {
            // Login success!
            // Update the database
            User::updateLastLoginDateForUserID($row['UserID'], $app->db);
            // Update the session
            $_SESSION['UserID'] = $row['UserID'];
            $_SESSION['Username'] = $row['Username'];
            $_SESSION['UserType'] = $row['UserType'];
            $_SESSION['ClubID'] = $row['ClubID'] != null ? $row['ClubID'] : -1;
            $_SESSION['ClubName'] = $row['ClubName'];
            $_SESSION['ConferenceID'] = $row['ConferenceID'] != null ? $row['ConferenceID'] : -1;
            $_SESSION['ConferenceName'] = $row['ConferenceName'];
            $_SESSION['PreferredLanguageID'] = $row['PreferredLanguageID'];
            $uiLanguage = Language::loadEnabledUILanguageWithID((int)$row['UILanguageID'], $app->db)
                ?? Language::loadDefaultUILanguage($app->db);
            $_SESSION['UILanguageID'] = $uiLanguage->languageID;
            $_SESSION['UILanguageAbbreviation'] = $uiLanguage->abbreviation;
            return new Redirect('/');
        } else {
            $error = 'Invalid access code';
            return new TwigView('home/login', compact('error'), 'Login');
        }
    }

    public function logout(PBEAppConfig $app, Request $request): Response
    {
        session_regenerate_id(false);
        session_destroy();
        return new Redirect('/login');
    }

    public function about(PBEAppConfig $app, Request $request): Response
    {
        $conferences = Conference::loadNonWebsiteConferences($app->db);
        return new TwigView('home/about', compact('conferences'), 'About');
    }

    public function activeClubs(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $conferences = Conference::loadAllConferencesByID($app->db);
        
        $thirtyDayClubs = Club::loadRecentlyActiveClubs($app->db, 30);
        $thirtyDayConferenceCounts = [];
        foreach ($thirtyDayClubs as $club) {
            if (!isset($thirtyDayConferenceCounts[$club->conferenceID])) {
                $thirtyDayConferenceCounts[$club->conferenceID] = 1;
            } else {
                $thirtyDayConferenceCounts[$club->conferenceID] += 1;
            }
        }
        $thirtyDayClubCount = count($thirtyDayClubs);

        $yearClubs = Club::loadRecentlyActiveClubs($app->db, 365);
        $yearConferenceCounts = [];
        foreach ($yearClubs as $club) {
            if (!isset($yearConferenceCounts[$club->conferenceID])) {
                $yearConferenceCounts[$club->conferenceID] = 1;
            } else {
                $yearConferenceCounts[$club->conferenceID] += 1;
            }
        }
        $yearClubCount = count($yearClubs);

        return new TwigView('home/active-clubs', compact('thirtyDayClubs', 'conferences', 'thirtyDayConferenceCounts', 'thirtyDayClubCount', 'yearClubs', 'yearConferenceCounts', 'yearClubCount'), 'Active Clubs');
    }

    public function studyGuides(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $currentYear = Year::loadCurrentYear($app->db);
        $guides = StudyGuide::loadCurrentStudyGuides($currentYear, $app->db);
        return new TwigView('home/study-guides', compact('guides'), 'Study Guides');
    }

    public function viewSettings(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $languages = Language::loadAllLanguages($app->db);
        $uiLanguages = Language::loadUIEnabledLanguages($app->db);
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages)
            ?? Language::loadDefaultLanguage($app->db);
        $userUILanguage = Language::findLanguageWithID(User::getUILanguageID(), $uiLanguages)
            ?? Language::loadDefaultUILanguage($app->db);

        $didUpdate = false;
        $error = '';
        return new TwigView(
            'home/settings',
            compact('languages', 'uiLanguages', 'userLanguage', 'userUILanguage', 'didUpdate', 'error'),
            Localization::translate('home.settings')
        );
    }

    public function updateSettings(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $languages = Language::loadAllLanguages($app->db);
        $uiLanguages = Language::loadUIEnabledLanguages($app->db);
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages)
            ?? Language::loadDefaultLanguage($app->db);
        $userUILanguage = Language::findLanguageWithID(User::getUILanguageID(), $uiLanguages)
            ?? Language::loadDefaultUILanguage($app->db);
        $didUpdate = false;
        $error = '';

        if (!CSRF::verifyToken('user-settings')) {
            $error = Localization::translate('settings.invalid_request');
            return new TwigView(
                'home/settings',
                compact('languages', 'uiLanguages', 'userLanguage', 'userUILanguage', 'didUpdate', 'error'),
                Localization::translate('home.settings')
            );
        }

        $languageIDToUse = (int)($request->post['language-select'] ?? 0);
        $uiLanguageIDToUse = (int)($request->post['ui-language-select'] ?? 0);
        $requestedQuestionLanguage = Language::findLanguageWithID($languageIDToUse, $languages);
        $requestedUILanguage = Language::findLanguageWithID($uiLanguageIDToUse, $uiLanguages);
        if ($requestedQuestionLanguage === null || $requestedUILanguage === null) {
            $error = Localization::translate('settings.invalid_language');
            return new TwigView(
                'home/settings',
                compact('languages', 'uiLanguages', 'userLanguage', 'userUILanguage', 'didUpdate', 'error'),
                Localization::translate('home.settings')
            );
        }

        User::updatePreferredLanguage(User::currentUserID(), $requestedQuestionLanguage->languageID, $app->db);
        User::updateUILanguage(User::currentUserID(), $requestedUILanguage->languageID, $app->db);
        $_SESSION['PreferredLanguageID'] = $requestedQuestionLanguage->languageID;
        $_SESSION['UILanguageID'] = $requestedUILanguage->languageID;
        $_SESSION['UILanguageAbbreviation'] = $requestedUILanguage->abbreviation;
        $userLanguage = $requestedQuestionLanguage;
        $userUILanguage = $requestedUILanguage;

        $prefersDarkMode = Util::validateBoolean($request->post, 'prefers-dark-mode');
        User::updateDarkModePreference(User::currentUserID(), $prefersDarkMode, $app->db);

        $didUpdate = true;
        return new TwigView(
            'home/settings',
            compact('languages', 'uiLanguages', 'userLanguage', 'userUILanguage', 'didUpdate', 'error'),
            Localization::translate('home.settings')
        );
    }

    public function currentYearStats(PBEAppConfig $app, Request $request): Response
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $languagesByID = Language::loadAllLanguagesByID($app->db);
        $year = Year::loadCurrentYear($app->db);
        $questionScope = QuestionScope::forApp($app, $app->db);

        $chapterStats = StatsLoader::loadQnAQuestionsByChapterInYear($year->yearID, $app->db, $questionScope);
        $verseStats = StatsLoader::loadQnAQuestionsByChapterAndVerseInYear($year->yearID, $app->db, $questionScope);
        $commentaryStats = StatsLoader::loadCommentaryQuestionsByYear($year->yearID, $app->db, $questionScope);

        $chapterStatsByLanguageID = [];
        foreach ($chapterStats as $chapterStat) {
            if (!isset($chapterStatsByLanguageID[$chapterStat['language']])) {
                $chapterStatsByLanguageID[$chapterStat['language']] = [];
            }
            $chapterStatsByLanguageID[$chapterStat['language']][] = $chapterStat;
        }
        $verseStatsByLanguageID = [];
        foreach ($verseStats as $verseStat) {
            if (!isset($verseStatsByLanguageID[$verseStat['language']])) {
                $verseStatsByLanguageID[$verseStat['language']] = [];
            }
            $verseStatsByLanguageID[$verseStat['language']][] = $verseStat;
        }
        $commentaryStatsByLanguageID = [];
        foreach ($commentaryStats as $commentaryStat) {
            if (!isset($commentaryStatsByLanguageID[$commentaryStat['language']])) {
                $commentaryStatsByLanguageID[$commentaryStat['language']] = [];
            }
            $commentaryStatsByLanguageID[$commentaryStat['language']][] = $commentaryStat;
        }

        $totalQuestions = 0;
        $totalCommentaryQuestions = 0;
        $totalQuestionsByLanguageID = [];
        $totalCommentaryQuestionsByLanguageID = [];
        foreach ($chapterStats as $stats) {
            $totalQuestions += $stats['count'];
            if (!isset($totalQuestionsByLanguageID[$stats['language']])) {
                $totalQuestionsByLanguageID[$stats['language']] = 0;
            }
            $totalQuestionsByLanguageID[$stats['language']] += $stats['count'];
        }
        foreach ($commentaryStats as $stats) {
            $totalCommentaryQuestions += $stats['count'];
            if (!isset($totalCommentaryQuestionsByLanguageID[$stats['language']])) {
                $totalCommentaryQuestionsByLanguageID[$stats['language']] = 0;
            }
            $totalCommentaryQuestionsByLanguageID[$stats['language']] += $stats['count'];
        }

        return new TwigView('home/stats', compact('year', 'chapterStats', 'verseStats', 'commentaryStats', 'totalQuestions', 'totalCommentaryQuestions', 'languagesByID', 'chapterStatsByLanguageID', 'verseStatsByLanguageID', 'commentaryStatsByLanguageID', 'totalQuestionsByLanguageID', 'totalCommentaryQuestionsByLanguageID'), 'Stats');
    }

    public function showContactForm(PBEAppConfig $app, Request $request): Response
    {
        return new TwigView('home/contact-us', [], 'Contact');
    }

    private function validateContactSubmission(PBEAppConfig $app, Request $request): ValidationStatus
    {
        $title = Util::validateString($request->post, 'title');
        $name = Util::validateString($request->post, 'name');
        $email = Util::validateEmail($request->post, 'email');
        $message = Util::validateString($request->post, 'message');
        $club = Util::validateString($request->post, 'club');
        $conference = Util::validateString($request->post, 'conference');
        $type = Util::validateString($request->post, 'type');

        $submission = new ContactFormSubmission(-1, $title);
        $submission->personName = $name;
        $submission->email = $email;
        $submission->message = $message;
        $submission->club = $club;
        $submission->conference = $conference;
        $submission->type = $type;

        if ($submission->title === null || $submission->title === '') {
            return new ValidationStatus(false, $submission, 'Title is required');
        }
        if ($submission->personName === null || $submission->personName === '') {
            return new ValidationStatus(false, $submission, 'Name is required');
        }
        if ($submission->email === null || $submission->email === '') {
            return new ValidationStatus(false, $submission, 'Email is required');
        }
        if ($submission->message === null || $submission->message === '') {
            return new ValidationStatus(false, $submission, 'Message is required');
        }

        return new ValidationStatus(true, $submission);
    }

    public function handleContactSubmission(PBEAppConfig $app, Request $request): Response
    {
        $status = $this->validateContactSubmission($app, $request);
        $submission = $status->output;
        /** @var ContactFormSubmission $submission */
        $errors = [];
        if ($submission->email === 'ericjonesmyemail@gmail.com') {
            return new TwigErrorMessage('No spam, thanks.'); // we get so much spam from this email....
        }
        $honeypotName = Util::validateString($request->post, 'fname');
        $isCaughtInHoneypot = $honeypotName !== '';
        if ($status->didValidate) {
            if ($app->isLocalHost) {
                // create contact form submission record
                $submission->create($app->db);
                return new Redirect('/contact?success');
            } else {
                $didSucceedRecaptcha = true;
                if ($app->recaptchaType === 'google') {
                    $recaptcha = new ReCaptcha($app->recaptchaPrivateKey);
                    $resp = $recaptcha
                        ->setExpectedHostname($app->recaptchaExpectedDomain)
                        ->verify($request->post['g-recaptcha-response'] ?? '');
                    /** @var \ReCaptcha\Response $resp */
                    if (!$resp->isSuccess()) {
                        $errors = $resp->getErrorCodes();
                    }
                } else {
                    $didSucceedRecaptcha = $this->checkCloudflare($app, $request);
                    if (!$didSucceedRecaptcha) {
                        $errors = ['Failed to verify recaptcha; please try again.'];
                    }
                }
                if ($didSucceedRecaptcha) {
                    // Verified!
                    if ($isCaughtInHoneypot) {
                        $submission->personName = '[HONEYPOT] ' . $submission->personName;
                    }
                    $submission->create($app->db);
                    // send email
                    if (!$isCaughtInHoneypot) {
                        Util::sendContactFormEmail(
                            $app->contactToEmail,
                            $submission->email, 
                            $submission->personName,
                            $app->contactSubjectPrefix,
                            $submission->title,
                            $submission->message . "\n\n" .
                            'From: ' . $submission->personName . "\n\n" .
                            'Email: ' . $submission->email . "\n\n" .
                            'Club: ' . $submission->club . "\n\n" .
                            'Conference: ' . $submission->conference . "\n\n" .
                            'Submission from user type: ' . ucfirst($submission->type) . "\n\n"
                        );
                    }
                    return new Redirect('/contact?success');
                }
            }
        } else {
            $errors = [$status->error];
        }
        return new TwigView('home/contact-us', compact('errors', 'submission'), 'Contact');
    }

    private function checkCloudflare(PBEAppConfig $app, Request $request): bool
    {
        // modified from: 
        // https://community.cloudflare.com/t/is-there-a-turnstile-php-installation-example/425587/2
        $captcha = Util::validateString($request->post, 'cf-turnstile-response');
        if (!$captcha) {
            return false; // CAPTCHA was entered incorrectly
        }
        $secretKey = $app->recaptchaPrivateKey;
        $ip = $_SERVER['REMOTE_ADDR'];
        $url_path = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $captcha,
            'remoteip' => $ip
        ];
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($data),
                'header' => 'Content-Type: application/x-www-form-urlencoded'
            ]
        ];
        $stream = stream_context_create($options);
        $result = file_get_contents($url_path, false, $stream);
        $response =  $result;
        $responseKeys = json_decode($response, true);
        // var_dump($responseKeys);die();
        return intval($responseKeys['success']) === 1;
    }
}
