<?php

namespace App\Models;

use PDO;

class Setting
{
    public $settingID;
    public $key;
    public $value;
    public $displayName;

    public function __construct(int $settingID, string $key, string $value)
    {
        $this->settingID = $settingID;
        $this->key = $key;
        $this->value = $value;
        $this->displayName = '';
    }

    private static function loadSettings(string $whereClause, array $whereParams, PDO $db) : array
    {

        $query = '
        SELECT SettingID, SettingKey, SettingValue, DisplayName
        FROM Settings
        ' . $whereClause . '
        ORDER BY DisplayName';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $setting = new Setting($row['SettingID'], $row['SettingKey'], $row['SettingValue']);
            $setting->displayName = $row['DisplayName'];
            $output[] = $setting;
        }
        return $output;
    }

    public static function loadAllSettings(PDO $db) : array
    {
        return Setting::loadSettings('', [], $db);
    }

    public static function loadAllSettingsAsKeyValueArray(PDO $db) : array
    {
        $data = Setting::loadSettings('', [], $db);
        $output = [];
        foreach ($data as $item) {
            $output[$item->key] = $item->value;
        }
        return $output;
    }

    public static function saveSetting(string $settingKey, $value, PDO $db)
    {
        $query = '
            UPDATE Settings SET SettingValue = ?, LastEdited = ? WHERE SettingKey = ?';
        $params = [
            $value,
            date("Y-m-d H:i:s"),
            $settingKey
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    public static function initAppWithSettings(PBEAppConfig $app)
    {
        $app->settings = Setting::loadAllSettingsAsKeyValueArray($app->db);
        $app->contactName = $app->settings[Setting::AboutContactNameKey()] ?? '[name redacted]';
        $app->contactEmail = $app->settings[Setting::AboutContactEmailKey()] ?? '[email redacted]';
        $app->websiteName = isset($app->settings[Setting::WebsiteNameKey()]) ? $app->settings[Setting::WebsiteNameKey()] : 'UCC Quiz Engine';
        $app->websiteTabTitle = isset($app->settings[Setting::WebsiteTabTitleKey()]) ? $app->settings[Setting::WebsiteTabTitleKey()] : 'UCC PBE';
        $app->analyticsURL = '';
        $app->analyticsSiteID = '1';
        $app->footerText = isset($app->settings[Setting::FooterTextKey()]) ? $app->settings['FooterText'] : '';
    }

    public static function AboutContactNameKey() : string
    {
        return 'AboutContactName';
    }

    public static function AboutContactEmailKey() : string
    {
        return 'AboutContactEmail';
    }

    public static function WebsiteNameKey() : string
    {
        return 'WebsiteName';
    }

    public static function WebsiteTabTitleKey() : string
    {
        return 'WebsiteTabTitle';
    }

    public static function FooterTextKey() : string
    {
        return 'FooterText';
    }
}
