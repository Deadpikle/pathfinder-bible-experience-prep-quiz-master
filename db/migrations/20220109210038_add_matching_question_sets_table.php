<?php

use Phinx\Migration\AbstractMigration;

class AddMatchingQuestionSetsTable extends AbstractMigration
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
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('MatchingQuestionSets', ['id' => 'MatchingQuestionSetID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Name', 'string', ['limit' => 100]);
        $table->addColumn('Description', 'string', ['limit' => 500]);
        $table->addColumn('LanguageID', 'integer', ['null' => true, 'default' => 1]);
        $table->addColumn('YearID', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('IsDeleted', 'boolean', ['default' => false]);
        $table->addForeignKey('LanguageID', 'Languages', 'LanguageID', ['delete'=> 'SET_NULL', 'update' => 'NO_ACTION']);
        $table->addForeignKey('YearID', 'Years', 'YearID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->create();
    }
}
