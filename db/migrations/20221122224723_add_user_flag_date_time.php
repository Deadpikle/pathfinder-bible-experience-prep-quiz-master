<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserFlagDateTime extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(): void
    {
        // https://stackoverflow.com/questions/41302757/mysql-default-value-for-new-records-only
        $table = $this->table('UserFlagged');
        $table->addColumn('DateTimeFlagged', 'datetime', ['default' => '2000-12-07 10:02:00']);
        $table->update();

        $table->changeColumn('DateTimeFlagged', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->update();
    }

    public function down(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        // https://stackoverflow.com/questions/41302757/mysql-default-value-for-new-records-only
        $table = $this->table('UserFlagged');
        $table->removeColumn('DateTimeFlagged');
        $table->update();
    }
}
