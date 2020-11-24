<?php

namespace App\Models;

use DateTime;
use PDO;

class Util
{
    public static function validateBoolean(array $array, string $key) : bool
    {
        return isset($array[$key]) && $array[$key] !== null && filter_var($array[$key], FILTER_VALIDATE_BOOLEAN);
    }
    
    public static function str_contains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    // https://stackoverflow.com/a/19271434/3938401
    public static function validateDate($date, $format = 'Y-m-d')
    {
        if ($date === null || $date === '') {
            return false;
        }
        $d = DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    // https://stackoverflow.com/a/19271434/3938401
    public static function validateDateFromArray($array, $key, $format = 'Y-m-d') : ?string
    {
        $data = isset($array) && isset($array[$key]) ? $array[$key] : null;
        if (!Util::validateDate($data, $format)) {
            $data = null;
        }
        return $data;
    }

    public static function validateString(array $array, string $key) : string
    {
        return isset($array[$key]) && $array[$key] !== null ? trim(filter_var($array[$key], FILTER_SANITIZE_STRING)) : '';
    }

    public static function validateURL(array $array, string $key) : string
    {
        return isset($array[$key]) && $array[$key] !== null ? trim(filter_var($array[$key], FILTER_SANITIZE_URL)) : '';
    }

    public static function validateInteger(array $array, string $key) : int
    {
        return isset($array[$key]) && $array[$key] !== null ? trim(filter_var($array[$key], FILTER_SANITIZE_NUMBER_INT)) : 0;
    }

    public static function validateEmail(array $array, string $key) : string
    {
        return isset($array[$key]) && $array[$key] !== null ? filter_var($array[$key], FILTER_SANITIZE_EMAIL) : '';
    }

    public static function generateUUID() : string
    {
        $bytes = random_bytes(16);
        $UUID = bin2hex($bytes);
        // yay for laziness on the hyphen inserts! code from https://stackoverflow.com/a/33484855/3938401
        $UUID = substr($UUID, 0, 8) . '-' . 
                substr($UUID, 8, 4) . '-' . 
                substr($UUID, 12, 4) . '-' . 
                substr($UUID, 16, 4)  . '-' . 
                substr($UUID, 20);
        return $UUID;
    }

    public static function shouldLowercaseOutput($output) : bool
    {
        return !\Yamf\Util::strStartsWith($output, 'T or') && 
               !(\Yamf\Util::strStartsWith($output, 'God') && !\Yamf\Util::strStartsWith($output, 'Gods') && 
                !\Yamf\Util::strStartsWith($output, 'gods')) &&
               !\Yamf\Util::strStartsWith($output, 'Christ') && 
               !\Yamf\Util::strStartsWith($output, 'Jesus');
    }
}
