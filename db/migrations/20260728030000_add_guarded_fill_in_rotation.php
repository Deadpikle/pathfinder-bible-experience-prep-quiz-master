<?php

use Phinx\Migration\AbstractMigration;

final class AddGuardedFillInRotation extends AbstractMigration
{
    public function up(): void
    {
        $this->table('Settings')
            ->changeColumn('SettingValue', 'text')
            ->update();

        $this->table('Questions')
            ->addColumn('IsActive', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'IsDeleted',
            ])
            ->addIndex(['Type', 'IsActive', 'IsDeleted'], ['name' => 'idx_questions_type_active'])
            ->update();

        $this->table('FillInRotationSets', [
            'id' => 'FillInRotationSetID',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('YearID', 'integer')
            ->addColumn('LanguageID', 'integer')
            ->addColumn('Name', 'string', ['limit' => 200])
            ->addColumn('SortOrder', 'integer')
            ->addColumn('IsEnabled', 'boolean', ['default' => true])
            ->addColumn('DateCreated', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('DateModified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['YearID', 'LanguageID', 'SortOrder'], ['unique' => true, 'name' => 'uq_fill_rotation_order'])
            ->addForeignKey('YearID', 'Years', 'YearID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('LanguageID', 'Languages', 'LanguageID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('FillInRotationSetQuestions', [
            'id' => 'FillInRotationSetQuestionID',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('FillInRotationSetID', 'integer')
            ->addColumn('QuestionID', 'integer')
            ->addColumn('SortOrder', 'integer')
            ->addIndex(['FillInRotationSetID', 'QuestionID'], ['unique' => true, 'name' => 'uq_fill_rotation_question'])
            ->addIndex(['FillInRotationSetID', 'SortOrder'], ['unique' => true, 'name' => 'uq_fill_rotation_question_order'])
            ->addForeignKey('FillInRotationSetID', 'FillInRotationSets', 'FillInRotationSetID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('QuestionID', 'Questions', 'QuestionID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('FillInRotationState', [
            'id' => 'FillInRotationStateID',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('YearID', 'integer')
            ->addColumn('LanguageID', 'integer')
            ->addColumn('CurrentFillInRotationSetID', 'integer', ['null' => true])
            ->addColumn('LastRotatedAt', 'datetime', ['null' => true])
            ->addColumn('RotationVersion', 'integer', ['default' => 0])
            ->addIndex(['YearID', 'LanguageID'], ['unique' => true, 'name' => 'uq_fill_rotation_state'])
            ->addForeignKey('YearID', 'Years', 'YearID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('LanguageID', 'Languages', 'LanguageID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('CurrentFillInRotationSetID', 'FillInRotationSets', 'FillInRotationSetID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('FillInRotationAudit', [
            'id' => 'FillInRotationAuditID',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('YearID', 'integer')
            ->addColumn('LanguageID', 'integer')
            ->addColumn('PreviousFillInRotationSetID', 'integer', ['null' => true])
            ->addColumn('NextFillInRotationSetID', 'integer', ['null' => true])
            ->addColumn('WasDryRun', 'boolean', ['default' => false])
            ->addColumn('DidSucceed', 'boolean', ['default' => false])
            ->addColumn('DistinctVerseCount', 'integer', ['default' => 0])
            ->addColumn('Message', 'text')
            ->addColumn('DateCreated', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['YearID', 'LanguageID', 'DateCreated'], ['name' => 'idx_fill_rotation_audit'])
            ->addForeignKey('YearID', 'Years', 'YearID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('LanguageID', 'Languages', 'LanguageID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('PreviousFillInRotationSetID', 'FillInRotationSets', 'FillInRotationSetID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addForeignKey('NextFillInRotationSetID', 'FillInRotationSets', 'FillInRotationSetID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        // Every existing active global fill-in collection becomes the first curated set.
        $this->execute("
            INSERT INTO FillInRotationSets (YearID, LanguageID, Name, SortOrder, IsEnabled)
            SELECT b.YearID, q.LanguageID, 'Migrated current fill-ins', 1, 1
            FROM Questions q
            INNER JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1
            INNER JOIN Verses v ON v.VerseID = q.StartVerseID
            INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
            INNER JOIN Books b ON b.BookID = c.BookID
            WHERE q.Type = 'bible-qna-fill' AND q.IsDeleted = 0 AND q.IsActive = 1
            GROUP BY b.YearID, q.LanguageID
        ");
        $this->execute("
            INSERT INTO FillInRotationSetQuestions (FillInRotationSetID, QuestionID, SortOrder)
            SELECT rotationSet.FillInRotationSetID, q.QuestionID,
                   ROW_NUMBER() OVER (
                       PARTITION BY rotationSet.FillInRotationSetID
                       ORDER BY b.BibleOrder, c.Number, v.Number, q.QuestionID
                   )
            FROM FillInRotationSets rotationSet
            INNER JOIN Questions q ON q.LanguageID = rotationSet.LanguageID
            INNER JOIN QuestionBanks qb ON qb.QuestionBankID = q.QuestionBankID AND qb.IsGlobal = 1
            INNER JOIN Verses v ON v.VerseID = q.StartVerseID
            INNER JOIN Chapters c ON c.ChapterID = v.ChapterID
            INNER JOIN Books b ON b.BookID = c.BookID AND b.YearID = rotationSet.YearID
            WHERE rotationSet.Name = 'Migrated current fill-ins'
              AND q.Type = 'bible-qna-fill' AND q.IsDeleted = 0 AND q.IsActive = 1
        ");
        $this->execute("
            INSERT INTO FillInRotationState
                (YearID, LanguageID, CurrentFillInRotationSetID, RotationVersion)
            SELECT YearID, LanguageID, FillInRotationSetID, 1
            FROM FillInRotationSets
            WHERE Name = 'Migrated current fill-ins'
        ");
    }

    public function down(): void
    {
        $this->table('FillInRotationAudit')->drop()->save();
        $this->table('FillInRotationState')->drop()->save();
        $this->table('FillInRotationSetQuestions')->drop()->save();
        $this->table('FillInRotationSets')->drop()->save();
        $this->table('Questions')
            ->removeIndexByName('idx_questions_type_active')
            ->removeColumn('IsActive')
            ->update();
        $this->table('Settings')
            ->changeColumn('SettingValue', 'string', ['limit' => 150])
            ->update();
    }
}
