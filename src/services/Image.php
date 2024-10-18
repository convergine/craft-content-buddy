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
use convergine\contentbuddy\models\SettingsModel;
use Craft;
use craft\elements\Asset;
use GuzzleHttp\Client;

class Image {

	public function generate($prompt, $folderUID) {
		$settings = BuddyPlugin::getInstance()->getSettings();
        $engine = $settings->imageModel;
		if($engine == 'openai') {
            $model = $settings->dalleModel;
            $image_size = $model == 'dall-e-3' ? $settings->imageSizeDalle3 : $settings->imageSize;
			return $this->sendOpenaiImageRequest($model, $prompt, $folderUID, $image_size);
		} else if ($engine == 'stability') {
            $image_size = $settings->imageSizeStability;
			return $this->sendStabilityImageRequest($prompt, $folderUID, $image_size);
		}
        return null;
	}

	function sendOpenaiImageRequest($model, $prompt, $folderUID, $dimensions ) {
		$client = new Client();

		$imagePrompt = $this->_applyImageStylesToPrompt( $prompt );

		$imageResponse = $client->request( 'POST', 'https://api.openai.com/v1/images/generations', [
			'body'    => json_encode( [
                'model'           => $model,
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
			$asset = $this->_uploadFileData( $folderUID, $image['b64_json'], $dimensions, $imagePrompt );
			if ( $asset ) {
				$assets[] = $asset;
			}
		}

		return $assets;
	}

	function sendStabilityImageRequest( $prompt, $folderUID, $dimensions ) {

		$_dimension = explode('x',$dimensions);
		$width = (int)$_dimension[0];
		$height = (int)$_dimension[1];

        /** @var SettingsModel $settings */
		$settings = BuddyPlugin::getInstance()->getSettings();

		$client = new Client();

        $model = $settings->stabilityEngine;
        $apiEndpoint = $this->getStabilityApiEndpoint($model);
        $apiData = $this->getStabilityApiData($model,$width,$height,$settings,$prompt);

		$imageResponse = $client->request( 'POST', 'https://api.stability.ai'.$apiEndpoint, $apiData);

		$body = $imageResponse->getBody();
        $json = json_decode( $body, true );
        Craft::info("ContentBuddy Image Result: " . $body, __METHOD__);

        $assets = array();

        if($this->isStableDiffusion3($model)) {
            $image = $json['image'] ?? null;
            $asset = $this->_uploadFileData( $folderUID, $image, $dimensions, $prompt );
            if ( $asset ) {
                $assets[] = $asset;
            }
        } else {
            $data = $json['artifacts'] ?? [];
            foreach ( $data as $image ) {
                $asset = $this->_uploadFileData( $folderUID, $image['base64'], $dimensions, $prompt );
                if ( $asset ) {
                    $assets[] = $asset;
                }
            }
        }

		return $assets;
	}

    private function isStableDiffusion3($model) : bool {
        return in_array($model, ['sd3','core','ultra']);
    }

    private function getStabilityApiEndpoint($model) : string {
        if($this->isStableDiffusion3($model)) {
            return "/v2beta/stable-image/generate/$model";
        } else {
            return "/v1/generation/$model/text-to-image";
        }
    }

    private function getStabilityApiData($model,$width,$height,$settings,$prompt) : array {
        if($this->isStableDiffusion3($model)) {
            return [
                'multipart' => [
                    [
                        'name' => 'prompt',
                        'contents' => $prompt,
                    ],
                    [
                        'name' => 'width',
                        'contents' => $width,
                    ],
                    [
                        'name' => 'height',
                        'contents' => $height,
                    ],
                    [
                        'name' => 'sampler',
                        'contents' => $settings->stabilitySampler,
                    ],
                    [
                        'name' => 'steps',
                        'contents' => $settings->stabilitySteps,
                    ],
                    [
                        'name' => 'cfg_scale',
                        'contents' => $settings->stabilityScale,
                    ],
                    [
                        'name' => 'seed',
                        'contents' => 0,
                    ],
                    [
                        'name' => 'style_preset',
                        'contents' => $settings->stabilityStyle,
                    ],
                    [
                        'name' => 'samples',
                        'contents' => 1,
                    ],
                ],
                'headers' => [
                    'Authorization' => "Bearer ".$settings->getStabilityApiKey(),
                    'Accept'  => 'application/json',
                ],
            ];
        } else {
            return [
                'body'    => json_encode([
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
                ]),
                'headers' => [
                    'Authorization' => $settings->getStabilityApiKey(),
                    'Content-Type'  => 'application/json',
                ],
            ];
        }
    }

    private function getStabilityApiHeaders($model,$key) : array {
        if(in_array($model, ['sd3','core','ultra'])) {
            return [
                'Authorization' => "Bearer $key",
                'Accept'  => 'image/*',
            ];
        } else {
            return [
                'Authorization' => $key,
                'Content-Type'  => 'application/json',
            ];
        }
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

	private function _uploadFileData( $folderUID, $imageData, $dimensions, $imagePrompt ) {
		$imagePromptWithOnlyLetters = preg_replace( '/[^A-Za-z0-9\- ]/', '', $imagePrompt );
		$imagePromptWithOnlyLetters = str_replace( ' ', '-', $imagePromptWithOnlyLetters );
		$imagePromptWithOnlyLetters = substr( $imagePromptWithOnlyLetters, 0, 40 );
		$imagePromptWithOnlyLetters = strtolower( $imagePromptWithOnlyLetters );

		$folder      = Craft::$app->getAssets()->getFolderByUid( $folderUID );
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