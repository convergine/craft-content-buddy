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
use craft\elements\Entry;
use craft\models\VolumeFolder;
use GuzzleHttp\Client;
use craft\elements\Asset;
use Craft;

class ContentGenerator {

	private string $_language;

	public const MIN_EXECUTION_TIME = 600;

	public function generateEntry( $data ) {

		$result                 = [ 'res' => true, 'msg' => '' ];
		$topic                  = $data['buddy_topic'];
		$include_outline        = boolval( $data['buddy_include_outline'] );
		$include_featured_image = boolval( $data['buddy_include_featured_image'] );
		$include_section_images = boolval( $data['buddy_include_section_images'] );
		$featured_field_id      = $data['buddy_featured_field_id'] ?? "";
		$include_tldr           = boolval( $data['buddy_include_tldr'] );
		$include_conclusion     = boolval( $data['buddy_include_conclusion'] );

		$number_of_sections          = intval( $data['buddy_sections'] ) ?? 3;
		$section_max_length_in_words = intval( $data['buddy_max_words'] ?? 1000 );
		$section_max_tokens          = intval( $section_max_length_in_words * 1.33 );
		$temperature                 = $data['temperature'] ?? BuddyPlugin::getInstance()->getSettings()->temperature;
		$number_of_articles          = intval( $data['buddy_number_of_articles'] ?? 1 );
		$seo_keywords                = $data['buddy_seo'] ?? '';
		$prompts                     = $data['buddy-prompts'];
		$folderId                    = $data['buddy_folder_id'] ?? null;
		$this->_language             = $data['buddy_language'] ?? 'en';

		$section           = $data['buddy_entry_section'];
		$site_id           = $data['buddy_entry_site'];
		$description_field = $data['buddy_entry_field'];
		list( $section_id, $type_id ) = explode( ':', $section );

		if ( ! $section_id || ! $type_id || ! $description_field ) {
			return [ 'res' => false, 'msg' => 'Please, select section and description field' ];
		}

		$request = BuddyPlugin::getInstance()->request;

		while ( $number_of_articles > 0 ) {


			$prompt_name_suffix = '';
			if ( ! empty( trim( $seo_keywords ) ) ) {
				$prompt_name_suffix = '-with-seo';
			}

			$content            = '';
			$generated_segments = [];

			try {
				$section_headlines = $request->send(
					$this->_buildPrompt( $prompts[ 'section-headlines' . $prompt_name_suffix ], array(
							'description'         => $topic,
							'number-of-headlines' => $number_of_sections,
							'keywords'            => $seo_keywords,
						)
					), 2000, $temperature );

				$article_intro = $request->send(
					$this->_buildPrompt( $prompts[ 'article-intro' . $prompt_name_suffix ], array(
							'description'       => $topic,
							'section-headlines' => $section_headlines,
							'keywords'          => $seo_keywords,
						)
					), 2000, $temperature );

				$content                             = $this->_addText( $content, $article_intro );
				$generated_segments['article-intro'] = $article_intro;

				$section_headlines_array = $this->_sectionHeadlinesArray( $section_headlines, $number_of_sections );

				if ( $include_outline ) {
					$content                                 = $this->_addOutline( $content, $section_headlines_array );
					$generated_segments['section-headlines'] = $section_headlines_array;
				}

				$title = $request->send(
					$this->_buildPrompt( $prompts[ 'article-title' . $prompt_name_suffix ], array(
							'description'       => $topic,
							'section-headlines' => implode( "\n", $section_headlines_array ),
							'keywords'          => $seo_keywords,
						)
					), 2000, $temperature
				);

				$title = $this->_cleanTitle( $title );

				$section_summaries = [];

				foreach ( $section_headlines_array as $headline ) {
					$section_content = $request->send(
						$this->_buildPrompt( $prompts[ 'section' . $prompt_name_suffix ], array(
								'description'      => $topic,
								'section-headline' => $headline,
								'keywords'         => $seo_keywords,
							)
						), $section_max_tokens, $temperature );

					if ( $include_tldr ) {
						$section_summaries[] = $request->send(
							$this->_buildPrompt( $prompts[ 'section-summary' . $prompt_name_suffix ], array(
									'section'  => $section_content,
									'keywords' => $seo_keywords,
								)
							), 2000, $temperature );
					}

					$content = $this->_addSubtitle( $content, $headline );

					if ( $include_section_images ) {
						$section_image = $request->send(
							$this->_buildPrompt( $prompts['image'], array(
									'text' => $section_content,
								)
							), 2000, $temperature );

						$imageService = new Image();
						$assets  = $imageService->generate( $section_image, $folderId );
						$content = $this->_addImages( $content, $assets );
					}

					$content                                      = $this->_addText( $content, $section_content );
					$generated_segments[ 'section-' . $headline ] = $section_content;
				}

				if ( $include_conclusion ) {
					$article_conclusion = $request->send(
						$this->_buildPrompt( $prompts[ 'article-conclusion' . $prompt_name_suffix ], array(
								'description'       => $topic,
								'section-headlines' => implode( "\n", $section_headlines_array ),
								'keywords'          => $seo_keywords,
							)
						), 2000, $temperature );

					$content                                  = $this->_addText( $content, $article_conclusion );
					$generated_segments['article-conclusion'] = $article_conclusion;
				}

				if ( $include_tldr ) {
					$text = implode( "\n", $section_summaries );

					$tldr_for_all_sections = $request->send(
						$this->_buildPrompt( $prompts[ 'tldr' . $prompt_name_suffix ], array(
								'text'     => $text,
								'keywords' => $seo_keywords,
							)
						), 2000, $temperature );

					$content                    = $this->_prependText( $content, $tldr_for_all_sections );
					$generated_segments['tldr'] = $tldr_for_all_sections;
				}

				$assetfield = null;
				if ( $include_featured_image && ! empty( $featured_field_id ) ) {
					$folder = $this->_getFolderId( $featured_field_id );
					if ( $folder ) {
						$featured_image = $request->send(
							$this->_buildPrompt( $prompts['image'], array(
									'text' => $article_intro,
								)
							), 2000, $temperature );

						$imageService = new Image();
						$featured_assets  = $imageService->generate( $featured_image, $folder->id );
						if ( $featured_assets ) {
							$assetfield = [ 'asset' => $featured_assets[0], 'handle' => $featured_field_id ];
						}

					}
				}

				$entry = $this->_createEntry(
					$title,
					$content,
					$section_id,
					$type_id,
					$description_field,
					$site_id,
					Craft::$app->user->getId(),
					$assetfield
				);
				if ( $entry ) {
					$result['msg'] .= '<li><a href="' . $entry->getCpEditUrl() . '" target="_blank">Entry generated</a></li>';
				}

			} catch ( \Throwable $e ) {
				$message       = $e->getMessage();
				$result['res'] = false;
				$result['msg'] = $message;
				return $result;
			}
			$number_of_articles --;
		}

		return $result;
	}

	/*public function sendRequest( $prompt, $maxTokens, $temperature ) {
		try {
			$model = BuddyPlugin::getInstance()->getSettings()->preferredModel;

			$maxTokens = min( $maxTokens, $this->_getMaxTokensForModel( $model ) );

			$client = new Client();
			$res = $client->request( 'POST', $this->_getEndpoint( $model ), [
				'body'    => $this->_buildTextGenerationRequestBody( $model, $prompt, $maxTokens, $temperature ),
				'headers' => [
					'Authorization' => 'Bearer ' . BuddyPlugin::getInstance()->getSettings()->getApiKey(),
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
			}
			$message .= "<br><br>Prompt: " . $prompt;
			$message .= "<br>Model: " . $model;
			$message .= "<br>Max tokens: " . $maxTokens;

			throw new \Exception( $message );
		}

		$choices = $json['choices'];

		return $this->_getTextGenerationBasedOnModel( $model, $choices );
	}*/



	/*protected function _getEndpoint( $model ) {
		if ( $this->isNewApi($model) ) {
			return 'https://api.openai.com/v1/chat/completions';
		}

		return 'https://api.openai.com/v1/completions';
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

			$systemMessage = BuddyPlugin::getInstance()->getSettings()->systemMessage;
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

	protected function _getTextGenerationBasedOnModel( $model, $choices ) {
		if ( $this->isNewApi($model) ) {
			return trim( $choices[0]['message']['content'] );
		}

		return trim( $choices[0]['text'] );
	}*/

	private function _buildPrompt( $prompt, $keyValueArray ) {
		foreach ( $keyValueArray as $key => $value ) {
			$prompt = str_replace( '[[' . $key . ']]', $value, $prompt );
		}

		return "Using the locale {$this->_language} " . $prompt;
	}

	private function _addText( $content, $text ) {
		// divide text into paragraphs
		$paragraphs = explode( "\n", $text );

		foreach ( $paragraphs as $paragraph ) {
			if ( empty( trim( $paragraph ) ) ) {
				continue;
			}

			$content .= '<p>' . htmlentities( $paragraph ) . '</p>';
		}

		return $content;
	}

	private function _sectionHeadlinesArray( $section_headlines, $number_of_sections ) {
		$section_headlines = explode( "\n", $section_headlines );
		$section_headlines = array_filter( $section_headlines, function ( $headline ) {
			return strlen( $headline ) > 0;
		} );
		$section_headlines = array_slice( $section_headlines, 0, $number_of_sections );

		return $section_headlines;
	}

	private function _cleanTitle( $title ) {
		// remove " and ' from the beginning and end of the title
		$title = trim( $title, '"' );

		return trim( $title, "'" );

	}

	private function _generateLinkAnchorId( $headline ) {
		return strtolower( trim(str_ireplace( [' ',"'",'"','\r','\n'], ['-','','','',''], $headline )) );
	}

	private function _addSubtitle( $content, $subtitle ) {
		$id = $this->_generateLinkAnchorId( $subtitle );
		return $content . '<h2 id="' . $id . '">' . htmlentities( $subtitle ) . '</h2>';
	}

	private function _prependText( $content, $text ) {
		// divide text into paragraphs
		$paragraphs = explode( "\n", $text );

		$text_to_prepend = '';
		foreach ( $paragraphs as $paragraph ) {
			if ( empty( trim( $paragraph ) ) ) {
				continue;
			}

			$text_to_prepend .= '<p>' . htmlentities( $paragraph ) . '</p>';
		}

		return $text_to_prepend . $content;
	}

	private function _addOutline( $content, $section_headlines ) {
		// add section_headlines in an ul list to the content
		$ul = '<ul>';
		foreach ( $section_headlines as $headline ) {
			$id = $this->_generateLinkAnchorId( $headline );
			$ul .= '<li>' . '<a href="#' . $id . '">' . htmlentities( $headline ) . '</a>' . '</li>';
		}

		$ul .= '</ul>';

		return $content . $ul;
	}

	private function _createEntry( $title, $content, $sectionId, $typeId, $field, $siteId, $author, $asset ) {
		$newEntry            = new Entry();
		$newEntry->siteId    = $siteId;
		$newEntry->sectionId = $sectionId;
		$newEntry->typeId    = $typeId;
		$newEntry->authorId  = $author;

		$newEntry->title = $title;
		$newEntry->setFieldValue( $field, $content );

		if ( $asset ) {

			$newEntry->setFieldValue( $asset['handle'], [ $asset['asset']->id ] );
		}

		$newEntry->setIsFresh( true );

		return Craft::$app->getDrafts()->saveElementAsDraft( $newEntry ) ? $newEntry : false;
	}



	private function _addImages( $content, $images ) {
		foreach ( $images as $image ) {
            $content .= "<figure><img src='" . $image->getUrl() . "' /></figure>";
		}

		return $content;
	}

	private function _getFolderId( $featured_field_id ): bool|VolumeFolder {
		$volume = Craft::$app->getFields()->getFieldByHandle( $featured_field_id );

		if ( $volume->sources == '*' ) {
			$_volumes = Craft::$app->getVolumes()->getAllVolumes();

			if ( $_volumes ) {
				$folder = Craft::$app->getAssets()->getRootFolderByVolumeId( $_volumes[0]->id );

				return $folder;
			}
		} elseif ( is_array( $volume->sources ) ) {
			$source    = $volume->sources[0];
			$volumeUID = str_replace( "volume:", '', $source );
			$volume    = Craft::$app->getVolumes()->getVolumeByUid( $volumeUID );
			$folder    = Craft::$app->getAssets()->getRootFolderByVolumeId( $volume->id );

			return $folder;

		}

		return false;
	}

	public static function getTimeLimitAlert(){
		$current_time = (int)trim(ini_get('max_execution_time'));
		if($current_time < self::MIN_EXECUTION_TIME) {
			return "<ul class='buddy-alert error' style='display:block;'><li>"
			       . Craft::t(
					'convergine-contentbuddy',
					'To avoid time-out errors, we strongly recommend changing your server <strong>max_execution_time</strong> to {preferred_time} (Current value: {current_time} seconds)',
					[ 'preferred_time' => self::MIN_EXECUTION_TIME, 'current_time' => $current_time] )
			       . "</li></ul>";
		}
		return '';
	}

	public function isNewApi($model){
		return strpos( $model, 'gpt-3.5-turbo' ) === 0 || strpos( $model, 'gpt-4' ) === 0 ;
	}
}