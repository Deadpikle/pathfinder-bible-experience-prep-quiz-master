<?php

namespace App\Services;

use RuntimeException;

final class QuestionCsvReader
{
    /**
     * @return array<int,array<string,string>>
     */
    public static function readFile(string $path): array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $headers = fgetcsv($stream, null, ',', '"', '');
            if ($headers === false) {
                throw new RuntimeException('The uploaded CSV file is empty.');
            }
            $headers = array_map(static fn ($header): string => trim((string)$header), $headers);
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            }
            $headerErrors = QuestionCsvSchema::validateHeaders($headers);
            if ($headerErrors !== []) {
                throw new RuntimeException(implode(' ', $headerErrors));
            }

            $rows = [];
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if (count(array_filter(
                    $values,
                    static fn ($value): bool => trim((string)$value) !== ''
                )) === 0) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException(
                        'A CSV row has ' . count($values) . ' columns; expected ' . count($headers) . '.'
                    );
                }
                $row = array_combine($headers, $values);
                if ($row === false) {
                    throw new RuntimeException('A CSV row could not be matched to the schema headers.');
                }
                foreach ($row as $column => $value) {
                    $row[$column] = trim(QuestionCsvSchema::unprotectSpreadsheetCell($value));
                }
                $rows[] = $row;
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
