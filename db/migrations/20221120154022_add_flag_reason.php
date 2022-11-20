<?php
declare(strict_types=1);

use App\Models\FlagReason;
use App\Models\UserFlagged;
use Phinx\Migration\AbstractMigration;

final class AddFlagReason extends AbstractMigration
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
    public function change(): void
    {
        $table = $this->table('UserFlagged');
        $table->addColumn('Reason', 'string', ['limit' => 50, 'default' => FlagReason::UNKNOWN]);
        $table->update();
    }
}
