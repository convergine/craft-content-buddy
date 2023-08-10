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

use convergine\contentbuddy\BuddyPlugin;
use craft\elements\Asset;
use GuzzleHttp\Client;
use Craft;

class Image {

	public function generate($prompt, $folderId){
		$settings = BuddyPlugin::getInstance()->getSettings();
		$image_size = $settings->imageSize;
		$engine = $settings->imageModel;
		if($engine == 'openai'){
			return $this->sendOpenaiImageRequest($prompt, $folderId, $image_size);
		}elseif ($engine == 'stability'){
			return $this->sendStabilityImageRequest($prompt, $folderId, $image_size);
		}

	}
	function sendOpenaiImageRequest( $prompt, $folderId, $dimensions ) {

		$client = new Client();

		$imagePrompt = $this->_applyImageStylesToPrompt( $prompt );

		$imageResponse = $client->request( 'POST', 'https://api.openai.com/v1/images/generations', [
			'body'    => json_encode( [
				'prompt'          => $imagePrompt,
				'n'               => 1,
				'size'            => $dimensions,
				'response_format' => 'b64_json',
			] ),
			'headers' => [
				'Authorization' => 'Bearer ' . BuddyPlugin::getInstance()->getSettings()->getApiKey(),
				'Content-Type'  => 'application/json',
			],
		] );

		$body = $imageResponse->getBody();
		$json = json_decode( $body, true );
		$data = $json['data'] ?? [];

		$assets = array();
		foreach ( $data as $image ) {
			$asset = $this->_uploadFileData( $folderId, $image['b64_json'], $dimensions, $imagePrompt );
			if ( $asset ) {
				$assets[] = $asset;
			}
		}

		return $assets;
	}

	function sendStabilityImageRequest( $prompt, $folderId, $dimensions ) {

		$_dimension = explode('x',$dimensions);
		$width = (int)$_dimension[0];
		$height = (int)$_dimension[1];

		$settings = BuddyPlugin::getInstance()->getSettings();

		$client = new Client();

		$imageResponse = $client->request( 'POST', 'https://api.stability.ai/v1/generation/' . $settings->stabilityEngine . '/text-to-image', [
			'body'    => json_encode( [
				'samples' => 1,
				'width' => $width,
				'height' => $height,
				'sampler' => $settings->stabilitySampler,
				'steps' => $settings->stabilitySteps,
				'cfg_scale' => $settings->stabilityScale,
				'seed' => 0,
				'style_preset' => $settings->stabilityStyle,
				'text_prompts' => [
					[
						'text' => $prompt,
						'weight' => 1,
					],
				]
			] ),
			'headers' => [
				'Authorization' => $settings->getStabilityApiKey(),//'sk-AkfUldDWjYCGZ5D154vP7ZxFrxeBRP6QhuYEsItzNf8zChTJ',
				'Content-Type'  => 'application/json',
			],
		] );

		$body = $imageResponse->getBody();
		$json = json_decode( $body, true );
		$data = $json['artifacts'] ?? [];

		$assets = array();
		foreach ( $data as $image ) {
			$asset = $this->_uploadFileData( $folderId, $image['base64'], $dimensions, $prompt );
			if ( $asset ) {
				$assets[] = $asset;
			}
		}

		return $assets;
	}

	private function _applyImageStylesToPrompt( $prompt ) {
		$stylesArray = BuddyPlugin::getInstance()->getSettings()->imagesStyles;
		$stylesArray = explode( "\n", $stylesArray );
		$imagePrompt = rtrim( rtrim( $prompt ), '.' );

		if ( ! empty( $stylesArray ) ) {
			$style       = $stylesArray[ array_rand( $stylesArray ) ];
			$imagePrompt .= ', ' . $style;
		}

		$imagePrompt = str_replace( '"', '', $imagePrompt );

		return str_replace( "'", '', $imagePrompt );
	}

	private function _uploadFileData( $folderId, $imageData, $dimensions, $imagePrompt ) {
		$imagePromptWithOnlyLetters = preg_replace( '/[^A-Za-z0-9\- ]/', '', $imagePrompt );
		$imagePromptWithOnlyLetters = str_replace( ' ', '-', $imagePromptWithOnlyLetters );
		$imagePromptWithOnlyLetters = substr( $imagePromptWithOnlyLetters, 0, 40 );
		$imagePromptWithOnlyLetters = strtolower( $imagePromptWithOnlyLetters );

		$folder      = Craft::$app->getAssets()->getFolderById( $folderId );
		$folder_path = Craft::getAlias( $folder->getVolume()->getFs()->getSettings()['path'] );
		if ( ! is_dir( $folder_path ) ) {
			throw new \Exception( 'Upload folder not found' );
		}

		$tmpFilePath  = $folder_path . '/image' . rand( 9999, 9999999 );
		$outputStream = fopen( $tmpFilePath, 'wb' );
		fwrite( $outputStream, base64_decode( $imageData ) );

		fclose( $outputStream );
		$mime_type = mime_content_type( $tmpFilePath );
		$extension = explode( '/', $mime_type )[1];

		$newFilename                   = $imagePromptWithOnlyLetters . '-' . $dimensions . '-' . rand( 0, 99999999 ) . '.' . $extension;
		$asset                         = new Asset();
		$asset->tempFilePath           = $tmpFilePath;
		$asset->filename               = $newFilename;
		$asset->newFolderId            = $folder->id;
		$asset->volumeId               = $folder->volumeId;
		$asset->avoidFilenameConflicts = true;
		$asset->setScenario( Asset::SCENARIO_CREATE );

		$result = Craft::$app->getElements()->saveElement( $asset );

		// In case of error, let user know about it.
		if ( $result === false ) {
			throw new Exception( 'Error while uploading asset' );
		}

		return $asset;
	}
}