<?php


use Phinx\Migration\AbstractMigration;

class CreateConferences extends AbstractMigration
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
        $table = $this->table('Conferences', ['id' => 'ConferenceID']);
        $table->addColumn('Name', 'string', ['limit' => 150]);
        $table->addColumn('URL', 'string', ['limit' => 350]);
        $table->addColumn('ContactName', 'string', ['limit' => 150]);
        $table->addColumn('ContactEmail', 'string', ['limit' => 150]);
        $table->save();
    }
}
