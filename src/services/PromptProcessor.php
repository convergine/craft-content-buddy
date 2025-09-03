<?php

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\records\BuddyPromptRecord;
use Craft;
use Exception;

class PromptProcessor {
	public function process($data) {
		$prompt = $data['prompt']??'';
		$query = $data['query']??'';
		$lang = $data['lang']??'';
		if(!$prompt || !$query){
			throw new Exception(Craft::t('convergine-contentbuddy', "Missing required params"));
		}

		if(false !== $systemPrompt = $this->_processSystemPrompt($prompt,$query,$lang)){
			return $systemPrompt;
		}

		$promptRecord = BuddyPromptRecord::findOne(['id'=>(int)$prompt]);
		if(!$promptRecord){
			throw new Exception(Craft::t('convergine-contentbuddy', "Prompt not found"));
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

	private function _processSystemPrompt($prompt,$query,$lang) {
		if($prompt == '_translate_'){
			$language = Craft::$app->getI18n()->getLocaleById( $lang)->getDisplayName() . ' (' . $lang . ')';
			$prompt="Translate to {$language}. Return the full translation only, for the following text: {$query}";
			return [
				'response'=>BuddyPlugin::getInstance()->request->send($prompt, 30000, 0.7, true, '', $lang),
				'replaceText'=>1
			];
		}elseif ($prompt == '_generate_images_'){
			$assetUID = BuddyPlugin::getInstance()->getSettings()->generateImageAssetId;
			$assets = (new Image())->generate($query,$assetUID);
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

	private function _countWords($str) {
		return count(preg_split('~[^\p{L}\p{N}\']+~u', $str));
	}

	private function _addImages( $content, $images ) {
		foreach ( $images as $image ) {
            $content .= "<figure><img src='" . $image->getUrl() . "' /></figure>";
		}
		return $content;
	}

    public function generate($data) : array {
        $id = $data['id'] ?? '';
        $type = $data['type'] ?? '';
        $handle = $data['handle'] ?? '';
        $name = $data['name'] ?? '';
        $amount = $data['amount'] ?? 1;

        if(!$id || !$type || !$handle) {
            throw new Exception(Craft::t('convergine-contentbuddy', "Missing required params"));
        }

        $element = match($type) {
            'category' => Craft::$app->categories->getCategoryById($id),
            'asset' => Craft::$app->assets->getAssetById($id),
            default => Craft::$app->entries->getEntryById($id),
        };

        if(!$element) {
            throw new Exception(Craft::t('convergine-contentbuddy', "Record not found"));
        }

        $results = [];
        for($i = 0; $i < $amount; $i++) {
            $results[] = BuddyPlugin::getInstance()->generateEntry->generate($element, $handle, $name);
        }

        return $results;
    }
}