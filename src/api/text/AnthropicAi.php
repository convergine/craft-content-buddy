<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class AnthropicAi extends TextApi {
	public function sendRequest( $prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = '' ): string {
		try {
			$model = $isTranslate ? $this->settings->anthropicTranslationModel : $this->settings->anthropicModel;

			if ( $isTranslate ) {
				$maxTokens = max( $maxTokens, $this->getMaxTokensForModel( $model ) );
			} else {
				$maxTokens = min( $maxTokens, $this->getMaxTokensForModel( $model ) );
			}

			$client = new Client();
			//TODO
			\Craft::info( 'Anthropic AI request body: ' . $this->buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature, $isTranslate, $instructions ), 'content-buddy' );
			$res = $client->request( 'POST', $this->getEndpoint( $model ), [
				'body'        => $this->buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature, $isTranslate, $instructions ),
				'headers'     => [
					'X-Api-Key'         => $this->settings->getAnthropicApiKey(),
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				],
				'http_errors' => false
			] );

			$body = $res->getBody();
			$json = json_decode( $body, true );
			if ( isset( $json['error'] ) ) {
				$message = $json['error']['message'];
				throw new Exception( $message );
			}
		} catch ( Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Prompt:<br>" . StringHelper::truncateWords( $prompt, 20, '...', true );
			$message .= "<br><br>Model: " . $model;
			$message .= "<br>Max tokens: " . $maxTokens;

			throw new Exception( $message );
		}
		$choices = $json['content'];

		return $this->getTextGenerationBasedOnModel( $model, $choices );
	}

	private function buildTextGenerationRequestBody( $model, $prompt, $maxTokensToGenerate, $temperature = 0.7, $isTranslate = false, $instructions = '' ): string {
		$systemMessages = [[
			'type' => 'text',
			'text' => 'Do not add any details or comments',
		]];

		$data = [
			'model'    => $model,
			'messages' => [
				[
					'role'    => 'user',
					'content' => $prompt,
				]
			],

			"temperature" => $temperature,
			'max_tokens'  => $maxTokensToGenerate,
		];
		$systemMessage = $this->settings->systemMessage;
		if ( ! empty( $systemMessage ) ) {
			$systemMessages[] = [
				'type' => 'text',
				'text' => $systemMessage,
			];
		}

		if ( $isTranslate ) {
			$systemContent = '';
			if ( str_contains( $prompt, '</craft-entry>' ) ) {
				$systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags, especially <craft-entry> tags. These tags must remain exactly as they appear in the input. Example: \'<craft-entry data-entry-id="24"></craft-entry>\' should never be modified. Keep the tags in the same order and format as the original text.';
			} else if ( preg_match( '/<[^>]*>/', $prompt ) ) {
				$systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags. Keep the tags in the same order and format as the original text.';
			}
			if ( ! empty( $systemContent ) ) {
				$systemMessages[] = [
					'type' => 'text',
					'text' => $systemContent
				];
			}
			$data['thinking'] = ['type'=>'disabled'];
		}

		if ( ! empty( $instructions ) ) {
			$systemMessages[] = [
				'type' => 'text',
				'text' => $instructions,
			];
		}

		if ( $systemMessages ) {
			$data['system'] = $systemMessages;
		}

		return json_encode( $data );
	}

	private function getTextGenerationBasedOnModel( $model, $choices ) {
		return trim( $choices[0]['text'] );
	}

	private function getEndpoint( $model ): string {
		return 'https://api.anthropic.com/v1/messages';
	}
}
