<?php
    // a bunch of code modified from http://www.fpdf.org/en/script/script3.php
    // and from http://www.fpdf.org/en/tutorial/tuto6.htm (html writing with Write())

    require_once('init.php');
    require_once('lib/fpdf181/fpdf.php');

    class UCCPDF extends FPDF {

        protected $B = 0;
        protected $I = 0;
        protected $U = 0;
        protected $HREF = '';
        protected $lineHeight = 7;
        
        function WriteHTML($html) {
            // HTML parser
            $html = str_replace("\n", ' ', $html);
            $a = preg_split('/<(.*)>/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($a as $i=>$e) {
                if ($i % 2 == 0) {
                    // Text
                    if ($this->HREF) {
                        $this->PutLink($this->HREF,$e);
                    }
                    else {
                        $this->Write($this->lineHeight, $e);
                    }
                }
                else {
                    // Tag
                    if ($e[0]=='/') {
                        $this->CloseTag(strtoupper(substr($e,1)));
                    }
                    else {
                        // Extract attributes
                        $a2 = explode(' ', $e);
                        $tag = strtoupper(array_shift($a2));
                        $attr = array();
                        foreach($a2 as $v) {
                            if (preg_match('/([^=]*)=["\']?([^"\']*)/', $v, $a3)) {
                                $attr[strtoupper($a3[1])] = $a3[2];
                            }
                        }
                        $this->OpenTag($tag,$attr);
                    }
                }
            }
        }
        
        function OpenTag($tag, $attr) {
            // Opening tag
            if ($tag=='B' || $tag=='I' || $tag=='U') {
                $this->SetStyle($tag, true);
            }
            if ($tag=='A') {
                $this->HREF = $attr['HREF'];
            }
            if ($tag=='BR') {
                $this->Ln(5);
            }
        }
        
        function CloseTag($tag) {
            // Closing tag
            if ($tag =='B' || $tag =='I' || $tag =='U') {
                $this->SetStyle($tag,false);
            }
            if ($tag=='A') {
                $this->HREF = '';
            }
        }
        
        function SetStyle($tag, $enable) {
            // Modify style and select corresponding font
            $this->$tag += ($enable ? 1 : -1);
            $style = '';
            foreach (array('B', 'I', 'U') as $s) {
                if ($this->$s > 0) {
                    $style .= $s;
                }
            }
            $this->SetFont('', $style);
        }
        
        function PutLink($URL, $txt) {
            // Put a hyperlink
            $this->SetTextColor(0,0,255);
            $this->SetStyle('U',true);
            $this->Write(5,$txt,$URL);
            $this->SetStyle('U',false);
            $this->SetTextColor(0);
        }

        // https://stackoverflow.com/a/36515771/3938401
        function SetCellMargin($margin){
            // Set cell margin
            $this->cMargin = $margin;
        }

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
            $this->SetY(-15.4);
            // Arial italic 8
            $this->SetFont('Arial', '', 12);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        }

        function SetWidths($w) {
            // Set the array of column widths
            $this->widths = $w;
        }
        
        function SetAligns($a) {
            // Set the array of column widths
            $this->aligns = $a;
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
            $maxLines = strlen($s);
            if ($maxLines > 0 and $s[$maxLines-1] == "\n") {
                $maxLines--;
            }
            $sep = -1;
            $i = 0;
            $j = 0;
            $l = 0;
            $nl = 1;
            while ($i < $maxLines) {
                $c = $s[$i];
                if ($c == "\n") {
                    $i++;
                    $sep=-1;
                    $j=$i;
                    $l=0;
                    $nl++;
                    continue;
                }
                if($c == ' ') {
                    $sep = $i;
                }
                $l += $cw[$c];
                if ($l > $wmax) {
                    if ($sep == -1) {
                        if ($i == $j) {
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

        function printTitle($title, $lineHeight, $x, $y, $w, $h, $DRAW_RECT, $WIDTH_OFFSET) {
            if (!$DRAW_RECT) {
                $this->Line($x + $w, $y, $x + $w, $y + $h);
            }
            $this->SetFont('Arial', 'B', 14);
            // print title
            $this->Cell($w - $WIDTH_OFFSET, $lineHeight, $title, 0, 1, 'C');
        }

        function OutputRow($data, $title, $lineHeight) {
            $this->lineHeight = $lineHeight;
            // Calculate the height of the row
            $maxLines = 0;
            $numberOfLinesInCell = array();
            $WIDTH_OFFSET = 0;
            $tallestCellIndex = 0;
            for ($i = 0; $i < count($data); $i++) {
                $outputToCheck = $i == 0 ? $title . "\n" . $data[$i] : $data[$i];
                $numberOfLines = $this->NbLines($this->widths[$i] - $WIDTH_OFFSET, $outputToCheck);
                $numberOfLinesInCell[] = $numberOfLines;
                if ($numberOfLines > $maxLines) {
                    $tallestCellIndex = $i;
                    $maxLines = $numberOfLines;
                }
            }
            $h = $lineHeight * $maxLines;
            // Issue a page break first if needed
            $DRAW_RECT = FALSE;
            $OFFSET = FALSE;
            $cellOffset = $OFFSET ? 5 : 0;
            $this->CheckPageBreak($h + $cellOffset);
            // Draw the cells of the row
            for ($i = 0; $i < count($data); $i++) {
                $w = $this->widths[$i];
                $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
                // Save the current position
                $x = $this->GetX();
                $y = $this->GetY();
                // Draw the border
                if ($DRAW_RECT) {
                    $this->Rect($x, $y, $w, $h + $cellOffset);
                }
                else {
                    $this->SetY($y + ($cellOffset / 2), FALSE);
                }
                // Print the text
                if ($i != $tallestCellIndex) {
                    // this isn't the tallest cell, so center the text vertically
                    $this->SetY($y + (($h - ($lineHeight * $numberOfLinesInCell[$i])) / 2), false);
                    if ($i == 0) { 
                        // printing the title portion of the question ("Question X -- Z Points")
                        $this->printTitle($title, $lineHeight, $x, $y, $w, $h, $DRAW_RECT, $WIDTH_OFFSET);
                    }
                    $this->SetFont('Arial', '', 14);
                    // have to set left margin and right margins properly so the html wrapping works just right and wraps
                    // text to the correct location
                    $lm = $this->lMargin;
                    $rm = $this->rMargin;
                    if ($i == 0) {
                        $this->SetLeftMargin($lm);
                        $this->SetRightMargin($w + $rm);
                    }
                    else {
                        $this->SetLeftMargin($w + $lm);
                    }
                    $this->WriteHTML($data[$i]);
                    $this->SetLeftMargin($lm);
                    $this->SetRightMargin($rm);
                }
                else {
                    if ($i == 0) {
                        $this->printTitle($title, $lineHeight, $x, $y, $w, $h, $DRAW_RECT, $WIDTH_OFFSET);
                    }
                    $this->SetFont('Arial', '', 14); // just in case
                    $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
                }
                // Put the position to the right of the cell
                $this->SetXY($x + $w, $y);
            }
            // Go to the next line
            $this->Ln($h + 4 + ($cellOffset / 2));
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
                $output = "Fill in the blanks for " . $verseText . ".";
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
                $output = "Fill in the blanks for SDA Bible Commentary, Volume " . $volume . ", " . $pageStr . ".";
            }
            else {
                $output = "According to the SDA Bible Commentary, Volume " . $volume . ", " . $pageStr . ", " . lcfirst($output);
            }
        }
        return $output;
    }

    function generate_fill_in($question) {
        $data = $question["fillInData"];
        $blankedOutput = "";
        $boldedOutput = "";
        $i = 0;
        foreach ($data as $questionWords) {
            if ($questionWords["before"] !== "") {
                $blankedOutput .= $questionWords["before"];
                $boldedOutput .= $questionWords["before"];
            }
            if ($questionWords["word"] !== "") {
                if ($questionWords["shouldBeBlanked"]) {
                    $blankedOutput .= "________";
                    $boldedOutput .= "<b>" . $questionWords["word"] . "</b>";
                }
                else {
                    $blankedOutput .= $questionWords["word"];
                    $boldedOutput .= $questionWords["word"];
                }
            }
            if ($questionWords["after"] !== "") {
                $blankedOutput .= $questionWords["after"];
                $boldedOutput .= $questionWords["after"];
            }
            if ($i != count($data) - 1) {
                $blankedOutput .= " ";
                $boldedOutput .= " ";
            }
            $i++;
        }
        return ["question" => $blankedOutput, "answer" => $boldedOutput];
    }

    $pdf = new UCCPDF('P','mm','Letter'); // 8.5 x 11 with Letter size
    $pdf->SetTitle("UCC PBE Study Guide");
    $pdf->SetAuthor("UCC Quiz Engine");
    $pdf->SetCreator("UCC Quiz Engine");
    $pdf->SetKeywords("quiz study Bible UCC PBE Pathfinder");
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
                $fillIn = generate_fill_in($question);
                $text .= "\n" . $fillIn["question"];
                $pdf->OutputRow([$text, $fillIn["answer"]], $title, 7);
            }
            $questionNumber++;
        }
    }

    $pdf->Output();
?>