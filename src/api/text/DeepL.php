<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Craft;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class DeepL extends TextApi {
    public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = ''): string {
		try {
			$translateData = $this->_getTranslatedData($prompt,$lang);

            $sendData = [
                'text'       => [$translateData['text']],
                "tag_handling"=>"html",
                "target_lang" => $translateData['lang'],
            ];

            if(!empty($this->settings->deepLGlossaryId) && $source_lang !== 'skip') {
                $source_lang = explode("-",$source_lang)[0];
                $sendData['source_lang'] = $source_lang ?: 'EN';
                $sendData['glossary_id'] = $this->settings->deepLGlossaryId;
            }

            $client = new Client();

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
            Craft::info( 'Result: '.$body, 'content-buddy' );
            if($res->getStatusCode() == 403) {
                Craft::info( 'DeepL ERROR', 'content-buddy' );
                throw new Exception( 'DeepL API key is invalid' );
            } else if(isset($json['message'])) {
				$message = $json['message'];
				Craft::info( 'DeepL ERROR', 'content-buddy' );
				Craft::info( $message, 'content-buddy' );

                // if glossary dictionary is not found, we can try again without it
                if(str_contains($message, 'No dictionary found for language pair')) {
                    return $this->sendRequest($prompt, $maxTokens, $temperature, $isTranslate, $instructions, $lang, 'skip');
                }

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

	private function getEndpoint() : string {
        $api_key = $this->settings->getDeepLApiKey();
        $api_version = $this->settings->getDeepLApiVersion();
        if($api_version == 'v1') {
            return "https://api.deepl.com/v1/translate";
        } else {
            return str_ends_with($api_key,':fx') ? "https://api-free.deepl.com/".$api_version."/translate" : "https://api.deepl.com/".$api_version."/translate";
        }
	}

	private function _getTextGeneration($result) : string {
		return $result ? trim($result['translations'][0]['text']) : '';
	}

	private function _getTranslatedData($prompt,$lang ):array {
		//$text = preg_replace('/^.*?: /', '', $prompt);
		$promptParts = explode(": ",$prompt,2);
		preg_match('/Translate to ([a-zA-Z\-]+),/', $promptParts[0], $promptInstructions);
		$lang = explode("-",$lang)[0];
		$text = $promptParts[1];
		return compact('lang','text');
	}

    public function getGlossaries() : array {
        $client = new Client();
        $res = $client->request('GET', 'https://api.deepl.com/v3/glossaries', [
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key '.$this->settings->getDeepLApiKey(),
                'Content-Type'  => 'application/json'
            ],
            'http_errors'=>false
        ]);
        $body = $res->getBody();
        $json = json_decode($body, true);
        if ($res->getStatusCode() == 403) {
            Craft::info('DeepL ERROR', 'content-buddy');
            throw new Exception('DeepL API key is invalid');
        } elseif (isset($json['message'])) {
            $message = $json['message'];
            Craft::info('DeepL ERROR', 'content-buddy');
            Craft::info($message, 'content-buddy');
            throw new Exception($message);
        }
        $glossaries = [];
        foreach ($json['glossaries'] as $glossary) {
            $glossaries[] = [
                'id' => $glossary['glossary_id'],
                'name' => $glossary['name'],
            ];
        }
        return $glossaries;
    }
}
