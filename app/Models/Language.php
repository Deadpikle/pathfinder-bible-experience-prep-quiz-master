<?php

namespace App\Models;

use PDO;

class Language
{
    public int $languageID;
    public string $name;
    public bool $isDefault;
    public bool $isUIEnabled;
    public string $altName;
    public string $abbreviation;

    public function __construct(int $languageID, string $name)
    {
        $this->languageID = $languageID;
        $this->name = $name;
        $this->isDefault = false;
        $this->isUIEnabled = false;
        $this->altName = '';
        $this->abbreviation = '';
    }

    public function getDisplayName(): string
    {
        $name = $this->name;
        if ($this->altName !== '') {
            $name .= ' (' . $this->altName . ')';
        }
        return $name;
    }

    /** @return array<Language> */
    private static function loadLanguages(string $whereClause, array $whereParams, PDO $db): array
    {
        $query = '
            SELECT LanguageID, Name, IsDefault, IsUIEnabled, AltName, Abbreviation
            FROM Languages
            ' . $whereClause . '
            ORDER BY Name';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $language = new Language($row['LanguageID'], $row['Name']);
            $language->isDefault = $row['IsDefault'];
            $language->isUIEnabled = (bool)$row['IsUIEnabled'];
            $language->altName = $row['AltName'];
            $language->abbreviation = $row['Abbreviation'];
            $output[] = $language;
        }
        return $output;
    }

    /** @return array<Language> */
    public static function loadAllLanguages(PDO $db): array
    {
        return Language::loadLanguages('', [], $db);
    }

    /** @return array<Language> */
    public static function loadUIEnabledLanguages(PDO $db): array
    {
        return Language::loadLanguages(' WHERE IsUIEnabled = 1 ', [], $db);
    }

    /** @return array<int, Language> */
    public static function loadAllLanguagesByID(PDO $db): array
    {
        $languages = Language::loadLanguages('', [], $db);
        $languagesByID = [];
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }
        return $languagesByID;
    }

    public static function loadLanguageWithID(?int $languageID, PDO $db): ?Language
    {
        $data = Language::loadLanguages(' WHERE LanguageID = ? ', [$languageID], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadDefaultLanguage(PDO $db): ?Language
    {
        $data = Language::loadLanguages(' WHERE IsDefault = 1 ', [], $db);
        return count($data) > 0 ? $data[0] : new Language(1, 'English');
    }

    public static function loadDefaultUILanguage(PDO $db): Language
    {
        $data = Language::loadLanguages(" WHERE IsUIEnabled = 1 AND Abbreviation = 'en' ", [], $db);
        if (count($data) > 0) {
            return $data[0];
        }

        $data = Language::loadLanguages(' WHERE IsUIEnabled = 1 ', [], $db);
        if (count($data) > 0) {
            return $data[0];
        }

        $english = Language::loadLanguages(" WHERE Abbreviation = 'en' ", [], $db);
        return count($english) > 0 ? $english[0] : new Language(1, 'English');
    }

    public static function loadEnabledUILanguageWithID(?int $languageID, PDO $db): ?Language
    {
        if ($languageID === null) {
            return null;
        }
        $data = Language::loadLanguages(' WHERE LanguageID = ? AND IsUIEnabled = 1 ', [$languageID], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadEnglishLanguage(PDO $db): Language
    {
        $data = Language::loadLanguages(" WHERE Abbreviation = 'en' ", [], $db);
        return count($data) > 0 ? $data[0] : self::loadDefaultUILanguage($db);
    }

    public static function findLanguageWithID(?int $languageID, array $languages): ?Language
    {
        foreach ($languages as $language) {
            if ($language->languageID === $languageID) {
                return $language;
            }
        }
        return null;
    }
}
