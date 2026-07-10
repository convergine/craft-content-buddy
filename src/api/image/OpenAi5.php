<?php

namespace convergine\contentbuddy\api\image;

use convergine\contentbuddy\api\ImageApi;
use convergine\contentbuddy\BuddyPlugin;
use GuzzleHttp\Client;
use yii\helpers\StringHelper;
use Exception;

class OpenAi5 extends ImageApi {
	function sendRequest( $prompt, $folderUID ): array {
		$model      = $this->settings->imageOpenai5Model;
		$image_size = $this->settings->imageSizeOpenai5;

		$client = new Client();

		$imagePrompt = $this->applyImageStylesToPrompt( $prompt );

		$requestData = [
			'model'           => $model,
			'prompt'          => $imagePrompt,
			'n'               => 1,
			'size'            => $image_size,
		];
		\Craft::info( 'Image OpenAI request body: ' . json_encode( $requestData ), 'content-buddy' );
		try {
			$imageResponse = $client->request( 'POST', 'https://api.openai.com/v1/images/generations', [
				'body'    => json_encode( $requestData ),
				'headers' => [
					'Authorization' => 'Bearer ' . $this->settings->getOpenAiApiKey(),
					'Content-Type'  => 'application/json',
				],
			] );

			$body = $imageResponse->getBody();
			$json = json_decode( $body, true );
			$data = $json['data'] ?? [];

			$assets = array();
			foreach ( $data as $image ) {
				$asset = $this->uploadFileData( $folderUID, $image['b64_json'], $image_size, $imagePrompt );
				if ( $asset ) {
					$assets[] = $asset;
				}
			}

			return $assets;
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Trace:<br>" . $e->getTraceAsString();
			$message .= "<br><br>Prompt:<br>" . StringHelper::truncateWords( $imagePrompt, 20, '...', true );
			$message .= "<br><br>Model: " . $model;

			throw new Exception( $message );
		}

	}

	private function applyImageStylesToPrompt( $prompt ): array|string {
		$stylesArray = $this->settings->imagesStyles;
		$stylesArray = explode( "\n", $stylesArray );
		$imagePrompt = rtrim( rtrim( $prompt ), '.' );

		if ( ! empty( $stylesArray ) ) {
			$style       = $stylesArray[ array_rand( $stylesArray ) ];
			$imagePrompt .= ', ' . $style;
		}

		$imagePrompt = str_replace( '"', '', $imagePrompt );

		return str_replace( "'", '', $imagePrompt );
	}
}
