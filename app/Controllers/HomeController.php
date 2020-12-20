<?php

namespace App\Controllers;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Club;
use App\Models\Conference;
use App\Models\HomeInfoSection;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\StudyGuide;
use App\Models\User;
use App\Models\Views\TwigView;
use App\Models\Year;
use App\Services\StatsLoader;

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
            WHERE EntryCode = ?';
        $stmt = $app->db->prepare($query);
        $params = [
            $request->post['access-code']
        ];
        $stmt->execute($params);
        if ($row = $stmt->fetch()) {
            // Login success!
            // Update the database
            $updateQuery = 'UPDATE Users SET LastLoginDate = ? WHERE UserID = ' . $row['UserID'];
            $statement = $app->db->prepare($updateQuery);
            $params = [
                date('Y-m-d H:i:s')
            ];
            $statement->execute($params);
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
        $userLanguage = Language::findLanguageWithID($_SESSION['PreferredLanguageID'], $languages);

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
        
        $userLanguage = Language::findLanguageWithID($_SESSION['PreferredLanguageID'], $languages);

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

        $totalQuestions = 0;
        foreach ($chapterStats as $stats) {
            $totalQuestions += $stats['count'];
        }

        return new TwigView('home/stats', compact('year', 'chapterStats', 'verseStats', 'totalQuestions'), 'Stats');
    }
}
