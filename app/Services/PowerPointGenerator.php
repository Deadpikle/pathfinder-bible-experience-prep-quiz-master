<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Util;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Shadow;

class PowerPointGenerator
{
    private $slideWidth;
    private $slideHeight;
    // TODO: footer for pbeprep.com (in slide master?) and footer for copyright
    // TODO: background image

    public function __construct()
    {
        $this->slideWidth = 1920;
        $this->slideHeight = 1080;
    }

    private function addQuestionNumber(Slide $slide, int $number)
    {
        $shape = $slide->createRichTextShape()
            ->setHeight(100)
            ->setWidth($this->slideWidth)
            ->setOffsetX(0)
            ->setOffsetY(0);
        $shape->getFill()
            ->setStartColor(new Color('FF251C9F'))
            ->setEndColor(new Color(Color::COLOR_BLACK))
            ->setFillType(Fill::FILL_GRADIENT_LINEAR);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $textRun = $shape->createTextRun('  Question ' . $number)
            ->getFont()
            ->setColor(new Color(Color::COLOR_WHITE))
            ->setSize(36);
    }

    private function addQuestionTextToSlide(Slide $slide, string $questionText, int $points = 1)
    {
        $shape = $slide->createRichTextShape()
            ->setHeight(350)
            ->setWidth($this->slideWidth - 100)
            ->setOffsetX(50)
            ->setOffsetY(150)
            ->setAutoFit(RichText::AUTOFIT_SHAPE)//, 90, 5)
            ->setVerticalOverflow(RichText::OVERFLOW_CLIP)
            ->setAutoShrinkHorizontal(false)
            ->setAutoShrinkVertical(false);
        $shape->getFill()
            ->setStartColor(new Color('FFE3E3F0'))
            ->setFillType(Fill::FILL_SOLID);
        $shape->getShadow()
            ->setVisible(true)
            ->setDirection(45)
            ->setDistance(10);
        $shape->setInsetLeft(16)->setInsetTop(16);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP);
        $shape->createTextRun('Question — ' . $points . ' ' . ($points === 1 ? 'point' : 'points'))
            ->getFont()->setSize(34)->setBold(true);
        $shape->createBreak();
        $shape->createTextRun($questionText)
            ->getFont()
            ->setSize(34);
    }

    private function addAnswerTextToSlide(Slide $slide, string $answerText)
    {
        $shape = $slide->createRichTextShape()
            ->setHeight(350)
            ->setWidth($this->slideWidth - 100)
            ->setOffsetX(50)
            ->setOffsetY(550)
            ->setAutoFit(RichText::AUTOFIT_SHAPE)//, 90, 5)
            ->setVerticalOverflow(RichText::OVERFLOW_CLIP)
            ->setAutoShrinkHorizontal(false)
            ->setAutoShrinkVertical(false);
        $shape->getFill()
            ->setStartColor(new Color('FFF6DFE0'))
            ->setFillType(Fill::FILL_SOLID);
        $shape->getShadow()
            ->setVisible(true)
            ->setDirection(45)
            ->setDistance(10);
        $shape->setInsetLeft(16)->setInsetTop(16);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP);
        $shape->createTextRun('Answer')
            ->getFont()->setSize(34)->setBold(true);
        $shape->createBreak();
        if (Util::str_contains('<b>', $answerText)) {
            while (strlen($answerText) > 0) {
                $startTagPos = strpos($answerText, '<b>');
                $endTagPos = strpos($answerText, '</b>');
                if ($startTagPos !== false && $endTagPos !== false) {
                    $before = substr($answerText, 0, $startTagPos);
                    $inside = substr($answerText, $startTagPos + 3, $endTagPos - $startTagPos - 3);
                    if ($before !== '') {
                        $shape->createTextRun($before)
                            ->getFont()
                            ->setSize(34);
                    }
                    $shape->createTextRun($inside)
                        ->getFont()
                        ->setBold(true)
                        ->setSize(34);
                    $answerText = substr($answerText, $endTagPos + 4);
                } else {
                    // add in remaining text, if any
                    if ($answerText !== '') {
                        $shape->createTextRun($answerText)
                            ->getFont()
                            ->setSize(34);
                    }
                    break;
                }
            }
        } else {
            $shape->createTextRun($answerText)
                ->getFont()
                ->setSize(34);
        }
    }

    private function setupSlideMaster(PhpPresentation $presentation)
    {
        $masterSlide = $presentation->getAllMasterSlides()[0];
        $shape = $masterSlide->createRichTextShape()
            ->setHeight(50)
            ->setWidth($this->slideWidth)
            ->setOffsetX(0)
            ->setOffsetY($this->slideHeight - 50);
            // ->setAutoFit(RichText::AUTOFIT_SHAPE)
            // ->setVerticalOverflow(RichText::OVERFLOW_CLIP)
            // ->setAutoShrinkVertical(true);
        $shape->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_BOTTOM);
        $shape->createTextRun('Presentation created on pbeprep.com. Scripture taken from the New King James Version®. Copyright © 1982 by Thomas Nelson. Used by permission. All rights reserved.')
            ->getFont()
            ->setSize(18);
    }

    public function outputPowerPoint(array $quizQuestionData, bool $viewFillInTheBlankAnswersInBold)
    {
        $presentation = new PhpPresentation();
        $presentation->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_CUSTOM, true)
            ->setCX($this->slideWidth, DocumentLayout::UNIT_PIXEL)
            ->setCY($this->slideHeight, DocumentLayout::UNIT_PIXEL);
        $this->setupSlideMaster($presentation);

        $properties = $presentation->getDocumentProperties();
        $properties->setCreator('pbeprep.com');
        $properties->setCompany('pbeprep.com');
        $properties->setTitle('PBE Prep & Quiz Master');
        $properties->setLastModifiedBy('pbeprep.com');

        $questions = $quizQuestionData['questions'];
        for ($i = 0; $i < count($questions); $i++) {
            $slide = $i === 0 ? $presentation->getActiveSlide() : $presentation->createSlide();
            $question = $questions[$i];
            $questionText = Util::getFullQuestionTextFromQuestion($question);
            if (!Question::isTypeFillIn($question["type"])) {
                // make question slide
                $this->addQuestionNumber($slide, $i + 1);
                $this->addQuestionTextToSlide($slide, $questionText, $question['points']);
                // make answer slide
                $slide = $presentation->createSlide();
                $this->addQuestionNumber($slide, $i + 1);
                $this->addQuestionTextToSlide($slide, $questionText, $question['points']);
                $this->addAnswerTextToSlide($slide, $question['answer']);
            } else {
                $fillIn = Util::generateFillInDataFromQuestion($question);
                $questionText .= "\n" . trim($fillIn["question"]);
                // make question slide
                $this->addQuestionNumber($slide, $i + 1);
                $this->addQuestionTextToSlide($slide, $questionText, $question['points']);
                // make answer slide
                $slide = $presentation->createSlide();
                if ($viewFillInTheBlankAnswersInBold) {
                    $this->addQuestionNumber($slide, $i + 1);
                    $this->addQuestionTextToSlide($slide, $questionText, $question['points']);
                    $this->addAnswerTextToSlide($slide, $fillIn['answer']);
                } else {
                    $this->addQuestionNumber($slide, $i + 1);
                    $this->addQuestionTextToSlide($slide, $questionText, $question['points']);
                    $this->addAnswerTextToSlide($slide, join(', ', $fillIn['blanked-words']));
                }
            }
        }
        // output presentation
        $oWriterPPTX = IOFactory::createWriter($presentation, 'PowerPoint2007');
        $filename = 'PBE Prep - Presentation - Generated on ' . date('Y-m-d-h-i-s') . '.pptx';
        header('Content-Type: application/application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment; filename="'. $filename . '"');
        ob_end_clean();
        $oWriterPPTX->save('php://output');
    }
}
