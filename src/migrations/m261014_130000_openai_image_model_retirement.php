<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\records\SettingsRecord;
use craft\db\Migration;
use craft\db\Query;

/**
 * m261014_130000_openai_image_model_retirement migration.
 *
 * OpenAI shut down DALL·E 2 and 3 on 2026-05-12 and shuts down gpt-image-1 on
 * 2026-10-23 and gpt-image-1-mini on 2026-12-01. The DALL·E provider was the
 * default, so installs on it are moved to the GPT Image provider, and saved GPT
 * Image models to their gpt-image-2.5 replacement.
 *
 * @see https://developers.openai.com/api/docs/deprecations
 */
class m261014_130000_openai_image_model_retirement extends Migration {
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		$table = SettingsRecord::tableName();

		if ( ! $this->db->tableExists( $table ) ) {
			return true;
		}

		$saved = ( new Query() )
			->select( [ 'name', 'value' ] )
			->from( $table )
			->where( [ 'name' => [ 'imageModel', 'dalleModel', 'imageSizeDalle3', 'imageOpenai5Model', 'imageSizeOpenai5' ] ] )
			->pairs( $this->db );

		foreach ( SettingsModel::retireOpenAiImageSettings( $saved ) as $name => $value ) {
			if ( array_key_exists( $name, $saved ) ) {
				$this->update( $table, [ 'value' => $value ], [ 'name' => $name ] );
			} else {
				$this->insert( $table, [ 'name' => $name, 'value' => $value ] );
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		echo "m261014_130000_openai_image_model_retirement cannot be reverted.\n";

		return false;
	}
}
