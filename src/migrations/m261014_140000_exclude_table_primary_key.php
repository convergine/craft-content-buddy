<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\ExcludeFromBulk;
use craft\db\Migration;

/**
 * m261014_140000_exclude_table_primary_key migration.
 *
 * Install.php created the bulk translation exclusions table without a primary
 * key from 1.2.8 to 1.3.0. m250617_110743_add_primary_key_to_exclude_table
 * only fixed upgraded installs: a fresh install marks every migration as
 * applied, so it never ran there. Adds the same `id` primary key where it is
 * still missing.
 */
class m261014_140000_exclude_table_primary_key extends Migration {
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		$table = ExcludeFromBulk::tableName();

		if ( ! $this->db->tableExists( $table ) ) {
			return true;
		}

		if ( ! empty( $this->db->getSchema()->getTableSchema( $table, true )?->primaryKey ) ) {
			return true;
		}

		$this->addColumn( $table, 'id', $this->primaryKey()->first() );

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		echo "m261014_140000_exclude_table_primary_key cannot be reverted.\n";

		return false;
	}
}
