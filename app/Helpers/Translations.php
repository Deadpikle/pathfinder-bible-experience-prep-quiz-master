<?php

namespace App\Helpers;

class Translations
{
    public static function getTranslationsForLanguageAbbr(string $languageAbbr): array
    {
        if ($languageAbbr === 'es') {
            return [
                'According to' => 'De acuerdo a',
                'Fill in the blanks for' => 'Llenar los espacios',
                'According to the SDA Bible Commentary for' => 'De acuerdo al Comentario Bíblico Adventista sobre',
                'Fill in the blanks for the SDA Bible Commentary for' => 'Llenar los espacios al Comentario Bíblico Adventista, Volumen',
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

    public static function translate(string $str, string $languageAbbr): string
    {
        // TODO: we need a full translation setup with string substitutions and everything else. for now, hack something in...
        if ($languageAbbr === 'en' || $languageAbbr === null || $languageAbbr === '') {
            return $str;
        }
        if ($languageAbbr === 'es') {
            $translations = self::getTranslationsForLanguageAbbr($languageAbbr);
            $output = isset($translations[$str]) ? $translations[$str] : $str;
            return $output;
        }
        if ($languageAbbr === 'fr') {
            return $str; // don't have any yet
        }
    }

    public static function t(string $str, string $languageAbbr): string
    {
        return self::translate($str, $languageAbbr);
    }
}
