<?php
namespace convergine\contentbuddy\models;

use convergine\contentbuddy\records\SettingsRecord;
use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\services\ProjectConfig;

class SettingsModel extends Model
{

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
    public string $preferredModel = 'gpt-3.5-turbo';

	/**
	 * @var string
	 */
	public string $preferredTranslationModel = 'gpt-3.5-turbo';

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
    public string $xAiModel = 'grok-beta';

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
	public string $systemMessage = '';

	private array $_supportedFieldTypes = [
		'craft\fields\PlainText',
		'craft\redactor\Field',
		'craft\ckeditor\Field',
		'abmat\tinymce\Field'
	];

	public string $imageModel = 'openai';

    public string $dalleModel = 'dall-e-3';

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

	 public function init():void {
		 parent::init();
		 $this->_getSettingsFromDb();
	 }

    /**
     * @return string
     */
    public function getOpenAiApiKey(): string {
        return App::parseEnv($this->apiToken);
    }

    /**
     * @return string
     */
    public function getXAiApiKey(): string {
        return App::parseEnv($this->xAiApiKey);
    }

    /**
     * @return string
     */
    public function getDeepLApiKey(): string {
        return App::parseEnv($this->deepLApiKey);
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
                    'uid'    => $field->uid,
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

	public function saveSettings(array $settings):bool {
		foreach ($settings as $name=>$value){
			$record = SettingsRecord::find()->where(['name'=>$name])->one();
			if(!$record){
				$record = new SettingsRecord();
			}
			if(!$this->hasProperty($name)){
				continue;
			}
			if(isset($value[ProjectConfig::ASSOC_KEY])){
				$_value = [];
				foreach ($value[ProjectConfig::ASSOC_KEY] as $item){
					$_value[$item[0]] = $item[1];
				}
				$value = $_value;
			}
			if(!is_string($value) && !is_numeric($value)){
				$value = json_encode($value);
			}
			$record->name = $name;
			$record->value = $value;
			$record->save();
		}
		return true;
	}

	private function _getSettingsFromDb():void {
		$settings = [];
		foreach (SettingsRecord::find()->all() as $row){
			$value = $row->value;
			if(null !== $encodedValue = json_decode($row->value,true)){
				$value = $encodedValue;
			}
			$settings[$row->name] = $value;
		}
		$this->setAttributes($settings,false);
	}

}
