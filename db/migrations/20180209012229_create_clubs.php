<?php


use Phinx\Migration\AbstractMigration;

class CreateClubs extends AbstractMigration
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
        $table = $this->table('Clubs', ['id' => 'ClubID']);
        $table->addColumn('Name', 'string', ['limit' => 150]);
        $table->addColumn('URL', 'string', ['limit' => 350]);
        $table->addColumn('ConferenceID', 'integer');
        $table->addForeignKey('ConferenceID', 'Conferences', 'ConferenceID', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION']);
        $table->save();
    }
}
