<?php


use Phinx\Seed\AbstractSeed;

class DefaultSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $userTypeData = [
            [
                'Type' => 'Guest',
                'DisplayName' => 'Guest'
            ],
            [
                'Type' => 'Pathfinder',
                'DisplayName' => 'Pathfinder'
            ],
            [
                'Type' => 'ClubAdmin',
                'DisplayName' => 'Club Administrator'
            ],
            [
                'Type' => 'ConferenceAdmin',
                'DisplayName' => 'Conference Administrator'
            ],
            [
                'Type' => 'WebAdmin',
                'DisplayName' => 'Website Administrator'
            ]
        ];
        $userTypes = $this->table('UserTypes');
        $userTypes->insert($userTypeData)->save();

        $settingsData = [
            [
                'SettingKey' => 'AboutContactName',
                'SettingValue' => '-',
                'DisplayName' => 'About Page - Contact Name'
            ],
            [
                'SettingKey' => 'AboutContactEmail',
                'SettingValue' => '-',
                'DisplayName' => 'About Page - Contact Email'
            ],
            [
                'SettingKey' => 'WebsiteName',
                'SettingValue' => 'PBE Prep and Quiz Master',
                'DisplayName' => 'Website Name'
            ],
            [
                'SettingKey' => 'WebsiteTabTitle',
                'SettingValue' => 'PBE',
                'DisplayName' => 'Website Title (Browser Tab)'
            ],
            [
                'SettingKey' => 'AnalyticsURL',
                'SettingValue' => '',
                'DisplayName' => 'Analytics URL'
            ],
            [
                'SettingKey' => 'AnalyticsSiteID',
                'SettingValue' => '1',
                'DisplayName' => 'Analytics Site ID'
            ],
            [
                'SettingKey' => 'FooterText',
                'SettingValue' => 'Scripture taken from the New King James Version®. Copyright © 1982 by Thomas Nelson. Used by permission. All rights reserved.',
                'DisplayName' => 'Footer Text'
            ]
        ];
        $settings = $this->table('Settings');
        $settings->insert($settingsData)->save();

        $conferencesData = [
            [
                'Name' => 'Website Administrators',
                'URL' => 'https://example.com',
                'ContactName' => '-',
                'ContactEmail' => '-'
            ]
        ];
        $conferences = $this->table('Conferences');
        $conferences->insert($conferencesData)->save();

        // Migrations run before this seed on a fresh environment, so the
        // website-admin conference needs the same fixed global + overlay bank
        // mappings that existing conferences receive during the migration.
        $questionBanks = $this->table('QuestionBanks');
        $questionBanks->insert([
            [
                'Name' => 'Website Administrators Private',
                'IsGlobal' => false,
                'IsDeleted' => false,
            ],
        ])->save();

        $conferenceQuestionBanks = $this->table('ConferenceQuestionBanks');
        $conferenceQuestionBanks->insert([
            [
                'ConferenceID' => 1,
                'QuestionBankID' => 1,
                'IsOverlay' => false,
            ],
            [
                'ConferenceID' => 1,
                'QuestionBankID' => 2,
                'IsOverlay' => true,
            ],
        ])->save();

        $clubsData = [
            [
                'Name' => 'Website Administrators',
                'URL' => 'https://example.com',
                'ConferenceID' => 1
            ]
        ];
        $clubs = $this->table('Clubs');
        $clubs->insert($clubsData)->save();

        $usersData = [
            [
                'Username' => 'Guest User',
                'EntryCode' => '4guest',
                'Password' => '',
                'LastLoginDate' => date('Y-m-d H:i:s'),
                'UserTypeID' => 1,
                'ClubID' => 1,
                'CreatedByID' => null
            ],
            [
                'Username' => 'Web Admin #1',
                'EntryCode' => 'pbedb7',
                'Password' => '',
                'LastLoginDate' => date('Y-m-d H:i:s'),
                'UserTypeID' => 5,
                'ClubID' => 1,
                'CreatedByID' => null
            ]
        ];
        $users = $this->table('Users');
        $users->insert($usersData)->save();

        $yearsData = [
            [
                'Year' => 2018,
                'IsCurrent' => true
            ],
            [
                'Year' => 2019,
                'IsCurrent' => false
            ],
            [
                'Year' => 2020,
                'IsCurrent' => false
            ],
            [
                'Year' => 2021,
                'IsCurrent' => false
            ],
            [
                'Year' => 2022,
                'IsCurrent' => false
            ],
            [
                'Year' => 2023,
                'IsCurrent' => false
            ],
            [
                'Year' => 2024,
                'IsCurrent' => false
            ],
            [
                'Year' => 2025,
                'IsCurrent' => false
            ]
        ];
        $years = $this->table('Years');
        $years->insert($yearsData)->save();

        $englishLanguageID = (int)$this->getAdapter()->getConnection()
            ->query("SELECT LanguageID FROM Languages WHERE Abbreviation = 'en' ORDER BY LanguageID LIMIT 1")
            ->fetchColumn();
        $defaultHomeMarkdown = file_get_contents(dirname(__DIR__, 2) . '/app/Content/default-home.en.md');
        if ($englishLanguageID > 0 && $defaultHomeMarkdown !== false) {
            $this->table('HomeContents')->insert([[
                'YearID' => 1,
                'ConferenceID' => 1,
                'UILanguageID' => $englishLanguageID,
                'Markdown' => $defaultHomeMarkdown,
                'UpdatedByUserID' => null,
                'DateUpdated' => date('Y-m-d H:i:s'),
            ]])->save();
        }

        $blankableWordsData = [
            [
                'Word' => 'and'
            ],
            [
                'Word' => 'is'
            ],
            [
                'Word' => 'not'
            ],
            [
                'Word' => 'the'
            ],
            [
                'Word' => 'a'
            ],
            [
                'Word' => 'or'
            ],
            [
                'Word' => 'but'
            ],
            [
                'Word' => '...'
            ],
            [
                'Word' => 'of'
            ],
            [
                'Word' => 'to'
            ],
        ];
        $blankableWords = $this->table('BlankableWords');
        $blankableWords->insert($blankableWordsData)->save();
    }
}
