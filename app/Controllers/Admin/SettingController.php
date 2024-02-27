<?php

namespace App\Controllers\Admin;

use App\Models\CSRF;
use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\PBEAppConfig;
use App\Models\Setting;
use App\Models\User;
use App\Models\Util;
use App\Models\Views\TwigView;
use Yamf\AppConfig;
use Yamf\Interfaces\IRequestValidator;
use Yamf\Responses\Response;

class SettingController extends BaseAdminController implements IRequestValidator
{
    /**
     * Validate a request before the normal controller method is called.
     * 
     * Return null if the request is valid. Otherwise, return a response
     * that will be output to the user rather than the normal controller method.
     */
    public function validateRequest(AppConfig $app, Request $request): ?Response
    {
        /** @var PBEAppConfig $app */
        $response = parent::validateRequest($app, $request);
        if ($response === null) {
            if ($app->isWebAdmin) {
                return null;
            }
            return new Redirect('/admin');
        }
        return $response;
    }

    public function viewSettings(AppConfig $app, Request $request): Response
    {
        $settings = Setting::loadAllSettings($app->db);
        return new TwigView('/admin/settings', compact('settings'), 'Settings');
    }

    public function saveSettings(AppConfig $app, Request $request): Response
    {
        $didError = false;
        if (CSRF::verifyToken('change-settings')) {
            // save settings
            Setting::saveSetting(Setting::AboutContactNameKey(), 
                                 Util::validateString($request->post, Setting::AboutContactNameKey()), $app->db);
            Setting::saveSetting(Setting::AboutContactEmailKey(), 
                                 Util::validateString($request->post, Setting::AboutContactEmailKey()), $app->db);
            Setting::saveSetting(Setting::WebsiteNameKey(), 
                                 Util::validateString($request->post, Setting::WebsiteNameKey()), $app->db);
            Setting::saveSetting(Setting::WebsiteTabTitleKey(), 
                                 Util::validateString($request->post, Setting::WebsiteTabTitleKey()), $app->db);
            Setting::saveSetting(Setting::FooterTextKey(), 
                                 Util::validateString($request->post, Setting::FooterTextKey()), $app->db);
            Setting::saveSetting(Setting::CurrentFillInChapters(), 
                                Util::validateString($request->post, Setting::CurrentFillInChapters()), $app->db);
            // re-init settings
            Setting::initAppWithSettings($app);
        } else {
            $didError = true;
        }
        // return view
        $settings = Setting::loadAllSettings($app->db);
        $didJustSave = true;
        return new TwigView('admin/settings', compact('settings', 'didJustSave', 'didError'), 'Settings');
    }
}
