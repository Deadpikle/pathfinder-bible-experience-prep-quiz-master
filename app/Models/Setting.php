<?php

namespace App\Models;

use PDO;

class Setting
{
    public int $settingID;
    public string $key;
    public string $value;
    public string $displayName;

    public function __construct(int $settingID, string $key, string $value, string $displayName = '')
    {
        $this->settingID = $settingID;
        $this->key = $key;
        $this->value = $value;
        $this->displayName = $displayName;
    }

    /** @return array<Setting> */
    private static function loadSettings(string $whereClause, array $whereParams, PDO $db): array
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

    /** @return array<Setting> */
    public static function loadAllSettings(PDO $db): array
    {
        $settings = Setting::loadSettings('', [], $db);
        $defaults = self::getDefaultSettings();
        $defaultDisplayNames = self::getDisplayNames();
        foreach ($defaults as $key => $defaultValue) {
            $didFind = false;
            foreach ($settings as $setting) {
                if ($setting->key === $key) {
                    $didFind = true;
                    break;
                }
            }
            if (!$didFind) {
                $settings[] = new Setting(-1, $key, $defaultValue, $defaultDisplayNames[$key] ?? $key);
            }
        }
        return $settings;
    }

    /** @return array<string,string> */
    public static function loadAllSettingsAsKeyValueArray(PDO $db): array
    {
        $data = self::loadAllSettings($db);
        $output = [];
        foreach ($data as $item) {
            $output[$item->key] = $item->value;
        }
        return $output;
    }

    public static function saveSetting(string $settingKey, $value, PDO $db)
    {
        // see if setting exists with key and save it for the first time if needed
        $existingSetting = self::loadSettings(' WHERE SettingKey = ? ', [$settingKey], $db);
        if (count($existingSetting) === 1) {
            $query = '
                UPDATE Settings SET SettingValue = ?, LastEdited = ? WHERE SettingKey = ?';
            $params = [
                $value,
                date("Y-m-d H:i:s"),
                $settingKey
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        } else {
            $defaultDisplayNames = self::getDisplayNames();
            $query = '
                INSERT INTO Settings (SettingValue, LastEdited, SettingKey, DisplayName) VALUES (?, ?, ?, ?)';
            $params = [
                $value,
                date("Y-m-d H:i:s"),
                $settingKey,
                $defaultDisplayNames[$settingKey] ?? $settingKey
            ];
            $stmt = $db->prepare($query);
            $stmt->execute($params);
        }
    }

    public static function initAppWithSettings(PBEAppConfig $app)
    {
        $app->settings = Setting::loadAllSettingsAsKeyValueArray($app->db);
        $app->contactName = $app->settings[Setting::AboutContactNameKey()] ?? '[name redacted]';
        $app->contactEmail = $app->settings[Setting::AboutContactEmailKey()] ?? '[email redacted]';
        $app->websiteName = isset($app->settings[Setting::WebsiteNameKey()]) ? $app->settings[Setting::WebsiteNameKey()] : 'PBE Prep and Quiz Master';
        $app->websiteTabTitle = isset($app->settings[Setting::WebsiteTabTitleKey()]) ? $app->settings[Setting::WebsiteTabTitleKey()] : 'PBE Prep and Quiz Master';
        $app->analyticsURL = '';
        $app->analyticsSiteID = '1';
        $app->footerText = isset($app->settings[Setting::FooterTextKey()]) ? $app->settings[Setting::FooterTextKey()] : '';
        $app->currentFillInChapters = isset($app->settings[Setting::CurrentFillInChapters()]) ? $app->settings[Setting::CurrentFillInChapters()] : '';
    }

    public static function AboutContactNameKey(): string
    {
        return 'AboutContactName';
    }

    public static function AboutContactEmailKey(): string
    {
        return 'AboutContactEmail';
    }

    public static function WebsiteNameKey(): string
    {
        return 'WebsiteName';
    }

    public static function WebsiteTabTitleKey(): string
    {
        return 'WebsiteTabTitle';
    }

    public static function FooterTextKey(): string
    {
        return 'FooterText';
    }

    public static function CurrentFillInChapters(): string
    {
        return 'CurrentFillInChapters';
    }

    public static function getDefaultSettings(): array
    {
        return [
            self::AboutContactNameKey() => '',
            self::AboutContactEmailKey() => '',
            self::WebsiteNameKey() => 'PBE Prep and Quiz Master',
            self::WebsiteTabTitleKey() => 'PBE Prep',
            self::FooterTextKey() => 'Scripture taken from the New King James Version®. Copyright © 1982 by Thomas Nelson. Used by permission. All rights reserved.',
            self::CurrentFillInChapters() => '',
        ];
    }

    public static function getDisplayNames(): array
    {
        return [
            self::AboutContactNameKey() => 'About Page - Contact Name',
            self::AboutContactEmailKey() => 'About Page - Contact Email',
            self::WebsiteNameKey() => 'Website Name',
            self::WebsiteTabTitleKey() => 'Website Title (Browser Tab)',
            self::FooterTextKey() => 'Footer Text',
            self::CurrentFillInChapters() => 'Current Chapter Availability for Fill In Questions',
        ];
    }
}
