<?php
    // a bunch of code modified from http://www.fpdf.org/en/script/script3.php

    require_once('init.php');
    require_once('lib/fpdf181/fpdf.php');

    print_r($_POST);
    die();

    class UCCPDF extends FPDF {
        // Page header
        function Header() {
            // Logo
            // $this->Image('logo.png',10,6,30);
            // Arial bold 15
            $this->SetFont('Arial','B',15);
            // Draw centered title
            $this->Cell(165.1, 10, 'UCC PBE Quiz Engine Study Guide', 0, 0, 'C');
            // Line break
            $this->Ln(15);
        }
        
        // Page footer
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial','I',8);
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

        function OutputRow($data, $lineHeight) {
            // Calculate the height of the row
            $nb = 0;
            for($i = 0; $i < count($data); $i++) {
                $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
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
                $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
                // Put the position to the right of the cell
                $this->SetXY($x + $w, $y);
            }
            // Go to the next line
            $this->Ln($h);
        }
    }

    $pdf = new UCCPDF('P','mm','Letter'); // 8.5 x 11 with Letter size
    $pdf->AliasNbPages();
    $pdf->SetMargins(25.4, 25.4); // 1 inch in mm
    $pdf->SetWidths([82.55, 82.55]);
    $pdf->AddPage();
    // 8.5 - 2 = 6.5 inches for content width = 165.1 mm
    // 165.1 / 2 = 82.55 mm for each half (questions on left, answers on right)
    $pdf->SetFont('Arial','B', 16);
    //$pdf->Cell(40,10,'Hello World!', 1);

    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->OutputRow([
        'Hi mom! I am a quiz question! This is a song of a young boy who likes to sing lots of songs.',
        'Hi mom! I am a quiz answer! This is a song of a young boy who likes to sing lots of songs twice. This is a song of a young boy who likes to sing lots of songs.'], 7);
    $pdf->Output();
?>