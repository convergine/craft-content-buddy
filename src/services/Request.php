<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 5/9/2023
 * Time: 2:59 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use GuzzleHttp\Client;
use Craft;

class Request {

	protected $_settings;

	public function __construct() {
		$this->_settings = BuddyPlugin::getInstance()->getSettings();
	}

	public function send( $prompt, $maxTokens, $temperature ) {
		try {
			$model = $this->_settings->preferredModel;

			$maxTokens = min( $maxTokens, $this->_getMaxTokensForModel( $model ) );

			$client = new Client();
			$res = $client->request( 'POST', $this->_getEndpoint( $model ), [
				'body'    => $this->_buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature ),
				'headers' => [
					'Authorization' => 'Bearer ' . $this->_settings->getApiKey(),
					'Content-Type'  => 'application/json',
				],
			] );

			$body = $res->getBody();
			$json = json_decode( $body, true );
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			if(strpos($message,'502 Bad Gateway')!=false || strpos($message,'500 Internal Server Error')!=false){
				$message = Craft::t('convergine-contentbuddy', 'badGatewayError');
			}elseif (strpos($message,'429 Too Many Requests')!=false){
				$message = Craft::t('convergine-contentbuddy', 'tooManyRequestsError');
			}elseif (strpos($message,'400 Bad Request')!=false){
				$message = Craft::t('convergine-contentbuddy', 'badRequestError');
			}elseif (strpos($message,'401 Unauthorized')!=false){
				$message = Craft::t('convergine-contentbuddy', 'unauthorizedError');
			}
			$message .= "<br><br>Prompt: " . $prompt;
			$message .= "<br>Model: " . $model;
			$message .= "<br>Max tokens: " . $maxTokens;

			throw new \Exception( $message );
		}

		$choices = $json['choices'];

		return $this->_getTextGenerationBasedOnModel( $model, $choices );
	}

	protected function _getMaxTokensForModel( $model ) {
		if ( $model == 'text-davinci-002' || $model == 'text-davinci-003' || strpos( $model, 'gpt-3.5-turbo' ) === 0 ) {
			return 4000;
		}

		if ( strpos( $model, 'gpt-4-32k' ) === 0 ) {
			return 32000;
		}

		if ( strpos( $model, 'gpt-4' ) === 0 ) {
			return 8000;
		}

		return 2000;
	}

	protected function _buildTextGenerationRequestBody( $model, $prompt, $maxTokensToGenerate, $temperature = 0.7 ) {
		if ( $this->isNewApi($model) ) {
			$messages = [];

			$systemMessage = $this->_settings->systemMessage;
			if ( ! empty( $systemMessage ) ) {
				$messages[] = [
					'role'    => 'system',
					'content' => $systemMessage,
				];
			}

			$messages[] = [
				'role'    => 'user',
				'content' => $prompt,
			];

			return json_encode( [
				'model'       => $model,
				'messages'    => $messages,
				"temperature" => $temperature,
				'max_tokens'  => $maxTokensToGenerate,
			] );
		}

		return json_encode( [
			'model'       => $model,
			'prompt'      => $prompt,
			"temperature" => $temperature,
			'max_tokens'  => $maxTokensToGenerate,
		] );
	}

	public function isNewApi($model){
		return strpos( $model, 'gpt-3.5-turbo' ) === 0 || strpos( $model, 'gpt-4' ) === 0 ;
	}

	protected function _getTextGenerationBasedOnModel( $model, $choices ) {
		if ( $this->isNewApi($model) ) {
			return trim( $choices[0]['message']['content'] );
		}

		return trim( $choices[0]['text'] );
	}

	protected function _getEndpoint( $model ) {
		if ( $this->isNewApi($model) ) {
			return 'https://api.openai.com/v1/chat/completions';
		}

		return 'https://api.openai.com/v1/completions';
	}
}