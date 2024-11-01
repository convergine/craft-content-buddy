<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class XAi extends TextApi {
    public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false): string {
        try {
            $model = $this->settings->xAiModel;

            $maxTokens = min( $maxTokens, $this->getMaxTokensForModel($model));

            $client = new Client();
            $res = $client->request( 'POST', $this->getEndpoint($model), [
                'body'    => $this->buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature ),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->settings->getXAiApiKey(),
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

        return $this->getTextGenerationBasedOnModel( $model, $choices );
    }

    private function buildTextGenerationRequestBody($model, $prompt, $maxTokensToGenerate, $temperature = 0.7) : string {
        $messages = [];

        $systemMessage = $this->settings->systemMessage;
        if(!empty($systemMessage)) {
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

    private function getTextGenerationBasedOnModel($model, $choices) {
        return trim($choices[0]['message']['content']);
    }

    private function getEndpoint($model): string {
        return 'https://api.x.ai/v1/chat/completions';
    }
}
