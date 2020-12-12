<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;
use PHPCensor\Model\Build;

class AddedBuildRequests extends AbstractMigration
{
    public function up()
    {
        $adapterType   = $this->getAdapter()->getAdapterType();
        $payloadParams = ['null' => true];
        if ('mysql' === $adapterType) {
            $payloadParams['limit'] = MysqlAdapter::TEXT_LONG;
        }

        $this
            ->table('build_requests')

            ->addColumn('project_id', 'integer')
            ->addColumn('user_id', 'integer', ['default' => 0])
            ->addColumn('source', 'integer', ['default' => Build::SOURCE_UNKNOWN])
            ->addColumn('payload', 'text', $payloadParams)
            ->addColumn('create_date', 'datetime')

            ->save();
    }

    public function down()
    {
        $this
            ->table('build_requests')
            ->drop()
            ->save();
    }
}
