<?php

namespace convergine\contentbuddy\api\image;

use convergine\contentbuddy\api\ImageApi;
use convergine\contentbuddy\BuddyPlugin;
use GuzzleHttp\Client;
use yii\helpers\StringHelper;
use Exception;

class OpenRouter extends ImageApi {
	function sendRequest( $prompt, $folderUID ): array {
		$model      = $this->settings->imageOpenRouterModel;
		$image_size = $this->settings->imageSizeOpenRouter ;

		$client = new Client();

		$imagePrompt = $this->applyImageStylesToPrompt( $prompt );

		// OpenRouter generates images through the OpenAI-compatible chat/completions
		// endpoint: send the prompt as a `messages` array with the `image` output
		// modality; the generated images come back on the assistant message.
		$requestData = [
			'model'      => $model,
			'messages'   => [
				[
					'role'    => 'user',
					'content' => $imagePrompt,
				],
			],
			'modalities' => [ 'image', 'text' ],
		];

		\Craft::info( 'Image OpenRouter request body: ' . json_encode( $requestData ), 'content-buddy' );
		try {
			$imageResponse = $client->request( 'POST', 'https://openrouter.ai/api/v1/chat/completions', [
				'body'    => json_encode( $requestData ),
				'headers'     => [
					'Authorization' => "Bearer {$this->settings->getOpenRouterApiKey()}",
					'Content-Type'  => 'application/json',
				],
				'http_errors' => false,
			] );

			$body = $imageResponse->getBody();
			$json = json_decode( $body, true );
			if ( isset( $json['error'] ) ) {
				throw new Exception( $json['error']['message'] ?? 'OpenRouter image request failed' );
			}
			$data = $json['choices'][0]['message']['images'] ?? [];

			$assets = array();
			foreach ( $data as $image ) {

				$imageData = $image['image_url']['url']??'';
				if($imageData) {
					$asset = $this->uploadFileData( $folderUID, $imageData, $image_size, $imagePrompt );
					if ( $asset ) {
						$assets[] = $asset;
					}
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
		$stylesArray = $this->settings->imageOpenRouterStyles;
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
