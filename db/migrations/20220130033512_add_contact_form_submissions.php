<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddContactFormSubmissions extends AbstractMigration
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
        $table = $this->table('ContactFormSubmissions', ['id' => 'ContactFormSubmissionID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Title', 'string', ['limit' => 500]);
        $table->addColumn('PersonName', 'string', ['limit' => 250]);
        $table->addColumn('Email', 'string', ['limit' => 500]);
        $table->addColumn('Message', 'string', ['limit' => 10000]);
        $table->addColumn('DateTimeSubmitted', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->create();
    }
}
