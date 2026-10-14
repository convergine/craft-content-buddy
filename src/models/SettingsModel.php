<?php

namespace convergine\contentbuddy\models;

use convergine\contentbuddy\records\SettingsRecord;
use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\services\ProjectConfig;

class SettingsModel extends Model {

	/**
	 * OpenAI models offered for preferredModel and preferredTranslationModel.
	 * Keep in sync with the dropdowns in settings/_api.twig and settings/_translation.twig.
	 */
	public const OPENAI_MODELS = [
		'gpt-5.6-sol',
		'gpt-5.6-terra',
		'gpt-5.6-luna',
		'gpt-5.1',
		'gpt-4.1',
		'gpt-4.1-mini',
		'gpt-4o',
		'gpt-4o-mini',
	];

	/**
	 * Retired OpenAI models and OpenAI's named replacement for each.
	 * gpt-4.5-preview (2025-07-14), gpt-5.1-codex and gpt-5.1-codex-mini (2026-07-23) are already shut down;
	 * gpt-3.5-turbo, gpt-4, gpt-4-turbo, gpt-4.1-nano, o1 and o3-mini shut down on 2026-10-23;
	 * gpt-5-nano on 2026-12-11.
	 *
	 * @see https://developers.openai.com/api/docs/deprecations
	 */
	public const RETIRED_OPENAI_MODELS = [
		'gpt-3.5-turbo'      => 'gpt-5.6-terra',
		'gpt-4'              => 'gpt-5.6-sol',
		'gpt-4-turbo'        => 'gpt-5.6-sol',
		'gpt-4.1-nano'       => 'gpt-5.6-luna',
		'gpt-4.5-preview'    => 'gpt-5.6-sol',
		'o1'                 => 'gpt-5.6-sol',
		'o3-mini'            => 'gpt-5.6-sol',
		'gpt-5.1-codex'      => 'gpt-5.6-sol',
		'gpt-5.1-codex-mini' => 'gpt-5.6-terra',
		'gpt-5-nano'         => 'gpt-5.6-luna',
	];

	public const DEFAULT_OPENAI_MODEL = 'gpt-5.6-terra';

	/**
	 * Maps a saved OpenAI model to one the settings still offer: retired models to their
	 * replacement, anything else unknown (e.g. gpt-5.1-mini, which never existed) to the default.
	 */
	public static function normalizeOpenAiModel( string $model ): string {
		if ( in_array( $model, self::OPENAI_MODELS, true ) ) {
			return $model;
		}

		return self::RETIRED_OPENAI_MODELS[ $model ] ?? self::DEFAULT_OPENAI_MODEL;
	}

	/**
	 * OpenAI image models offered for imageOpenai5Model. Keep in sync with settings/_image.twig.
	 */
	public const OPENAI_IMAGE_MODELS = [ 'gpt-image-2.5-flare', 'gpt-image-2.5-sunburst' ];

	/**
	 * gpt-image-1 shuts down on 2026-10-23, gpt-image-1-mini on 2026-12-01.
	 *
	 * @see https://developers.openai.com/api/docs/deprecations
	 */
	public const RETIRED_OPENAI_IMAGE_MODELS = [
		'gpt-image-1'      => 'gpt-image-2.5-sunburst',
		'gpt-image-1-mini' => 'gpt-image-2.5-flare',
	];

	public const DEFAULT_OPENAI_IMAGE_MODEL = 'gpt-image-2.5-flare';

	/**
	 * Moves saved image settings off retired OpenAI image models.
	 * DALL·E 2 and 3 were shut down on 2026-05-12, so the DALL·E provider ('openai', or the older
	 * 'openai-dall-e-2') switches to the GPT Image provider, keeping the orientation of the DALL·E 3 size.
	 *
	 * @param array $settings Saved values by setting name; missing names fall back to the defaults.
	 *
	 * @return array Only the settings that change.
	 */
	public static function retireOpenAiImageSettings( array $settings ): array {
		$wanted = [];

		if ( in_array( $settings['imageModel'] ?? null, [ 'openai', 'openai-dall-e-2' ], true ) ) {
			$dalle3Size = ( $settings['dalleModel'] ?? 'dall-e-3' ) === 'dall-e-3' ? ( $settings['imageSizeDalle3'] ?? '' ) : '';

			$wanted['imageModel']       = 'openai5';
			$wanted['imageSizeOpenai5'] = match ( $dalle3Size ) {
				'1024x1792' => '1024x1536',
				'1792x1024' => '1536x1024',
				default     => '1024x1024',
			};
			// A GPT Image model saved next to DALL·E was never chosen, only posted with the form.
			$wanted['imageOpenai5Model'] = self::DEFAULT_OPENAI_IMAGE_MODEL;
		} elseif ( isset( $settings['imageOpenai5Model'] ) && ! in_array( $settings['imageOpenai5Model'], self::OPENAI_IMAGE_MODELS, true ) ) {
			$wanted['imageOpenai5Model'] = self::RETIRED_OPENAI_IMAGE_MODELS[ $settings['imageOpenai5Model'] ] ?? self::DEFAULT_OPENAI_IMAGE_MODEL;
		}

		return array_filter( $wanted, fn( $value, $name ) => $value !== ( $settings[ $name ] ?? null ), ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * @var string
	 */
	public string $apiToken = '';

	/**
	 * @var string
	 */
	public string $textAi = 'openai';

	/**
	 * @var string
	 */
	public string $translationAi = 'openai';

	/**
	 * @var string
	 */
	public string $openAiAssistantId = '';
	public string $openAiAssistantThreadId = '';

	/**
	 * @var string
	 */
	public string $preferredModel = self::DEFAULT_OPENAI_MODEL;

	/**
	 * @var string
	 */
	public string $preferredTranslationModel = self::DEFAULT_OPENAI_MODEL;

	/**
	 * OpenAI Vector Store IDs for the file_search tool when generating text.
	 * Comma- or whitespace-separated; may be an environment variable reference.
	 * @var string
	 */
	public string $openAiVectorStoreIds = '';

	/**
	 * OpenAI Vector Store IDs for the file_search tool when translating.
	 * Comma- or whitespace-separated; may be an environment variable reference.
	 * @var string
	 */
	public string $openAiTranslationVectorStoreIds = '';

	/**
	 * @var string
	 */
	public string $deepLApiKey = '';

	/**
	 * @var string
	 */
	public string $deepLApiVersion = 'v2';

	/**
	 * @var string
	 */
	public string $deepLGlossaryId = '';

	/**
	 * @var string
	 */
	public string $xAiModel = 'grok-4.3';

	/**
	 * @var string Only sent for grok-4.3: none|low|medium|high
	 */
	public string $xAiReasoningEffort = 'low';

	/**
	 * @var string
	 */
	public string $xAiApiKey = '';

	/**
	 * @var double
	 */
	public float $temperature = 0.7;

	/**
	 * @var float|int
	 */
	public float $frequencyPenalty = 0;

	/**
	 * @var float|int
	 */
	public float $presencePenalty = 0;

	/**
	 * @var array
	 */
	public array $enabledFields = [];

	/**
	 * @var bool
	 */
	public bool $usePageLang = true;

	/**
	 * @var string
	 */
	public string $imagesStyles = 'colourful';

	/**
	 * @var string
	 */
	public string $imageSize = '512x512';

	/**
	 * @var string
	 */
	public string $imageSizeDalle3 = '1024x1024';

	/**
	 * @var string
	 */
	public string $imageSizeStability = '512x512';

	/**
	 * @var string
	 */
	public string $imageSizeOpenai5 = '1024x1024';

	/**
	 * @var string
	 */
	public string $systemMessage = '';

	private array $_supportedFieldTypes = [
		'craft\fields\PlainText',
		'craft\redactor\Field',
		'craft\ckeditor\Field',
		'abmat\tinymce\Field'
	];

	public string $imageModel = 'openai5';

	public string $dalleModel = 'dall-e-3';
	public string $imageOpenai5Model = self::DEFAULT_OPENAI_IMAGE_MODEL;

	// stability ai options

	public string $stabilityAPIKey = '';

	public string $stabilityEngine = 'sd3.5-large';

	public string $stabilitySampler = 'DDIM';

	public int $stabilitySteps = 50;

	public int $stabilityScale = 7;

	public string $stabilityStyle = 'enhance';

	// Generate image based on text

	public bool $generateImageFromText = false;

	public string $generateImageAssetId = '';

	public bool $enableTranslationMenu = true;

	public bool $translateSlugs = false;

	//Enable bulk translation to all languages at once
	public bool $enableBulkTranslation = false;

	public int $delayLanguage = 30;
	public int $delayEntry = 10;
	public int $delaySection = 10;

	public int $maxAttempts = 5;
	public int $ttr = 300;

	public string $anthropicModel = 'claude-sonnet-4-5-20250929';
	public string $anthropicApiKey = '';
	public string $anthropicTranslationModel = 'claude-sonnet-4-5-20250929';

	public string $openrouterModel = '';
	public string $openrouterApiKey = '';
	public string $openrouterTranslationModel = '';

	public string $apiOpenRouteImageApiToken = '';

	public string $imageOpenRouterModel = '';
	public string $imageOpenRouterStyles = '';
	public string $imageSizeOpenRouter = '1:1';

	public function init(): void {
		parent::init();
		$this->_getSettingsFromDb();
	}

	/**
	 * @return string
	 */
	public function getOpenAiApiKey(): string {
		return App::parseEnv( $this->apiToken );
	}

	/**
	 * @return string
	 */
	public function getXAiApiKey(): string {
		return App::parseEnv( $this->xAiApiKey );
	}

	/**
	 * @return string
	 */
	public function getAnthropicApiKey(): string {
		return App::parseEnv( $this->anthropicApiKey );
	}

	/**
	 * @return string
	 */
	public function getDeepLApiKey(): string {
		return App::parseEnv( $this->deepLApiKey );
	}

	/**
	 * @return string
	 */
	public function getDeepLApiVersion(): string {
		return $this->deepLApiVersion;
	}

	/**
	 * @return string
	 */
	public function getStabilityApiKey(): string {
		return App::parseEnv( $this->stabilityAPIKey );
	}

	/**
	 * @return string
	 */
	public function getOpenRouterApiKey(): string {
		return App::parseEnv( $this->openrouterApiKey );
	}

	/**
	 * @return string[]
	 */
	public function getOpenAiVectorStoreIds( bool $isTranslate ): array {
		$raw = $isTranslate ? $this->openAiTranslationVectorStoreIds : $this->openAiVectorStoreIds;

		return self::parseVectorStoreIds( (string) App::parseEnv( $raw ) );
	}

	/**
	 * @return string[]
	 */
	public static function parseVectorStoreIds( string $raw ): array {
		$ids = preg_split( '/[\s,]+/', $raw, - 1, PREG_SPLIT_NO_EMPTY );

		return array_values( array_unique( $ids ) );
	}

	public function titleFieldEnabled() {
		return isset( $this->enabledFields['title'] ) && $this->enabledFields['title'];
	}

	public function getRegularFieldsList(): array {
		$isCraft5 = version_compare( Craft::$app->getInfo()->version, '5.0', '>=' );

		$fields = [];

		foreach ( \Craft::$app->getFields()->getAllFields() as $field ) {

			if ( in_array( ( new \ReflectionClass( $field ) )->getName(), $this->_supportedFieldTypes ) ) {
				$field_obj = [
					'id'     => $field->id,
					'uid'    => $field->uid,
					'handle' => $field->handle,
					'name'   => $field->name,
					'type'   => $this->_getClass( $field )
				];
				if ( ! $isCraft5 ) {
					$field_obj['group'] = $field->getGroup()->name;
				}
				$fields[] = $field_obj;
			}

		}

		return $fields;
	}

	public function getMatrixFieldsList(): array {
		if ( version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ) {
			return array();
		}
		$matrixFields = [];
		foreach ( \Craft::$app->getFields()->getFieldsByType( 'craft\fields\Matrix' ) as $matrixField ) {
			$matrixFields [ $matrixField->handle ]['name']   = $matrixField->name;
			$matrixFields [ $matrixField->handle ]['fields'] = [];
			foreach ( \Craft::$app->getMatrix()->getBlockTypesByFieldId( $matrixField->id ) as $block ) {
				foreach ( \Craft::$app->getFields()->getAllFields( "matrixBlockType:" . $block->uid ) as $blockField ) {
					if ( in_array( ( new \ReflectionClass( $blockField ) )->getName(), $this->_supportedFieldTypes ) ) {
						$matrixFields [ $matrixField->handle ]['fields'][] = [
							'id'     => $blockField->id,
							'uid'    => $blockField->uid,
							'handle' => $blockField->handle,
							'name'   => $blockField->name,
							'group'  => $block->name,
							'type'   => $this->_getClass( $blockField )
						];
					}
				}
			}
		}

		return $matrixFields;
	}

	public function isAvailableForFields(): bool {
		return ( ( $this->textAi == 'openai' && $this->apiToken )
		         || ( $this->textAi == 'xai' && $this->xAiApiKey )
		         || ( $this->textAi == 'anthropic' && $this->anthropicApiKey )
		         || ( $this->textAi == 'openrouter' && $this->openrouterApiKey)
		);

	}

	protected function _getClass( $object ): string {
		return str_replace( [
			'craft\fields\\',
			'craft\redactor\Field',
			'craft\ckeditor\Field'
		],
			[
				'',
				'Redactor',
				'CK Editor'
			], ( new \ReflectionClass( $object ) )->getName() );
	}

	public function getLanguages() {
		return [
			'en' => Craft::t( 'convergine-contentbuddy', 'English' ) . ' (English)',
			'de' => Craft::t( 'convergine-contentbuddy', 'German' ) . ' (Deutsch)',
			'fr' => Craft::t( 'convergine-contentbuddy', 'French' ) . ' (Français)',
			'es' => Craft::t( 'convergine-contentbuddy', 'Spanish' ) . ' (Español)',
			'it' => Craft::t( 'convergine-contentbuddy', 'Italian' ) . ' (Italiano)',
			'pt' => Craft::t( 'convergine-contentbuddy', 'Portuguese' ) . ' (Português)',
			'nl' => Craft::t( 'convergine-contentbuddy', 'Dutch' ) . ' (Nederlands)',
			'pl' => Craft::t( 'convergine-contentbuddy', 'Polish' ) . ' (Polski)',
			'ru' => Craft::t( 'convergine-contentbuddy', 'Russian' ) . ' (Русский)',
			'ja' => Craft::t( 'convergine-contentbuddy', 'Japanese' ) . ' (日本語)',
			'zh' => Craft::t( 'convergine-contentbuddy', 'Chinese' ) . ' (中文)',
			'br' => Craft::t( 'convergine-contentbuddy', 'Brazilian Portuguese' ) . ' (Português Brasileiro)',
			'tr' => Craft::t( 'convergine-contentbuddy', 'Turkish' ) . ' (Türkçe)',
			'ar' => Craft::t( 'convergine-contentbuddy', 'Arabic' ) . ' (العربية)',
			'ko' => Craft::t( 'convergine-contentbuddy', 'Korean' ) . ' (한국어)',
			'hi' => Craft::t( 'convergine-contentbuddy', 'Hindi' ) . ' (हिन्दी)',
			'id' => Craft::t( 'convergine-contentbuddy', 'Indonesian' ) . ' (Bahasa Indonesia)',
			'sv' => Craft::t( 'convergine-contentbuddy', 'Swedish' ) . ' (Svenska)',
			'da' => Craft::t( 'convergine-contentbuddy', 'Danish' ) . ' (Dansk)',
			'fi' => Craft::t( 'convergine-contentbuddy', 'Finnish' ) . ' (Suomi)',
			'no' => Craft::t( 'convergine-contentbuddy', 'Norwegian' ) . ' (Norsk)',
			'ro' => Craft::t( 'convergine-contentbuddy', 'Romanian' ) . ' (Română)',
			'ka' => Craft::t( 'convergine-contentbuddy', 'Georgian' ) . ' (ქართული)',
			'vi' => Craft::t( 'convergine-contentbuddy', 'Vietnamese' ) . ' (Tiếng Việt)',
			'hu' => Craft::t( 'convergine-contentbuddy', 'Hungarian' ) . ' (Magyar)',
			'bg' => Craft::t( 'convergine-contentbuddy', 'Bulgarian' ) . ' (Български)',
			'el' => Craft::t( 'convergine-contentbuddy', 'Greek' ) . ' (Ελληνικά)',
			'fa' => Craft::t( 'convergine-contentbuddy', 'Persian' ) . ' (فارسی)',
			'sk' => Craft::t( 'convergine-contentbuddy', 'Slovak' ) . ' (Slovenčina)',
			'cs' => Craft::t( 'convergine-contentbuddy', 'Czech' ) . ' (Čeština)',
			'lt' => Craft::t( 'convergine-contentbuddy', 'Lithuanian' ) . ' (Lietuvių)'
		];
	}

	public function saveSettings( array $settings ): bool {
		foreach ( $settings as $name => $value ) {
			$record = SettingsRecord::find()->where( [ 'name' => $name ] )->one();
			if ( ! $record ) {
				$record = new SettingsRecord();
			}
			if ( ! $this->hasProperty( $name ) ) {
				continue;
			}
			if ( isset( $value[ ProjectConfig::ASSOC_KEY ] ) ) {
				$_value = [];
				foreach ( $value[ ProjectConfig::ASSOC_KEY ] as $item ) {
					$_value[ $item[0] ] = $item[1];
				}
				$value = $_value;
			}
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				$value = json_encode( $value );
			}
			$record->name  = $name;
			$record->value = $value;
			$record->save();


		}
		$this->_afterSaveSettings( $settings );

		return true;
	}

	private function _afterSaveSettings( $settings ) {
		$this->_getSettingsFromDb();
	}

	private function _getSettingsFromDb(): void {
		$settings = [];
		foreach ( SettingsRecord::find()->all() as $row ) {
			$value = $row->value;
			if ( null !== $encodedValue = json_decode( $row->value, true ) ) {
				$value = $encodedValue;
			}
			$settings[ $row->name ] = $value;
		}
		$this->setAttributes( $settings, false );
	}

}
