<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/8/2023
 * Time: 12:50 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\PromptModel;
use convergine\contentbuddy\records\BuddyPromptRecord;
use Craft;

class PromptProcessor {

	public function process($data){
		$prompt = $data['prompt']??'';
		$query = $data['query']??'';
		$lang = $data['lang']??'';
		if(!$prompt || !$query){
			throw new \Exception(Craft::t('convergine-contentbuddy', "Missing required params"));
		}

		if(false !== $systemPrompt = $this->_processSystemPrompt($prompt,$query,$lang)){
			return $systemPrompt;
		}

		$promptRecord = BuddyPromptRecord::findOne(['id'=>(int)$prompt]);
		if(!$promptRecord){
			throw new \Exception(Craft::t('convergine-contentbuddy', "Prompt not found"));
		}

		$prompt = preg_replace('/(\[\[text\]\])/', $query, $promptRecord->template);
		$prompt = "Using the locale $lang\n\n$prompt";

		$temperature = $promptRecord->temperature;
		$maxTokens = $promptRecord->wordsNumber;
		if ($promptRecord->wordsType == 2) {

			$maxTokens = round($this->_countWords($query) * $promptRecord->wordsMultiplier * 1.33);
		}

		return [
			'response'=>BuddyPlugin::getInstance()->request->send($prompt, $maxTokens, $temperature),
			'replaceText'=>$promptRecord->replaceText
		];
	}

	private function _processSystemPrompt($prompt,$query,$lang){

		if($prompt == '_translate_'){
			$prompt="Translate to {$lang}: {$query}";
			return [
				'response'=>BuddyPlugin::getInstance()->request->send($prompt, 30000, 0.7),
				'replaceText'=>1
			];
		}elseif ($prompt == '_generate_images_'){
			$assetId = BuddyPlugin::getInstance()->getSettings()->generateImageAssetId;
			$assets = (new Image())->generate($query,$assetId);
			$content = $query;
			if($assets){
				$content = $this->_addImages( $content, $assets );
			}
			return [
				'response' => $content,
				'replaceText'=>1
			];
		}
		return false;
	}

	private function _countWords($str){
		return count(preg_split('~[^\p{L}\p{N}\']+~u', $str));
	}

	private function _addImages( $content, $images ) {
		foreach ( $images as $image ) {
			$content .= '<img src="' . $image->getUrl() . '" />';
		}

		return $content;
	}
}