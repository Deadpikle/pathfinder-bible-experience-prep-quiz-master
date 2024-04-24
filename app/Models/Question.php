<?php

namespace App\Models;

use PDO;

use App\Models\Year;
use DateTime;
use Exception;
use PDOException;
use Throwable;

class Question
{
    public int $questionID;
    public string $question;
    public string $answer;
    public int $numberPoints;
    public string $dateCreated;
    public string $dateModified;
    public string $type;
    public bool $isDeleted;
    
    public int $creatorID;
    public int $lastEditedByID;
    public int $startVerseID;
    public int $endVerseID;
    public int $commentaryID;
    public ?int $commentaryStartPage;
    public ?int $commentaryEndPage;
    public int $languageID;
    public ?Language $language;

    public function __construct(int $questionID)
    {
        $this->questionID = $questionID;
        $this->question = '';
        $this->answer = '';
        $this->numberPoints = 0;
        $this->dateCreated = '';
        $this->dateModified = '';
        $this->type = self::getBibleQnAType();
        $this->isDeleted = false;
        
        $this->creatorID = -1;
        $this->lastEditedByID = -1;
        $this->startVerseID = -1;
        $this->endVerseID = -1;
        $this->commentaryID = -1;
        $this->commentaryStartPage = 0;
        $this->commentaryEndPage = 0;
        $this->languageID = -1;
        $this->language = null;
    }

    /** @return array<Language> */
    private static function loadQuestions(string $whereClause, array $whereParams, PDO $db): array
    {
        // IFnull(uf.UserFlaggedID, 0) AS IsFlagged
        $query = '
            SELECT q.QuestionID, Type, q.Question, Answer, NumberPoints, StartVerseID, EndVerseID,
                q.CommentaryID, CommentaryStartPage, CommentaryEndPage, q.LanguageID, DateCreated, DateModified,
                IsDeleted, LanguageID, CreatorID, LastEditedByID
            FROM Questions q '
            . $whereClause;
        
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $data = $stmt->fetchAll();
        $output = [];
        $questionIDToLanguageID = [];
        $questionsByQuestionID = [];
        foreach ($data as $row) {
            $question = new Question($row['QuestionID']);
            $question->question = $row['Question'];
            $question->answer = $row['Answer'];
            $question->numberPoints = $row['NumberPoints'];
            $question->dateCreated = $row['DateCreated'];
            $question->dateModified = $row['DateModified'];
            $question->type = $row['Type'];
            $question->isDeleted = $row['IsDeleted'];
            
            $question->creatorID = $row['CreatorID'];
            $question->lastEditedByID = $row['LastEditedByID'];
            $question->startVerseID = $row['StartVerseID'] ?? -1;
            $question->endVerseID = $row['EndVerseID'] ?? -1;
            $question->commentaryID = $row['CommentaryID'] ?? -1;
            $question->commentaryStartPage = $row['CommentaryStartPage'];
            $question->commentaryEndPage = $row['CommentaryEndPage'];
            $question->languageID = $row['LanguageID'];
            $questionIDToLanguageID[$question->questionID] = $question->languageID;
            $questionsByQuestionID[$question->questionID] = $question;

            $output[] = $question;
        }
        $languagesByID = Language::loadAllLanguagesByID($db);
        foreach ($questionIDToLanguageID as $questionID => $languageID) {
            $questionsByQuestionID[$questionID]->language = $languagesByID[$languageID];
        }
        return $output;
    }

    /** @return array<Question> */
    public static function loadAllNonDeletedQuestions(PDO $db): array
    {
        return Question::loadQuestions('WHERE IsDeleted = 0', [], $db);
    }

    public static function loadQuestionWithID(int $questionID, PDO $db): ?Question
    {
        $data = Question::loadQuestions('WHERE QuestionID = ?', [$questionID], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function isTypeBibleQnA(string $type): bool
    {
        return $type === Question::getBibleQnAType() || $type == Question::getBibleQnAFillType();
    }

    public static function getBibleQnAType(): string
    {
        return 'bible-qna';
    }

    public static function getBibleQnAFillType(): string
    {
        return 'bible-qna-fill';
    }

    public static function getCommentaryQnAType(): string
    {
        return 'commentary-qna';
    }

    public static function getCommentaryQnAFillType(): string
    {
        return 'commentary-qna-fill';
    }

    public function isBibleQnA(): bool
    {
        return Question::isTypeBibleQnA($this->type);
    }

    public static function isTypeCommentaryQnA(string $type): bool
    {
        return $type === Question::getCommentaryQnAType() || $type == Question::getCommentaryQnAFillType();
    }

    public function isCommentaryQnA(): bool
    {
        return Question::isTypeCommentaryQnA($this->type);
    }

    public static function isTypeFillIn(string $type): bool
    {
        return $type === Question::getBibleQnAFillType() || $type === Question::getCommentaryQnAFillType();
    }

    public function isFillIn(): bool
    {
        return Question::isTypeFillIn($this->type);
    }

    public function updateDeletedFlag(bool $flag, PDO $db)
    {
        $query = 'UPDATE Questions SET IsDeleted = ? WHERE QuestionID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$flag, $this->questionID]);
    }

    public function create(PDO $db)
    {
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryID, CommentaryStartPage, CommentaryEndPage, LanguageID, CreatorID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $params = [
            $this->type,
            trim($this->question),
            trim($this->answer),
            $this->numberPoints,
            $this->lastEditedByID,
            $this->startVerseID,
            $this->endVerseID,
            $this->commentaryID,
            $this->commentaryStartPage,
            $this->commentaryEndPage,
            $this->languageID,
            $this->creatorID
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $this->questionID = intval($db->lastInsertId());
    }

    public function update(PDO $db)
    {
        $query = '
            UPDATE Questions SET Type = ?, Question = ?, Answer = ?, NumberPoints = ?, LastEditedByID = ?, StartVerseID = ?, EndVerseID = ?,
            CommentaryID = ?, CommentaryStartPage = ?, CommentaryEndPage = ?, LanguageID = ?, DateModified = ? WHERE QuestionID = ?';
        $params = [
            $this->type,
            trim($this->question),
            trim($this->answer),
            $this->numberPoints,
            $this->lastEditedByID,
            $this->startVerseID,
            $this->endVerseID,
            $this->commentaryID,
            $this->commentaryStartPage,
            $this->commentaryEndPage,
            $this->languageID,
            date('Y-m-d H:i:s'),
            $this->questionID
        ];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    }

    public static function getNumberOfFillInBibleQuestionsForCurrentYear(PDO $db): int
    {
        $currentYear = Year::loadCurrentYear($db);
        return Question::getNumberOfFillInBibleQuestions($currentYear, $db);
    }

    public static function getNumberOfFillInBibleQuestions(Year $year, PDO $db): int
    {
        $query = '
            SELECT COUNT(q.QuestionID) AS QuestionCount
            FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? 
                AND q.Type = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([ $year->yearID, Question::getBibleQnAFillType() ]);
        $bookQuestionData = $stmt->fetch();
        return $bookQuestionData !== null ? $bookQuestionData['QuestionCount'] : 0;
    }

    /** @return array<int,int> */
    public static function getNumberOfFillInBibleQuestionsPerLanguage(Year $year, PDO $db): array
    {
        // this could probably be improved to be a single query with a GROUP BY
        $languages = Language::loadAllLanguages($db);
        $fillIns = [];
        $query = '
            SELECT COUNT(q.QuestionID) AS QuestionCount
            FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? 
                AND q.LanguageID = ?
                AND q.Type = "bible-qna-fill"';
        $stmt = $db->prepare($query);
        foreach ($languages as $language) {
            $stmt->execute([
                $year->yearID,
                $language->languageID
            ]);
            $bookQuestionData = $stmt->fetch();
            if ($bookQuestionData != null) {
                $fillIns[$language->languageID] = $bookQuestionData['QuestionCount'];
            } else {
                $fillIns[$language->languageID] = 0;
            }
        }
        return $fillIns;
    }

    /**
     * Load MatchingQuestionItem list for a given chapter
     *
     * @param int $chapterID
     * @param int $languageID
     * @param PDO $db
     *
     * @return array<MatchingQuestionItem>
     */
    public static function loadMatchingFillInQuestionsForChapterAndLanguage(int $chapterID, int $languageID, PDO $db): array
    {
        $query = '
            SELECT q.Question, v.VerseID, v.Number AS VerseNumber, b.Name, c.Number AS ChapterNumber
            FROM Questions q 
                JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE c.ChapterID = ? AND q.LanguageID = ? AND q.Type = ?';

        $stmt = $db->prepare($query);
        $stmt->execute([
            $chapterID,
            $languageID,
            self::getBibleQnAFillType()
        ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $matchingItem = new MatchingQuestionItem(
                -1,
                $row['Name'] . ' ' . $row['ChapterNumber'] . ':' . $row['VerseNumber'],
                $row['Question']
            );
            $matchingItem->startVerseID = $row['VerseID'];
            $output[] = $matchingItem;
        }
        return $output;
    }

    public static function loadFillInsForChapterAndLanguage(int $chapterID, int $languageID, PDO $db): array
    {
        $query = '
            SELECT q.Question, v.VerseID, v.Number AS VerseNumber, b.Name, c.Number AS ChapterNumber
            FROM Questions q 
                JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE c.ChapterID = ? AND q.LanguageID = ? AND q.Type = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([
            $chapterID,
            $languageID,
            self::getBibleQnAFillType()
        ]);
        $data = $stmt->fetchAll();
        $output = [];
        foreach ($data as $row) {
            $output[] = [
                'question' => $row['Question'],
                'chapter' => $row['ChapterNumber'],
                'verse' => $row['VerseNumber']
            ];
        }
        return $output;
    }
    
    public static function loadQuestionsWithFilters(string $questionFilter, string $questionType, string $bookFilter, string $chapterFilter, string $volumeFilter, string $searchText, int $pageSize, int $pageOffset, int $languageID, int $userID, PBEAppConfig $app, PDO $db): array
    {
        try {
            $whereClause = '';
            $isFlagged = false;
            $flaggedJoinClause = '';
            $extraSelect = '';
            if (isset($questionFilter)) {
                if ($questionFilter == 'recent') {
                    $eightDaysAgo = date('Y-m-d 00:00:00', strtotime('-8 days'));
                    $whereClause = ' WHERE DateCreated >= "' . $eightDaysAgo . '"';
                } else if ($questionFilter == 'flagged') {
                    $isFlagged = true;
                    $flaggedJoinClause =  ' JOIN UserFlagged uf ON q.QuestionID = uf.QuestionID ';
                    if (!$app->isWebAdmin) {
                        // if web admin, can view all flagged questions from all users.
                        // otherwise, only see your own.
                        $whereClause = ' WHERE uf.UserID = ' . $userID;
                    }
                    $extraSelect = ', uf.UserID AS FlagUserID, uf.Reason AS FlagReason, uf.DateTimeFlagged AS FlagDateTime ';
                }
            }
            $questionType = $questionType ?? Question::getBibleQnAType();
            if ($whereClause == '') {
                $whereClause = ' WHERE (Type = "' . $questionType . '" OR Type = "' . $questionType . '-fill") ';
            } else {
                $whereClause .= ' AND (Type = "' . $questionType . '" OR Type = "' . $questionType . '-fill") ';
            }
            // TODO: why are these if/else things in two separate sections exactly?
            if (strpos($questionType, 'bible') !== false) {
                if (isset($bookFilter) && is_numeric($bookFilter) && $bookFilter != -1) {
                    $whereClause .= ' AND bStart.BookID = ' . $bookFilter;
                }
                if (isset($chapterFilter) && is_numeric($chapterFilter) && $chapterFilter != -1) {
                    $whereClause .= ' AND cStart.ChapterID = ' . $chapterFilter;
                }
            } else if (strpos($questionType, 'commentary') !== false) {
                if (isset($volumeFilter) && is_numeric($volumeFilter) && $volumeFilter != -1) {
                    $whereClause .= ' AND comm.CommentaryID = ' . $volumeFilter;
                }
            }
    
            $isUsingCustomSearchText = false;
            if (isset($searchText)) {
                $text = trim($searchText);
                if ($text !== '') {
                    $isUsingCustomSearchText = true;
                    $searchText = $text;
                }
            }
    
            $currentYear = (Year::loadCurrentYear($db))->yearID;
    
            if ($questionType == Question::getBibleQnAType() || $questionType == Question::getBibleQnAFillType()) {
                $orderByClause = ' ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number, q.QuestionID ';
                $whereClause .= ' AND IsDeleted = 0 AND bStart.YearID = ' . $currentYear . ' AND (q.EndVerseID IS null OR bEnd.YearID = ' . $currentYear . ')';
            } else if ($questionType == Question::getCommentaryQnAType() || $questionType == Question::getCommentaryQnAFillType()) {
                $orderByClause = ' ORDER BY comm.Number, CommentaryStartPage, CommentaryEndPage, q.QuestionID ';
                $whereClause .= ' AND IsDeleted = 0 AND comm.YearID = ' . $currentYear;
            } else {
                $orderByClause = '';
            }
    
            if (!isset($pageSize)) {
                $pageSize = 10;
            }
    
            if (!isset($pageOffset)) {
                $pageOffset = 0;
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
                    bStart.BibleOrder AS StartBibleOrder, bEnd.BibleOrder AS EndBibleOrder,
                    bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                    bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                    Type, comm.Number AS CommentaryVolume, comm.TopicName, CommentaryStartPage, CommentaryEndPage,
                    l.LanguageID, l.Name AS LanguageName, l.AltName AS LanguageAltName ' . $extraSelect;
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
    
                    if (strpos($questionText, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if (strpos($answer, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if (strpos($startBook, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if (strpos($startVerse, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if ($endBook !== '' && strpos($endBook, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if ($endVerse !== '' && strpos($endVerse, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if ($startFull !== '' && strpos($startFull, $searchText) !== false) {
                        $tmpQuestions[] = $question;
                    } else if ($endFull !== '' && strpos($endFull, $searchText) !== false) {
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
            } else {
                $stmt = $db->query('SELECT COUNT(*) AS QuestionCount ' . $fromPortion);
                $row = $stmt->fetch(); 
                $totalQuestions = $row['QuestionCount'];
            }
            foreach ($questions as &$question) {
                $question['IsFlagged'] = $isFlagged;
                $question['FlagUserID'] = $question['FlagUserID'] ?? -1;
                $question['FlagReason'] = FlagReason::toHumanReadable($question['FlagReason'] ?? '');
                $question['FlagDateTime'] = $question['FlagDateTime'] ?? '';
                $question['FlagReadableDateTime'] = isset($question['FlagDateTime']) 
                    ? (new DateTime($question['FlagDateTime']))->format('F j, Y \\a\\t h:i A') 
                    : '';
            }
    
            $output = [
                'questions' => $questions,
                'totalQuestions' => $totalQuestions
            ];
            return $output;
        }
        catch (PDOException $e) {
            return ['error' => print_r($e, true)]; // TODO: return an actual error!
        }
        catch (Throwable $e) {
            return ['error' => print_r($e, true)];
        }
        return [];
    }
}
