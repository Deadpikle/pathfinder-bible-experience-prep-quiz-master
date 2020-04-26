<?php

// from https://github.com/umpirsky/twig-php-function/

namespace App\ViewExtensions;

use App\Models\AFMAppConfig;
use App\Models\CSRF;
use App\Models\PBEAppConfig;
use App\Models\Setting;
use App\Models\User;
use DateTime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Yamf\AppConfig;

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
        ];
        return $twigFunctions;
    }
}
