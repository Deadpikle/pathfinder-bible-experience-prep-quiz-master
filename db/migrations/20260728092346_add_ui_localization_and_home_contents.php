<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUiLocalizationAndHomeContents extends AbstractMigration
{
    public function up(): void
    {
        $this->table('Languages')
            ->addColumn('IsUIEnabled', 'boolean', ['default' => false, 'null' => false, 'after' => 'IsDefault'])
            ->update();

        $englishLanguage = $this->fetchRow(
            "SELECT LanguageID FROM Languages WHERE Abbreviation = 'en' ORDER BY IsDefault DESC, LanguageID LIMIT 1"
        );
        if ($englishLanguage === false) {
            $englishLanguage = $this->fetchRow(
                'SELECT LanguageID FROM Languages ORDER BY IsDefault DESC, LanguageID LIMIT 1'
            );
        }
        if ($englishLanguage === false) {
            throw new RuntimeException('At least one language must exist before UI localization can be enabled.');
        }
        $englishLanguageID = (int)$englishLanguage['LanguageID'];

        $this->execute('UPDATE Languages SET IsUIEnabled = 0');
        $this->execute('UPDATE Languages SET IsUIEnabled = 1 WHERE LanguageID = ' . $englishLanguageID);

        $this->table('Users')
            ->addColumn('UILanguageID', 'integer', [
                'default' => $englishLanguageID,
                'null' => false,
                'after' => 'PreferredLanguageID',
            ])
            ->addForeignKey('UILanguageID', 'Languages', 'LanguageID', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->update();

        $this->table('HomeContents', [
            'id' => 'HomeContentID',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('YearID', 'integer', ['null' => false])
            ->addColumn('ConferenceID', 'integer', ['null' => false])
            ->addColumn('UILanguageID', 'integer', ['null' => false])
            ->addColumn('Markdown', 'text', ['null' => false])
            ->addColumn('UpdatedByUserID', 'integer', ['null' => true])
            ->addColumn('DateUpdated', 'datetime', ['null' => false])
            ->addIndex(['YearID', 'ConferenceID', 'UILanguageID'], [
                'unique' => true,
                'name' => 'UX_HomeContents_Scope',
            ])
            ->addForeignKey('YearID', 'Years', 'YearID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('ConferenceID', 'Conferences', 'ConferenceID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('UILanguageID', 'Languages', 'LanguageID', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            ->addForeignKey('UpdatedByUserID', 'Users', 'UserID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $year = $this->fetchRow('SELECT YearID FROM Years WHERE IsCurrent = 1 ORDER BY YearID DESC LIMIT 1');
        $conference = $this->fetchRow("SELECT ConferenceID FROM Conferences WHERE Name = 'Website Administrators' LIMIT 1");
        if ($year !== false) {
            $yearID = (int)$year['YearID'];
            $rows = [];
            foreach ($this->legacyHomeMarkdownByConference($yearID) as $conferenceID => $markdown) {
                $rows[] = [
                    'YearID' => $yearID,
                    'ConferenceID' => $conferenceID,
                    'UILanguageID' => $englishLanguageID,
                    'Markdown' => $markdown,
                    'UpdatedByUserID' => null,
                    'DateUpdated' => date('Y-m-d H:i:s'),
                ];
            }

            $adminConferenceID = $conference === false ? 0 : (int)$conference['ConferenceID'];
            if ($adminConferenceID > 0 && !array_key_exists($adminConferenceID, $this->indexRowsByConference($rows))) {
                $markdown = file_get_contents(dirname(__DIR__, 2) . '/app/Content/default-home.en.md');
                if ($markdown !== false) {
                    $rows[] = [
                        'YearID' => $yearID,
                        'ConferenceID' => $adminConferenceID,
                        'UILanguageID' => $englishLanguageID,
                        'Markdown' => $markdown,
                        'UpdatedByUserID' => null,
                        'DateUpdated' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            if ($rows !== []) {
                $this->table('HomeContents')->insert($rows)->save();
            }
        }
    }

    public function down(): void
    {
        $this->table('HomeContents')->drop()->save();
        $this->table('Users')->dropForeignKey('UILanguageID')->save();
        $this->table('Users')->removeColumn('UILanguageID')->update();
        $this->table('Languages')->removeColumn('IsUIEnabled')->update();
    }

    /** @return array<int,string> */
    private function legacyHomeMarkdownByConference(int $yearID): array
    {
        $conferenceRows = $this->fetchAll(
            'SELECT DISTINCT ConferenceID FROM HomeInfoSections WHERE YearID = ' . $yearID
        );
        $contents = [];
        foreach ($conferenceRows as $conferenceRow) {
            $conferenceID = (int)$conferenceRow['ConferenceID'];
            $sections = $this->fetchAll(
                'SELECT HomeInfoSectionID, Name, Subtitle
                 FROM HomeInfoSections
                 WHERE YearID = ' . $yearID . ' AND ConferenceID = ' . $conferenceID . '
                 ORDER BY SortOrder, HomeInfoSectionID'
            );
            $blocks = [];
            foreach ($sections as $section) {
                $block = '## ' . $this->escapeMarkdownText((string)$section['Name']);
                $subtitle = trim((string)($section['Subtitle'] ?? ''));
                if ($subtitle !== '') {
                    $block .= "\n\n*" . $this->escapeMarkdownText($subtitle) . '*';
                }
                $lines = $this->fetchAll(
                    'SELECT HomeInfoLineID, Name
                     FROM HomeInfoLines
                     WHERE HomeInfoSectionID = ' . (int)$section['HomeInfoSectionID'] . '
                     ORDER BY SortOrder, HomeInfoLineID'
                );
                foreach ($lines as $line) {
                    $items = $this->fetchAll(
                        'SELECT Text, IsLink, URL
                         FROM HomeInfoItems
                         WHERE HomeInfoLineID = ' . (int)$line['HomeInfoLineID'] . '
                         ORDER BY SortOrder, HomeInfoItemID'
                    );
                    $itemText = [];
                    foreach ($items as $item) {
                        $text = $this->escapeMarkdownText((string)$item['Text']);
                        $url = trim((string)$item['URL']);
                        if ((bool)$item['IsLink'] && $url !== '' && $this->isSafeLegacyURL($url)) {
                            $text = '[' . $text . '](' . str_replace(['(', ')'], ['%28', '%29'], $url) . ')';
                        }
                        $itemText[] = $text;
                    }
                    $lineName = $this->escapeMarkdownText((string)$line['Name']);
                    $value = implode(' / ', array_filter($itemText, static fn(string $item): bool => $item !== ''));
                    if ($lineName !== '') {
                        $value = '**' . $lineName . ($value !== '' ? ':** ' : '**') . $value;
                    }
                    if ($value !== '') {
                        $block .= "\n\n- " . $value;
                    }
                }
                $blocks[] = $block;
            }
            $markdown = trim(implode("\n\n", $blocks));
            if ($markdown !== '') {
                $contents[$conferenceID] = $markdown;
            }
        }
        return $contents;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,true> */
    private function indexRowsByConference(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $index[(int)$row['ConferenceID']] = true;
        }
        return $index;
    }

    private function escapeMarkdownText(string $text): string
    {
        return trim(str_replace(
            ['\\', '*', '_', '[', ']', '`', '#'],
            ['\\\\', '\\*', '\\_', '\\[', '\\]', '\\`', '\\#'],
            $text
        ));
    }

    private function isSafeLegacyURL(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}
