<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\ExcludeFromBulk;
use craft\db\Migration;

/**
 * m250617_110743_add_primary_key_to_exclude_table migration.
 */
class m250617_110743_add_primary_key_to_exclude_table extends Migration
{
    public function safeUp(): bool
    {
        $table = ExcludeFromBulk::tableName();

        if(!$this->db->tableExists($table)) {
            return true;
        }

        $primaryKeys = $this->getPrimaryKeyColumns($table);
        if(!empty($primaryKeys)) {
            return true;
        }

        $this->addColumn($table, 'id', $this->primaryKey()->first());

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }

    /**
     * Get primary key columns for a table
     */
    private function getPrimaryKeyColumns(string $tableName): array
    {
        $schema = $this->db->getSchema()->getTableSchema($tableName);
        return $schema?->primaryKey ?? [];
    }
}
