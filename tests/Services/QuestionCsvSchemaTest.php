<?php

use App\Services\QuestionCsvReader;
use App\Services\QuestionCsvSchema;
use PHPUnit\Framework\TestCase;

final class QuestionCsvSchemaTest extends TestCase
{
    public function testCanonicalSchemaHasExactlySixteenColumns(): void
    {
        $this->assertCount(16, QuestionCsvSchema::COLUMNS);
        $this->assertSame([], QuestionCsvSchema::validateHeaders(QuestionCsvSchema::COLUMNS));
        $this->assertSame(QuestionCsvSchema::COLUMNS, array_keys(QuestionCsvSchema::requirementMatrix()));
    }

    public function testHeaderValidationReportsMissingUnexpectedAndDuplicateColumns(): void
    {
        $headers = QuestionCsvSchema::COLUMNS;
        $headers[0] = "\xEF\xBB\xBFType";
        $this->assertSame([], QuestionCsvSchema::validateHeaders($headers));

        $headers[1] = 'Type';
        $errors = implode(' ', QuestionCsvSchema::validateHeaders($headers));
        $this->assertStringContainsString('Duplicate', $errors);
        $this->assertStringContainsString('Fill in?', $errors);
    }

    public function testConditionalRequiredFieldsAreValidated(): void
    {
        $bible = $this->blankRow([
            'Type' => 'Bible',
            'Fill in?' => 'No',
            'Question' => 'Who spoke?',
            'Answer' => 'Peter',
            'Start Book' => 'Acts',
            'Start Chapter' => '2',
            'Start Verse' => '14',
        ]);
        $this->assertSame([], QuestionCsvSchema::validateRow($bible));

        $bible['End Book'] = 'Acts';
        $this->assertStringContainsString(
            'must be supplied together',
            implode(' ', QuestionCsvSchema::validateRow($bible))
        );

        $commentary = $this->blankRow([
            'Type' => 'Commentary',
            'Fill in?' => 'Yes',
            'Question' => 'Complete this quotation.',
            'Commentary Number' => '7',
            'Commentary Topic' => 'Acts',
        ]);
        $this->assertSame([], QuestionCsvSchema::validateRow($commentary));
    }

    public function testSpreadsheetFormulaProtectionIsReversible(): void
    {
        foreach (['=2+2', '+cmd', '-10', '@SUM(A1:A2)', "\t=2+2", "'literal"] as $value) {
            $protected = QuestionCsvSchema::protectSpreadsheetCell($value);
            $this->assertStringStartsWith("'", $protected);
            $this->assertSame($value, QuestionCsvSchema::unprotectSpreadsheetCell($protected));
        }
        $this->assertSame('ordinary text', QuestionCsvSchema::protectSpreadsheetCell('ordinary text'));
    }

    public function testReaderHandlesBomQuotedCommasAndEmbeddedNewlines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pbe-csv-');
        $this->assertNotFalse($path);
        $stream = fopen($path, 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, QuestionCsvSchema::COLUMNS, ',', '"', '', "\r\n");
        fputcsv($stream, [
            'Bible', 'No', 'English', "Who, exactly,\nspoke?", 'Peter', '1',
            'Acts', '2', '14', '', '', '', '', '', '', '',
        ], ',', '"', '', "\r\n");
        fclose($stream);

        try {
            $rows = QuestionCsvReader::readFile($path);
            $this->assertCount(1, $rows);
            $this->assertSame("Who, exactly,\nspoke?", $rows[0]['Question']);
        } finally {
            unlink($path);
        }
    }

    /** @param array<string,string> $overrides
     *  @return array<string,string>
     */
    private function blankRow(array $overrides): array
    {
        return array_replace(array_fill_keys(QuestionCsvSchema::COLUMNS, ''), $overrides);
    }
}
