<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 5/5/2023
 * Time: 1:56 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\records\BuddyPromptRecord;
use Craft;
use yii\base\Exception;

class ImportExport extends \craft\base\Component {

	public static string $version = 'v1';
	public const SETTINGS_LIST = [
		'general'          => [
			'label'    => 'General Content Buddy Settings',
			'settings' => [
				'enableTranslationMenu',
				'enableBulkTranslation',
				'delayLanguage',
				'delayEntry',
				'delaySection',
				'maxAttempts',
				'ttr'
			]
		],
		'text-generation'  => [
			'label'    => 'Text Generation Settings',
			'settings' => [
				'textAi',
				'preferredModel',
				'apiToken',
				'xAiModel',
				'xAiApiKey',
				'xAiReasoningEffort',
				'anthropicModel',
				'anthropicApiKey',
				'openrouterModel',
				'openrouterApiKey',
				'systemMessage',

			]
		],
		'translation'      => [
			'label'    => 'Translation Settings',
			'settings' => [
				'translationAi',
				'preferredTranslationModel',
				'openAiAssistantId',
				'apiToken',
				'xAiModel',
				'xAiApiKey',
				'xAiReasoningEffort',
				'anthropicTranslationModel',
				'anthropicApiKey',
				'openrouterTranslationModel',
				'openrouterApiKey',
				'deepLApiVersion',
				'deepLApiKey',
				'deepLGlossaryId',
				'translateSlugs'
			]
		],
		'image-generation' => [
			'label'    => 'Image Generation Settings',
			'settings' => [
				'imageModel',
				'dalleModel',
				'imagesStyles',
				'imageSizeDalle3',
				'imageSize',
				'imageOpenai5Model',
				'imageSizeOpenai5',
				'imageOpenRouterModel',
				'imageOpenRouterStyles',
				'imageSizeOpenRouter',
				'apiOpenRouteImageApiToken',
				'stabilityAPIKey',
				'stabilityEngine',
				'stabilityStyle',
				'stabilitySampler',
				'imageSizeStability',
				'generateImageFromText',
				'generateImageAssetId'
			]
		],
		'fields'           => [
			'label'    => 'Fields Settings',
			'settings' => [
				'enabledFields'
			]
		],
		'prompt-templates' => [
			'label'    => 'Prompt Templates',

		],
	];

	public function export( array $settings ): array {
		$settingsModel = BuddyPlugin::getInstance()->getSettings();
		$data          = [
			'exportVersion' => self::$version
		];
		foreach ( $settings as $key => $val ) {

			if ( $val && isset( self::SETTINGS_LIST[ $key ]['settings'] ) ) {
				$settingsList = [];
				foreach ( self::SETTINGS_LIST[ $key ]['settings'] as $settingItem ) {
					if ( property_exists( $settingsModel, $settingItem ) ) {
						$settingsList[ $settingItem ] = $settingsModel->{$settingItem};
					}
				}
				$data[ $key ] = $settingsList;
			}elseif ($key == 'prompt-templates' && $val){
				$data[$key] = $this->_addPromptsList();
			}
		}


		return $data;
	}

	private function _addPromptsList():array {
		$promptsList = [];
		$prompts = BuddyPromptRecord::find()->orderBy(['order'=>SORT_ASC])->all();
		foreach ($prompts as $prompt){
			/**
			 * @var BuddyPromptRecord $prompt
			 */
			$promptsList[] = $prompt->toArray();
		}
		return $promptsList;
	}

	/**
	 * @throws Exception
	 */
	public function import( array $data ): bool {
		if ( ! isset( $data['exportVersion'] ) ) {
			throw new Exception( 'Incorrect file content' );
		}
		if ( $data['exportVersion'] != self::$version ) {
			throw new Exception( 'Incompatible settings version' );
		}
		$settingsModel    = BuddyPlugin::getInstance()->getSettings();
		$settingsToImport = [];
		$prompts = [];
		foreach ( self::SETTINGS_LIST as $key => $settingData ) {
			// A partial export omits the sections that were left unchecked.
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}
			if($key == 'prompt-templates'){
				$prompts = $data[ $key ];

			}else {
				foreach ( $settingData['settings'] as $settingName ) {
					if ( isset( $data[ $key ][ $settingName ] ) ) {
						$settingsToImport[ $settingName ] = $data[ $key ][ $settingName ];
					}
				}
			}
		}
		$settingsModel->saveSettings( $settingsToImport );
		if($prompts){
			$this->_importPrompts($prompts);
		}

		return true;
	}

	private function _importPrompts(array $prompts):void {
		// delete all current prompts
		$currentPrompts = BuddyPromptRecord::find()->orderBy(['order'=>SORT_ASC])->all();
		foreach ($currentPrompts as $prompt){
			$prompt->delete();
		}
		foreach ($prompts as $prompt){
			$promptRecord = new BuddyPromptRecord();
			$promptRecord->setAttributes($prompt,false);
			$promptRecord->save();
		}

	}
}