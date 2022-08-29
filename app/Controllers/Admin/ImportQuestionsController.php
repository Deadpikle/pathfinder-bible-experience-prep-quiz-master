<?php

namespace App\Controllers\Admin;

use Yamf\Request;
use Yamf\Responses\Redirect;
use Yamf\Responses\View;

use App\Models\Club;
use App\Models\Commentary;
use App\Models\Conference;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\StudyGuide;
use App\Models\User;
use App\Models\Views\TwigView;
use App\Models\Year;

class ImportQuestionsController extends BaseAdminController
{
    public function viewImportPage(PBEAppConfig $app, Request $request)
    {
        $defaultLanguage = Language::loadDefaultLanguage($app->db);
        return new TwigView('admin/upload-csv', compact('defaultLanguage'), 'Upload Questions');
    }

    // getHexChar from http://forums.devshed.com/php-development-5/comparing-hex-values-comprising-string-249095.html
    private function getHexChar($hexCode) {
        return chr(hexdec($hexCode));
    }

    public function saveImportedQuestions(PBEAppConfig $app, Request $request)
    {
        // TODO: refactor to a model or other class to handle this
        $questionsSuccessfullyAdded = 0;
        $questionsFailedToAdd = 0;
        $errors = '';
        $allLanguages = Language::loadAllLanguages($app->db);

        $currentYear = Year::loadCurrentYear($app->db);
        $bibleFillIns = Question::getNumberOfFillInBibleQuestionsPerLanguage($currentYear, $app->db);
        $languagesByID = [];
        $languages = Language::loadAllLanguages($app->db);
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }

        $tmpName = $_FILES['csv']['tmp_name'];
        $contents = file_get_contents($tmpName);
        // check if UTF-8 encoded file
        if ($contents[0] == $this->getHexChar('EF') && $contents[1] == $this->getHexChar('BB') && $contents[2] == $this->getHexChar('BF')) {
            $contents = substr($contents, 3);
        }
        // split file by items
        $rows = explode("\r", $contents);
        // get csv data
        $csv = array_map('str_getcsv', $rows);
        /** @var array $csv */
        // make it an associate array with csv keys => values
        array_walk($csv, function(&$a) use ($csv) {
            if (count($a) == count($csv[0])) {
                $a = array_combine($csv[0], $a);
                foreach ($a as $key => $value) {
                    $a[trim($key)] = trim($value);
                }
            }
        });
        array_shift($csv); // remove column header (yay http://php.net/manual/en/function.str-getcsv.php)
        
        // get all the commentaries for the current year
        $commentaries = Commentary::loadCommentariesForYear($currentYear->yearID, $app->db);
        $commentaryMap = [];
        foreach ($commentaries as $commentary) {
            $commentaryNumber = $commentary->number;
            $commentaryTopic = $commentary->topicName;
            $commentaryMap[$commentaryNumber . $commentaryTopic] = $commentary;
        }

        // get all the chapter-verse-data
        $bookQuery = '
            SELECT b.Name AS BookName, c.Number AS ChapterNumber, v.VerseID, v.Number AS VerseNumber
            FROM Books b 
                JOIN Chapters c ON b.BookID = c.BookID
                LEFT JOIN Verses v ON c.ChapterID = v.ChapterID
            WHERE b.YearID = ?
            ORDER BY b.Name, ChapterNumber, VerseNumber';
        $bookStmnt = $app->db->prepare($bookQuery);
        $bookStmnt->execute([$currentYear->yearID]);
        $bookData = $bookStmnt->fetchAll();
        // put it in a nice format for easily querying later
        $rawBooks = [];
        foreach ($bookData as $bookRow) {
            $bookName = $bookRow["BookName"];
            $chapterNumber = $bookRow["ChapterNumber"];
            $verseID = $bookRow["VerseID"];
            $verseNumber = $bookRow["VerseNumber"];
            if (!isset($rawBooks[$bookName])) {
                $rawBooks[$bookName] = [];
            }
            if (!isset($rawBooks[$bookName][$chapterNumber])) {
                $rawBooks[$bookName][$chapterNumber] = array();
            }
            $rawBooks[$bookName][$chapterNumber][$verseNumber] = $verseID;
        }
        // prepare the statement
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryID, CommentaryStartPage, CommentaryEndPage, CreatorID, IsDeleted, LanguageID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $app->db->prepare($query);
        foreach ($csv as $row) {
            if (!isset($row["Question"]) || !isset($row["Start Book"]) || !isset($row["Fill in?"])
                || !isset($row["Start Chapter"]) || !isset($row["Start Verse"]) || !isset($row["Type"])) {
                if (count($row) > 1) {
                    $questionsFailedToAdd++;
                    $errors .= "Unable to add question: " . (isset($row["Question"]) ? $row["Questions"] : "(question unavailable)") . " -- Invalid column data.<br>";
                }
                // else it was probably just a blank row!
                continue; // get rid of blank rows.
            }
            /*$keys = array_keys($row);
            print_r($keys);
            echo "<br><br>";
            print_r($row);
            echo "<br><br>";
            foreach ($keys as $key) {
                echo $key . " => " . $row[$key] . "<br>";
                // if (trim($key) !== $key) {
                //     die("no");
                // }
            }
            var_dump($row);
            echo ($row["Type"]);
            die();*/
            try {
                $questionType = "";
                if (!isset($row["Fill in?"])) {
                    $questionsFailedToAdd++;
                    $errors .= "Data does not have the Fill in? column.<br>";
                    continue;
                }
                $questionText = trim($row["Question"] ?? '');
                $answerText = trim($row["Answer"] ?? '');
                if ($questionText === '' && $answerText === '') {
                    continue; // bail -- question was intentionally left blank
                }

                $language = trim($row["Language"] ?? 'English');
                if ($language === '') {
                    $language = 'English';
                }
                $languageID = -1;
                foreach ($allLanguages as $availableLanguage) {
                    //echo $availableLanguage["Name"] . ' vs ' . $language . '<br>';
                    if ($language == $availableLanguage->name || $language == $availableLanguage->altName) {
                        //echo 'found it' . '<br>';
                        $languageID = $availableLanguage->languageID;
                        break;
                    }
                }
                if ($languageID == -1) {
                    $questionsFailedToAdd++;
                    $errors .= "Unable to add question: " . $row["Question"] . " -- Couldn't find language " . $language . ".<br>";
                    continue;
                }
                $fillInDataFromUser = strtolower(trim($row["Fill in?"]));
                $isFillInTheBlank = $fillInDataFromUser === "yes" || $fillInDataFromUser === "true" || $fillInDataFromUser === 1;
                $row["Type"] = trim($row["Type"]);
                $needsToSubtractTotalBibleFillInIfFailed = false;
                if ($row["Type"] === "Bible") {
                    if ($isFillInTheBlank) {
                        if ($bibleFillIns[$languageID] >= 500 && $app->ENABLE_NKJV_RESTRICTIONS) {
                            $questionsFailedToAdd++;
                            $errors .= "Unable to add question: " . $row["Question"] . " -- Reached max number of Bible questions for " 
                                . $languagesByID[$languageID]->getDisplayName() . ".<br>";
                            continue;
                        }
                        $bibleFillIns[$languageID]++;
                        $needsToSubtractTotalBibleFillInIfFailed = true;
                        $questionType = "bible-qna-fill";
                    }
                    else {
                        $questionType = "bible-qna";
                    }
                }
                else if ($row["Type"] === "Commentary") {
                    if ($isFillInTheBlank) {
                        $questionType = Question::getCommentaryQnAFillType();
                    }
                    else {
                        $questionType = Question::getCommentaryQnAType();
                    }
                }
                if ($questionType === "") {
                    $questionsFailedToAdd++;
                    $errors .= "Unable to add question: " . $row["Question"] . " -- Invalid question type.<br>";
                    if ($needsToSubtractTotalBibleFillInIfFailed) {
                        $bibleFillIns[$languageID]--;
                    }
                    continue;
                }


                if (Question::isTypeBibleQnA($questionType)) {
                    // find verse id for start
                    $bookName = trim($row["Start Book"]);
                    $chapterNumber = trim($row["Start Chapter"]);
                    $verseNumber = trim($row["Start Verse"]);
                    if ($bookName !== ''
                        && $chapterNumber !== ''
                        && $verseNumber !== ''
                        && isset($rawBooks[$bookName]) 
                        && isset($rawBooks[$bookName][$chapterNumber]) 
                        && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                        $startVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                    }
                    else {
                        $questionsFailedToAdd++;
                        $errors .= "Unable to add Bible question: " . $row["Question"] . " -- Invalid book name, chapter, and/or verse.<br>";
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }
                    $bookName = trim($row["End Book"] ?? '');
                    $chapterNumber = trim($row["End Chapter"] ?? '');
                    $verseNumber = trim($row["End Verse"] ?? '');
                    if ($bookName !== "") {
                        if ($bookName !== ''
                            && $chapterNumber !== ''
                            && $verseNumber !== ''
                            && isset($rawBooks[$bookName]) 
                            && isset($rawBooks[$bookName][$chapterNumber]) 
                            && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                            $endVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                        }
                        else {
                            $endVerseID = null;
                        }
                    }
                    else {
                        $endVerseID = null;
                    }
                    
                    $commentaryID = null;
                    $commentaryStartPage = null;
                    $commentaryEndPage = null;
                }
                else if (Question::isTypeCommentaryQnA($questionType)) {
                    $commentaryNumber = trim($row["Commentary Number"]);
                    $commentaryTopic = trim($row["Commentary Topic"]);
                    $commentaryStartPage = $row["Start Page"];
                    $commentaryEndPage = $row["End Page"];
                    if ($commentaryStartPage === '') {
                        $commentaryStartPage = null;
                    }
                    if ($commentaryEndPage === '') {
                        $commentaryEndPage = null;
                    }
                    $commentaryKey = $commentaryNumber . $commentaryTopic;
                    if (isset($commentaryMap[$commentaryKey])) {
                        $commentaryID = $commentaryMap[$commentaryKey]->commentaryID;
                    }
                    else {
                        $questionsFailedToAdd++;
                        $errors .= "Unable to add commentary question: " . $row["Question"] . " -- Invalid number and/or topic.<br>";
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }

                    $startVerseID = null;
                    $endVerseID = null;
                }

                $points = isset($row["Points"]) ? $row["Points"] : "";
                if (trim($points) == "") {
                    $points = '1';
                }

                $questionText = str_replace('“', '"', $questionText);
                $questionText = str_replace('”', '"', $questionText);
                $questionText = str_replace('‘', "'", $questionText);
                $questionText = str_replace('’', "'", $questionText);
                $questionText = str_replace("\r\n", " ", $questionText);
                $questionText = str_replace("\r", " ", $questionText);
                $questionText = str_replace("\n", " ", $questionText);
                $questionText = str_replace("\xCA", "", $questionText);
                $questionText = str_replace("\xD1", " - ", $questionText);
                $questionText = str_replace("\xD2", '"', $questionText);
                $questionText = str_replace("\xD3", '"', $questionText);
                $questionText = str_replace("\xD4", "'", $questionText);
                $questionText = str_replace("\xD5", "'", $questionText);
                $answerText = str_replace('“', '"', $answerText);
                $answerText = str_replace('”', '"', $answerText);
                $answerText = str_replace('‘', "'", $answerText);
                $answerText = str_replace('’', "'", $answerText);
                $answerText = str_replace("\r\n", " ", $answerText);
                $answerText = str_replace("\r", " ", $answerText);
                $answerText = str_replace("\n", " ", $answerText);
                $answerText = str_replace("\xCA", "", $answerText);
                $answerText = str_replace("\xD1", " - ", $answerText);
                $answerText = str_replace("\xD2", '"', $answerText);
                $answerText = str_replace("\xD3", '"', $answerText);
                $answerText = str_replace("\xD4", "'", $answerText);
                $answerText = str_replace("\xD5", "'", $answerText);

                $params = [
                    $questionType, 
                    $questionText,
                    $answerText,
                    $points,
                    User::currentUserID(),
                    $startVerseID,
                    $endVerseID,
                    $commentaryID,
                    $commentaryStartPage,
                    $commentaryEndPage,
                    User::currentUserID(),
                    (int)false, // IsDeleted
                    $languageID
                ];
                //print_r($params);
                //die();
                $stmt->execute($params);
                $questionsSuccessfullyAdded++;
            }
            catch (\PDOException $e) {
                $errors .= "Error inserting question " . $row["Question"] . ": " . $e->getMessage() . "<br>";
                $questionsFailedToAdd++;
                if (isset($needsToSubtractTotalBibleFillInIfFailed) && $needsToSubtractTotalBibleFillInIfFailed) {
                    $bibleFillIns[$languageID]--;
                }
                //print_r($e);
                //die();
            }
        }

        $defaultLanguage = Language::loadDefaultLanguage($app->db);
        $didProcessUpload = true;
        return new TwigView('admin/upload-csv', compact('errors', 'questionsSuccessfullyAdded', 'questionsFailedToAdd', 'defaultLanguage', 'didProcessUpload'), 'Upload Questions');
    }
}
