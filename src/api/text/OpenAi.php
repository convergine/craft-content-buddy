<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Craft;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class OpenAi extends TextApi {
    public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = ''): string {
        try {

            $model = $isTranslate?$this->settings->preferredTranslationModel:$this->settings->preferredModel;

            $maxTokens = min( $maxTokens, $this->getMaxTokensForModel( $model ) );

            Craft::info( "Translate with OpenAI, max tokens: ".$maxTokens , 'content-buddy' );

            $client = new Client();
            $res = $client->request( 'POST', $this->getEndpoint( $model ), [
                'body'    => $this->buildTextGenerationRequestBody($model, $prompt, $maxTokens, $temperature, $isTranslate, $instructions),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->settings->getOpenAiApiKey(),
                    'Content-Type'  => 'application/json',
                ],
                'http_errors'=>false
            ] );

            $body = $res->getBody();
            $json = json_decode( $body, true );
            if(isset($json['error'])){
                $message = $json['error']['message'];

                throw new Exception( $message );
            }
        } catch ( Throwable $e ) {
            $message = $e->getMessage();
            $message .= "<br><br>Prompt:<br>" . StringHelper::truncateWords($prompt,20,'...',true);
            $message .= "<br><br>Model: " . $model;
            $message .= "<br>Max tokens: " . $maxTokens;

            throw new Exception( $message );
        }

        $choices = $json['choices'];
        $text = $this->getTextGenerationBasedOnModel( $model, $choices );

        //check for repeated phrases
        if(in_array($lang,['gv','gd','ga'])) {
            $normalized = preg_replace('/[\p{P}]+/u', '', strtolower($text));
            $normalized = preg_replace('/\s+/', ' ', $normalized);
            if(preg_match_all('/(\b(?:\w+\s?){1,6})\s+(\1\s*){2,}/u', $normalized, $matches)) {
                Craft::info( 'OpenAI response contains repeated phrases: ' . implode(', ', $matches[0]), 'content-buddy');
                return $this->sendRequest($prompt, $maxTokens, $temperature, $isTranslate, $instructions, $lang);
            }
        }

        return $text;
    }

    private function buildTextGenerationRequestBody($model, $prompt, $maxTokensToGenerate, $temperature = 0.7, $isTranslate = false, $instructions = '') {
        $messages = [];

        $systemMessage = $this->settings->systemMessage;
        if ( ! empty( $systemMessage ) ) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemMessage,
            ];
        }

        if($isTranslate) {
            $systemContent = '';
            if(str_contains($prompt, '</craft-entry>')) {
                $systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags, especially <craft-entry> tags. These tags must remain exactly as they appear in the input. Example: \'<craft-entry data-entry-id="24"></craft-entry>\' should never be modified. URLs should never be modified. Keep the tags in the same order and format as the original text.';
            } else if(preg_match('/<[^>]*>/', $prompt)) {
                $systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags. URLs should never be modified. Keep the tags in the same order and format as the original text.';
            }
            if(!empty($systemContent)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $systemContent
                ];
            }

            if(str_contains($prompt, '%20')) {
                $messages[] = [
                    'role' => 'system',
                    'content' => 'Do NOT translate or alter any URLs in the text. Example: \'https://www.example.com/files/This%20Sentence%20Should%20Not%20Be%20Translated.png\' should remain exactly as it appears in the input.'
                ];
                $messages[] = [
                    'role' => 'system',
                    'content' => 'Do NOT translate or alter any text surrounded by URL encoding like "%20". Example: \'This%20Sentence%20Should%20Not%20Be%20Translated\' should remain exactly as it appears in the input.'
                ];
            }
        }

        if(!empty($instructions)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $instructions,
            ];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $prompt,
        ];

        $body = [
            'model'       => $model,
            'messages'    => $messages,
        ];

        if($this->isReasoningModel($model)) {
            $body['max_completion_tokens'] = $maxTokensToGenerate;
        } else {
            $body['max_tokens'] = $maxTokensToGenerate;
            $body['temperature'] = $temperature;
        }

        Craft::info( 'OpenAI request body: ' . json_encode( $body ), 'content-buddy' );

        return json_encode($body);
    }

    private function getTextGenerationBasedOnModel($model, $choices) : string {
        return trim( $choices[0]['message']['content'] );
    }

    private function isReasoningModel($model) : bool {
        return in_array($model, ['o1', 'o1-mini', 'o3-mini']);
    }

    private function getEndpoint($model) : string {
        return 'https://api.openai.com/v1/chat/completions';
    }
}
