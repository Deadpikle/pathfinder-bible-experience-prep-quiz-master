<?php

use Phinx\Migration\AbstractMigration;

final class AddQuizAttemptsAndMastery extends AbstractMigration
{
    public function change(): void
    {
        $attempts = $this->table('QuizAttempts', [
            'id' => 'QuizAttemptID',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $attempts
            ->addColumn('UserID', 'integer')
            ->addColumn('YearID', 'integer')
            ->addColumn('LanguageID', 'integer', ['null' => true])
            ->addColumn('StartedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('CompletedAt', 'datetime', ['null' => true])
            ->addColumn('IsCompleted', 'boolean', ['default' => false])
            ->addColumn('PointsEarned', 'integer', ['default' => 0])
            ->addColumn('PointsPossible', 'integer', ['default' => 0])
            ->addColumn('CompletionHash', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('DateModified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['UserID', 'IsCompleted', 'StartedAt'], ['name' => 'idx_attempt_user_status_started'])
            ->addIndex(['YearID', 'LanguageID', 'IsCompleted'], ['name' => 'idx_attempt_year_language_status'])
            ->addForeignKey('UserID', 'Users', 'UserID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('YearID', 'Years', 'YearID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('LanguageID', 'Languages', 'LanguageID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $items = $this->table('QuizAttemptItems', [
            'id' => 'QuizAttemptItemID',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $items
            ->addColumn('QuizAttemptID', 'integer')
            ->addColumn('QuestionID', 'integer', ['null' => true])
            ->addColumn('SortOrder', 'integer')
            ->addColumn('QuestionType', 'string', ['limit' => 50])
            ->addColumn('QuestionSnapshot', 'text')
            ->addColumn('AnswerSnapshot', 'text')
            ->addColumn('ReferenceSnapshot', 'string', ['limit' => 500, 'default' => ''])
            ->addColumn('PointsPossible', 'integer', ['default' => 0])
            ->addColumn('UserAnswer', 'text', ['null' => true])
            ->addColumn('WasCorrect', 'boolean', ['null' => true])
            ->addColumn('PointsEarned', 'integer', ['null' => true])
            ->addColumn('AnsweredAt', 'datetime', ['null' => true])
            ->addIndex(['QuizAttemptID', 'SortOrder'], ['unique' => true, 'name' => 'uq_attempt_item_order'])
            ->addIndex(['QuizAttemptID', 'QuestionID'], ['name' => 'idx_attempt_item_question'])
            ->addForeignKey('QuizAttemptID', 'QuizAttempts', 'QuizAttemptID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('QuestionID', 'Questions', 'QuestionID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $mastery = $this->table('UserQuestionMastery', [
            'id' => 'UserQuestionMasteryID',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $mastery
            ->addColumn('UserID', 'integer')
            ->addColumn('QuestionID', 'integer')
            ->addColumn('LastQuizAttemptItemID', 'integer', ['null' => true])
            ->addColumn('TimesAttempted', 'integer', ['default' => 0])
            ->addColumn('TimesCorrect', 'integer', ['default' => 0])
            ->addColumn('BestPointsEarned', 'integer', ['default' => 0])
            ->addColumn('LastPointsEarned', 'integer', ['default' => 0])
            ->addColumn('LastPointsPossible', 'integer', ['default' => 0])
            ->addColumn('IsMastered', 'boolean', ['default' => false])
            ->addColumn('LastAnsweredAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['UserID', 'QuestionID'], ['unique' => true, 'name' => 'uq_mastery_user_question'])
            ->addIndex(['UserID', 'IsMastered'], ['name' => 'idx_mastery_user_status'])
            ->addForeignKey('UserID', 'Users', 'UserID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('QuestionID', 'Questions', 'QuestionID', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('LastQuizAttemptItemID', 'QuizAttemptItems', 'QuizAttemptItemID', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }
}
