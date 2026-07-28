<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;
use Throwable;

final class QuestionBank
{
    public int $questionBankID;
    public string $name;
    public bool $isGlobal;
    public bool $isDeleted;
    public string $dateCreated;
    public string $dateModified;

    public function __construct(int $questionBankID, string $name, bool $isGlobal)
    {
        $this->questionBankID = $questionBankID;
        $this->name = $name;
        $this->isGlobal = $isGlobal;
        $this->isDeleted = false;
        $this->dateCreated = '';
        $this->dateModified = '';
    }

    /** @return array<QuestionBank> */
    public static function loadAll(PDO $db, bool $includeDeleted = false): array
    {
        $whereClause = $includeDeleted ? '' : ' WHERE IsDeleted = 0 ';
        return self::loadBanks($whereClause . ' ORDER BY IsGlobal DESC, Name, QuestionBankID', [], $db);
    }

    public static function loadWithID(int $questionBankID, PDO $db): ?QuestionBank
    {
        $banks = self::loadBanks(' WHERE QuestionBankID = ? ', [$questionBankID], $db);
        return $banks[0] ?? null;
    }

    public static function loadGlobal(PDO $db): ?QuestionBank
    {
        $banks = self::loadBanks(
            ' WHERE IsGlobal = 1 AND IsDeleted = 0 ORDER BY QuestionBankID LIMIT 1 ',
            [],
            $db
        );
        return $banks[0] ?? null;
    }

    public static function loadConferenceOverlay(int $conferenceID, PDO $db): ?QuestionBank
    {
        $banks = self::loadBanks('
            INNER JOIN ConferenceQuestionBanks cqb
                ON cqb.QuestionBankID = qb.QuestionBankID
            WHERE cqb.ConferenceID = ?
                AND cqb.IsOverlay = 1
                AND qb.IsDeleted = 0
            LIMIT 1
        ', [$conferenceID], $db);
        return $banks[0] ?? null;
    }

    /** @return array<QuestionBank> */
    public static function loadVisibleForConference(?int $conferenceID, PDO $db): array
    {
        if ($conferenceID === null || $conferenceID <= 0) {
            return self::loadBanks(
                ' WHERE qb.IsGlobal = 1 AND qb.IsDeleted = 0 ORDER BY qb.QuestionBankID ',
                [],
                $db
            );
        }

        return self::loadBanks('
            LEFT JOIN ConferenceQuestionBanks cqb
                ON cqb.QuestionBankID = qb.QuestionBankID
                AND cqb.ConferenceID = ?
            WHERE qb.IsDeleted = 0
                AND (qb.IsGlobal = 1 OR cqb.ConferenceQuestionBankID IS NOT NULL)
            ORDER BY qb.IsGlobal DESC, qb.Name, qb.QuestionBankID
        ', [$conferenceID], $db);
    }

    /**
     * Provision the two fixed bank slots for a newly-created conference.
     * Existing mappings are left untouched, making the method safe to retry.
     */
    public static function ensureConferenceBanks(int $conferenceID, string $conferenceName, PDO $db): void
    {
        if ($conferenceID <= 0) {
            throw new RuntimeException('A valid conference is required to provision question banks.');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $globalBank = self::loadGlobal($db);
            if ($globalBank === null) {
                throw new RuntimeException('The global question bank has not been configured.');
            }

            $mapGlobal = $db->prepare('
                INSERT INTO ConferenceQuestionBanks (ConferenceID, QuestionBankID, IsOverlay)
                VALUES (?, ?, 0)
                ON DUPLICATE KEY UPDATE QuestionBankID = VALUES(QuestionBankID)
            ');
            $mapGlobal->execute([$conferenceID, $globalBank->questionBankID]);

            $overlay = self::loadConferenceOverlay($conferenceID, $db);
            if ($overlay === null) {
                $insertBank = $db->prepare('
                    INSERT INTO QuestionBanks (Name, IsGlobal, IsDeleted)
                    VALUES (?, 0, 0)
                ');
                $insertBank->execute([trim($conferenceName) . ' Private']);
                $overlayBankID = (int)$db->lastInsertId();

                $mapOverlay = $db->prepare('
                    INSERT INTO ConferenceQuestionBanks (ConferenceID, QuestionBankID, IsOverlay)
                    VALUES (?, ?, 1)
                ');
                $mapOverlay->execute([$conferenceID, $overlayBankID]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<QuestionBank> */
    private static function loadBanks(string $querySuffix, array $params, PDO $db): array
    {
        $stmt = $db->prepare('
            SELECT DISTINCT qb.QuestionBankID, qb.Name, qb.IsGlobal, qb.IsDeleted,
                qb.DateCreated, qb.DateModified
            FROM QuestionBanks qb
            ' . $querySuffix);
        $stmt->execute($params);

        $banks = [];
        foreach ($stmt->fetchAll() as $row) {
            $bank = new QuestionBank(
                (int)$row['QuestionBankID'],
                (string)$row['Name'],
                (bool)$row['IsGlobal']
            );
            $bank->isDeleted = (bool)$row['IsDeleted'];
            $bank->dateCreated = (string)$row['DateCreated'];
            $bank->dateModified = (string)$row['DateModified'];
            $banks[] = $bank;
        }
        return $banks;
    }
}
