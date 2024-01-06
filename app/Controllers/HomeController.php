<?php

namespace App\Controllers;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Club;
use App\Models\Conference;
use App\Models\ContactFormSubmission;
use App\Models\HomeInfoSection;
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
use ReCaptcha\ReCaptcha;

class HomeController
{
    public function index(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $title = 'Home';
        
        $conference = Conference::loadAdminConference($app->db);
        $conferenceID = $conference->conferenceID ?? $_SESSION['ConferenceID'];
        $sections = HomeInfoSection::loadSections(Year::loadCurrentYear($app->db), $conferenceID, $app->db);
        return new TwigView('home/index', compact('sections'), 'Home');
    }

    public function showLoginScreen(PBEAppConfig $app, Request $request)
    {
        return new TwigView('home/login', null, 'Login');
    }

    public function attemptLogin(PBEAppConfig $app, Request $request)
    {
        // TODO: refactor to models
        $query = '
            SELECT UserID, Username, ut.Type AS UserType, c.ClubID AS ClubID, c.Name AS ClubName,
                conf.ConferenceID, conf.Name AS ConferenceName, u.PreferredLanguageID
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
            return new Redirect('/');
        } else {
            $error = 'Invalid access code';
            return new TwigView('home/login', compact('error'), 'Login');
        }
    }

    public function logout(PBEAppConfig $app, Request $request)
    {
        session_regenerate_id(false);
        session_destroy();
        return new Redirect('/login');
    }

    public function about(PBEAppConfig $app, Request $request)
    {
        $conferences = Conference::loadNonWebsiteConferences($app->db);
        return new TwigView('home/about', compact('conferences'), 'About');
    }

    public function activeClubs(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $clubs = Club::loadRecentlyActiveClubs($app->db);
        $conferences = Conference::loadAllConferencesByID($app->db);

        $conferenceCounts = [];
        foreach ($clubs as $club) {
            if (!isset($conferenceCounts[$club->conferenceID])) {
                $conferenceCounts[$club->conferenceID] = 1;
            } else {
                $conferenceCounts[$club->conferenceID] += 1;
            }
        }
        $clubCount = count($clubs);

        return new TwigView('home/active-clubs', compact('clubs', 'conferences', 'conferenceCounts', 'clubCount'), 'Active Clubs');
    }

    public function studyGuides(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $currentYear = Year::loadCurrentYear($app->db);
        $guides = StudyGuide::loadCurrentStudyGuides($currentYear, $app->db);
        return new TwigView('home/study-guides', compact('guides'), 'Study Guides');
    }

    public function viewSettings(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $languages = Language::loadAllLanguages($app->db);
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages);

        $didUpdate = false;
        return new TwigView('home/settings', compact('languages', 'userLanguage', 'didUpdate'), 'Settings');
    }

    public function updateSettings(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $languages = Language::loadAllLanguages($app->db);
        $languageIDToUse = $request->post['language-select'];
        User::updatePreferredLanguage(User::currentUserID(), $languageIDToUse, $app->db);
        
        $_SESSION['PreferredLanguageID'] = $languageIDToUse; // TODO: refactor to User somewhere
        
        $userLanguage = Language::findLanguageWithID(User::getPreferredLanguageID(), $languages);

        $didUpdate = true;
        return new TwigView('home/settings', compact('languages', 'userLanguage', 'didUpdate'), 'Settings');
    }

    public function currentYearStats(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $year = Year::loadCurrentYear($app->db);
        $chapterStats = StatsLoader::loadQnAQuestionsByChapterInYear($year->yearID, $app->db);
        $verseStats = StatsLoader::loadQnAQuestionsByChapterAndVerseInYear($year->yearID, $app->db);
        $commentaryStats = StatsLoader::loadCommentaryQuestionsByYear($year->yearID, $app->db);

        $totalQuestions = 0;
        $totalCommentaryQuestions = 0;
        foreach ($chapterStats as $stats) {
            $totalQuestions += $stats['count'];
        }
        foreach ($commentaryStats as $stats) {
            $totalCommentaryQuestions += $stats['count'];
        }

        return new TwigView('home/stats', compact('year', 'chapterStats', 'verseStats', 'commentaryStats', 'totalQuestions', 'totalCommentaryQuestions'), 'Stats');
    }

    public function showContactForm(PBEAppConfig $app, Request $request)
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

    public function handleContactSubmission(PBEAppConfig $app, Request $request)
    {
        $status = $this->validateContactSubmission($app, $request);
        $submission = $status->output;
        /** @var ContactFormSubmission $submission */
        $errors = [];
        if ($submission->email === 'ericjonesmyemail@gmail.com') {
            return new TwigErrorMessage('No spam, thanks.'); // we get so much spam from this email....
        }
        if ($status->didValidate) {
            if ($app->isLocalHost) {
                // create contact form submission record
                $submission->create($app->db);
                return new Redirect('/contact?success');
            } else {
                $recaptcha = new ReCaptcha($app->recaptchaPrivateKey);
                $errors = [];
                $resp = $recaptcha
                    ->setExpectedHostname($app->recaptchaExpectedDomain)
                    ->verify($request->post['g-recaptcha-response'] ?? '');
                /** @var \ReCaptcha\Response $resp */
                if ($resp->isSuccess()) {
                    // Verified!
                    $submission->create($app->db);
                    // send email
                    Util::sendContactFormEmail(
                        $app->contactToEmail,
                        $submission->email, 
                        $submission->personName,
                        $app->contactSubjectPrefix,
                        $submission->title,
                        $submission->message . "\n\n" .
                            'Club: ' . $submission->club . "\n\n" .
                            'Conference: ' . $submission->conference . "\n\n" .
                            'Submission from: ' . ucfirst($submission->type) . "\n\n"
                    );
                    return new Redirect('/contact?success');
                } else {
                    $errors = $resp->getErrorCodes();
                }
            }
        } else {
            $errors = [$status->error];
        }
        return new TwigView('home/contact-us', compact('errors', 'submission'), 'Contact');
    }
}
