<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddContactFormFields extends AbstractMigration
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
        $table = $this->table('ContactFormSubmissions');
        $table->addColumn('Club', 'string', ['limit' => 250]);
        $table->addColumn('Conference', 'string', ['limit' => 300]);
        $table->addColumn('Type', 'string', ['limit' => 25]);
        $table->update();
    }
}
