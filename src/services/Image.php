<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/2/2023
 * Time: 3:41 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\api\image\OpenAi5;
use convergine\contentbuddy\api\image\StabilityAi;
use convergine\contentbuddy\api\image\OpenRouter;
use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;

class Image {
	public function generate($prompt, $folderUID): ?array {
        /** @var SettingsModel $settings */
		$settings = BuddyPlugin::getInstance()->getSettings();

        $engine = $settings->imageModel;
		// 'openai' and 'openai-dall-e-2' were the DALL·E provider, shut down by OpenAI on 2026-05-12.
		if( in_array( $engine, [ 'openai5', 'openai', 'openai-dall-e-2' ], true ) ) {
			$openaiApi = new OpenAi5();
			return $openaiApi->sendRequest($prompt, $folderUID);
		} else if($engine == 'stability') {
            $stabilityApi = new StabilityAi();
            return $stabilityApi->sendRequest($prompt, $folderUID);
		}else if($engine == 'openrouter') {
			$openrouterApi = new OpenRouter();
			return $openrouterApi->sendRequest($prompt, $folderUID);
		}

        return null;
	}
}
