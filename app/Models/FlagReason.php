<?php

namespace App\Models;

// this should be changed into an enum, but we are still PHP 7.4 compatible
class FlagReason
{
    public const UNKNOWN = 'unknown';
    public const TOO_VAGUE = 'vague';
    public const NEEDS_MORE_DETAILS = 'more-details';
    public const WRONG_REFERENCE = 'wrong-reference';
    public const INCORRECT_POINTS = 'incorrect-points';
    public const SPELLING_TYPOS = 'spelling-typos';
    public const CONFUSING = 'confusing';
    public const NEEDS_PRACTICE = 'needs-practice';
    public const SAVE_FOR_LATER = 'save-for-later';
    public const OTHER = 'other';

    public static function validateReason(string $reason): string
    {
        if ($reason === FlagReason::TOO_VAGUE ||
            $reason === FlagReason::NEEDS_MORE_DETAILS ||
            $reason === FlagReason::WRONG_REFERENCE ||
            $reason === FlagReason::INCORRECT_POINTS ||
            $reason === FlagReason::SPELLING_TYPOS ||
            $reason === FlagReason::CONFUSING ||
            $reason === FlagReason::NEEDS_PRACTICE ||
            $reason === FlagReason::SAVE_FOR_LATER ||
            $reason === FlagReason::OTHER) {
            return $reason;
        }
        return FlagReason::UNKNOWN;
    }

    public static function toHumanReadable(string $constReason): string
    {
        switch ($constReason)
        {
            case FlagReason::TOO_VAGUE:
                return 'Question is too vague';
            case FlagReason::NEEDS_MORE_DETAILS:
                return 'Answer needs more details';
            case FlagReason::WRONG_REFERENCE:
                return 'Wrong book/chapter/verse reference';
            case FlagReason::INCORRECT_POINTS:
                return 'Incorrect points';
            case FlagReason::SPELLING_TYPOS:
                return 'Spelling/Typos';
            case FlagReason::CONFUSING:
                return 'Confusing';
            case FlagReason::NEEDS_PRACTICE:
                return 'Need to practice this question';
            case FlagReason::SAVE_FOR_LATER:
                return 'Save this question for later';
            case FlagReason::OTHER:
                return 'Other';
        }
        return 'Unknown';
    }
}
