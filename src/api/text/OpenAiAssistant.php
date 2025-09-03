<?php

namespace convergine\contentbuddy\api\text;

use convergine\contentbuddy\api\TextApi;
use Craft;
use Exception;
use GuzzleHttp\Client;
use Throwable;
use yii\helpers\StringHelper;

class OpenAiAssistant extends TextApi {
	public function sendRequest( $prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang = '', $source_lang = '' ): string {
		try {

			Craft::info( 'Start processing translation with OpenAi assistant', 'content-buddy' );
			Craft::info( 'Prompt: ' . $prompt, 'content-buddy' );

			$assistantId = $this->settings->openAiAssistantId;

			$message = [
				'role'    => 'user',
				'content' => $prompt,
			] ;
			// Step 1: Create thread and add message
			$threadId = $this->createThread($message);
			Craft::info( 'Create new threadId: ' . $threadId, 'content-buddy' );

			// Step 2: Run the assistant on the thread
			$runId = $this->_runAssistant( $assistantId, $threadId );

			// Step 3: Poll for run completion
			$this->_waitForRunCompletion( $threadId, $runId );

			// Step 4: Get messages (responses)
			$text = $this->_getThreadMessages( $threadId, $runId );

		} catch ( Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Prompt:<br>" . StringHelper::truncateWords( $prompt, 20, '...', true );
			$message .= "<br>Max tokens: " . $maxTokens;

			throw new Exception( $message );
		}

		return $text;
	}

	public function getAssistants() {
		return $this->_openaiGet( '/assistants?limit=40' );
	}

	public function createThread($message) {
		$response = $this->_openaiPost( '/threads', [
			'messages'=>[$message]
		] );

		return $response['id'];
	}

	private function _addMessageToThread( $threadId, $content ) {
		$this->_openaiPost( "/threads/{$threadId}/messages", [
			'role'    => 'user',
			'content' => $content,
		] );
	}

	private function _runAssistant( $assistantId, $threadId ) {
		$response = $this->_openaiPost( "/threads/{$threadId}/runs", [
			'assistant_id' => $assistantId,
		] );

		return $response['id'];
	}

	private function _waitForRunCompletion( $threadId, $runId ) {
		$status = 'in_progress';
		$try    = 1;
		$sleep  = 0;
		while ( in_array( $status, [ 'queued', 'in_progress' ] ) ) {
			$_sleep = 1;
			if ( $try == 3 ) {
				$_sleep = 2;
			} elseif ( $try > 4 ) {
				$_sleep = $try * 3;
			}

			sleep( $_sleep );
			$sleep += $_sleep;
			$run   = $this->_openaiGet( "/threads/{$threadId}/runs/{$runId}" );
			Craft::info( "Run Completion #{$try}: " . print_r( array_intersect_key( $run, array_flip(
					[
						"id",
						"object",
						"created_at",
						"assistant_id",
						"thread_id",
						"status",
						"started_at",
						"expires_at"
					]
				) ), 1 ), 'content-buddy' );
			$status = $run['status'];
			$try ++;
			if ( $try > 5 && in_array( $status, [ 'queued', 'in_progress' ] ) ) {
				throw new Exception( "No Completion after {$try} trying and {$sleep} seconds" );
			}
		}
	}

	private function _getThreadMessages( $threadId, $runId='' ) {
		$endpoint = "/threads/{$threadId}/messages";
		if($runId){
			$endpoint .="?run_id={$runId}";
		}
		$response = $this->_openaiGet( $endpoint );
		$messages = $response['data'];
		Craft::info( 'Got last massages', 'content-buddy' );
		//Craft::info( 'Got last massages: '.print_r($messages,1), 'content-buddy');
		foreach ( array_reverse( $messages ) as $message ) {
			if ( $message['role'] === 'assistant' ) {
				Craft::info( 'Got last massage: ' . print_r( $message, 1 ), 'content-buddy' );

				return $message['content'][0]['text']['value'];
			}
		}

		return null;
	}

	private function _openaiPost( $endpoint, $data ) {
		return $this->_openaiRequest( 'POST', $endpoint, $data );
	}

	private function _openaiGet( $endpoint ) {
		return $this->_openaiRequest( 'GET', $endpoint );
	}

	private function _openaiRequest( $method, $endpoint, $data = [] ) {

		try {
			$client  = new Client();
			$options = [
				'headers'     => [
					'Authorization' => 'Bearer ' . $this->settings->getOpenAiApiKey(),
					'Content-Type'  => 'application/json',
					'OpenAI-Beta'   => 'assistants=v2',
				],
				'http_errors' => false
			];
			if ( $method == 'POST' ) {
				$options['body'] = json_encode( $data );
			}
			$res  = $client->request( $method, $this->getEndpoint( $endpoint ), $options );
			$body = $res->getBody();
			$json = json_decode( $body, true );
			if ( isset( $json['error'] ) ) {
				$message = $json['error']['message'];

				throw new Exception( $message );
			}
		} catch ( Throwable $e ) {
			$message = $e->getMessage();
			$message .= "<br><br>Endpoit:<br>" . $endpoint;
			$message .= "<br><br>Data: " . print_r( $data, true );

			throw new Exception( $message );
		}

		return $json;
	}

	private function getEndpoint( $endpoint ): string {
		return 'https://api.openai.com/v1' . $endpoint;
	}

}
