<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class OpenAi extends TextApi {
    public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $lang=''): string {
        try {

            $model = $isTranslate?$this->settings->preferredTranslationModel:$this->settings->preferredModel;

            $maxTokens = min( $maxTokens, $this->getMaxTokensForModel( $model ) );
            if($isTranslate && $model!='gpt-4o-mini' && $model!='gpt-4o'){
                $maxTokens = $maxTokens /2;
            }

            $client = new Client();
            $res = $client->request( 'POST', $this->getEndpoint( $model ), [
                'body'    => $this->buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature ),
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

        return $this->getTextGenerationBasedOnModel( $model, $choices );
    }

    private function buildTextGenerationRequestBody($model, $prompt, $maxTokensToGenerate, $temperature = 0.7) {
        $messages = [];

        $systemMessage = $this->settings->systemMessage;
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
