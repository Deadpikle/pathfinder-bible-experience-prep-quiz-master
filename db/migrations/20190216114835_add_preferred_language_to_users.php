<?php


use Phinx\Migration\AbstractMigration;

class AddPreferredLanguageToUsers extends AbstractMigration
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
    public function up()
    {
        $table = $this->table('Users');
        $table->addColumn('PreferredLanguageID', 'integer', ['null' => false, 'default' => 1]);
        $table->addForeignKey('PreferredLanguageID', 'Languages', 'LanguageID', ['delete'=> 'CASCADE', 'update' => 'NO_ACTION']);
        $table->save();
    }

    public function down()
    {
        $this->table('Users')
              ->dropForeignKey('PreferredLanguageID')
              ->save();

        $this->table('Users')
              ->removeColumn('PreferredLanguageID')
              ->save();
    }
}
