<?php

namespace App\Models;

use PDO;

class Util
{
    public static function validateBoolean(array $array, string $key) : bool
    {
        return isset($array[$key]) && $array[$key] !== null && filter_var($array[$key], FILTER_VALIDATE_BOOLEAN);
    }
}
