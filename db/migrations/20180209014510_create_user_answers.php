<?php


use Phinx\Migration\AbstractMigration;

class CreateUserAnswers extends AbstractMigration
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
        $table = $this->table('UserAnswers', ['id' => 'UserAnswerID']);
        $table->addColumn('Answer', 'string', ['limit' => 5000]);
        $table->addColumn('DateAnswered', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $table->addColumn('WasCorrect', 'string', ['default' => false]);

        $table->addColumn('QuestionID', 'integer');
        $table->addColumn('UserID', 'integer');

        $table->addForeignKey('QuestionID', 'Questions', 'QuestionID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->addForeignKey('UserID', 'Users', 'UserID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->create();
    }
}
