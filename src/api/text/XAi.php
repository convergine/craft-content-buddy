<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class XAi extends TextApi {
    public function sendRequest($prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = ''): string {
        try {
            $model = $this->settings->xAiModel;

	        // xAI publishes no max-output-token figure for the current Grok models, and
	        // max_tokens is deprecated in favour of max_completion_tokens. Omitting the
	        // cap lets the model generate up to its context length, which is what a
	        // translation needs; content generation still honours the caller's budget.
	        if($isTranslate) {
		        $maxTokens = null;
	        }

            $client = new Client();
            $res = $client->request( 'POST', $this->getEndpoint($model), [
                'body'    => $this->buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature, $isTranslate, $instructions ),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->settings->getXAiApiKey(),
                    'Content-Type'  => 'application/json',
                ],
                'http_errors'=>false
            ] );

            $body = $res->getBody();
            $json = json_decode( $body, true );
            if(isset($json['error'])) {
                $message = $json['error'];
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

    private function buildTextGenerationRequestBody($model, $prompt, $maxTokensToGenerate, $temperature = 0.7, $isTranslate = false, $instructions = '') : string {
        $messages = [];

        $systemMessage = $this->settings->systemMessage;
        if(!empty($systemMessage)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemMessage,
            ];
        }

        if($isTranslate) {
            $systemContent = '';
            if(str_contains($prompt, '</craft-entry>')) {
                $systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags, especially <craft-entry> tags. These tags must remain exactly as they appear in the input. Example: \'<craft-entry data-entry-id="24"></craft-entry>\' should never be modified. Keep the tags in the same order and format as the original text.';
            } else if(preg_match('/<[^>]*>/', $prompt)) {
                $systemContent = 'You are a translator. Do NOT remove, add new, translate, or alter any HTML (this includes <iframe> tags) or custom tags. Keep the tags in the same order and format as the original text.';
            }
            if(!empty($systemContent)) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $systemContent
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
            "temperature" => $temperature,
        ];

        if($maxTokensToGenerate !== null) {
            $body['max_completion_tokens'] = $maxTokensToGenerate;
        }

        if($this->supportsReasoningEffort($model)) {
            $body['reasoning_effort'] = $this->settings->xAiReasoningEffort;
        }

        return json_encode( $body );
    }

    /**
     * reasoning_effort is only accepted by grok-4.3.
     * @see https://docs.x.ai/docs/api-reference
     */
    private function supportsReasoningEffort($model): bool {
        return $model === 'grok-4.3';
    }

    private function getTextGenerationBasedOnModel($model, $choices) {
        return trim($choices[0]['message']['content']);
    }

    private function getEndpoint($model): string {
        return 'https://api.x.ai/v1/chat/completions';
    }
}
