<?php

namespace App\Services;

use InvalidArgumentException;
use PDO;

/** Resolves book/chapter/verse selections to an inclusive canonical range. */
final class QuestionReferenceFilter
{
    public const START_KEY_SQL = '(bStart.BibleOrder * 1000000 + cStart.Number * 1000 + vStart.Number)';
    public const END_KEY_SQL = 'COALESCE((bEnd.BibleOrder * 1000000 + cEnd.Number * 1000 + vEnd.Number), '
        . self::START_KEY_SQL . ')';

    /**
     * @return null|array{start:int,end:int}
     */
    public static function resolve(
        int $yearID,
        int $bookID,
        int $chapterID,
        int $verseID,
        PDO $db
    ): ?array {
        if ($bookID <= 0) {
            if ($chapterID > 0 || $verseID > 0) {
                throw new InvalidArgumentException('A chapter or verse filter requires a book.');
            }
            return null;
        }
        if ($chapterID <= 0 && $verseID > 0) {
            throw new InvalidArgumentException('A verse filter requires a chapter.');
        }

        $where = 'b.YearID = ? AND b.BookID = ?';
        $params = [$yearID, $bookID];
        if ($chapterID > 0) {
            $where .= ' AND c.ChapterID = ?';
            $params[] = $chapterID;
        }
        if ($verseID > 0) {
            $where .= ' AND v.VerseID = ?';
            $params[] = $verseID;
        }

        $keySql = '(b.BibleOrder * 1000000 + c.Number * 1000 + v.Number)';
        $stmt = $db->prepare('
            SELECT MIN(' . $keySql . ') AS RangeStart, MAX(' . $keySql . ') AS RangeEnd
            FROM Books b
            INNER JOIN Chapters c ON c.BookID = b.BookID
            INNER JOIN Verses v ON v.ChapterID = c.ChapterID
            WHERE ' . $where);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false || $row['RangeStart'] === null || $row['RangeEnd'] === null) {
            throw new InvalidArgumentException('The selected book, chapter, or verse is not valid for the current year.');
        }

        return ['start' => (int)$row['RangeStart'], 'end' => (int)$row['RangeEnd']];
    }

    /** @return array{sql:string,params:array<int>} */
    public static function overlapPredicate(?array $range): array
    {
        if ($range === null) {
            return ['sql' => '1 = 1', 'params' => []];
        }

        return [
            // `+ 0` gives placeholders numeric affinity in SQLite while remaining
            // a no-op in MariaDB; PDO otherwise binds execute-array values as text.
            'sql' => self::START_KEY_SQL . ' <= (? + 0) AND ' . self::END_KEY_SQL . ' >= (? + 0)',
            'params' => [(int)$range['end'], (int)$range['start']],
        ];
    }
}
