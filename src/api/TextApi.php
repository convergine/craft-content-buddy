<?php

namespace convergine\contentbuddy\api;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;

abstract class TextApi {
	protected SettingsModel $settings;

	public function __construct() {
		/** @var SettingsModel $settings */
		$settings       = BuddyPlugin::getInstance()->getSettings();
		$this->settings = $settings;
	}

	function sendRequest( $prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = '' ) {
	}

	protected function getMaxTokensForModel( $model ): int {
		return match ( $model ) {
			"grok-beta", "grok-2", "grok-3", "grok-3-fast", "grok-3-mini", "grok-3-mini-fast" => 130000,
			"claude-sonnet-4-5-20250929", "claude-haiku-4-5-20251001", "claude-opus-4-5-20251101" => 64000,
			"gpt-4.1", "gpt-4.1-mini" => 32000,
			"gpt-4o", "gpt-4o-mini", "o1", "o1-mini", "o3-mini", "gpt-4.5-preview" => 15900,
			"gpt-4", "grok-2-vision", "grok-vision-beta" => 7900,
			"gpt-4-turbo", "gpt-3.5-turbo" => 3900,
			default => 2000
		};
	}

	protected function isGPT5Model( string $model ): bool {
		return str_contains( $model, 'gpt-5' );
	}

	protected function isReasoningModel( string $model ): bool {
		return in_array( $model, [ 'o1', 'o1-mini', 'o3-mini', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano' ] );
	}
}
