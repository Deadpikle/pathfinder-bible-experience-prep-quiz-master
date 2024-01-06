<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAbbrToLanguages extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('Languages');
        $table->addColumn('Abbreviation', 'string', ['limit' => 3, 'default' => 'en']);
        $table->update();

        $builder = $this->getQueryBuilder();
        $builder
            ->update('Languages')
            ->set('Abbreviation', 'fr')
            ->where(['Name' => 'Français'])
            ->execute();
        $builder = $this->getQueryBuilder();
        $builder
            ->update('Languages')
            ->set('Abbreviation', 'es')
            ->where(['Name' => 'Español'])
            ->execute();
    }
}
