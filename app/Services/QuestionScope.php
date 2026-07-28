<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PBEAppConfig;
use App\Models\QuestionBank;
use App\Models\User;
use PDO;

/**
 * The single authorization boundary for question-bank reads and writes.
 *
 * Controllers should construct a scope once, validate explicit bank selections
 * with canReadBank()/canWriteBank(), and append the predicate returned by
 * readPredicate()/writePredicate() to every question query.
 */
final class QuestionScope
{
    public const ROLE_GUEST = 'Guest';
    public const ROLE_PATHFINDER = 'Pathfinder';
    public const ROLE_CLUB_ADMIN = 'ClubAdmin';
    public const ROLE_CONFERENCE_ADMIN = 'ConferenceAdmin';
    public const ROLE_WEB_ADMIN = 'WebAdmin';

    /** @var array<int> */
    private array $readableBankIDs;
    /** @var array<int> */
    private array $writableBankIDs;
    private ?int $defaultWriteBankID;

    /**
     * @param array<int> $readableBankIDs
     * @param array<int> $writableBankIDs
     */
    private function __construct(
        array $readableBankIDs,
        array $writableBankIDs,
        ?int $defaultWriteBankID
    ) {
        $this->readableBankIDs = self::normalizeIDs($readableBankIDs);
        $this->writableBankIDs = self::normalizeIDs($writableBankIDs);
        $this->defaultWriteBankID = $defaultWriteBankID !== null
            && in_array($defaultWriteBankID, $this->writableBankIDs, true)
                ? $defaultWriteBankID
                : ($this->writableBankIDs[0] ?? null);
    }

    public static function forApp(
        PBEAppConfig $app,
        PDO $db,
        ?int $conferenceID = null
    ): QuestionScope {
        $role = self::ROLE_PATHFINDER;
        if ($app->isWebAdmin) {
            $role = self::ROLE_WEB_ADMIN;
        } elseif ($app->isConferenceAdmin) {
            $role = self::ROLE_CONFERENCE_ADMIN;
        } elseif ($app->isClubAdmin) {
            $role = self::ROLE_CLUB_ADMIN;
        } elseif ($app->isGuest) {
            $role = self::ROLE_GUEST;
        }

        return self::forRole(
            $role,
            $conferenceID ?? User::currentConferenceID(),
            $db
        );
    }

    public static function forUser(
        int $userID,
        PDO $db,
        ?int $fallbackConferenceID = null
    ): QuestionScope {
        $stmt = $db->prepare('
            SELECT ut.Type, c.ConferenceID
            FROM Users u
            INNER JOIN UserTypes ut ON ut.UserTypeID = u.UserTypeID
            LEFT JOIN Clubs c ON c.ClubID = u.ClubID
            WHERE u.UserID = ? AND u.WasDeleted = 0
            LIMIT 1
        ');
        $stmt->execute([$userID]);
        $user = $stmt->fetch();

        if ($user === false) {
            return self::forRole(
                self::ROLE_GUEST,
                $fallbackConferenceID,
                $db
            );
        }

        $role = (string)$user['Type'];
        // The shared Guest account belongs to the website-admin club in legacy
        // data, while the selected conference lives in the session.
        $conferenceID = $role === self::ROLE_GUEST && $fallbackConferenceID !== null
            ? $fallbackConferenceID
            : ($user['ConferenceID'] !== null
                ? (int)$user['ConferenceID']
                : $fallbackConferenceID);

        return self::forRole($role, $conferenceID, $db);
    }

    public static function forRole(string $role, ?int $conferenceID, PDO $db): QuestionScope
    {
        $global = QuestionBank::loadGlobal($db);
        $globalBankID = $global?->questionBankID;

        if ($role === self::ROLE_WEB_ADMIN) {
            $allBankIDs = array_map(
                static fn (QuestionBank $bank): int => $bank->questionBankID,
                QuestionBank::loadAll($db)
            );
            return new QuestionScope(
                $allBankIDs,
                $globalBankID !== null ? [$globalBankID] : [],
                $globalBankID
            );
        }

        if ($role === self::ROLE_GUEST) {
            return new QuestionScope(
                $globalBankID !== null ? [$globalBankID] : [],
                [],
                null
            );
        }

        $visibleBanks = QuestionBank::loadVisibleForConference($conferenceID, $db);
        $readableBankIDs = array_map(
            static fn (QuestionBank $bank): int => $bank->questionBankID,
            $visibleBanks
        );

        $writableBankIDs = [];
        if (
            $conferenceID !== null
            && $conferenceID > 0
            && in_array($role, [self::ROLE_CONFERENCE_ADMIN, self::ROLE_CLUB_ADMIN], true)
        ) {
            $overlay = QuestionBank::loadConferenceOverlay($conferenceID, $db);
            if ($overlay !== null) {
                $writableBankIDs[] = $overlay->questionBankID;
            }
        }

        return new QuestionScope(
            $readableBankIDs,
            $writableBankIDs,
            $writableBankIDs[0] ?? null
        );
    }

    /**
     * Useful for workers and focused tests that already resolved authorization.
     *
     * @param array<int> $readableBankIDs
     * @param array<int> $writableBankIDs
     */
    public static function fromBankIDs(
        array $readableBankIDs,
        array $writableBankIDs = [],
        ?int $defaultWriteBankID = null
    ): QuestionScope {
        return new QuestionScope($readableBankIDs, $writableBankIDs, $defaultWriteBankID);
    }

    /** @return array<int> */
    public function readableBankIDs(): array
    {
        return $this->readableBankIDs;
    }

    /** @return array<int> */
    public function writableBankIDs(): array
    {
        return $this->writableBankIDs;
    }

    public function defaultWriteBankID(): ?int
    {
        return $this->defaultWriteBankID;
    }

    public function canReadBank(int $questionBankID): bool
    {
        return in_array($questionBankID, $this->readableBankIDs, true);
    }

    public function canWriteBank(int $questionBankID): bool
    {
        return in_array($questionBankID, $this->writableBankIDs, true);
    }

    /** @return array{sql: string, params: array<int>} */
    public function readPredicate(string $questionAlias = 'q'): array
    {
        return $this->predicate($this->readableBankIDs, $questionAlias);
    }

    /** @return array{sql: string, params: array<int>} */
    public function writePredicate(string $questionAlias = 'q'): array
    {
        return $this->predicate($this->writableBankIDs, $questionAlias);
    }

    /**
     * Legacy query builders still using PDO::query() can consume this literal.
     * Every value has first been normalized to a positive integer.
     */
    public function readPredicateLiteral(string $questionAlias = 'q'): string
    {
        return $this->literalPredicate($this->readableBankIDs, $questionAlias);
    }

    /** @param array<int> $bankIDs */
    private function predicate(array $bankIDs, string $questionAlias): array
    {
        $column = self::safeAlias($questionAlias) . '.QuestionBankID';
        if ($bankIDs === []) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        return [
            'sql' => $column . ' IN (' . implode(', ', array_fill(0, count($bankIDs), '?')) . ')',
            'params' => $bankIDs,
        ];
    }

    /** @param array<int> $bankIDs */
    private function literalPredicate(array $bankIDs, string $questionAlias): string
    {
        if ($bankIDs === []) {
            return '1 = 0';
        }

        return self::safeAlias($questionAlias)
            . '.QuestionBankID IN (' . implode(', ', $bankIDs) . ')';
    }

    /** @param array<int> $bankIDs
     *  @return array<int>
     */
    private static function normalizeIDs(array $bankIDs): array
    {
        $normalized = [];
        foreach ($bankIDs as $bankID) {
            $bankID = (int)$bankID;
            if ($bankID > 0) {
                $normalized[$bankID] = $bankID;
            }
        }
        return array_values($normalized);
    }

    private static function safeAlias(string $questionAlias): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $questionAlias) === 1
            ? $questionAlias
            : 'q';
    }
}
