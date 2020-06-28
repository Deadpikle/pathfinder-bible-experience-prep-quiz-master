<?php

namespace App\Models;

// code from https://stackoverflow.com/a/31683058/3938401
class CSRF
{
    public static function createTokens(bool $forceCreateNew = false)
    {
        if (empty($_SESSION['csrf_token']) || $forceCreateNew) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (empty($_SESSION['hmac_key_token']) || $forceCreateNew) {
            $_SESSION['hmac_key_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function getTokenInputField(string $formName = '') : string
    {
        CSRF::createTokens();
        if ($formName === '') {
            return '
                <input type="hidden" name="token" value="'
                    . $_SESSION['csrf_token'] . '"
                />';
        }
        return '
            <input type="hidden" name="token" value="'
                . hash_hmac('sha256', $formName, $_SESSION['hmac_key_token']) . '"
            />';
    }

    public static function verifyToken(string $formName = '') : bool
    {
        if (!isset($_POST['token']) || $_POST['token'] == '') {
            return false;
        }
        CSRF::createTokens();
        if ($formName == '') {
            return hash_equals($_SESSION['csrf_token'], $_POST['token']);
        } else {
            $calc = hash_hmac('sha256', $formName, $_SESSION['hmac_key_token']);
            return hash_equals($calc, $_POST['token']);
        }
    }
}
