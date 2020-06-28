<?php


use Phinx\Migration\AbstractMigration;

class AddLanguages extends AbstractMigration
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
        // add table
        $table = $this->table('Languages', ['id' => 'LanguageID', 'collation'=>'utf8mb4_unicode_ci']);
        $table->addColumn('Name', 'string', ['limit' => 250]);
        $table->addColumn('IsDefault', 'boolean', ['limit' => 250, 'default' => false]);
        $table->create();
        // add data
        $languagesData = [
            [
                'Name' => 'English',
                'IsDefault' => true
            ],
            [
                'Name' => 'Français',
                'IsDefault' => false
            ],
            [
                'Name' => 'Español',
                'IsDefault' => false
            ]
        ];
        $this->table('Languages')->insert($languagesData)->save();
    }
}
