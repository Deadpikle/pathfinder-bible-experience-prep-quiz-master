<?php


use Phinx\Migration\AbstractMigration;

class CreateSettings extends AbstractMigration
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
        $table = $this->table('Settings', ['id' => 'SettingID']);
        $table->addColumn('SettingKey', 'string', ['limit' => 150]);
        $table->addColumn('SettingValue', 'string', ['limit' => 150]);
        $table->addColumn('DisplayName', 'string', ['limit' => 150]);
        $table->addColumn('LastEdited', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);

        $table->save();
    }
}
