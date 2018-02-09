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
                'SettingValue' => '//babien.co/analytics/',
                'DisplayName' => 'Analytics URL'
            ],
            [
                'SettingKey' => 'AnalyticsSiteID',
                'SettingValue' => '1',
                'DisplayName' => 'Analytics Site ID'
            ],
        ];
        $settings = $this->table('Settings');
        $settings->insert($settingsData)->save();

        $conferencesData = [
            [
                'Name' => 'UCC',
                'URL' => 'https://uccsda.org/English/Pages/HomePage.aspx',
                'ContactName' => '-',
                'ContactEmail' => '-'
            ],
        ];
        $conferences = $this->table('Conferences');
        $conferences->insert($conferencesData)->save();

        // TODO: clubs
        // TODO: users
        // TODO: Home info sections/lines
        // TODO: years
    }
}
