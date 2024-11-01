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

use convergine\contentbuddy\api\image\OpenAi;
use convergine\contentbuddy\api\image\StabilityAi;
use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;

class Image {
	public function generate($prompt, $folderUID): ?array {
        /** @var SettingsModel $settings */
		$settings = BuddyPlugin::getInstance()->getSettings();

        $engine = $settings->imageModel;
		if($engine == 'openai') {
            $openaiApi = new OpenAi();
            return $openaiApi->sendRequest($prompt, $folderUID);
		} else if($engine == 'stability') {
            $stabilityApi = new StabilityAi();
            return $stabilityApi->sendRequest($prompt, $folderUID);
		}

        return null;
	}
}
