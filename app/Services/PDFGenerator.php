<?php

namespace App\Services;

use App\Helpers\Translations;
use App\Models\Question;
use App\Models\Util;

class PDFGenerator
{
    public static function generatePDF(array $quizMaterials, bool $isFrontBack, bool $viewFillInTheBlankAnswersInBold, string $userLanguageAbbr = 'en'): PBEPDF // TODO: not nullable
    {
        $pdf = new PBEPDF('P','mm','Letter'); // 8.5 x 11 with Letter size

        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
        $pdf->AddFont('DejaVu','b','DejaVuSansCondensed-Bold.ttf',true);
        $pdf->AddFont('DejaVu','bi','DejaVuSansCondensed-BoldOblique.ttf',true);
        $pdf->AddFont('DejaVu','i','DejaVuSansCondensed-Oblique.ttf',true);
        $pdf->SetFont('DejaVu','',14);

        $pdf->userLanguageAbbr = $userLanguageAbbr;
        $pdf->SetTitle(Translations::t('PBE Study Guide', $userLanguageAbbr, true));
        $pdf->SetAuthor("Quiz Master"); // TODO: should be website name
        $pdf->SetCreator("Quiz Master");
        $pdf->SetKeywords("quiz study Bible PBE Pathfinder");
        $pdf->SetSubject(Translations::t('PBE Study Guides', $userLanguageAbbr, true));
        $pdf->AliasNbPages();
        $pdf->SetMargins(25.4, 25.4); // 1 inch in mm
        $pdf->SetWidths([82.55, 82.55]);
        $pdf->SetAligns(['L', 'L']);
        $pdf->setCellMargin(3);
        $pdf->AddPage();
       
        // 8.5 - 2 = 6.5 inches for content width = 165.1 mm
        // 165.1 / 2 = 82.55 mm for each half (questions on left, answers on right)
        // $pdf->SetFont('Arial','', 14);
        //$pdf->Cell(40,10,'Hello World!', 1);
        if ($quizMaterials["totalQuestions"] <= 0) {
            $pdf->MultiCell(165.1, 10, 
            'No questions available! Please try selecting some different Bible chapters, commentaries, and/or resetting your saved answers!');        
        }
        else {
            $questionNumber = 1;
            //if (!isset($_GET["type"]) || $_GET["type"] == "lr" || $_GET["type"] !== "fb") {
            if (!$isFrontBack) {
                // left/right questions (question and answer on single row on same page)
                foreach ($quizMaterials["questions"] as $question) {
                    $points = $question['points'];
                    $pointsStr = Translations::t($points == 1 ? 'point' : 'points', $question['language']->abbreviation, true);
                    $title = Translations::t('Question', $question['language']->abbreviation, true) . ' ' . $questionNumber . " — " . $points . ' ' . $pointsStr;
                    $questionText = Util::getFullQuestionTextFromQuestion($question, true);
                    if (!Question::isTypeFillIn($question["type"])) {
                        $pdf->OutputQuestionAnswerRow($questionText, $question["answer"], $title);
                    }
                    else {
                        // for fill in the blanks, the full answer is stored
                        // in the question field
                        $fillIn = Util::generateFillInDataFromQuestion($question);
                        $questionText .= "\n" . trim($fillIn["question"]);
                        if ($viewFillInTheBlankAnswersInBold) {
                            $pdf->OutputQuestionAnswerRow($questionText, trim($fillIn["answer"]), $title);
                        }
                        else {
                            $pdf->OutputQuestionAnswerRow($questionText, join(", ", $fillIn["blanked-words"]), $title);
                        }
                        //$pdf->OutputQuestionAnswerRow($questionText, $fillIn["answer"], $title);
                    }
                    $questionNumber++;
                }
            } 
            else {
                // pre-measure everything and figure out question formatting so pdf can just handle output
                foreach ($quizMaterials["questions"] as &$question) {
                    $points = $question["points"];
                    $pointsStr = Translations::t($points == 1 ? 'point' : 'points', $question['language']->abbreviation, true);
                    $title = Translations::t('Question', $question['language']->abbreviation, true) . ' ' . $questionNumber . " — " . $points . " " . $pointsStr;
                    $questionText = trim(Util::getFullQuestionTextFromQuestion($question, true));
                    if (Question::isTypeFillIn($question["type"])) {
                        $fillIn = Util::generateFillInDataFromQuestion($question);
                        $questionText .= "\n" . trim($fillIn["question"]);
                        $question["is-fill-in"] = true;
                        if ($viewFillInTheBlankAnswersInBold) {
                            $question["output-answer"] = trim($fillIn["answer"]);
                        }
                        else {
                            $question["output-answer"] = join(", ", $fillIn["blanked-words"]);
                        }
                    }
                    else {
                        $question["output-answer"] = trim($question["answer"]);
                        $question["is-fill-in"] = false;
                    }
                    $question["title"] = $title;
                    $question["question-text"] = trim($questionText); // so we don't need to regenerate it again later
                    // measure measure measure
                    $question["q-row-count"] = $pdf->GetNumberOfLinesForOutput($title . "\n" . $questionText, 0);
                    $question["q-height"] = $pdf->GetHeight($question["q-row-count"]);
                    $question["a-row-count"] = $pdf->GetNumberOfLinesForOutput($question["output-answer"], 1);
                    $question["a-height"] = $pdf->GetHeight($question["a-row-count"]);
                    $question["output-height"] = max($question["q-height"], $question["a-height"]);
                    $question["q-taller"] = $question["q-height"] > $question["a-height"];
                    $questionNumber++;
                }
                $pdf->OutputFrontBackPages($quizMaterials["questions"]);
            }
        }
        return $pdf;
    }
}
