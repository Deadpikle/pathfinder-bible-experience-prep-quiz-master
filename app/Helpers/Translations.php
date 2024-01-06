<?php

namespace App\Helpers;

class Translations
{
    public static function utf8($str): string
    {
        return iconv('UTF-8', 'ISO-8859-1', $str);
    }

    public static function getTranslationsForLanguageAbbr(string $languageAbbr): array
    {
        if ($languageAbbr === 'es') {
            return [
                'According to' => 'Según',
                'Fill in the blanks for' => 'Llene los espacios en blanco para',
                'According to the SDA Bible Commentary, Volume' => 'Según el Comentario Bíblico Adventista, Volumen',
                'Fill in the blanks for SDA Bible Commentary, Volume' => 'Llene los espacios en blanco para el Comentario Bíblico Adventista, Volumen',
                'PBE Study Guide' => 'Guía de Estudio PBE',
                'PBE Study Guides' => 'Guías de Estudio PBE',
                'Question' => 'Pregunta',
                'point' => 'punto',
                'points' => 'puntos',
                'Volume' => 'volumen',
                'of' => 'de',
                'Page' => 'Página',
                'point possible' => 'punto posible',
                'points possible' => 'puntos posibles',
                'Answer' => 'Respuesta',
                'Show Answer' => 'Mostrar Respuesta',
                'The answer is:' => 'La respuesta es:',
                // Bible books
                'Joshua' => 'Josué',
                'Judges' => 'Jueces',
            ];
        }
        return [];
    }

    public static function translate(string $str, string $languageAbbr, bool $useISO8859 = false): string
    {
        // TODO: we need a full translation setup with string substitutions and everything else. for now, hack something in...
        if ($languageAbbr === 'en' || $languageAbbr === null || $languageAbbr === '') {
            return $str;
        }
        if ($languageAbbr === 'es') {
            $translations = self::getTranslationsForLanguageAbbr($languageAbbr);
            $output = isset($translations[$str]) ? $translations[$str] : $str;
            return $useISO8859 ? self::utf8($output) : $output;
        }
        if ($languageAbbr === 'fr') {
            return $str; // don't have any yet
        }
    }

    public static function t(string $str, string $languageAbbr, bool $useISO8859 = false): string
    {
        return self::translate($str, $languageAbbr, $useISO8859);
    }
}
