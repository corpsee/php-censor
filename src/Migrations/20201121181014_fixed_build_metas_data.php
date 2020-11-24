<?php

use Phinx\Migration\AbstractMigration;

class FixedBuildMetasData extends AbstractMigration
{
    public function up()
    {
        $metaKeys = $this->fetchAll("SELECT DISTINCT key FROM build_metas");
        foreach ($metaKeys as $metaKey) {
            $metaKeyString = $metaKey['key'];
            $metaKeyParts  = \explode('-', $metaKeyString);
            if (2 === \count($metaKeyParts) && !empty($metaKeyParts[0]) && !empty($metaKeyParts[1])) {
                $pluginName = $metaKeyParts[0];
                $newMetaKey = $metaKeyParts[1];

                $pluginSql  = "'{$pluginName}'";
                if ('plugin' === $pluginName) {
                    $pluginSql = "NULL";
                }

                $this->execute("UPDATE build_metas SET key = '{$newMetaKey}', plugin = {$pluginSql} WHERE key = '{$metaKeyString}'");
            }
        }
    }

    public function down()
    {
    }
}
