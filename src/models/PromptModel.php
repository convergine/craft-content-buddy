<?php
namespace convergine\contentbuddy\models;
use craft\base\Model;
use DateTime;
class PromptModel extends Model
{

	const  GENERATE_FIXED_WORDS_COUNT = 1;
	const GENERATE_RELATIVE_WORDS_COUNT = 2;

	/**
	 * @var int
	 */
	public int $id = 0;

	/**
	 * @var string
	 */
	public string $label = '';

	/**
	 * @var string
	 */
	public string $template = '';

	/**
	 * @var int
	 */
	public int $wordsType = 1;

	/**
	 * @var int
	 */
	public int $wordsNumber = 400;

	/**
	 * @var float
	 */
	public float $wordsMultiplier = 1;

	/**
	 * @var float
	 */
	public float $temperature = 0.7;

	/**
	 * @var bool
	 */
	public bool $replaceText = true;

	/**
	 * @var bool
	 */
	public bool $active = true;

	/**
	 * @var DateTime
	 */
	public DateTime $dateCreated;

	/**
	 * @var DateTime
	 */
	public DateTime $dateUpdated;

	/**
	 * @var string
	 */
	public string $uid;

	/**
	 * @return DateTime
	 */
	public function getDateCreated(): DateTime
	{
		if (!isset($this->dateCreated)) {
			$this->dateCreated = new DateTime('now');
		}
		return $this->dateCreated;
	}

	/**
	 * @return DateTime
	 */
	public function getDateUpdated(): DateTime
	{
		if (!isset($this->dateUpdated)) {
			$this->dateUpdated = new DateTime('now');
		}
		return $this->dateUpdated;
	}

	/**
	 * @return array
	 */
	public function defineRules(): array
	{
		$rules = parent::defineRules();
		$rules=[
			[['label', 'template', 'temperature', 'wordsMultiplier', 'wordsNumber','wordsType'], 'required'],
			[['wordsMultiplier', 'wordsNumber'], 'number'],
			['wordsType', 'validateType'],
		];

		return $rules;
	}

	public function validateType()
	{
		if ($this->wordsType == self::GENERATE_FIXED_WORDS_COUNT) {
			if($this->wordsNumber<5){
				$this->addError('wordsNumber', \Craft::t('convergine-contentbuddy',
					Craft::t('convergine-contentbuddy', 'Words count must be more than 5')));
			}

		}else{

		}

	}
}