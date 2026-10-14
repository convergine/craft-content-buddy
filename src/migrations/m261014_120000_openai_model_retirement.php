<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\records\SettingsRecord;
use craft\db\Migration;
use craft\db\Query;

/**
 * m261014_120000_openai_model_retirement migration.
 *
 * OpenAI shuts down gpt-3.5-turbo (the old default), gpt-4, gpt-4-turbo,
 * gpt-4.1-nano, o1 and o3-mini on 2026-10-23, and gpt-5-nano on 2026-12-11.
 * gpt-4.5-preview, gpt-5.1-codex and gpt-5.1-codex-mini are already gone, and
 * gpt-5.1-mini never existed. None of them are offered in the settings
 * dropdowns any more, so an install that saved one would render a select with
 * no matching option and lose the value on the next save. Each saved value is
 * moved to OpenAI's named replacement, anything else unknown to the default.
 *
 * @see https://developers.openai.com/api/docs/deprecations
 */
class m261014_120000_openai_model_retirement extends Migration {
	/**
	 * Settings that hold an OpenAI model id.
	 */
	private const SETTINGS = [ 'preferredModel', 'preferredTranslationModel' ];

	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		$table = SettingsRecord::tableName();

		if ( ! $this->db->tableExists( $table ) ) {
			return true;
		}

		$rows = ( new Query() )
			->select( [ 'id', 'value' ] )
			->from( $table )
			->where( [ 'name' => self::SETTINGS ] )
			->all( $this->db );

		foreach ( $rows as $row ) {
			$model = SettingsModel::normalizeOpenAiModel( (string) $row['value'] );
			if ( $model !== $row['value'] ) {
				$this->update( $table, [ 'value' => $model ], [ 'id' => $row['id'] ] );
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		echo "m261014_120000_openai_model_retirement cannot be reverted.\n";

		return false;
	}
}
