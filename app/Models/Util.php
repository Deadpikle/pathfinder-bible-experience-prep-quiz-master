<?php

namespace App\Models;

use DateTime;
use PDO;
use Yamf\Util as YamfUtil;

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
        return isset($array[$key]) && $array[$key] !== null ? trim(filter_var($array[$key])) : '';
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

    public static function getFullQuestionTextFromQuestion($question) {
        $type = $question['type'];
        $output = trim($question['question']);
        $isFillIn = Question::isTypeFillIn($type);
        // TODO: rework logic here to be....right.
        if (!$isFillIn) {
            $output = self::fixQuestionMarkOnQuestion($output);
        }
        if (Question::isTypeBibleQnA($type)) {
            $startBook = $question['startBook'];
            $startChapter = $question['startChapter'];
            $startVerse = $question['startVerse'];
            $endBook = $question['endBook'];
            $endChapter = $question['endChapter'];
            $endVerse = $question['endVerse'];
            $verseText = $startBook . ' ' . $startChapter . ':' . $startVerse;
            if ($endBook !== '' && $startVerse != $endVerse) {
                if ($startChapter == $endChapter) {
                    $verseText .= '-' . $endVerse;
                }
                else {
                    $endPart = $endChapter . ':' . $endVerse;
                    $verseText .= '-' . $endPart;
                }
            }
            if ($isFillIn) {
                $output = 'Fill in the blanks for ' . $verseText . '.';
            }
            else {
                if (!\Yamf\Util::strStartsWith($output, $startBook) && Util::shouldLowercaseOutput($output)) {
                    $output = lcfirst($output);
                }
                $output = "According to " . $verseText . ", " . $output;
            }
        }
        else if (Question::isTypeCommentaryQnA($type)) {
            $volume = $question['volume'];
            $startPage = trim($question['startPage'] ?? '');
            $endPage = isset($question['endPage']) ? trim($question['endPage'] ?? '') : null;
            $pageStr = '';
            if ($endPage != null && $endPage != '' && $endPage > $startPage) {
                $pageStr = 'pp. ' . $startPage . '-' . $endPage;
            } else if ($startPage !== '') {
                $pageStr = 'p. ' . $startPage;
            }
            if ($isFillIn) {
                $output = 'Fill in the blanks for SDA Bible Commentary, Volume ' . $volume . ', ' . $pageStr . '.';
            } else {
                if (!\Yamf\Util::strStartsWith($output, $volume) && Util::shouldLowercaseOutput($output)) {
                    $output = lcfirst($output);
                }
                $output = 'According to the SDA Bible Commentary, Volume ' . $volume . ', ' . ($pageStr !== '' ? $pageStr . ', ' : '') . $output;
            }
        }
        return trim($output);
    }

    public static function generateFillInDataFromQuestion($question) {
        $data = $question["fillInData"];
        $blankedOutput = "";
        $boldedOutput = "";
        $i = 0;
        $blankedWords = [];
        foreach ($data as $questionWords) {
            if ($questionWords["before"] !== "") {
                $blankedOutput .= $questionWords["before"];
                $boldedOutput .= $questionWords["before"];
            }
            if ($questionWords["word"] !== "") {
                if ($questionWords["shouldBeBlanked"]) {
                    $blankedWords[] = $questionWords["word"];
                    $blankedOutput .= "________";
                    $boldedOutput .= "<b>" . $questionWords["word"] . "</b>";
                }
                else {
                    $blankedOutput .= $questionWords["word"];
                    $boldedOutput .= $questionWords["word"];
                }
            }
            if ($questionWords["after"] !== "" && $questionWords["after"] !== "...") {
                $blankedOutput .= $questionWords["after"];
                $boldedOutput .= $questionWords["after"];
            }
            if ($i != count($data) - 1) {
                $blankedOutput .= " ";
                $boldedOutput .= " ";
            }
            $i++;
        }
        return ["question" => $blankedOutput, "answer" => $boldedOutput, "blanked-words" => $blankedWords];
    }

    public static function getBibleNames(): array
    {
        static $names = [];
        if (count($names) === 0) {
            $names = json_decode(file_get_contents('files/bible-names/all-names.json'));
        }
        return $names;
    }

    public static function shouldLowercaseOutput($output): bool
    {
        $names = self::getBibleNames();
        $firstWords = explode(' ', $output);
        $firstWord = '';
        if (count($firstWords) > 0) {
            $firstWord = $firstWords[0];
        }
        return !\Yamf\Util::strStartsWith($output, 'T or') && 
               !(\Yamf\Util::strStartsWith($output, 'God') && !\Yamf\Util::strStartsWith($output, 'Gods') && 
                !\Yamf\Util::strStartsWith($output, 'gods')) &&
               !\Yamf\Util::strStartsWith($output, 'Christ') && 
               !\Yamf\Util::strStartsWith($output, 'Jesus') &&
               !in_array($firstWord, $names);
    }

    public static function fixQuestionMarkOnQuestion(string $input): string
    {
        if (mb_strlen($input) > 0) {
            if (YamfUtil::strEndsWith(mb_strtolower($input), "specific.") ||
                YamfUtil::strEndsWith(mb_strtolower($input), "specific") ||
                YamfUtil::strEndsWith(mb_strtolower($input), "be specific")) {
                if (!YamfUtil::strEndsWith($input, '.')) {
                    $input .= '.';
                }
            } else {
                if (YamfUtil::strEndsWith($input, '.') ||
                    YamfUtil::strEndsWith($input, '!') ||
                    YamfUtil::strEndsWith($input, ',')) {
                    $input = substr($input, 0, -1) . '?';
                } else if (!YamfUtil::strEndsWith($input, "?")) {
                    $input .= "?";
                }
            }
        }
        return $input;
    }

    public static function sendContactFormEmail(string $toEmail, string $fromEmail, string $fromName, string $subjectPrefix, string $subject, string $message): bool
    {
        $to      = $toEmail;
        $subject = '[' . $subjectPrefix . '] ' . $subject;
        $headers = 'From: ' . $fromName . '<' . $to . ">\n" .
            'Reply-To: ' . $fromEmail . "\n" .
            'X-Mailer: PHP/' . phpversion();
        return mail($to, $subject, $message, $headers);
    }
}
