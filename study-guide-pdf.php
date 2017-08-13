<?php
    // a bunch of code modified from http://www.fpdf.org/en/script/script3.php

    require_once('init.php');
    require_once('lib/fpdf181/fpdf.php');

    class UCCPDF extends FPDF {
        // Page header
        function Header() {
            // Logo
            // $this->Image('logo.png',10,6,30);
            // Arial bold 15
            $this->SetY(15.4);
            $this->SetFont('Arial', 'B', 15);
            // Draw centered title
            $this->Cell(165.1, 10, 'UCC PBE Study Guide', 0, 0, 'C');
            // Line break
            $this->Ln(15);
        }
        
        // Page footer
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', '', 10);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        }

        function SetWidths($w) {
            // Set the array of column widths
            $this->widths = $w;
        }

        function GetCurrentFont() {
            return $this->CurrentFont;
        }

        function CheckPageBreak($h) {
            // If the height h would cause an overflow, add a new page immediately
            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }
        }

        function NbLines($w, $txt) {
            // Computes the number of lines a MultiCell of width w will take
            $cw = &$this->CurrentFont['cw'];
            if ($w == 0) {
                $w= $this->w - $this->rMargin - $this->x;
            }
            $wmax = ($w-2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r",'',$txt);
            $nb = strlen($s);
            if ($nb > 0 and $s[$nb-1] == "\n") {
                $nb--;
            }
            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $nl = 1;
            while ($i < $nb) {
                $c = $s[$i];
                if ($c == "\n") {
                    $i++;
                    $sep=-1;
                    $j=$i;
                    $l=0;
                    $nl++;
                    continue;
                }
                if($c==' ') {
                    $sep = $i;
                }
                $l += $cw[$c];
                if ($l > $wmax) {
                    if($sep==-1) {
                        if($i==$j) {
                            $i++;
                        }
                    }
                    else {
                        $i = $sep+1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $nl++;
                }
                else {
                    $i++;
                }
            }
            return $nl;
        }

        function OutputRow($data, $title, $lineHeight) {
            // Calculate the height of the row
            $nb = 0;
            for ($i = 0; $i < count($data); $i++) {
                $outputToCheck = $i == 0 ? $title . "\n" . $data[$i] : $data[$i];
                $nb = max($nb, $this->NbLines($this->widths[$i], $outputToCheck));
            }
            $h = $lineHeight * $nb;
            // Issue a page break first if needed
            $this->CheckPageBreak($h);
            // Draw the cells of the row
            for ($i = 0; $i < count($data); $i++) {
                $w = $this->widths[$i];
                $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
                // Save the current position
                $x = $this->GetX();
                $y = $this->GetY();
                // Draw the border
                $this->Rect($x, $y, $w, $h);
                // Print the text
                if ($i == 0) {
                    $this->SetFont('Arial', 'B', 14);
                    $this->Cell($w, $lineHeight, $title, 0, 1, 'C');
                }
                $this->SetFont('Arial', '', 14);
                $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
                // Put the position to the right of the cell
                $this->SetXY($x + $w, $y);
            }
            // Go to the next line
            $this->Ln($h);
        }
    }

    function get_question_text($question) {
        $type = $question["type"];
        $output = $question["question"];
        $isFillIn = is_fill_in($type);
        if (!$isFillIn && !ends_with($output, "?")) {
            $output .= "?";
        }
        if (is_bible_qna($type)) {
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
                $output = "Fill in the blanks for " . $verseText;
            }
            else {
                $output = "According to " . $verseText . ", " . lcfirst($output);
            }
        }
        else if (is_commentary_qna($type)) {
            $volume = $question["volume"];
            $startPage = $question["startPage"];
            $endPage = isset($question["endPage"]) ? $question["endPage"] : NULL;
            $pageStr = "";
            if ($endPage != NULL && $endPage != "" && $endPage > $startPage) {
                $pageStr = "pp. " . $startPage . "-" . $endPage;
            }
            else {
                $pageStr = "p. " . $startPage;
            }
            if ($isFillIn) {
                $output = "Fill in the blanks for SDA Bible Commentary, Volume " . $volume . ", " . $pageStr;
            }
            else {
                $output = "According to the SDA Bible Commentary, Volume " . $volume . ", " . $pageStr . ", " . lcfirst($output);
            }
        }
        return $output;
    }

    function generate_fill_in($question) {
        $data = $question["fillInData"];
        $output = "";
        $i = 0;
        foreach ($data as $questionWords) {
            if ($questionWords["before"] !== "") {
                $output .= $questionWords["before"];
            }
            if ($questionWords["word"] !== "") {
                if ($questionWords["shouldBeBlanked"]) {
                    $output .= "________";
                }
                else {
                    $output .= $questionWords["word"];
                }
            }
            if ($questionWords["after"] !== "") {
                $output .= $questionWords["after"];
            }
            if ($i != count($data) - 1) {
                $output .= " ";
            }
            $i++;
        }
        return $output;
    }

    $pdf = new UCCPDF('P','mm','Letter'); // 8.5 x 11 with Letter size
    $pdf->AliasNbPages();
    $pdf->SetMargins(25.4, 25.4); // 1 inch in mm
    $pdf->SetWidths([82.55, 82.55]);
    $pdf->AddPage();
    // 8.5 - 2 = 6.5 inches for content width = 165.1 mm
    // 165.1 / 2 = 82.55 mm for each half (questions on left, answers on right)
    $pdf->SetFont('Arial','', 14);
    //$pdf->Cell(40,10,'Hello World!', 1);

    // exchange $_POST for actual params
    $params = array();
    $params["maxQuestions"] = $_POST["max-questions"];
    $params["maxPoints"] = $_POST["max-points"];
    $params["questionTypes"] = $_POST["question-types"];
    $params["questionOrder"] = $_POST["order"];
    $params["fillInPercent"] = $_POST["fill-in-percent"];

    $shouldAvoidPastCorrect = "false";
    if (isset($_POST["no-questions-answered-correct"]) && $_POST["no-questions-answered-correct"] != NULL) {
        $shouldAvoidPastCorrect = "true";
    }
    $params["shouldAvoidPastCorrect"] = $shouldAvoidPastCorrect;
    $params["quizItems"] = isset($_POST["quiz-items"]) ? $_POST["quiz-items"] : array();
    $params["userID"] = $_SESSION["UserID"];
    // generate the quiz
    $quizMaterials = generate_quiz_questions($pdo, $params);
    if ($quizMaterials["totalQuestions"] <= 0) {
        $pdf->MultiCell(165.1, 10, 
        'No questions available! Please try selecting some different Bible chapters, commentaries, and/or resetting your saved answers!');        
    }
    else {
        $questionNumber = 1;
        foreach ($quizMaterials["questions"] as $question) {
            //$question = $question["question"];
            $answer = $question["answer"];
            $points = $question["points"];
            $pointsStr = $points == 1 ? "point" : "points";
            $title = "Question " . $questionNumber . " -- " . $points . " " . $pointsStr;
            $text = get_question_text($question);
            if (!is_fill_in($question["type"])) {
                $pdf->OutputRow([$text, $question["answer"]], $title, 7);
            }
            else {
                // for fill in the blanks, the full answer is stored
                // in the question field
                $text .= "\n" . generate_fill_in($question);
                $pdf->OutputRow([$text, $question["question"]], $title, 7);
            }
            $questionNumber++;
        }
    }

    $pdf->Output();
?>