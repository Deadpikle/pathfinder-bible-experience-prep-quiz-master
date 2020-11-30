<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Util;
use Yamf\Util as YamfUtil;

class PDFGenerator
{
    private static function get_question_text($question) {
        $type = $question["type"];
        $output = trim($question["question"]);
        $isFillIn = Question::isTypeFillIn($type);
        if (!$isFillIn && YamfUtil::strEndsWith($output, '.') && strlen($output) > 0) {
            $output = substr($output, 0, -1) . '?';
        }
        if (!$isFillIn && !YamfUtil::strEndsWith($output, "?") && !YamfUtil::strEndsWith($output, "specific.")) {
            $output .= "?";
        }
        if (Question::isTypeBibleQnA($type)) {
            $startBook = $question["startBook"];
            $startChapter = $question["startChapter"];
            $startVerse = $question["startVerse"];
            $endBook = $question["endBook"];
            $endChapter = $question["endChapter"];
            $endVerse = $question["endVerse"];
            $verseText = $startBook . " " . $startChapter . ":" . $startVerse;
            if ($endBook !== "" && $startVerse != $endVerse) {
                if ($startChapter == $endChapter) {
                    $verseText .= "-" . $endVerse;
                }
                else {
                    $endPart = $endChapter . ":" . $endVerse;
                    $verseText .= "-" . $endPart;
                }
            }
            if ($isFillIn) {
                $output = "Fill in the blanks for " . $verseText . ".";
            }
            else {
                if (!\Yamf\Util::strStartsWith($output, $startBook) && Util::shouldLowercaseOutput($output)) {
                    $output = lcfirst($output);
                }
                $output = "According to " . $verseText . ", " . $output;
            }
        }
        else if (Question::isTypeCommentaryQnA($type)) {
            $volume = $question["volume"];
            $startPage = $question["startPage"];
            $endPage = isset($question["endPage"]) ? $question["endPage"] : null;
            $pageStr = "";
            if ($endPage != null && $endPage != "" && $endPage > $startPage) {
                $pageStr = "pp. " . $startPage . "-" . $endPage;
            }
            else {
                $pageStr = "p. " . $startPage;
            }
            if ($isFillIn) {
                $output = "Fill in the blanks for SDA Bible Commentary, Volume " . $volume . ", " . $pageStr . ".";
            }
            else {
                if (!\Yamf\Util::strStartsWith($output, $volume) && Util::shouldLowercaseOutput($output)) {
                    $output = lcfirst($output);
                }
                $output = "According to the SDA Bible Commentary, Volume " . $volume . ", " . $pageStr . ", " . $output;
            }
        }
        return $output;
    }

    private static function generate_fill_in($question) {
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

    public static function generatePDF(array $quizMaterials, bool $isFrontBack, bool $viewFillInTheBlankAnswersInBold) : PBEPDF // TODO: not nullable
    {
        $pdf = new PBEPDF('P','mm','Letter'); // 8.5 x 11 with Letter size
        $pdf->SetTitle("PBE Study Guide");
        $pdf->SetAuthor("Quiz Master"); // TODO: should be website name
        $pdf->SetCreator("Quiz Master");
        $pdf->SetKeywords("quiz study Bible PBE Pathfinder");
        $pdf->SetSubject("PBE Study Guides");
        $pdf->AliasNbPages();
        $pdf->SetMargins(25.4, 25.4); // 1 inch in mm
        $pdf->SetWidths([82.55, 82.55]);
        $pdf->SetAligns(['L', 'L']);
        $pdf->setCellMargin(3);
        $pdf->AddPage();
        // 8.5 - 2 = 6.5 inches for content width = 165.1 mm
        // 165.1 / 2 = 82.55 mm for each half (questions on left, answers on right)
        $pdf->SetFont('Arial','', 14);
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
                    $points = $question["points"];
                    $pointsStr = $points == 1 ? "point" : "points";
                    $title = "Question " . $questionNumber . " -- " . $points . " " . $pointsStr;
                    $questionText = trim(PDFGenerator::get_question_text($question));
                    if (!Question::isTypeFillIn($question["type"])) {
                        $pdf->OutputQuestionAnswerRow($questionText, $question["answer"], $title);
                    }
                    else {
                        // for fill in the blanks, the full answer is stored
                        // in the question field
                        $fillIn = PDFGenerator::generate_fill_in($question);
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
                    $pointsStr = $points == 1 ? "point" : "points";
                    $title = "Question " . $questionNumber . " -- " . $points . " " . $pointsStr;
                    $questionText = trim(PDFGenerator::get_question_text($question));
                    if (Question::isTypeFillIn($question["type"])) {
                        $fillIn = PDFGenerator::generate_fill_in($question);
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
                }
                $pdf->OutputFrontBackPages($quizMaterials["questions"]);
            }
        }
        return $pdf;
    }
}
