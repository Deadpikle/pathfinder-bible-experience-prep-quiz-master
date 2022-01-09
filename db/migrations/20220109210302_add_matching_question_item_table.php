<?php

use Phinx\Migration\AbstractMigration;

class AddMatchingQuestionItemTable extends AbstractMigration
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
        $table = $this->table('MatchingQuestionItems', ['id' => 'MatchingQuestionItemID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Question', 'string', ['limit' => 250]);
        $table->addColumn('Answer', 'string', ['limit' => 1000]);
        $table->addColumn('DateCreated', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('DateModified', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('IsDeleted', 'boolean', ['default' => false]);

        $table->addColumn('CreatorID', 'integer', ['null' => true]);
        $table->addColumn('LastEditedByID', 'integer', ['null' => true]);
        $table->addColumn('StartVerseID', 'integer', ['null' => true]);
        $table->addColumn('EndVerseID', 'integer', ['null' => true]);
        $table->addColumn('MatchingQuestionSetID', 'integer', ['null' => false]);

        $table->addForeignKey('CreatorID', 'Users', 'UserID', ['delete'=> 'SET_NULL', 'update' => 'NO_ACTION']);
        $table->addForeignKey('LastEditedByID', 'Users', 'UserID', ['delete'=> 'SET_NULL', 'update' => 'NO_ACTION']);
        $table->addForeignKey('StartVerseID', 'Verses', 'VerseID', ['delete'=> 'SET_NULL', 'update' => 'NO_ACTION']);
        $table->addForeignKey('EndVerseID', 'Verses', 'VerseID', ['delete'=> 'SET_NULL', 'update' => 'NO_ACTION']);
        $table->addForeignKey('MatchingQuestionSetID', 'MatchingQuestionSets', 'MatchingQuestionSetID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);

        $table->create();
    }
}
