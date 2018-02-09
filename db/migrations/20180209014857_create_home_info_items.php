<?php


use Phinx\Migration\AbstractMigration;

class CreateHomeInfoItems extends AbstractMigration
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
        $table = $this->table('HomeInfoItems', ['id' => 'HomeInfoItemID']);
        $table->addColumn('IsLink', 'boolean', ['default' => false]);
        $table->addColumn('Text', 'string', ['limit' => 500]);
        $table->addColumn('URL', 'string', ['limit' => 500]);
        $table->addColumn('SortOrder', 'integer');

        $table->addColumn('HomeInfoLineID', 'integer');

        $table->addForeignKey('HomeInfoLineID', 'HomeInfoLines', 'HomeInfoLineID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->save();
    }
}
