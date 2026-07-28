<?php

namespace App\Controllers\Admin;

use App\Helpers\Translations;
use Yamf\Request;

use App\Models\Commentary;
use App\Models\CSRF;
use App\Models\Language;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Models\Util;
use App\Models\Views\TwigView;
use App\Models\Year;
use App\Services\QuestionCsvReader;
use App\Services\QuestionCsvSchema;
use App\Services\QuestionScope;
use App\Services\FillInAvailabilityPolicy;
use Yamf\Responses\Response;

class ImportQuestionsController extends BaseAdminController
{
    public function viewImportPage(PBEAppConfig $app, Request $request): Response
    {
        $defaultLanguage = Language::loadDefaultLanguage($app->db);
        [$questionBanks, $selectedQuestionBankID] = $this->loadWritableQuestionBanks($app);
        $csvRequirementMatrix = QuestionCsvSchema::requirementMatrix();
        return new TwigView('admin/upload-csv', compact(
            'defaultLanguage',
            'questionBanks',
            'selectedQuestionBankID',
            'csvRequirementMatrix'
        ), 'Upload Questions');
    }

    public function saveImportedQuestions(PBEAppConfig $app, Request $request): Response
    {
        $questionsSuccessfullyAdded = 0;
        $questionsFailedToAdd = 0;
        $errors = '';
        $allLanguages = Language::loadAllLanguages($app->db);
        $defaultLanguage = Language::loadDefaultLanguage($app->db);
        [$questionBanks, $defaultQuestionBankID] = $this->loadWritableQuestionBanks($app);
        $selectedQuestionBankID = Util::validateInteger($request->post, 'question-bank-id');
        if ($selectedQuestionBankID <= 0) {
            $selectedQuestionBankID = $defaultQuestionBankID;
        }
        $scope = QuestionScope::forApp($app, $app->db);
        if (!CSRF::verifyToken('import-questions') || !$scope->canWriteBank($selectedQuestionBankID)) {
            return new Response(403);
        }
        $globalQuestionBankID = QuestionBank::loadGlobal($app->db)?->questionBankID ?? -1;
        $csvRequirementMatrix = QuestionCsvSchema::requirementMatrix();

        $currentYear = Year::loadCurrentYear($app->db);
        $bibleFillIns = Question::getNumberOfFillInBibleQuestionsPerLanguage($currentYear, $app->db);
        $languagesByID = [];
        $languages = Language::loadAllLanguages($app->db);
        foreach ($languages as $language) {
            $languagesByID[$language->languageID] = $language;
        }

        $tmpName = $_FILES['csv']['tmp_name'] ?? '';
        if (
            $tmpName === ''
            || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file($tmpName)
        ) {
            $didProcessUpload = true;
            $questionsFailedToAdd = 1;
            $errors = 'No readable CSV file was uploaded.';
            return new TwigView('admin/upload-csv', compact(
                'errors', 'questionsSuccessfullyAdded', 'questionsFailedToAdd', 'defaultLanguage',
                'didProcessUpload', 'questionBanks', 'selectedQuestionBankID', 'csvRequirementMatrix'
            ), 'Upload Questions');
        }
        try {
            $csv = QuestionCsvReader::readFile($tmpName);
        } catch (\RuntimeException $exception) {
            $didProcessUpload = true;
            $questionsFailedToAdd = 1;
            $errors = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return new TwigView('admin/upload-csv', compact(
                'errors', 'questionsSuccessfullyAdded', 'questionsFailedToAdd', 'defaultLanguage',
                'didProcessUpload', 'questionBanks', 'selectedQuestionBankID', 'csvRequirementMatrix'
            ), 'Upload Questions');
        }
        
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
            $bookName = $bookRow['BookName'];
            $chapterNumber = $bookRow['ChapterNumber'];
            $verseID = $bookRow['VerseID'];
            $verseNumber = $bookRow['VerseNumber'];
            if (!isset($rawBooks[$bookName])) {
                $rawBooks[$bookName] = [];
            }
            if (!isset($rawBooks[$bookName][$chapterNumber])) {
                $rawBooks[$bookName][$chapterNumber] = [];
            }
            $rawBooks[$bookName][$chapterNumber][$verseNumber] = $verseID;
        }
        // Match translated book/topic names using each configured language.
        $translations = [];
        foreach ($languages as $language) {
            $translations[$language->abbreviation] = Translations::getTranslationsForLanguageAbbr($language->abbreviation);
        }
        // prepare the statement
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryID, CommentaryStartPage, CommentaryEndPage, CreatorID, IsDeleted, LanguageID,
            QuestionBankID)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $app->db->prepare($query);
        foreach ($csv as $row) {
            $safeQuestion = htmlspecialchars(
                $row['Question'] ?: '(question text not set)',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
            $schemaErrors = QuestionCsvSchema::validateRow($row);
            if ($schemaErrors !== []) {
                $questionsFailedToAdd++;
                $errors .= 'Unable to add question: '
                    . $safeQuestion
                    . ' -- '
                    . htmlspecialchars(implode(' ', $schemaErrors), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '<br>';
                continue;
            }
            if (!isset($row['Question']) || !isset($row['Start Book']) || !isset($row['Fill in?'])
                || !isset($row['Start Chapter']) || !isset($row['Start Verse']) || !isset($row['Type'])) {
                if (count($row) > 1) {
                    $questionsFailedToAdd++;
                    $errors .= 'Unable to add question: ' . $safeQuestion . ' -- Invalid column data.<br>';
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
            $availabilityLockHeld = false;
            try {
                $questionType = '';
                if (!isset($row['Fill in?'])) {
                    $questionsFailedToAdd++;
                    $errors .= 'Data does not have the Fill in? column.<br>';
                    continue;
                }
                $questionText = trim($row['Question'] ?? '');
                $answerText = trim($row['Answer'] ?? '');
                if ($questionText === '' && $answerText === '') {
                    continue; // bail -- question was intentionally left blank
                }

                $language = trim($row['Language'] ?? $defaultLanguage->name);
                if ($language === '') {
                    $language = $defaultLanguage->name;
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
                    $errors .= 'Unable to add question: ' . $safeQuestion . ' -- Couldn\'t find language '
                        . htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.<br>';
                    continue;
                }
                $isFillInTheBlank = QuestionCsvSchema::parseBoolean($row['Fill in?']) === true;
                $row['Type'] = trim($row['Type']);
                $needsToSubtractTotalBibleFillInIfFailed = false;
                if ($row['Type'] === 'Bible') {
                    if ($isFillInTheBlank) {
                        if ($selectedQuestionBankID !== $globalQuestionBankID) {
                            $questionsFailedToAdd++;
                            $errors .= 'Unable to add question: ' . $safeQuestion
                                . ' -- Bible fill-in questions can only be imported into the global bank.<br>';
                            continue;
                        }
                        $bibleFillIns[$languageID]++;
                        $needsToSubtractTotalBibleFillInIfFailed = true;
                        $questionType = Question::getBibleQnAFillType();
                    } else {
                        $questionType = Question::getBibleQnAType();
                    }
                } else if ($row['Type'] === 'Commentary') {
                    if ($isFillInTheBlank) {
                        $questionType = Question::getCommentaryQnAFillType();
                    } else {
                        $questionType = Question::getCommentaryQnAType();
                    }
                }
                if ($questionType === '') {
                    $questionsFailedToAdd++;
                    $errors .= 'Unable to add question: ' . $safeQuestion . ' -- Invalid question type.<br>';
                    if ($needsToSubtractTotalBibleFillInIfFailed) {
                        $bibleFillIns[$languageID]--;
                    }
                    continue;
                }


                if (Question::isTypeBibleQnA($questionType)) {
                    // find verse id for start
                    $bookName = trim($row['Start Book']);
                    if (!isset($rawBooks[$bookName])) {
                        // check translations
                        foreach ($translations as $translationList) {
                            $key = array_search($bookName, $translationList, true);
                            if ($key !== false) {
                                $bookName = $key;
                                break;
                            }
                        }
                    }
                    $chapterNumber = trim($row['Start Chapter']);
                    $verseNumber = trim($row['Start Verse']);
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
                        $errors .= 'Unable to add Bible question: ' . $safeQuestion . ' -- Invalid book name, chapter, and/or verse.<br>';
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }
                    $bookName = trim($row['End Book'] ?? '');
                    $chapterNumber = trim($row['End Chapter'] ?? '');
                    $verseNumber = trim($row['End Verse'] ?? '');
                    if ($bookName !== "") {
                        if (!isset($rawBooks[$bookName])) {
                            foreach ($translations as $translationList) {
                                $key = array_search($bookName, $translationList, true);
                                if ($key !== false) {
                                    $bookName = $key;
                                    break;
                                }
                            }
                        }
                        if ($bookName !== ''
                            && $chapterNumber !== ''
                            && $verseNumber !== ''
                            && isset($rawBooks[$bookName]) 
                            && isset($rawBooks[$bookName][$chapterNumber]) 
                            && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                            $endVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                        }
                        else {
                            $questionsFailedToAdd++;
                            $errors .= 'Unable to add Bible question: ' . $safeQuestion
                                . ' -- Invalid ending book, chapter, and/or verse.<br>';
                            if ($needsToSubtractTotalBibleFillInIfFailed) {
                                $bibleFillIns[$languageID]--;
                            }
                            continue;
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
                    $commentaryNumber = trim($row['Commentary Number']);
                    $commentaryTopic = trim($row['Commentary Topic']);
                    $commentaryStartPage = $row['Start Page'];
                    $commentaryEndPage = $row['End Page'];
                    if ($commentaryStartPage === '') {
                        $commentaryStartPage = null;
                    }
                    if ($commentaryEndPage === '') {
                        $commentaryEndPage = null;
                    }
                    $commentaryKey = $commentaryNumber . $commentaryTopic;
                    if (!isset($commentaryMap[$commentaryKey])) {
                        // check translations
                        foreach ($translations as $translationList) {
                            $key = array_search($commentaryTopic, $translationList, true);
                            if ($key !== false) {
                                $commentaryTopic = $key;
                                $commentaryKey = $commentaryNumber . $commentaryTopic;
                                break;
                            }
                        }
                    }
                    if (isset($commentaryMap[$commentaryKey])) {
                        $commentaryID = $commentaryMap[$commentaryKey]->commentaryID;
                    } else {
                        $questionsFailedToAdd++;
                        $errors .= 'Unable to add commentary question: ' . $safeQuestion . ' -- Invalid number and/or topic.<br>';
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }

                    $startVerseID = null;
                    $endVerseID = null;
                }

                $points = isset($row['Points']) ? $row['Points'] : "";
                if (trim($points) == '') {
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

                $questionPassesWordFilter = Util::doesTextPassWordFilter($questionText);
                $answerPassesWordFilter = Util::doesTextPassWordFilter($answerText);
                if (!$questionPassesWordFilter || !$answerPassesWordFilter) {
                    $questionsFailedToAdd++;
                    $errors .= 'Unable to add question: '
                        . $safeQuestion
                        . ' -- The ' . (!$questionPassesWordFilter ? 'question' : 'answer') . ' text has invalid text.<br>';
                    if ($needsToSubtractTotalBibleFillInIfFailed) {
                        $bibleFillIns[$languageID]--;
                    }
                    continue;
                }

                if ($app->ENABLE_NKJV_RESTRICTIONS && $questionType === Question::getBibleQnAFillType()) {
                    try {
                        FillInAvailabilityPolicy::acquireLock($app->db);
                        $availabilityLockHeld = true;
                    } catch (\RuntimeException $exception) {
                        $questionsFailedToAdd++;
                        $errors .= 'Unable to add question: ' . $safeQuestion . ' -- '
                            . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . '<br>';
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }
                    $prospectiveQuestion = new Question(-1);
                    $prospectiveQuestion->type = $questionType;
                    $prospectiveQuestion->question = $questionText;
                    $prospectiveQuestion->answer = $answerText;
                    $prospectiveQuestion->numberPoints = (int)$points;
                    $prospectiveQuestion->startVerseID = $startVerseID;
                    $prospectiveQuestion->endVerseID = $endVerseID;
                    $prospectiveQuestion->languageID = $languageID;
                    $prospectiveQuestion->questionBankID = $selectedQuestionBankID;
                    $audit = (new FillInAvailabilityPolicy())->auditProspectiveQuestion($prospectiveQuestion, $app->db);
                    if (!$audit['allowed']) {
                        $questionsFailedToAdd++;
                        $errors .= 'Unable to add question: ' . $safeQuestion . ' -- '
                            . htmlspecialchars(implode(' ', $audit['errors']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . '<br>';
                        if ($needsToSubtractTotalBibleFillInIfFailed) {
                            $bibleFillIns[$languageID]--;
                        }
                        continue;
                    }
                }

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
                    $languageID,
                    $selectedQuestionBankID
                ];
                //print_r($params);
                //die();
                $stmt->execute($params);
                $questionsSuccessfullyAdded++;
            }
            catch (\PDOException $e) {
                $errors .= 'Error inserting question '
                    . $safeQuestion
                    . ': The database rejected the row.<br>';
                $questionsFailedToAdd++;
                if (isset($needsToSubtractTotalBibleFillInIfFailed) && $needsToSubtractTotalBibleFillInIfFailed) {
                    $bibleFillIns[$languageID]--;
                }
                //print_r($e);
                //die();
            }
            finally {
                if ($availabilityLockHeld) {
                    FillInAvailabilityPolicy::releaseLock($app->db);
                }
            }
        }

        $didProcessUpload = true;
        return new TwigView('admin/upload-csv', compact(
            'errors',
            'questionsSuccessfullyAdded',
            'questionsFailedToAdd',
            'defaultLanguage',
            'didProcessUpload',
            'questionBanks',
            'selectedQuestionBankID',
            'csvRequirementMatrix'
        ), 'Upload Questions');
    }

    /** @return array{0:array<QuestionBank>,1:int} */
    private function loadWritableQuestionBanks(PBEAppConfig $app): array
    {
        $scope = QuestionScope::forApp($app, $app->db);
        $writableBankIDs = $scope->writableBankIDs();
        $banks = array_values(array_filter(
            QuestionBank::loadAll($app->db),
            static fn (QuestionBank $bank): bool => in_array($bank->questionBankID, $writableBankIDs, true)
        ));
        return [$banks, $scope->defaultWriteBankID() ?? -1];
    }
}
