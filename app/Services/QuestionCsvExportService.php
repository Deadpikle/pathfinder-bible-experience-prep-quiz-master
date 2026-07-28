<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Year;
use InvalidArgumentException;
use PDO;

final class QuestionCsvExportService
{
    /**
     * @param array<string,mixed> $filters
     */
    public static function export(int $questionBankID, array $filters, PDO $db): string
    {
        if ($questionBankID <= 0) {
            throw new InvalidArgumentException('A single question bank is required for export.');
        }
        $year = Year::loadCurrentYear($db);
        if ($year === null) {
            throw new InvalidArgumentException('A current PBE year is required for export.');
        }

        $where = ['q.QuestionBankID = ?', 'q.IsDeleted = 0', 'q.IsActive = 1'];
        $params = [$questionBankID];
        $questionFilter = trim((string)($filters['questionFilter'] ?? 'all'));
        if (!in_array($questionFilter, ['all', 'recent', 'flagged'], true)) {
            throw new InvalidArgumentException('Invalid question status filter.');
        }
        if ($questionFilter === 'recent') {
            $where[] = 'q.DateCreated >= DATE_SUB(CURRENT_DATE, INTERVAL 8 DAY)';
        } elseif ($questionFilter === 'flagged') {
            $flagUserID = self::positiveInt($filters['flagUserID'] ?? -1);
            if ($flagUserID > 0) {
                $where[] = 'EXISTS (SELECT 1 FROM UserFlagged uf WHERE uf.QuestionID = q.QuestionID AND uf.UserID = ?)';
                $params[] = $flagUserID;
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM UserFlagged uf WHERE uf.QuestionID = q.QuestionID)';
            }
        }
        $questionType = trim((string)($filters['questionType'] ?? ''));
        if ($questionType !== '') {
            if (!in_array($questionType, [Question::getBibleQnAType(), Question::getCommentaryQnAType()], true)) {
                throw new InvalidArgumentException('Invalid question type filter.');
            }
            $where[] = '(q.Type = ? OR q.Type = ?)';
            array_push($params, $questionType, $questionType . '-fill');
        } else {
            $where[] = 'q.Type IN (?, ?, ?, ?)';
            array_push(
                $params,
                Question::getBibleQnAType(),
                Question::getBibleQnAFillType(),
                Question::getCommentaryQnAType(),
                Question::getCommentaryQnAFillType()
            );
        }

        $where[] = '((q.StartVerseID IS NOT NULL AND bStart.YearID = ?) '
            . 'OR (q.CommentaryID IS NOT NULL AND comm.YearID = ?))';
        array_push($params, $year->yearID, $year->yearID);
        $where[] = '(q.StartVerseID IS NULL OR q.EndVerseID IS NULL OR bEnd.YearID = ?)';
        $params[] = $year->yearID;

        $languageID = self::positiveInt($filters['languageID'] ?? -1);
        if ($languageID > 0) {
            $where[] = 'q.LanguageID = ?';
            $params[] = $languageID;
        }

        $bookID = self::positiveInt($filters['bookFilter'] ?? -1);
        $chapterID = self::positiveInt($filters['chapterFilter'] ?? -1);
        $verseID = self::positiveInt($filters['verseFilter'] ?? -1);
        if (
            $questionType !== Question::getCommentaryQnAType()
            && ($bookID > 0 || $chapterID > 0 || $verseID > 0)
        ) {
            $range = QuestionReferenceFilter::resolve(
                $year->yearID,
                $bookID,
                $chapterID,
                $verseID,
                $db
            );
            $rangePredicate = QuestionReferenceFilter::overlapPredicate($range);
            $where[] = $rangePredicate['sql'];
            array_push($params, ...$rangePredicate['params']);
        }

        $volumeID = self::positiveInt($filters['volumeFilter'] ?? -1);
        if ($questionType !== Question::getBibleQnAType() && $volumeID > 0) {
            $where[] = 'q.CommentaryID = ?';
            $params[] = $volumeID;
        }

        $searchText = trim((string)($filters['searchText'] ?? ''));
        if ($searchText !== '') {
            $where[] = '(LOWER(q.Question) LIKE ? OR LOWER(q.Answer) LIKE ?)';
            $search = '%' . strtolower($searchText) . '%';
            array_push($params, $search, $search);
        }

        $stmt = $db->prepare('
            SELECT q.Type, q.Question, q.Answer, q.NumberPoints,
                l.Name AS LanguageName,
                bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                comm.Number AS CommentaryNumber, comm.TopicName AS CommentaryTopic,
                q.CommentaryStartPage, q.CommentaryEndPage
            FROM Questions q
            LEFT JOIN Languages l ON l.LanguageID = q.LanguageID
            LEFT JOIN Verses vStart ON vStart.VerseID = q.StartVerseID
            LEFT JOIN Chapters cStart ON cStart.ChapterID = vStart.ChapterID
            LEFT JOIN Books bStart ON bStart.BookID = cStart.BookID
            LEFT JOIN Verses vEnd ON vEnd.VerseID = q.EndVerseID
            LEFT JOIN Chapters cEnd ON cEnd.ChapterID = vEnd.ChapterID
            LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
            LEFT JOIN Commentaries comm ON comm.CommentaryID = q.CommentaryID
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY q.Type, bStart.BibleOrder, cStart.Number, vStart.Number,
                comm.Number, q.CommentaryStartPage, q.QuestionID
        ');
        $stmt->execute($params);

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new InvalidArgumentException('Unable to create the CSV export.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, QuestionCsvSchema::COLUMNS, ',', '"', '', "\r\n");
        foreach ($stmt->fetchAll() as $row) {
            $isBible = Question::isTypeBibleQnA((string)$row['Type']);
            $csvRow = [
                $isBible ? QuestionCsvSchema::BIBLE : QuestionCsvSchema::COMMENTARY,
                Question::isTypeFillIn((string)$row['Type']) ? 'Yes' : 'No',
                $row['LanguageName'],
                $row['Question'],
                $row['Answer'],
                $row['NumberPoints'],
                $row['StartBook'],
                $row['StartChapter'],
                $row['StartVerse'],
                $row['EndBook'],
                $row['EndChapter'],
                $row['EndVerse'],
                $row['CommentaryNumber'],
                $row['CommentaryTopic'],
                $row['CommentaryStartPage'],
                $row['CommentaryEndPage'],
            ];
            $csvRow = array_map([QuestionCsvSchema::class, 'protectSpreadsheetCell'], $csvRow);
            fputcsv($stream, $csvRow, ',', '"', '', "\r\n");
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read the generated CSV export.');
        }
        return $contents;
    }

    private static function positiveInt(mixed $value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value !== false && $value > 0 ? $value : -1;
    }
}
