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
use yii\helpers\StringHelper;

class Request {

	protected $_settings;

	public function __construct() {
		$this->_settings = BuddyPlugin::getInstance()->getSettings();
	}

	public function send( $prompt, $maxTokens, $temperature,$isTranslate = false ) {
		try {
			$model = $this->_settings->preferredModel;

			$maxTokens = min( $maxTokens, $this->_getMaxTokensForModel( $model ) );
			if($isTranslate){
				$maxTokens = $maxTokens /2;
			}

			$client = new Client();
			$res = $client->request( 'POST', $this->_getEndpoint( $model ), [
				'body'    => $this->_buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature ),
				'headers' => [
					'Authorization' => 'Bearer ' . $this->_settings->getApiKey(),
					'Content-Type'  => 'application/json',
				],
				'http_errors'=>false
			] );

			$body = $res->getBody();
			$json = json_decode( $body, true );
			if(isset($json['error'])){
				$message = $json['error']['message'];

				throw new \Exception( $message );
			}
		} catch ( \Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Prompt:<br>" . StringHelper::truncateWords($prompt,20,'...',true);
			$message .= "<br><br>Model: " . $model;
			$message .= "<br>Max tokens: " . $maxTokens;

			throw new \Exception( $message );
		}

		$choices = $json['choices'];

		return $this->_getTextGenerationBasedOnModel( $model, $choices );
	}

	protected function _getMaxTokensForModel( $model ) {
        return match($model) {
            "gpt-4o-mini" => 15900,
            "gpt-4" => 7900,
            "gpt-4o", "gpt-4-turbo", "gpt-3.5-turbo" => 3900,
            default => 2000
        };
	}

	protected function _buildTextGenerationRequestBody( $model, $prompt, $maxTokensToGenerate, $temperature = 0.7 ) {
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

	protected function _getTextGenerationBasedOnModel( $model, $choices ) {
        return trim( $choices[0]['message']['content'] );
	}

	protected function _getEndpoint( $model ) {
        return 'https://api.openai.com/v1/chat/completions';
	}
}