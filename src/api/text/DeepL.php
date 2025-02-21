<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Craft;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class DeepL extends TextApi {
	public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $lang=''): string {
		try {
			$translateData = $this->_getTranslatedData($prompt,$lang);
			$client = new Client();
			$sendData = [
				'text'       => [$translateData['text']],
				"tag_handling"=>"html",
				"target_lang" => $translateData['lang'],
			];

			Craft::info( "Translate with DeepL" , 'content-buddy' );
			Craft::info(  StringHelper::truncateWords($prompt,20,'...',true), 'content-buddy' );
			Craft::info( $sendData, 'content-buddy' );

			$res = $client->request( 'POST', $this->getEndpoint( ), [
				'body'    => json_encode($sendData),
				'headers' => [
					'Authorization' => 'DeepL-Auth-Key '.$this->settings->getDeepLApiKey(),
					'Content-Type'  => 'application/json'
				],
				'http_errors'=>false
			] );

			$body = $res->getBody();
			$json = json_decode( $body, true );
			if(isset($json['message'])){
				$message = $json['message'];
				Craft::info( 'DeepL ERROR', 'content-buddy' );
				Craft::info( $message, 'content-buddy' );
				throw new Exception( $message );
			}
		} catch ( Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Send Data:<br>" . print_r($sendData,1);
			$message .= "<br><br>Model: DeepL";

			throw new Exception( $message );
		}

		return $this->_getTextGeneration($json );
	}

	private function getEndpoint() {
		return "https://api-free.deepl.com/v2/translate";
	}

	private function _getTextGeneration( array $result):string {
		return trim( $result['translations'][0]['text'] );
	}

	private function _getTranslatedData($prompt,$lang ):array {
		//$text = preg_replace('/^.*?: /', '', $prompt);
		$promptParts = explode(": ",$prompt,2);
		preg_match('/Translate to ([a-zA-Z\-]+),/', $promptParts[0], $promptInstructions);
		$lang = explode("-",$lang)[0];
		$text = $promptParts[1];
		return compact('lang','text');
	}
}