<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\SettingsRecord;
use craft\db\Migration;

/**
 * m260709_140000_grok_model_retirement migration.
 *
 * xAI retired grok-3, grok-4-0709, grok-4-fast-reasoning,
 * grok-4-fast-non-reasoning and grok-code-fast-1 on 2026-05-15, and the
 * grok-2/grok-beta generation is gone from the model list. The retired slugs
 * still resolve — they silently redirect to grok-4.3 and bill at grok-4.3
 * rates — but they are no longer offered in the settings dropdowns, so an
 * existing install would render a select with no matching option and lose the
 * value on the next save.
 *
 * grok-code-fast-1 now routes to grok-build-0.1, a Code API model, so it is
 * remapped to grok-4.3 rather than carried over.
 *
 * @see https://docs.x.ai/developers/migration/may-15-retirement
 */
class m260709_140000_grok_model_retirement extends Migration {
	/**
	 * Models still offered in _api.twig and _translation.twig.
	 */
	private const SUPPORTED = [ 'grok-4.3', 'grok-4.5', 'grok-4.20-0309-reasoning' ];

	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		$table = SettingsRecord::tableName();

		if ( ! $this->db->tableExists( $table ) ) {
			return true;
		}

		$this->update( $table, [ 'value' => 'grok-4.3' ], [
			'and',
			[ 'name' => 'xAiModel' ],
			[ 'not in', 'value', self::SUPPORTED ],
		] );

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		echo "m260709_140000_grok_model_retirement cannot be reverted.\n";

		return false;
	}
}
