<?php

// from https://github.com/umpirsky/twig-php-function/

namespace App\ViewExtensions;

use App\Helpers\Translations;
use App\Models\AFMAppConfig;
use App\Models\Conference;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\Setting;
use App\Models\User;
use DateTime;
use PDO;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Yamf\AppConfig;
use Yamf\Util as YamfUtil;

class AppViewExtension extends AbstractExtension
{
    public function __construct()
    {
    }

    function navbar_link($app, $name, $path, $basePath)
    {
        $currentRequest = str_replace($app->basePath, '', $_SERVER['REQUEST_URI']);
        if (\Yamf\Util::strEndsWith($currentRequest, '/')) {
            $currentRequest = substr($currentRequest, 0, -1);
        }
        $parts = explode('/', $currentRequest);
        $currentRequest = count($parts) > 1 ? $parts[1] : '';
        // figure out whether the current navbar item is active or not based on the URL
        $isCurrent = false;
        $pathNoSlashes = str_replace("/", "", $path);
        if ($path === "/" && $currentRequest === "") {
            $isCurrent = true;
        } elseif ($currentRequest !== "" && $pathNoSlashes != "" && strpos($currentRequest, $pathNoSlashes) !== false) {
            $isCurrent = true;
        }
        $liClass = $isCurrent ? "active" : "";
        return '<li class="' . $liClass . ' nav-item"><a class="nav-link" href="' . $basePath . $path . '">' . $name . '</a></li>';
    }

    function isUserLoggedIn(PBEAppConfig $app)
    {
        return User::isLoggedIn($app->db);
    }

    function currentUsername()
    {
        return $_SESSION['Username'];
    }

    function currentConferenceID()
    {
        return $_SESSION['ConferenceID'];
    }

    function webAdminConferenceID(PBEAppConfig $app)
    {
        $conference = Conference::loadAdminConference($app->db);
        return $conference->conferenceID ?? $_SESSION['ConferenceID'];
    }

    function csrf(string $qualifier) : string
    {
        return CSRF::getTokenInputField($qualifier);
    }

    function formatDate(?string $date) : string
    {
        if ($date === null) {
            return '';
        }
        $item = date_create_from_format('Y-m-d', $date);
        if ($item !== false) {
            return $item->format('F j, Y');
        }
        return '';
    }

    function currentUserID() : ?int
    {
        return User::currentUserID();
    }

    function addYearsToDate(?string $date, int $years, ?int $additionalMonths = 0) : string
    {
        if ($date === null) {
            return '';
        }
        if ($additionalMonths === null) {
            $additionalMonths = 0;
        }
        return DateTime::createFromFormat('Y-m-d', $date)
            ->modify('-' . $additionalMonths . ' months')
            ->modify('+' . $years . ' years')
            ->format('F j, Y');
    }

    function strEndsWith(?string $haystack, ?string $needle) : string
    {
        if ($haystack === null || $needle === null) {
            return false;
        }
        return YamfUtil::strEndsWith($haystack, $needle);
    }

    function requestURI() : string
    {
        return $_SERVER['REQUEST_URI'];
    }

    function getConst($className, $constantName): string
    {
        return constant('\\App\\Models\\' . $className . '::' . $constantName);
    }

    function translate(string $str, string $languageAbbreviation): string
    {
        return Translations::t($str, $languageAbbreviation);
    }

    function getUserLanguageAbbr(PDO $db): string
    {
        return User::getPreferredLanguage($db)->abbreviation ?? 'en';
    }

    // // // settings
    // // //

    public function getFunctions()
    {
        $twigFunctions = [
            new TwigFunction('navbar_link', [$this, 'navbar_link']),
            new TwigFunction('isUserLoggedIn', [$this, 'isUserLoggedIn']),
            new TwigFunction('csrf', [$this, 'csrf']),
            new TwigFunction('formatDate', [$this, 'formatDate']),
            new TwigFunction('currentUserID', [$this, 'currentUserID']),
            new TwigFunction('addYearsToDate', [$this, 'addYearsToDate']),
            new TwigFunction('strEndsWith', [$this, 'strEndsWith']),
            new TwigFunction('strEndsWith', [$this, 'strEndsWith']),
            new TwigFunction('requestURI', [$this, 'requestURI']),
            new TwigFunction('outputHomeSections', [$this, 'outputHomeSections']),
            new TwigFunction('currentUsername', [$this, 'currentUsername']),
            new TwigFunction('currentConferenceID', [$this, 'currentConferenceID']),
            new TwigFunction('webAdminConferenceID', [$this, 'webAdminConferenceID']),
            new TwigFunction('getConst', [$this, 'getConst']),
            new TwigFunction('translate', [$this, 'translate']),
            new TwigFunction('getUserLanguageAbbr', [$this, 'getUserLanguageAbbr']),
        ];
        return $twigFunctions;
    }
}
