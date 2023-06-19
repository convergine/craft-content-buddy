<?php
namespace convergine\contentbuddy\models;

use craft\base\Model;
use craft\helpers\App;
use Craft;

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

	/**
	 * @return string
	 */
	public function getApiKey(): string
	{
		return App::parseEnv($this->apiToken);
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
