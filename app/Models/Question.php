<?php

namespace App\Models;

use PDO;

class Question
{
    public $questionID;
    public $question;
    public $answer;
    public $numberPoints;
    public $dateCreated;
    public $dateModified;
    public $isFlagged;
    public $type;
    public $commentaryStartPage;
    public $commentaryEndPage;
    public $isDeleted;
    
    public $creatorID;
    public $lastEditedByID;
    public $startVerseID;
    public $endVerseID;
    public $commentaryID;
    public $languageID;

    public function __construct(int $questionID)
    {
        $this->questionID = $questionID;
    }

    public function loadQuestions(string $questionFilter, string $questionType, string $bookFilter, string $volumeFilter, string $searchText, int $pageSize, int $pageOffset, int $languageID, int $userID, PDO $db) : string
    {
        try {
            $whereClause = '';
            $isFlagged = FALSE;
            $flaggedJoinClause = '';
            $questionType = 'bible-qna';
            if (isset($questionFilter)) {
                $questionFilter = $questionFilter;
                if ($questionFilter == 'recent') {
                    $eightDaysAgo = date('Y-m-d 00:00:00', strtotime('-8 days'));
                    $whereClause = ' WHERE DateCreated >= "' . $eightDaysAgo . '"';
                }
                else if ($questionFilter == 'flagged') {
                    $isFlagged = TRUE;
                    $flaggedJoinClause =  ' JOIN UserFlagged uf ON q.QuestionID = uf.QuestionID ';
                    $whereClause = ' WHERE UserID = ' . $userID;
                }
            }
            if (isset($questionType)) {
                $questionType = $questionType;
            }
            if ($whereClause == '') {
                $whereClause = ' WHERE (Type = "' . $questionType . '" OR Type = "' . $questionType . '-fill") ';
            }
            else {
                $whereClause .= ' WHERE (Type = "' . $questionType . '" OR Type = "' . $questionType . '-fill") ';
            }
            // TODO: why are these if/else things in two separate sections exactly?
            if (strpos($questionType, 'bible') !== FALSE) {
                if (isset($bookFilter) && is_numeric($bookFilter) && $bookFilter != -1) {
                    $whereClause .= ' AND bStart.BookID = ' . $bookFilter;
                }
                if (isset($chapterFilter) && is_numeric($chapterFilter) && $chapterFilter != -1) {
                    $whereClause .= ' AND cStart.ChapterID = ' . $chapterFilter;
                }
            }
            else if (strpos($questionType, 'commentary') !== FALSE) {
                if (isset($volumeFilter) && is_numeric($volumeFilter) && $volumeFilter != -1) {
                    $whereClause .= ' AND comm.CommentaryID = ' . $volumeFilter;
                }
            }
    
            $isUsingCustomSearchText = FALSE;
            $searchText = '';
            if (isset($searchText)) {
                $text = trim($searchText);
                if ($text !== '') {
                    $isUsingCustomSearchText = true;
                    $searchText = trim($text);
                }
            }
    
            $currentYear = get_active_year($db)['YearID'];
    
            if ($questionType == 'bible-qna' || $questionType == 'bible-qna-fill') {
                $orderByClause = ' ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number, q.QuestionID ';
                $whereClause .= ' AND IsDeleted = 0 AND bStart.YearID = ' . $currentYear . ' AND (q.EndVerseID IS NULL OR bEnd.YearID = ' . $currentYear . ')';
            }
            else if ($questionType == 'commentary-qna' || $questionType == 'commentary-qna-fill') {
                $orderByClause = ' ORDER BY comm.Number, CommentaryStartPage, CommentaryEndPage, q.QuestionID ';
                $whereClause .= ' AND IsDeleted = 0 AND comm.YearID = ' . $currentYear;
            }
            else {
                $orderByClause = '';
            }
    
            $pageSize = 10;
            if (isset($pageSize)) {
                $pageSize = $pageSize;
            }
    
            $pageOffset = 0;
            if (isset($pageOffset)) {
                $pageOffset = $pageOffset;
            }
    
            // check if need to filter by language
            if ($languageID != -1) {
                if ($whereClause == '') {
                    $whereClause = ' WHERE l.LanguageID = ' . $languageID . ' ';
                }
                else {
                    $whereClause .= ' AND l.LanguageID = ' . $languageID . ' ';
                }
            }
    
            $selectPortion = '
                SELECT q.QuestionID, Question, Answer, NumberPoints, DateCreated,
                    bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                    bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                    Type, comm.Number AS CommentaryVolume, comm.TopicName, CommentaryStartPage, CommentaryEndPage,
                    l.LanguageID, l.Name AS LanguageName, l.AltName AS LanguageAltName ';
            $fromPortion = '
                FROM Questions q 
                    LEFT JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
                    LEFT JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
                    LEFT JOIN Books bStart ON bStart.BookID = cStart.BookID
                    LEFT JOIN Languages l ON q.LanguageID = l.LanguageID
    
                    LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
                    LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID
                    LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
                    LEFT JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID 
                    ' . $flaggedJoinClause . '
                    ' . $whereClause . '
                    ' . $orderByClause;
            if (!$isUsingCustomSearchText) {
                $limitClause = '
                    LIMIT ' . $pageOffset . ',' . $pageSize;  
            }
            else {
                $limitClause = ''; 
                // will handle limit manually, which, while expensive, is necessary
                // unless we implement full text search for the questions, which
                // would be awesome but a whole lot of work :( We'd also want
                // to add some 'searchable' columns for 'Chapter:Verse' and
                // 'Book Chapter:Verse'.
            }
            $fullQuery = $selectPortion . $fromPortion . $limitClause;
            $stmt = $db->query($fullQuery);
            $questions = $stmt->fetchAll();
    
            if ($isUsingCustomSearchText) {
                $tmpQuestions = [];
                $searchText = strtolower($searchText);
                foreach ($questions as $question) {
                    // see if it matches the search text
                    $questionText = strtolower($question['Question']);
                    $answer = strtolower($question['Answer']);
                    $startBook = strtolower($question['StartBook']);
                    $startVerse = strtolower($question['StartChapter']) . ':' . $question['StartVerse'];
                    $startFull = strtolower($startBook) . ' ' . $startVerse;
                    $endBook = '';
                    $endVerse = '';
                    if ($question['EndBook'] !== '') {
                        $endBook = strtolower($question['EndBook']);
                    }
                    if ($question['EndChapter'] !== '') {
                        $endVerse = strtolower($question['EndChapter']) . ':' . $question['EndVerse'];
                    }
                    $endFull = '';
                    if ($endBook !== '' && $endVerse !== '') {
                        $endFull = strtolower($endBook) . ' ' . $endVerse;
                    }
    
                    if (strpos($questionText, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if (strpos($answer, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if (strpos($startBook, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if (strpos($startVerse, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if ($endBook !== '' && strpos($endBook, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if ($endVerse !== '' && strpos($endVerse, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if ($startFull !== '' && strpos($startFull, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                    else if ($endFull !== '' && strpos($endFull, $searchText) !== FALSE) {
                        $tmpQuestions[] = $question;
                    }
                }
                $totalQuestions = count($tmpQuestions);
                // now apply the LIMIT manually
                $offset = $pageOffset;
                if (count($tmpQuestions) > $pageOffset) {
                    $tmpQuestions = array_slice($tmpQuestions, $pageOffset);
                }
                if (count($tmpQuestions) > $pageSize) {
                    $tmpQuestions = array_slice($tmpQuestions, 0, $pageSize);
                }
                // set output to tmpQuestions
                $questions = $tmpQuestions;
            }
            else {
                $stmt = $db->query('SELECT COUNT(*) AS QuestionCount ' . $fromPortion);
                $row = $stmt->fetch(); 
                $totalQuestions = $row['QuestionCount'];
            }
    
            $output = json_encode(array(
                'questions' => $questions,
                'totalQuestions' => $totalQuestions
            ));
            header('Content-Type: application/json; charset=utf-8');
            return $output;
        }
        catch (PDOException $e) {
            return print_r($e, true);
        }
        catch (Exception $e) {
            return print_r($e, true);
        }
    }
}
