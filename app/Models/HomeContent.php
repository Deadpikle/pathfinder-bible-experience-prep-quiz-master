<?php

namespace App\Models;

use App\Helpers\SafeMarkdown;
use PDO;

class HomeContent
{
    public int $homeContentID;
    public int $yearID;
    public int $conferenceID;
    public int $uiLanguageID;
    public string $markdown;
    public ?int $updatedByUserID;
    public string $dateUpdated;
    public bool $isLanguageFallback;
    public bool $isGlobal;

    public function __construct(int $homeContentID = -1)
    {
        $this->homeContentID = $homeContentID;
        $this->yearID = -1;
        $this->conferenceID = -1;
        $this->uiLanguageID = -1;
        $this->markdown = '';
        $this->updatedByUserID = null;
        $this->dateUpdated = '';
        $this->isLanguageFallback = false;
        $this->isGlobal = false;
    }

    public static function loadExact(int $yearID, int $conferenceID, int $uiLanguageID, PDO $db): ?HomeContent
    {
        $stmt = $db->prepare('
            SELECT HomeContentID, YearID, ConferenceID, UILanguageID, Markdown,
                   UpdatedByUserID, DateUpdated
            FROM HomeContents
            WHERE YearID = ? AND ConferenceID = ? AND UILanguageID = ?
            LIMIT 1');
        $stmt->execute([$yearID, $conferenceID, $uiLanguageID]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /**
     * Load global content followed by an optional conference override. Each
     * scope falls back independently to English when the requested UI locale
     * has no authored content.
     *
     * @return array<HomeContent>
     */
    public static function loadForHome(
        int $yearID,
        int $conferenceID,
        int $globalConferenceID,
        int $requestedLanguageID,
        int $englishLanguageID,
        PDO $db
    ): array {
        $scopeIDs = [$globalConferenceID];
        if ($conferenceID > 0 && $conferenceID !== $globalConferenceID) {
            $scopeIDs[] = $conferenceID;
        }

        $output = [];
        foreach ($scopeIDs as $scopeID) {
            $content = self::loadExact($yearID, $scopeID, $requestedLanguageID, $db);
            $isFallback = false;
            if ($content !== null && trim($content->markdown) === '') {
                $content = null;
            }
            if ($content === null && $requestedLanguageID !== $englishLanguageID) {
                $content = self::loadExact($yearID, $scopeID, $englishLanguageID, $db);
                $isFallback = $content !== null;
            }
            if ($content === null && $scopeID === $globalConferenceID) {
                $content = new HomeContent();
                $content->yearID = $yearID;
                $content->conferenceID = $globalConferenceID;
                $content->uiLanguageID = $englishLanguageID;
                $content->markdown = self::defaultEnglishMarkdown();
                $isFallback = $requestedLanguageID !== $englishLanguageID;
            }
            if ($content !== null && trim($content->markdown) !== '') {
                $content->isLanguageFallback = $isFallback;
                $content->isGlobal = $scopeID === $globalConferenceID;
                $output[] = $content;
            }
        }
        return $output;
    }

    public function save(PDO $db): void
    {
        $stmt = $db->prepare('
            INSERT INTO HomeContents
                (YearID, ConferenceID, UILanguageID, Markdown, UpdatedByUserID, DateUpdated)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                Markdown = VALUES(Markdown),
                UpdatedByUserID = VALUES(UpdatedByUserID),
                DateUpdated = VALUES(DateUpdated)');
        $stmt->execute([
            $this->yearID,
            $this->conferenceID,
            $this->uiLanguageID,
            $this->markdown,
            $this->updatedByUserID,
            date('Y-m-d H:i:s'),
        ]);
        if ($this->homeContentID < 1) {
            $saved = self::loadExact($this->yearID, $this->conferenceID, $this->uiLanguageID, $db);
            $this->homeContentID = $saved?->homeContentID ?? -1;
            $this->dateUpdated = $saved?->dateUpdated ?? '';
        }
    }

    /** @param array<string, scalar|null> $placeholders */
    public function render(array $placeholders = []): string
    {
        $markdown = $this->markdown;
        foreach ($placeholders as $name => $value) {
            $markdown = str_replace(':' . $name, (string)($value ?? ''), $markdown);
        }
        return SafeMarkdown::render($markdown);
    }

    public static function defaultEnglishMarkdown(): string
    {
        $path = dirname(__DIR__) . '/Content/default-home.en.md';
        $content = file_get_contents($path);
        return $content === false ? '' : $content;
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): HomeContent
    {
        $content = new HomeContent((int)$row['HomeContentID']);
        $content->yearID = (int)$row['YearID'];
        $content->conferenceID = (int)$row['ConferenceID'];
        $content->uiLanguageID = (int)$row['UILanguageID'];
        $content->markdown = (string)$row['Markdown'];
        $content->updatedByUserID = $row['UpdatedByUserID'] === null ? null : (int)$row['UpdatedByUserID'];
        $content->dateUpdated = (string)$row['DateUpdated'];
        return $content;
    }
}
