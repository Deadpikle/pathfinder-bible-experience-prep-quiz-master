<?php

namespace App\Services;

use App\Models\FlagReason;
use App\Models\PBEAppConfig;
use App\Models\Question;
use App\Models\Year;
use DateTime;
use InvalidArgumentException;
use PDO;

/** Parameterized question-list query with bank and reference-range scoping. */
final class QuestionListService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{questions:array,totalQuestions:int}
     */
    public static function load(array $filters, int $userID, PBEAppConfig $app, PDO $db): array
    {
        $questionType = (string)($filters['questionType'] ?? Question::getBibleQnAType());
        if (!in_array($questionType, [Question::getBibleQnAType(), Question::getCommentaryQnAType()], true)) {
            throw new InvalidArgumentException('Invalid question type filter.');
        }

        $questionFilter = (string)($filters['questionFilter'] ?? 'all');
        if (!in_array($questionFilter, ['all', 'recent', 'flagged'], true)) {
            throw new InvalidArgumentException('Invalid question status filter.');
        }

        $year = Year::loadCurrentYear($db);
        if ($year === null) {
            return ['questions' => [], 'totalQuestions' => 0];
        }

        $scope = QuestionScope::forApp($app, $db);
        $bankID = self::positiveInt($filters['questionBankID'] ?? -1);
        if ($bankID > 0) {
            if (!$scope->canReadBank($bankID)) {
                throw new InvalidArgumentException('The selected question bank is not available to this user.');
            }
            $bankPredicate = ['sql' => 'q.QuestionBankID = ?', 'params' => [$bankID]];
        } else {
            $bankPredicate = $scope->readPredicate('q');
        }

        $where = [
            'q.IsDeleted = 0',
            'q.IsActive = 1',
            '(q.Type = ? OR q.Type = ?)',
            $bankPredicate['sql'],
        ];
        $params = [$questionType, $questionType . '-fill', ...$bankPredicate['params']];

        $flagJoin = '';
        $flagSelect = ', NULL AS FlagUserID, NULL AS FlagReason, NULL AS FlagDateTime';
        if ($questionFilter === 'recent') {
            $where[] = 'q.DateCreated >= DATE_SUB(CURRENT_DATE, INTERVAL 8 DAY)';
        } elseif ($questionFilter === 'flagged') {
            $flagJoin = ' INNER JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID ';
            $flagSelect = ', uf.UserID AS FlagUserID, uf.Reason AS FlagReason, uf.DateTimeFlagged AS FlagDateTime';
            if (!$app->isWebAdmin) {
                $where[] = 'uf.UserID = ?';
                $params[] = $userID;
            }
        }

        if ($questionType === Question::getBibleQnAType()) {
            $where[] = 'bStart.YearID = ?';
            $where[] = '(q.EndVerseID IS NULL OR bEnd.YearID = ?)';
            $params[] = $year->yearID;
            $params[] = $year->yearID;

            $range = QuestionReferenceFilter::resolve(
                $year->yearID,
                self::positiveInt($filters['bookFilter'] ?? -1),
                self::positiveInt($filters['chapterFilter'] ?? -1),
                self::positiveInt($filters['verseFilter'] ?? -1),
                $db
            );
            $rangePredicate = QuestionReferenceFilter::overlapPredicate($range);
            $where[] = $rangePredicate['sql'];
            array_push($params, ...$rangePredicate['params']);
            $orderBy = 'bStart.BibleOrder, cStart.Number, vStart.Number, bEnd.BibleOrder, cEnd.Number, vEnd.Number, q.QuestionID';
        } else {
            $where[] = 'comm.YearID = ?';
            $params[] = $year->yearID;
            $volumeID = self::positiveInt($filters['volumeFilter'] ?? -1);
            if ($volumeID > 0) {
                $where[] = 'comm.CommentaryID = ?';
                $params[] = $volumeID;
            }
            $orderBy = 'comm.Number, q.CommentaryStartPage, q.CommentaryEndPage, q.QuestionID';
        }

        $languageID = self::positiveInt($filters['languageID'] ?? -1);
        if ($languageID > 0) {
            $where[] = 'q.LanguageID = ?';
            $params[] = $languageID;
        }

        $searchText = trim((string)($filters['searchText'] ?? ''));
        if ($searchText !== '') {
            $where[] = '(LOWER(q.Question) LIKE ? OR LOWER(q.Answer) LIKE ? '
                . 'OR LOWER(CONCAT(COALESCE(bStart.Name, ""), " ", COALESCE(cStart.Number, ""), ":", COALESCE(vStart.Number, ""))) LIKE ? '
                . 'OR LOWER(CONCAT(COALESCE(bEnd.Name, ""), " ", COALESCE(cEnd.Number, ""), ":", COALESCE(vEnd.Number, ""))) LIKE ?)';
            $search = '%' . strtolower($searchText) . '%';
            array_push($params, $search, $search, $search, $search);
        }

        $fromSql = '
            FROM Questions q
            LEFT JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
            LEFT JOIN Chapters cStart ON vStart.ChapterID = cStart.ChapterID
            LEFT JOIN Books bStart ON bStart.BookID = cStart.BookID
            LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
            LEFT JOIN Chapters cEnd ON vEnd.ChapterID = cEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            LEFT JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID
            LEFT JOIN Languages l ON q.LanguageID = l.LanguageID
            LEFT JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID
            ' . $flagJoin . '
            WHERE ' . implode(' AND ', $where);

        $countStmt = $db->prepare('SELECT COUNT(*) AS QuestionCount ' . $fromSql);
        $countStmt->execute($params);
        $totalQuestions = (int)($countStmt->fetch()['QuestionCount'] ?? 0);

        $pageSize = min(max((int)($filters['pageSize'] ?? 100), 1), 10000);
        $pageOffset = max((int)($filters['pageOffset'] ?? 0), 0);
        $selectSql = '
            SELECT q.QuestionID, q.Question, q.Answer, q.NumberPoints, q.DateCreated,
                q.QuestionBankID, qb.Name AS QuestionBankName,
                bStart.BibleOrder AS StartBibleOrder, bEnd.BibleOrder AS EndBibleOrder,
                bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                q.Type, comm.Number AS CommentaryVolume, comm.TopicName,
                q.CommentaryStartPage, q.CommentaryEndPage,
                l.LanguageID, l.Name AS LanguageName, l.AltName AS LanguageAltName'
                . $flagSelect
                . $fromSql
                . ' ORDER BY ' . $orderBy
                . ' LIMIT ' . $pageOffset . ', ' . $pageSize;
        $stmt = $db->prepare($selectSql);
        $stmt->execute($params);
        $questions = $stmt->fetchAll();

        foreach ($questions as &$question) {
            $question['IsFlagged'] = $questionFilter === 'flagged';
            $question['CanWrite'] = $scope->canWriteBank((int)$question['QuestionBankID']);
            $question['FlagUserID'] = $question['FlagUserID'] !== null ? (int)$question['FlagUserID'] : -1;
            $question['FlagReason'] = FlagReason::toHumanReadable((string)($question['FlagReason'] ?? ''));
            $question['FlagDateTime'] = (string)($question['FlagDateTime'] ?? '');
            $question['FlagReadableDateTime'] = $question['FlagDateTime'] !== ''
                ? (new DateTime($question['FlagDateTime']))->format('F j, Y \\a\\t h:i A')
                : '';
        }
        unset($question);

        return ['questions' => $questions, 'totalQuestions' => $totalQuestions];
    }

    private static function positiveInt(mixed $value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value !== false && $value > 0 ? $value : -1;
    }
}
