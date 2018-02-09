<?php


use Phinx\Migration\AbstractMigration;

class CreateHomeInfoSections extends AbstractMigration
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
        $table = $this->table('HomeInfoSections', ['id' => 'HomeInfoSectionID']);
        $table->addColumn('Name', 'string', ['limit' => 300]);
        $table->addColumn('SortOrder', 'integer');

        $table->addColumn('YearID', 'integer');
        $table->addColumn('ConferenceID', 'integer');

        $table->addForeignKey('YearID', 'Years', 'YearID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->addForeignKey('ConferenceID', 'Conferences', 'ConferenceID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->save();
    }
}
