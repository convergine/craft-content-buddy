<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Craft;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class OpenAiResponses extends TextApi {
	public function sendRequest( $prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = '' ): string {
		try {

			$model = $isTranslate ? $this->settings->preferredTranslationModel : $this->settings->preferredModel;

			$client = new Client();
			$res    = $client->request( 'POST', $this->getEndpoint( $model ), [
				'body'        => $this->buildTextGenerationRequestBody( $model, $prompt, $isTranslate, $instructions ),
				'headers'     => [
					'Authorization' => 'Bearer ' . $this->settings->getOpenAiApiKey(),
					'Content-Type'  => 'application/json',
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

		$output = $json['output'];
		$text    = $this->getTextGenerationBasedOnModel( $model, $output );

		//check for repeated phrases
		if ( in_array( $lang, [ 'gv', 'gd', 'ga' ] ) ) {
			$normalized = preg_replace( '/[\p{P}]+/u', '', strtolower( $text ) );
			$normalized = preg_replace( '/\s+/', ' ', $normalized );
			if ( preg_match_all( '/(\b(?:\w+\s?){1,6})\s+(\1\s*){2,}/u', $normalized, $matches ) ) {
				Craft::info( 'OpenAI response contains repeated phrases: ' . implode( ', ', $matches[0] ), 'content-buddy' );

				return $this->sendRequest( $prompt, $maxTokens, $temperature, $isTranslate, $instructions, $lang );
			}
		}

		return $text;
	}

	private function buildTextGenerationRequestBody( $model, $prompt, $isTranslate = false, $instructions = '' ) {
		$instructionsArr = [];
		$systemMessage   = $this->settings->systemMessage;
		if ( ! empty( $systemMessage ) ) {
			$instructionsArr[] = $systemMessage;
		}

		if ( $isTranslate ) {
			$systemContent = '';
			if ( str_contains( $prompt, '</craft-entry>' ) ) {
				$systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags, especially <craft-entry> tags. These tags must remain exactly as they appear in the input. Example: \'<craft-entry data-entry-id="24"></craft-entry>\' should never be modified. URLs should never be modified. Keep the tags in the same order and format as the original text.';
			} else if ( preg_match( '/<[^>]*>/', $prompt ) ) {
				$systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags. URLs should never be modified. Keep the tags in the same order and format as the original text.';
			}
			if ( ! empty( $systemContent ) ) {
				$instructionsArr[] = $systemContent;
			}

			if ( str_contains( $prompt, '%20' ) ) {

				$instructionsArr[] = 'Do NOT translate or alter any URLs in the text. Example: \'https://www.example.com/files/This%20Sentence%20Should%20Not%20Be%20Translated.png\' should remain exactly as it appears in the input.';
				$instructionsArr[] = 'Do NOT translate or alter any text surrounded by URL encoding like "%20". Example: \'This%20Sentence%20Should%20Not%20Be%20Translated\' should remain exactly as it appears in the input.';
			}
		}

		if ( ! empty( $instructions ) ) {

			$instructionsArr[] = $instructions;
		}

		$body = [
			'model'        => $model,
			'instructions' => join('\n',$instructionsArr),
			'input'        => $prompt
		];

		return json_encode( $body );
	}

	private function getTextGenerationBasedOnModel( $model, $output ): string {
		$text = '';
		foreach ($output as $key=>$val){
			if($val['type'] =='message' && $val['status']=='completed'){
				foreach ($val['content'] as  $content){
					if($content['type']=='output_text'){
						$text .= $content['text'];
					}

				}
			}
		}
		return trim( $text );
	}

	private function getEndpoint( $model ): string {
		return 'https://api.openai.com/v1/responses';
	}
}
