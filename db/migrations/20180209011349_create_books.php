<?php


use Phinx\Migration\AbstractMigration;

class CreateBooks extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('Books',  ['id' => 'BookID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Name', 'string', ['limit' => 100]);
        $table->addColumn('NumberChapters', 'integer', ['default' => 0]);
        $table->addColumn('YearID', 'integer');
        $table->addForeignKey('YearID', 'Years', 'YearID', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION']);
        $table->create();
    }
}
