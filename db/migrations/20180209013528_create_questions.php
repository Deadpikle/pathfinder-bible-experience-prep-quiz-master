<?php


use Phinx\Migration\AbstractMigration;

class CreateQuestions extends AbstractMigration
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
        $table = $this->table('Questions', ['id' => 'QuestionID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Question', 'string', ['limit' => 10000]);
        $table->addColumn('Answer', 'string', ['limit' => 10000]);
        $table->addColumn('NumberPoints', 'integer');
        $table->addColumn('DateCreated', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('DateModified', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('IsFlagged', 'boolean', ['default' => false]);
        $table->addColumn('Type', 'string', ['limit' => 50]);
        $table->addColumn('CommentaryStartPage', 'integer', ['null' => true]);
        $table->addColumn('CommentaryEndPage', 'integer', ['null' => true]);
        $table->addColumn('IsDeleted', 'boolean', ['default' => false]);
        // FKs
        $table->addColumn('CreatorID', 'integer', ['null' => true]);
        $table->addColumn('LastEditedByID', 'integer', ['null' => true]);
        $table->addColumn('StartVerseID', 'integer', ['null' => true]);
        $table->addColumn('EndVerseID', 'integer', ['null' => true]);
        $table->addColumn('CommentaryID', 'integer', ['null' => true]);

        $table->addForeignKey('CreatorID', 'Users', 'UserID', ['delete'=> 'SET_null', 'update' => 'NO_ACTION']);
        $table->addForeignKey('LastEditedByID', 'Users', 'UserID', ['delete'=> 'SET_null', 'update' => 'NO_ACTION']);
        $table->addForeignKey('StartVerseID', 'Verses', 'VerseID', ['delete'=> 'SET_null', 'update' => 'NO_ACTION']);
        $table->addForeignKey('EndVerseID', 'Verses', 'VerseID', ['delete'=> 'SET_null', 'update' => 'NO_ACTION']);
        $table->addForeignKey('CommentaryID', 'Commentaries', 'CommentaryID', ['delete'=> 'SET_null', 'update' => 'NO_ACTION']);

        $table->create();
    }
}
