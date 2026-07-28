<?php

namespace App\Services;

/**
 * The canonical interchange schema for question CSV imports and exports.
 *
 * Keeping the header and conditional requirements here prevents the upload
 * documentation, importer, exporter, and sample workbook from drifting apart.
 */
final class QuestionCsvSchema
{
    public const COLUMNS = [
        'Type',
        'Fill in?',
        'Language',
        'Question',
        'Answer',
        'Points',
        'Start Book',
        'Start Chapter',
        'Start Verse',
        'End Book',
        'End Chapter',
        'End Verse',
        'Commentary Number',
        'Commentary Topic',
        'Start Page',
        'End Page',
    ];

    public const BIBLE = 'Bible';
    public const COMMENTARY = 'Commentary';

    /**
     * Human-readable requirements used by the upload screen.
     *
     * @return array<string,array{bible:string,commentary:string}>
     */
    public static function requirementMatrix(): array
    {
        return [
            'Type' => ['bible' => 'Required: Bible', 'commentary' => 'Required: Commentary'],
            'Fill in?' => ['bible' => 'Required: Yes/No', 'commentary' => 'Required: Yes/No'],
            'Language' => ['bible' => 'Optional; defaults to the site default', 'commentary' => 'Optional; defaults to the site default'],
            'Question' => ['bible' => 'Required', 'commentary' => 'Required'],
            'Answer' => ['bible' => 'Required unless Fill in? is Yes', 'commentary' => 'Required unless Fill in? is Yes'],
            'Points' => ['bible' => 'Optional; defaults to 1', 'commentary' => 'Optional; defaults to 1'],
            'Start Book' => ['bible' => 'Required', 'commentary' => 'Leave blank'],
            'Start Chapter' => ['bible' => 'Required', 'commentary' => 'Leave blank'],
            'Start Verse' => ['bible' => 'Required', 'commentary' => 'Leave blank'],
            'End Book' => ['bible' => 'Optional; all three End fields are required together', 'commentary' => 'Leave blank'],
            'End Chapter' => ['bible' => 'Optional; all three End fields are required together', 'commentary' => 'Leave blank'],
            'End Verse' => ['bible' => 'Optional; all three End fields are required together', 'commentary' => 'Leave blank'],
            'Commentary Number' => ['bible' => 'Leave blank', 'commentary' => 'Required'],
            'Commentary Topic' => ['bible' => 'Leave blank', 'commentary' => 'Required'],
            'Start Page' => ['bible' => 'Leave blank', 'commentary' => 'Optional'],
            'End Page' => ['bible' => 'Leave blank', 'commentary' => 'Optional'],
        ];
    }

    /** @return array<string> */
    public static function validateHeaders(array $headers): array
    {
        $headers = array_map(static fn ($header): string => trim((string)$header), $headers);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        $errors = [];
        $duplicates = array_keys(array_filter(array_count_values($headers), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            $errors[] = 'Duplicate column header(s): ' . implode(', ', $duplicates) . '.';
        }

        $missing = array_values(array_diff(self::COLUMNS, $headers));
        if ($missing !== []) {
            $errors[] = 'Missing required column header(s): ' . implode(', ', $missing) . '.';
        }

        $unexpected = array_values(array_diff($headers, self::COLUMNS));
        if ($unexpected !== []) {
            $errors[] = 'Unexpected column header(s): ' . implode(', ', $unexpected) . '.';
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string>
     */
    public static function validateRow(array $row): array
    {
        $errors = [];
        $type = trim((string)($row['Type'] ?? ''));
        $fillIn = self::parseBoolean($row['Fill in?'] ?? null);
        $question = trim((string)($row['Question'] ?? ''));
        $answer = trim((string)($row['Answer'] ?? ''));

        if ($type !== self::BIBLE && $type !== self::COMMENTARY) {
            $errors[] = 'Type must be Bible or Commentary.';
        }
        if ($fillIn === null) {
            $errors[] = 'Fill in? must be Yes/No or True/False.';
        }
        if ($question === '') {
            $errors[] = 'Question is required.';
        } elseif (mb_strlen($question) > 10000) {
            $errors[] = 'Question cannot exceed 10,000 characters.';
        }
        if ($fillIn === false && $answer === '') {
            $errors[] = 'Answer is required when Fill in? is No.';
        } elseif (mb_strlen($answer) > 10000) {
            $errors[] = 'Answer cannot exceed 10,000 characters.';
        }

        $points = trim((string)($row['Points'] ?? ''));
        if ($points !== '' && (!ctype_digit($points) || (int)$points < 1)) {
            $errors[] = 'Points must be a positive whole number.';
        }

        if ($type === self::BIBLE) {
            foreach (['Start Book', 'Start Chapter', 'Start Verse'] as $column) {
                if (trim((string)($row[$column] ?? '')) === '') {
                    $errors[] = $column . ' is required for Bible questions.';
                }
            }
            self::validatePositiveInteger($row, 'Start Chapter', $errors);
            self::validatePositiveInteger($row, 'Start Verse', $errors);

            $endValues = array_map(
                static fn (string $column): string => trim((string)($row[$column] ?? '')),
                ['End Book', 'End Chapter', 'End Verse']
            );
            $populatedEndValues = count(array_filter($endValues, static fn (string $value): bool => $value !== ''));
            if ($populatedEndValues !== 0 && $populatedEndValues !== 3) {
                $errors[] = 'End Book, End Chapter, and End Verse must be supplied together.';
            }
            if ($populatedEndValues === 3) {
                self::validatePositiveInteger($row, 'End Chapter', $errors);
                self::validatePositiveInteger($row, 'End Verse', $errors);
            }
        } elseif ($type === self::COMMENTARY) {
            foreach (['Commentary Number', 'Commentary Topic'] as $column) {
                if (trim((string)($row[$column] ?? '')) === '') {
                    $errors[] = $column . ' is required for Commentary questions.';
                }
            }
            self::validatePositiveInteger($row, 'Commentary Number', $errors);
            self::validateOptionalPositiveInteger($row, 'Start Page', $errors);
            self::validateOptionalPositiveInteger($row, 'End Page', $errors);
        }

        return array_values(array_unique($errors));
    }

    public static function parseBoolean(mixed $value): ?bool
    {
        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            'yes', 'true', '1' => true,
            'no', 'false', '0' => false,
            default => null,
        };
    }

    /**
     * Prevent spreadsheet programs from interpreting exported user content as
     * a formula. Doubling a genuine leading apostrophe makes this reversible.
     */
    public static function protectSpreadsheetCell(mixed $value): string
    {
        $value = (string)($value ?? '');
        if ($value === '') {
            return '';
        }
        if ($value[0] === "'") {
            return "'" . $value;
        }
        if (preg_match('/^[=+\-@\t\r\n]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    }

    /** Reverse protectSpreadsheetCell() for lossless export/import cycles. */
    public static function unprotectSpreadsheetCell(mixed $value): string
    {
        $value = (string)($value ?? '');
        if (str_starts_with($value, "''")) {
            return substr($value, 1);
        }
        if (preg_match("/^'[=+\\-@\\t\\r\\n]/", $value) === 1) {
            return substr($value, 1);
        }
        return $value;
    }

    /** @param array<string,mixed> $row */
    private static function validatePositiveInteger(array $row, string $column, array &$errors): void
    {
        $value = trim((string)($row[$column] ?? ''));
        if ($value !== '' && (!ctype_digit($value) || (int)$value < 1)) {
            $errors[] = $column . ' must be a positive whole number.';
        }
    }

    /** @param array<string,mixed> $row */
    private static function validateOptionalPositiveInteger(array $row, string $column, array &$errors): void
    {
        self::validatePositiveInteger($row, $column, $errors);
    }
}
