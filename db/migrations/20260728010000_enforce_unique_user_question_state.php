<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnforceUniqueUserQuestionState extends AbstractMigration
{
    public function up(): void
    {
        // Keep the newest answer for each user/question pair. The ID breaks ties for
        // data imported with identical timestamps.
        $this->execute('
            DELETE older
            FROM UserAnswers older
            INNER JOIN UserAnswers newer
                ON newer.UserID = older.UserID
                AND newer.QuestionID = older.QuestionID
                AND (
                    newer.DateAnswered > older.DateAnswered
                    OR (
                        newer.DateAnswered = older.DateAnswered
                        AND newer.UserAnswerID > older.UserAnswerID
                    )
                )
        ');

        // Flags are state rather than history. Preserve the most recently-created
        // row so its reason and timestamp survive the cleanup.
        $this->execute('
            DELETE older
            FROM UserFlagged older
            INNER JOIN UserFlagged newer
                ON newer.UserID = older.UserID
                AND newer.QuestionID = older.QuestionID
                AND (
                    newer.DateTimeFlagged > older.DateTimeFlagged
                    OR (
                        newer.DateTimeFlagged = older.DateTimeFlagged
                        AND newer.UserFlaggedID > older.UserFlaggedID
                    )
                )
        ');

        $this->table('UserAnswers')
            ->addIndex(
                ['UserID', 'QuestionID'],
                ['unique' => true, 'name' => 'UX_UserAnswers_User_Question']
            )
            ->update();

        $this->table('UserFlagged')
            ->addIndex(
                ['UserID', 'QuestionID'],
                ['unique' => true, 'name' => 'UX_UserFlagged_User_Question']
            )
            ->update();
    }

    public function down(): void
    {
        // MariaDB may reuse the composite unique as the supporting index for
        // the UserID foreign key and remove its original implicit index. Give
        // that foreign key an explicit replacement before dropping the unique.
        $flagged = $this->table('UserFlagged');
        if (!$flagged->hasIndexByName('IX_UserFlagged_UserID')) {
            $flagged->addIndex(['UserID'], ['name' => 'IX_UserFlagged_UserID'])->update();
        }
        $answers = $this->table('UserAnswers');
        if (!$answers->hasIndexByName('IX_UserAnswers_UserID')) {
            $answers->addIndex(['UserID'], ['name' => 'IX_UserAnswers_UserID'])->update();
        }

        $this->table('UserFlagged')
            ->removeIndexByName('UX_UserFlagged_User_Question')
            ->update();

        $this->table('UserAnswers')
            ->removeIndexByName('UX_UserAnswers_User_Question')
            ->update();
    }
}
