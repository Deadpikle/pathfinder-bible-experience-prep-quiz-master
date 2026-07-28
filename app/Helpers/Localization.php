<?php

namespace App\Helpers;

use DateTimeImmutable;
use IntlDateFormatter;
use NumberFormatter;

/**
 * UI localization only. Question/Bible-language translation remains separate.
 */
final class Localization
{
    public const DEFAULT_LOCALE = 'en';
    public const SUPPORTED_LOCALES = ['en', 'es', 'fr'];

    /** @var array<string, array<string, string>> */
    private static array $catalogs = [];

    public static function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale ?? '')));
        $locale = explode('-', $locale)[0] ?? '';
        return in_array($locale, self::SUPPORTED_LOCALES, true)
            ? $locale
            : self::DEFAULT_LOCALE;
    }

    public static function currentLocale(): string
    {
        return self::normalizeLocale($_SESSION['UILanguageAbbreviation'] ?? self::DEFAULT_LOCALE);
    }

    /** @return array<string, string> */
    public static function catalog(?string $locale = null): array
    {
        $locale = self::normalizeLocale($locale ?? self::currentLocale());
        if (!isset(self::$catalogs[$locale])) {
            $path = dirname(__DIR__) . '/Localization/' . $locale . '.php';
            $catalog = require $path;
            self::$catalogs[$locale] = is_array($catalog) ? $catalog : [];
        }
        return self::$catalogs[$locale];
    }

    /**
     * Translate a stable message key. Named placeholders use :placeholder syntax.
     * Missing draft translations fall back to English and then to the key itself.
     *
     * @param array<string, scalar|null> $parameters
     */
    public static function translate(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale = self::normalizeLocale($locale ?? self::currentLocale());
        $catalog = self::catalog($locale);
        $english = self::catalog(self::DEFAULT_LOCALE);
        $message = $catalog[$key] ?? $english[$key] ?? $key;

        if ($parameters === []) {
            return $message;
        }

        $replacements = [];
        foreach ($parameters as $name => $value) {
            $replacements[':' . $name] = (string)($value ?? '');
        }
        return strtr($message, $replacements);
    }

    public static function formatDate(?string $date, ?string $locale = null): string
    {
        if ($date === null || trim($date) === '') {
            return '';
        }

        try {
            $value = new DateTimeImmutable($date);
        } catch (\Exception) {
            return '';
        }

        $locale = self::normalizeLocale($locale ?? self::currentLocale());
        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                date_default_timezone_get()
            );
            $formatted = $formatter->format($value);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $value->format('F j, Y');
    }

    public static function formatNumber(int|float $number, ?string $locale = null): string
    {
        $locale = self::normalizeLocale($locale ?? self::currentLocale());
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatted = $formatter->format($number);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        return is_int($number) ? (string)$number : number_format($number, 2, '.', ',');
    }

    public static function javascriptCatalogJson(?string $locale = null): string
    {
        $keys = [
            'common.cancel',
            'common.loading',
            'common.save',
            'common.search',
            'common.select_language',
            'common.settings_saved',
            'questions.show_answer',
            'questions.hide_answer',
            'quiz.correct',
            'quiz.incorrect',
        ];
        $messages = [];
        foreach ($keys as $key) {
            $messages[$key] = self::translate($key, [], $locale);
        }

        return json_encode(
            ['locale' => self::normalizeLocale($locale ?? self::currentLocale()), 'messages' => $messages],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
