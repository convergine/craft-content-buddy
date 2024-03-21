<?php
namespace convergine\contentbuddy\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

class SettingsModel extends Model
{

	/**
	 * @var string
	 */
	public string $apiToken = '';

	/**
	 * @var string
	 */
	public string $preferredModel = 'gpt-3.5-turbo';

	/**
	 * @var int
	 */
	public int $maxTokens = 256;

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
	public string $systemMessage = '';

	private array $_supportedFieldTypes = [
        'craft\fields\PlainText',
        'craft\redactor\Field',
        'craft\ckeditor\Field'
    ];

	public string $imageModel = 'openai';

	// stability ai options

	public string $stabilityAPIKey = '';

	public string $stabilityEngine = 'stable-diffusion-512-v2-1';

	public string $stabilitySampler = 'DDIM';

	public int $stabilitySteps = 50;

	public int $stabilityScale = 7;

	public string $stabilityStyle = 'enhance';

	// Generate image based on text

	public bool $generateImageFromText = false;

	public int $generateImageAssetId = 0;

	/**
	 * @return string
	 */
	public function getApiKey(): string
	{
		return App::parseEnv($this->apiToken);
	}

	/**
	 * @return string
	 */
	public function getStabilityApiKey(): string
	{
		return App::parseEnv($this->stabilityAPIKey);
	}

	public function titleFieldEnabled(){
		return isset($this->enabledFields['title']) && $this->enabledFields['title'];
	}

	public function getRegularFieldsList(): array {
        $isCraft5 = version_compare(Craft::$app->getInfo()->version, '5.0', '>=');

		$fields = [];

		foreach ( \Craft::$app->getFields()->getAllFields() as $field ) {

			if ( in_array( ( new \ReflectionClass( $field ) )->getName(), $this->_supportedFieldTypes ) ) {
                $field_obj = [
                    'id'     => $field->id,
                    'handle' => $field->handle,
                    'name'   => $field->name,
                    'type'   => $this->_getClass( $field )
                ];
                if(!$isCraft5) {
                    $field_obj['group'] = $field->getGroup()->name;
                }
				$fields[] = $field_obj;
			}

		}

		return $fields;
	}

	public function getMatrixFieldsList(): array {
        if(version_compare(Craft::$app->getInfo()->version, '5.0', '>=')) {
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
	
	public function getLanguages (){
		return [
			'en' => Craft::t('convergine-contentbuddy','English') . ' (English)',
			'de' => Craft::t('convergine-contentbuddy','German') . ' (Deutsch)',
			'fr' => Craft::t('convergine-contentbuddy','French') . ' (Français)',
			'es' => Craft::t('convergine-contentbuddy','Spanish') . ' (Español)',
			'it' => Craft::t('convergine-contentbuddy','Italian') . ' (Italiano)',
			'pt' => Craft::t('convergine-contentbuddy','Portuguese') . ' (Português)',
			'nl' => Craft::t('convergine-contentbuddy','Dutch') . ' (Nederlands)',
			'pl' => Craft::t('convergine-contentbuddy','Polish') . ' (Polski)',
			'ru' => Craft::t('convergine-contentbuddy','Russian') . ' (Русский)',
			'ja' => Craft::t('convergine-contentbuddy','Japanese') . ' (日本語)',
			'zh' => Craft::t('convergine-contentbuddy','Chinese') . ' (中文)',
			'br' => Craft::t('convergine-contentbuddy','Brazilian Portuguese') . ' (Português Brasileiro)',
			'tr' => Craft::t('convergine-contentbuddy','Turkish') . ' (Türkçe)',
			'ar' => Craft::t('convergine-contentbuddy','Arabic') . ' (العربية)',
			'ko' => Craft::t('convergine-contentbuddy','Korean') . ' (한국어)',
			'hi' => Craft::t('convergine-contentbuddy','Hindi') . ' (हिन्दी)',
			'id' => Craft::t('convergine-contentbuddy','Indonesian') . ' (Bahasa Indonesia)',
			'sv' => Craft::t('convergine-contentbuddy','Swedish') . ' (Svenska)',
			'da' => Craft::t('convergine-contentbuddy','Danish') . ' (Dansk)',
			'fi' => Craft::t('convergine-contentbuddy','Finnish') . ' (Suomi)',
			'no' => Craft::t('convergine-contentbuddy','Norwegian') . ' (Norsk)',
			'ro' => Craft::t('convergine-contentbuddy','Romanian') . ' (Română)',
			'ka' => Craft::t('convergine-contentbuddy','Georgian') . ' (ქართული)',
			'vi' => Craft::t('convergine-contentbuddy','Vietnamese') . ' (Tiếng Việt)',
			'hu' => Craft::t('convergine-contentbuddy','Hungarian') . ' (Magyar)',
			'bg' => Craft::t('convergine-contentbuddy','Bulgarian') . ' (Български)',
			'el' => Craft::t('convergine-contentbuddy','Greek') . ' (Ελληνικά)',
			'fa' => Craft::t('convergine-contentbuddy','Persian') . ' (فارسی)',
			'sk' => Craft::t('convergine-contentbuddy','Slovak') . ' (Slovenčina)',
			'cs' => Craft::t('convergine-contentbuddy','Czech') . ' (Čeština)',
			'lt' => Craft::t('convergine-contentbuddy','Lithuanian') . ' (Lietuvių)'
		];
	}
}
