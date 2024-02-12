<?php

namespace App\Services;

use App\Helpers\Translations;
use tFPDF;
use tFPDF\PDF;

// a bunch of code modified from http://www.fpdf.org/en/script/script3.php
// and from http://www.fpdf.org/en/tutorial/tuto6.htm (html writing with Write())

// may want to switch to tPDF at https://github.com/DocnetUK/tfpdf
// eventually but it will require substantial changes to our subclass
// as they have changed var names, etc. 
// (May be needed for PHP 8 compatibility; unknown if this will be the case.)

require_once('lib/tfpdf/tfpdf.php');
class PBEPDF extends tFPDF {

    protected $B = 0;
    protected $I = 0;
    protected $U = 0;
    protected $HREF = '';
    protected $lineHeight = 7;
    protected $WIDTH_OFFSET = 0;
    protected $DRAW_RECT = false;
    protected $USE_CELL_OFFSET = false;
    protected $widths;
    protected $aligns;

    public string $userLanguageAbbr;
    
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
                    $attr = [];
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
        $this->SetY(15.4);
        $this->SetFont('DejaVu', 'b', 15);
        // Draw centered title
        $this->Cell(165.1, 10, Translations::t('PBE Study Guide', $this->userLanguageAbbr, true), 0, 0, 'C');
        // Line break
        $this->Ln(15);
    }

    function utf8($str)
    {
        return iconv('UTF-8', 'ISO-8859-1', $str);
    }
    
    // Page footer
    function Footer() {
        $this->SetY(-23.4);
        $this->SetFont('DejaVu', '', 12);
        // Page number
        $this->Cell(0, 10, Translations::t('Page', $this->userLanguageAbbr, true) . ' ' . $this->PageNo() . ' ' 
            . Translations::t('of', $this->userLanguageAbbr, true) . ' {nb}', 0, 0, 'C');
        $this->Ln(10);
        $this->SetFont('DejaVu', '', 7);
        $this->MultiCell(165.1, 5, Translations::t('Scripture taken from the New King James Version® Copyright © 1982 by Thomas Nelson. Used by permission. All rights reserved.', $this->userLanguageAbbr), 0, 'C');
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

    function WillAddedHeightExceedPage($h) {
        return $this->GetY() + $h > $this->PageBreakTrigger;
    }

    function MakePageBreakIfNeeded($h) {
        // If the height h would cause an overflow, add a new page immediately
        if ($this->WillAddedHeightExceedPage($h)) {
            $this->AddPage($this->CurOrientation);
        }
    }

    public function NbLines($w,$txt)
    {
        // tPDF version of NbLines: https://stackoverflow.com/a/64555676/3938401
        $unifontSubset = property_exists($this, 'unifontSubset') && $this->unifontSubset;   # compatible with FPDF and TFPDF.
    
        // Output text with automatic or explicit line breaks
        if(!isset($this->CurrentFont))
            $this->Error('No font has been set');
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin);
        //$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',(string)$txt);
        if ($unifontSubset) {
            $nb=mb_strlen($s, 'utf-8');
            while($nb>0 && mb_substr($s,$nb-1,1,'utf-8')=="\n") $nb--;
        }
        else {
            $nb = strlen($s);
            if($nb>0 && substr($s, $nb-1, 1) == "\n")
                $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while($i<$nb)
        {
            // Get next character
            if ($unifontSubset) {
                $c = mb_substr($s,$i,1,'UTF-8');
            }
            else {
                $c = substr($s,$i,1);
            }
            if($c=="\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                continue;
            }
            if($c==' ')
            {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
    
            if ($unifontSubset) { $l += $this->GetStringWidth($c); }
            else { $l += $cw[$c]*$this->FontSize/1000; }
    
            if($l>$wmax)
            {
                // Automatic line break
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                {
                    $i = $sep+1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
    

    function NbLinesOldFpdf($w, $txt) {
        // Computes the number of lines a MultiCell of width w will take.
        // This was slightly modified from fpdf examples - for formatting and var names; not algorithmically.
        // http://www.fpdf.org/en/script/script3.php
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w= $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r",'',$txt);
        $maxLines = strlen($s);
        if ($maxLines > 0 and $s[$maxLines - 1] == "\n") {
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
                $sep = -1;
                $j = $i;
                $l = 0;
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

    function DrawQuestionTitle($title, $x, $y, $w, $h) {
        //echo $this->GetY() . "<br>";
        $this->SetFont('DejaVu', 'b', 14);
        // print title
        $this->Cell($w - $this->WIDTH_OFFSET, $this->lineHeight, $title, 0, 1, 'C');
    }

    function GetNumberOfLinesForOutput($text, $columnIndex) {
        $textToMeasure = strip_tags($text);
        return $this->NbLines($this->widths[$columnIndex] - $this->WIDTH_OFFSET, $textToMeasure);
    }

    function GetHeight($numberOfLines) {
        return $numberOfLines * $this->lineHeight;
    }

    function DrawOutput($text, $height, $numberOfLines, $cellOffset, $columnIndex, $shouldVerticallyCenter, $rowHeight) {
        $w = $this->widths[$columnIndex];
        $a = isset($this->aligns[$columnIndex]) ? $this->aligns[$columnIndex] : 'L';
        // Save the current position
        $x = $this->GetX();
        $y = $this->GetY();
        // Draw the border
        if ($this->DRAW_RECT) {
            $this->Rect($x, $y, $w, $height + $cellOffset);
        }
        else {
            $this->SetY($y + ($cellOffset / 2), false);
        }
        // center the text vertically as needed
        if ($shouldVerticallyCenter) {
            $this->SetY($y + (($rowHeight - ($this->lineHeight * $numberOfLines)) / 2), false);
        }
        $this->SetFont('DejaVu', '', 14);
        // have to set left margin and right margins properly so the html wrapping works just right and wraps
        // text to the correct location
        $lm = $this->lMargin;
        $rm = $this->rMargin;
        if ($columnIndex == 0) {
            $this->SetLeftMargin($lm);
            $this->SetRightMargin($w + $rm);
        }
        else {
            $this->SetLeftMargin($w + $lm);
        }
        $this->WriteHTML($text);
        $this->SetLeftMargin($lm);
        $this->SetRightMargin($rm);
    }

    // $data[0] == question, $data[1] == answer
    function OutputQuestionAnswerRow($question, $answer, $title) {
        // Calculate the height of the row
        $questionRowCount = $this->GetNumberOfLinesForOutput($title . "\n" . $question, 0);
        $questionHeight = $this->GetHeight($questionRowCount);
        $answerRowCount = $this->GetNumberOfLinesForOutput($answer, 1);
        $answerHeight = $this->GetHeight($answerRowCount);
        $outputHeight = max($questionHeight, $answerHeight);
        $isQuestionHeightBigger = $questionHeight > $answerHeight;

        // Issue a page break first if needed
        $cellOffset = $this->USE_CELL_OFFSET ? 5 : 0;
        $this->MakePageBreakIfNeeded($outputHeight + $cellOffset);

        // Draw separator line between question and answer
        $x = $this->GetX();
        $y = $this->GetY();
        $firstWidth = $this->widths[0];
        if (!$this->DRAW_RECT) {
            $this->Line($x + $firstWidth, $y, $x + $firstWidth, $y + $outputHeight);
        }
        // Draw the title portion of the question ("Question X -- Z Points")
        if (!$isQuestionHeightBigger) {
            // make sure title is centered properly
            $this->SetY($y + (($outputHeight - ($this->lineHeight * $questionRowCount)) / 2), false);
        }
        $this->DrawQuestionTitle($title, $x, $y, $firstWidth, $outputHeight);
        // offset question output by 1 row since the title was printed
        $this->SetY($y + $this->lineHeight);
        $this->DrawOutput($question, $questionHeight, $questionRowCount, $cellOffset, 0, !$isQuestionHeightBigger, $outputHeight);
        $this->SetXY($x + $this->widths[0], $y);
        // Draw answer
        $this->DrawOutput($answer, $answerHeight, $answerRowCount, $cellOffset, 1, $isQuestionHeightBigger, $outputHeight);
        $this->SetXY($x + $this->widths[1], $y);
        // Go to the next line
        $this->Ln($outputHeight + 4 + ($cellOffset / 2));
    }

    private function outputAnswerPage($data, $outputUntilIndex, $startIndexForAnswers, $cellOffset) {
        $this->AddPage($this->CurOrientation);
        $this->outputVerticalSeparatorLine();
        $this->outputTopLine();
        $this->outputMarginLines();
        $this->SetY($this->tMargin + 5);
        $lastColumn = -1;
        for ($j = $startIndexForAnswers; $j < $outputUntilIndex; $j++) {
            // output answers with answers in the opposite column as the question
            $answer = $data[$j];
                // so front/back printing works, we have to swap the column that the answer is shown in (compared to the question's column)
            $outputColumn = $answer["column"] == 0 ? 1 : 0;
            if ($outputColumn == 1) {
                $this->SetXY($this->lMargin + $this->widths[0], $this->GetY());
            }
            else if ($lastColumn != $outputColumn && $outputColumn == 0) {
                $this->SetXY($this->lMargin, $this->tMargin + 5);
            }
            else if ($outputColumn == 0) {
                $this->SetXY($this->lMargin, $this->GetY());
            }
            $lastColumn = $outputColumn;
            $x = $this->GetX();
            $y = $this->GetY();
            $answerText = $answer["output-answer"];
            $outputHeight = $answer["output-height"];
            $answerHeight = $answer["a-height"];
            $answerRowCount = $answer["a-row-count"];
            $isQuestionHeightBigger = $answer["q-taller"];
            $this->DrawOutput($answerText, $answerHeight, $answerRowCount, $cellOffset, $outputColumn, $isQuestionHeightBigger, $outputHeight);
            $this->SetY($y);
            $this->outputHorizontalSeparator($outputColumn, $outputHeight, $y);
            // Go to the next line
            $this->Ln($outputHeight + 4 + ($cellOffset / 2));
        }
    }

    function outputVerticalSeparatorLine() {
        if (!$this->DRAW_RECT) {
            $x = $this->lMargin + $this->widths[0];
            $this->Line($x, $this->tMargin + 3, $x, $this->GetPageHeight() - $this->bMargin - 3);
        }
    }

    function outputHorizontalSeparator($column, $outputHeight, $y) {
        if ($column == 0) {
            $startX = $this->lMargin;
            $endX = $this->lMargin + $this->widths[0];
            $yCoord = $y + $outputHeight + 2;
            $this->Line($startX, $yCoord, $endX, $yCoord);
        }
        else {
            $startX = $this->lMargin + $this->widths[0];
            $endX = $this->lMargin + $this->widths[0] * 2;
            $yCoord = $y + $outputHeight + 2;
            $this->Line($startX, $yCoord, $endX, $yCoord);
        }
    }

    function outputTopLine() {
        $startX = $this->lMargin;
        $endX = $this->lMargin + $this->widths[0] * 2;
        $yCoord = $this->tMargin + 3;
        $this->Line($startX, $yCoord, $endX, $yCoord);
    }

    function outputMarginLines() {
        $startX = $this->lMargin;
        $endX = $this->lMargin + $this->widths[0] * 2;
        $this->Line($startX, $this->tMargin + 3, $startX, $this->GetPageHeight() - $this->bMargin - 3);
        $this->Line($endX, $this->tMargin + 3, $endX, $this->GetPageHeight() - $this->bMargin - 3);
    }

    function OutputFrontBackPages($data) {
        $cellOffset = $this->USE_CELL_OFFSET ? 5 : 0;
        $currentColumn = 0;
        $startIndexForAnswers = 0;
        $this->outputVerticalSeparatorLine();
        $this->outputTopLine();
        $this->outputMarginLines();
        for ($i = 0; $i < count($data); $i++) {
            //echo $this->GetY() . "<br>";
            $question = &$data[$i];
            // fit as many into first column as possible, then fit as many into second column as possible
            $y = $this->GetY();
            if ($this->WillAddedHeightExceedPage($question["output-height"])) {
                if ($currentColumn == 0) {
                    $currentColumn = 1;
                    $this->SetY($this->tMargin + 5); // tbh I'm not sure why I need this magical # 5 right now...used a couple places
                }
                else if ($currentColumn == 1) {
                    // need to page break and output answers
                    $this->outputAnswerPage($data, $i, $startIndexForAnswers, $cellOffset);
                    $this->AddPage($this->CurOrientation);
                    $this->outputVerticalSeparatorLine();
                    $this->outputTopLine();
                    $this->outputMarginLines();
                    $currentColumn = 0;
                    $startIndexForAnswers = $i;
                }
            }
            // OK, output next question
            $question["column"] = $currentColumn;
            $title = $question["title"];
            $outputHeight = $question["output-height"];
            $questionHeight = $question["q-height"];
            $questionRowCount = $question["q-row-count"];
            $isQuestionHeightBigger = $question["q-taller"];
                // Draw separator line between question and answer
            $x = $this->GetX();
            $y = $this->GetY();
            $firstWidth = $this->widths[0];
            // Draw the title portion of the question ("Question X -- Z Points")
            if (!$isQuestionHeightBigger) {
                // make sure title is centered properly
                $this->SetY($y + (($outputHeight - ($this->lineHeight * $questionRowCount)) / 2), false);
            }
            if ($currentColumn == 1) {
                $this->SetXY($x + $this->widths[0], $y);
            }
            $this->DrawQuestionTitle($title, $x, $y, $firstWidth, $outputHeight);
            // offset question output by 1 row since the title was printed
            $this->SetY($y + $this->lineHeight);
            $this->DrawOutput($question["question-text"], $questionHeight, $questionRowCount, $cellOffset, $currentColumn, !$isQuestionHeightBigger, $outputHeight);
            $this->SetY($y);
            $this->outputHorizontalSeparator($currentColumn, $outputHeight, $y);
            $this->Ln($outputHeight + 4 + ($cellOffset / 2));
        }
        // need to output final page of answers!
        $this->outputAnswerPage($data, count($data), $startIndexForAnswers, $cellOffset);
    }
}
