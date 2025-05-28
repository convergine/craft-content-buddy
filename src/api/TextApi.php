<?php

namespace convergine\contentbuddy\api;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;

abstract class TextApi {
    protected SettingsModel $settings;

    public function __construct() {
        /** @var SettingsModel $settings */
        $settings = BuddyPlugin::getInstance()->getSettings();
        $this->settings = $settings;
    }

    function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '') {}

    protected function getMaxTokensForModel($model): int {
        return match($model) {
            "grok-beta", "grok-2", "grok-3", "grok-3-fast", "grok-3-mini", "grok-3-mini-fast" => 130000,
            "gpt-4o", "gpt-4o-mini", "o1", "o1-mini", "o3-mini", "gpt-4.5-preview" => 15900,
            "gpt-4", "grok-2-vision", "grok-vision-beta" => 7900,
            "gpt-4-turbo", "gpt-3.5-turbo" => 3900,
            default => 2000
        };
    }
}
