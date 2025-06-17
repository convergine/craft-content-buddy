<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\ExcludeFromBulk;
use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\MigrationHelper;

/**
 * m250612_081315_exclude_from_bulk_translations migration.
 */
class m250612_081315_exclude_from_bulk_translations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
	    $this->archiveTableIfExists( ExcludeFromBulk::tableName() );
	    if(!$this->db->tableExists(ExcludeFromBulk::tableName())) {
		    $this->createTable( ExcludeFromBulk::tableName(), [
                'id'          => $this->primaryKey(),
                'elementId'   => $this->integer(),
			    'siteId'      => $this->integer(),
			    'dateCreated' => $this->dateTime()->notNull(),
			    'dateUpdated' => $this->dateTime()->notNull(),
			    'uid'         => $this->uid(),
		    ] );
		    $this->createIndex(null, ExcludeFromBulk::tableName(), 'elementId', false);
		    $this->createIndex(null, ExcludeFromBulk::tableName(), 'siteId', false);
		    $this->addForeignKey(null, ExcludeFromBulk::tableName(), ['elementId'], CraftTable::ELEMENTS, ['id'], 'SET NULL');
		    $this->addForeignKey(null, ExcludeFromBulk::tableName(), ['siteId'], CraftTable::SITES, ['id'], 'SET NULL');
	    }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
	    $this->_removeTables();
        return false;
    }

	/**
	 * @return void
	 */
	protected function _removeTables() {
		$tables = [
			ExcludeFromBulk::tableName()
		];
		foreach ($tables as $table) {
			if ($this->_tableExists($table)) {
				$this->dropAllForeignKeysToTable($table);
				MigrationHelper::dropAllForeignKeysOnTable($table, $this);
			}
		}
		foreach ( $tables as $table ) {
			$this->dropTableIfExists( $table );
		}
	}

	private function _tableExists(string $tableName): bool
	{
		$schema = $this->db->getSchema();
		$schema->refresh();

		$rawTableName = $schema->getRawTableName($tableName);
		$table = $schema->getTableSchema($rawTableName);

		return (bool)$table;
	}

}
