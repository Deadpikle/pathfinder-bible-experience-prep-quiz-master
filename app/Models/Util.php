<?php

namespace App\Models;

use PDO;

class Util
{
    public static function validateBoolean(array $array, string $key) : bool
    {
        return isset($array[$key]) && $array[$key] !== null && filter_var($array[$key], FILTER_VALIDATE_BOOLEAN);
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
}
