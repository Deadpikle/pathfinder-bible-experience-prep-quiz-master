<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScopedQuestionBanks extends AbstractMigration
{
    private const GLOBAL_BANK_ID = 1;

    public function up(): void
    {
        $this->table(
            'QuestionBanks',
            ['id' => 'QuestionBankID', 'collation' => 'utf8mb4_unicode_ci']
        )
            ->addColumn('Name', 'string', ['limit' => 200])
            ->addColumn('IsGlobal', 'boolean', ['default' => false])
            ->addColumn('IsDeleted', 'boolean', ['default' => false])
            ->addColumn('DateCreated', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('DateModified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['IsGlobal', 'IsDeleted'], ['name' => 'IX_QuestionBanks_Scope'])
            ->create();

        $this->table(
            'ConferenceQuestionBanks',
            ['id' => 'ConferenceQuestionBankID', 'collation' => 'utf8mb4_unicode_ci']
        )
            ->addColumn('ConferenceID', 'integer')
            ->addColumn('QuestionBankID', 'integer')
            ->addColumn('IsOverlay', 'boolean', ['default' => true])
            ->addForeignKey(
                'ConferenceID',
                'Conferences',
                'ConferenceID',
                ['delete' => 'CASCADE', 'update' => 'NO_ACTION']
            )
            ->addForeignKey(
                'QuestionBankID',
                'QuestionBanks',
                'QuestionBankID',
                ['delete' => 'CASCADE', 'update' => 'NO_ACTION']
            )
            ->addIndex(
                ['ConferenceID', 'QuestionBankID'],
                ['unique' => true, 'name' => 'UX_ConferenceQuestionBanks_Bank']
            )
            ->addIndex(
                ['ConferenceID', 'IsOverlay'],
                ['unique' => true, 'name' => 'UX_ConferenceQuestionBanks_Slot']
            )
            ->create();

        $this->execute(sprintf(
            "INSERT INTO QuestionBanks (QuestionBankID, Name, IsGlobal, IsDeleted) VALUES (%d, 'Global', 1, 0)",
            self::GLOBAL_BANK_ID
        ));

        $pdo = $this->getAdapter()->getConnection();
        $conferences = $pdo->query('SELECT ConferenceID, Name FROM Conferences ORDER BY ConferenceID')->fetchAll();
        $insertBank = $pdo->prepare('
            INSERT INTO QuestionBanks (Name, IsGlobal, IsDeleted)
            VALUES (?, 0, 0)
        ');
        $mapBank = $pdo->prepare('
            INSERT INTO ConferenceQuestionBanks (ConferenceID, QuestionBankID, IsOverlay)
            VALUES (?, ?, ?)
        ');

        foreach ($conferences as $conference) {
            $conferenceID = (int)$conference['ConferenceID'];
            $mapBank->execute([$conferenceID, self::GLOBAL_BANK_ID, 0]);

            $insertBank->execute([trim((string)$conference['Name']) . ' Private']);
            $overlayBankID = (int)$pdo->lastInsertId();
            $mapBank->execute([$conferenceID, $overlayBankID, 1]);
        }

        // ID 1 is deliberately stable: legacy insert paths can continue creating
        // global questions until their bank picker is wired into the UI.
        $this->table('Questions')
            ->addColumn('QuestionBankID', 'integer', [
                'after' => 'QuestionID',
                'default' => self::GLOBAL_BANK_ID,
                'null' => false,
            ])
            ->addForeignKey(
                'QuestionBankID',
                'QuestionBanks',
                'QuestionBankID',
                ['delete' => 'RESTRICT', 'update' => 'NO_ACTION']
            )
            ->addIndex(['QuestionBankID', 'IsDeleted'], ['name' => 'IX_Questions_Bank_Deleted'])
            ->update();
    }

    public function down(): void
    {
        $this->table('Questions')
            ->removeIndexByName('IX_Questions_Bank_Deleted')
            ->dropForeignKey('QuestionBankID')
            ->removeColumn('QuestionBankID')
            ->update();

        $this->table('ConferenceQuestionBanks')->drop()->save();
        $this->table('QuestionBanks')->drop()->save();
    }
}
