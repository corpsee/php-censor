<?php

use Phinx\Migration\AbstractMigration;

class FixedBuildMetas extends AbstractMigration
{
    public function up()
    {
        $this
            ->table('build_metas')

            ->addColumn('plugin', 'string', ['limit' => 250, 'null' => true])

            ->renameColumn('meta_key', 'key')
            ->renameColumn('meta_value', 'value')

            ->save();
    }

    public function down()
    {
        $this
            ->table('build_metas')

            ->removeColumn('plugin')

            ->renameColumn('key', 'meta_key')
            ->renameColumn('value', 'meta_value')

            ->save();
    }
}
