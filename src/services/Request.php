<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 5/9/2023
 * Time: 2:59 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\api\text\DeepL;
use convergine\contentbuddy\api\text\OpenAi;
use convergine\contentbuddy\api\text\XAi;
use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;

class Request {
	protected SettingsModel $settings;

	public function __construct() {
		/** @var SettingsModel $settings */
		$settings       = BuddyPlugin::getInstance()->getSettings();
		$this->settings = $settings;
	}

	public function send( $prompt, $maxTokens, $temperature, $isTranslate = false,$lang='' ) {
		$textAi = $isTranslate ? $this->settings->translationAi:$this->settings->textAi;
		if ( $textAi == 'deepl' ) {
			$textApi = new DeepL();
		}elseif ( $textAi == 'xai' ) {
			$textApi = new XAi();
		} else { //default to openai
			$textApi = new OpenAi();
		}

		return $textApi->sendRequest( $prompt, $maxTokens, $temperature, $isTranslate, $lang );
	}
}
