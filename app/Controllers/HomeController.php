<?php

namespace App\Controllers;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Conference;
use App\Models\PBEAppConfig;

class HomeController
{
    public function index(PBEAppConfig $app, Request $request)
    {
        if (!$app->loggedIn) {
            return new Redirect('/login');
        }
        $title = 'Home';
        
        $sections = load_home_sections($app->db, $_SESSION["ConferenceID"]);
        return new View('home/index', compact('sections'), 'Home');
    }

    public function showLoginScreen(PBEAppConfig $app, Request $request)
    {
        return new View('home/login', null, 'Login');
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
            if ($row['ClubID'] != null) {
                $_SESSION['ClubID'] = $row['ClubID'];
            } else {
                $_SESSION['ClubID'] = -1;
            }
            $_SESSION['ClubName'] = $row['ClubName'];
            if ($row['ConferenceID'] != null) {
                $_SESSION['ConferenceID'] = $row['ConferenceID'];
            } else {
                $_SESSION['ConferenceID'] = -1;
            }
            $_SESSION['ConferenceName'] = $row['ConferenceName'];
            $_SESSION['PreferredLanguageID'] = $row['PreferredLanguageID'];
            return new Redirect('/');
        } else {
            $error = 'Invalid access code';
            return new View('home/login', compact('error'), 'Login');
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
        return new View('home/about', compact('conferences'), 'About');
    }
}
