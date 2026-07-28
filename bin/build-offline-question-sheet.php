#!/usr/bin/env php
<?php

use App\Services\QuestionCsvSchema;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

chdir(dirname(__DIR__));
require_once 'vendor/autoload.php';

$options = getopt('', ['inspect::', 'render-html::', 'render-sheet::', 'output::', 'copy-output::']);
if (isset($options['inspect']) || isset($options['render-html'])) {
    $input = is_string($options['inspect'] ?? null) && $options['inspect'] !== ''
        ? $options['inspect']
        : 'files/offline-question-sheet.xlsx';
    $workbook = IOFactory::load($input);
    $inspection = [];
    foreach ($workbook->getWorksheetIterator() as $sheet) {
        $preview = [];
        $maxRow = min(8, $sheet->getHighestDataRow());
        $maxColumn = min(16, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        for ($row = 1; $row <= $maxRow; $row++) {
            $values = [];
            for ($column = 1; $column <= $maxColumn; $column++) {
                $values[] = $sheet->getCell([$column, $row])->getFormattedValue();
            }
            $preview[] = $values;
        }
        $inspection[] = [
            'title' => $sheet->getTitle(),
            'rows' => $sheet->getHighestDataRow(),
            'columns' => $sheet->getHighestDataColumn(),
            'freezePane' => $sheet->getFreezePane(),
            'preview' => $preview,
            'headerStyle' => [
                'fill' => $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB(),
                'fontBold' => $sheet->getStyle('A1')->getFont()->getBold(),
                'fontColor' => $sheet->getStyle('A1')->getFont()->getColor()->getARGB(),
            ],
        ];
    }
    fwrite(STDOUT, json_encode($inspection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    if (isset($options['render-html'])) {
        $htmlPath = is_string($options['render-html']) && $options['render-html'] !== ''
            ? $options['render-html']
            : 'outputs/offline-question-sheet-preview.html';
        $directory = dirname($htmlPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $writer = new Html($workbook);
        if (isset($options['render-sheet']) && $options['render-sheet'] !== '') {
            $writer->setSheetIndex(max(0, (int)$options['render-sheet']));
        } else {
            $writer->writeAllSheets();
        }
        $writer->save($htmlPath);
    }
    exit(0);
}

$spreadsheet = new Spreadsheet();
$questions = $spreadsheet->getActiveSheet();
$questions->setTitle('Questions');
$questions->setShowGridlines(false);
$questions->getTabColor()->setRGB('1F4E78');
$headers = QuestionCsvSchema::COLUMNS;
$questions->fromArray($headers, null, 'A1', true);
$questions->fromArray([
    [
        'Bible', 'No', 'English', 'Who created the heavens and the earth?', 'God', 1,
        'Genesis', 1, 1, '', '', '', '', '', '', '',
    ],
    [
        'Commentary', 'No', 'English', 'What central theme is emphasized?', 'God’s saving work', 2,
        '', '', '', '', '', '', 1, 'Genesis', 12, 13,
    ],
], null, 'A2', true);
$questions->freezePane('A2');
$questions->setAutoFilter('A1:P3');
$questions->getRowDimension(1)->setRowHeight(32);
$questions->getStyle('A1:P1')->applyFromArray([
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']],
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '17365D']]],
]);
$questions->getStyle('A2:P3')->applyFromArray([
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D9E2F3']]],
]);
$questions->getStyle('A2:P2')->getFill()->setFillType('solid')->getStartColor()->setRGB('EAF2F8');
$questions->getStyle('A3:P3')->getFill()->setFillType('solid')->getStartColor()->setRGB('F7F9FB');
$questions->getStyle('D2:E500')->getNumberFormat()->setFormatCode('@');
$questions->getStyle('G2:P500')->getNumberFormat()->setFormatCode('@');

$widths = [
    'A' => 14, 'B' => 12, 'C' => 15, 'D' => 42, 'E' => 36, 'F' => 10,
    'G' => 18, 'H' => 14, 'I' => 12, 'J' => 18, 'K' => 14, 'L' => 12,
    'M' => 20, 'N' => 24, 'O' => 13, 'P' => 13,
];
foreach ($widths as $column => $width) {
    $questions->getColumnDimension($column)->setWidth($width);
}

$listValidations = [
    'A2:A500' => '"Bible,Commentary"',
    'B2:B500' => '"Yes,No"',
    'C2:C500' => '"English,French,Spanish"',
];
foreach ($listValidations as $range => $formula) {
    $validation = new DataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowErrorMessage(true);
    $validation->setErrorTitle('Invalid value');
    $validation->setError('Choose a value from the list.');
    $validation->setShowDropDown(true);
    $validation->setFormula1($formula);
    $questions->setDataValidation($range, $validation);
}

$requirements = $spreadsheet->createSheet();
$requirements->setTitle('Requirements');
$requirements->setShowGridlines(false);
$requirements->getTabColor()->setRGB('70AD47');
$requirements->mergeCells('A1:C1');
$requirements->setCellValue('A1', 'PBE question CSV requirements');
$requirements->mergeCells('A2:C2');
$requirements->setCellValue('A2', 'Enter data on the Questions sheet, keep row 1 unchanged, then save that sheet as UTF-8 CSV before upload.');
$requirements->fromArray(['Column', 'Bible question', 'Commentary question'], null, 'A4', true);
$row = 5;
foreach (QuestionCsvSchema::requirementMatrix() as $column => $matrix) {
    $requirements->fromArray([$column, $matrix['bible'], $matrix['commentary']], null, 'A' . $row, true);
    $row++;
}
$requirements->freezePane('A5');
$requirements->getStyle('A1:C1')->applyFromArray([
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F4E78']],
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$requirements->getRowDimension(1)->setRowHeight(28);
$requirements->getStyle('A2:C2')->applyFromArray([
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2F0D9']],
    'font' => ['italic' => true, 'color' => ['rgb' => '375623']],
    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$requirements->getRowDimension(2)->setRowHeight(34);
$requirements->getStyle('A4:C4')->applyFromArray([
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '5B9BD5']],
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$requirements->getStyle('A5:C' . ($row - 1))->applyFromArray([
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D9E2F3']]],
]);
for ($dataRow = 5; $dataRow < $row; $dataRow++) {
    if ($dataRow % 2 === 0) {
        $requirements->getStyle('A' . $dataRow . ':C' . $dataRow)->getFill()->setFillType('solid')->getStartColor()->setRGB('F3F6F9');
    }
}
$requirements->getColumnDimension('A')->setWidth(24);
$requirements->getColumnDimension('B')->setWidth(56);
$requirements->getColumnDimension('C')->setWidth(56);

$spreadsheet->setActiveSheetIndex(0);
$spreadsheet->getProperties()
    ->setCreator('PBE Prep')
    ->setTitle('PBE question CSV sample')
    ->setDescription('Canonical 16-column question import template and requirement matrix.');

$output = is_string($options['output'] ?? null) && $options['output'] !== ''
    ? $options['output']
    : 'files/offline-question-sheet.xlsx';
$outputDirectory = dirname($output);
if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0777, true);
}
(new Xlsx($spreadsheet))->save($output);
if (isset($options['copy-output']) && is_string($options['copy-output']) && $options['copy-output'] !== '') {
    $copyDirectory = dirname($options['copy-output']);
    if (!is_dir($copyDirectory)) {
        mkdir($copyDirectory, 0777, true);
    }
    (new Xlsx($spreadsheet))->save($options['copy-output']);
}

fwrite(STDOUT, "Wrote {$output}\n");
