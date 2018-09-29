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
                'SettingValue' => 'PBE Quiz Engine',
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
                'CreatedByID' => NULL
            ],
            [
                'Username' => 'Web Admin #1',
                'EntryCode' => 'pbedb7',
                'Password' => '',
                'LastLoginDate' => date('Y-m-d H:i:s'),
                'UserTypeID' => 5,
                'ClubID' => 1,
                'CreatedByID' => NULL
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

        $homeSectionsData = [
            [
                'Name' => '2018 Dates',
                'SortOrder' => 0,
                'YearID' => 1,
                'ConferenceID' => 1
            ],
            [
                'Name' => 'Resources',
                'SortOrder' => 1,
                'YearID' => 1,
                'ConferenceID' => 1
            ],
            [
                'Name' => 'Books',
                'SortOrder' => 2,
                'YearID' => 1,
                'ConferenceID' => 1
            ]
        ];
        $homeSections = $this->table('HomeInfoSections');
        $homeSections->insert($homeSectionsData)->save();

        $homeInfoLinesData = [
            [
                'Name' => '',
                'SortOrder' => 0,
                'HomeInfoSectionID' => 1
            ],
            [
                'Name' => '',
                'SortOrder' => 1,
                'HomeInfoSectionID' => 1
            ],
            [
                'Name' => '',
                'SortOrder' => 2,
                'HomeInfoSectionID' => 1
            ],
            [
                'Name' => '',
                'SortOrder' => 3,
                'HomeInfoSectionID' => 1
            ],
            [
                'Name' => '',
                'SortOrder' => 0,
                'HomeInfoSectionID' => 2
            ],
            [
                'Name' => '',
                'SortOrder' => 0,
                'HomeInfoSectionID' => 2
            ],
            [
                'Name' => '',
                'SortOrder' => 1,
                'HomeInfoSectionID' => 3
            ],
        ];
        $homeInfoLines = $this->table('HomeInfoLines');
        $homeInfoLines->insert($homeInfoLinesData)->save();

        $homeInfoItemsData = [
            [
                'Text' => 'District',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 0,
                'HomeInfoLineID' => 1
            ],
            [
                'Text' => 'January 13, 2018',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 1,
                'HomeInfoLineID' => 1
            ],
            [
                'Text' => 'See your area coordinator for location',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 2,
                'HomeInfoLineID' => 1
            ],
            [
                'Text' => 'UCC',
                'IsLink' => 1,
                'URL' => 'uccsda.org',
                'SortOrder' => 0,
                'HomeInfoLineID' => 2
            ],
            [
                'Text' => 'February 24, 2018',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 1,
                'HomeInfoLineID' => 2
            ],
            [
                'Text' => 'Location TBA (East Cascade District)',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 2,
                'HomeInfoLineID' => 2
            ],
            [
                'Text' => 'Union',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 0,
                'HomeInfoLineID' => 3
            ],
            [
                'Text' => 'March 10, 2018',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 1,
                'HomeInfoLineID' => 3
            ],
            [
                'Text' => 'Location TBA (Idaho Conference - Boise Area)',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 2,
                'HomeInfoLineID' => 3
            ],
            [
                'Text' => 'Division',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 0,
                'HomeInfoLineID' => 4
            ],
            [
                'Text' => 'April 20-21, 2018',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 1,
                'HomeInfoLineID' => 4
            ],
            [
                'Text' => 'Location: Orlando, Florida',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 2,
                'HomeInfoLineID' => 4
            ],
            [
                'Text' => 'Official PBE Website',
                'IsLink' => 1,
                'URL' => 'http://www.pathfindersonline.org/pathfinder-bible-experience',
                'SortOrder' => 0,
                'HomeInfoLineID' => 5
            ],
            [
                'Text' => 'PBE Manual',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 0,
                'HomeInfoLineID' => 6
            ],
            [
                'Text' => 'Purchase Link',
                'IsLink' => 1,
                'URL' => 'http://www.adventsource.org/as30/store-productDetails.aspx?ID=38282',
                'SortOrder' => 1,
                'HomeInfoLineID' => 6
            ],
            [
                'Text' => 'PDF Download',
                'IsLink' => 1,
                'URL' => 'http://www.pathfindersonline.org/pdf/PBE/PBE_ApplicationGuideExodus_2016.pdf',
                'SortOrder' => 2,
                'HomeInfoLineID' => 6
            ],
            [
                'Text' => 'Pathfinder Bible NKJV',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 0,
                'HomeInfoLineID' => 7
            ],
            [
                'Text' => 'Hardback',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 1,
                'HomeInfoLineID' => 7
            ],
            [
                'Text' => 'Software',
                'IsLink' => 0,
                'URL' => '',
                'SortOrder' => 2,
                'HomeInfoLineID' => 7
            ]
        ];
        $homeInfoItems = $this->table('HomeInfoItems');
        $homeInfoItems->insert($homeInfoItemsData)->save();

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
