<?php

namespace convergine\contentbuddy\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $label
 * @property string $template
 * @property bool $active
 *
 * @property-read \yii\db\ActiveQueryInterface $element
 */
class BuddyPromptRecord extends ActiveRecord {

	/*public string $label = '';
	public string $template = '';
	public int $active = 0;
	public int $replaceText = 0;
	public int $wordsType = 0;
	public int $wordsNumber = 0;
	public float $wordsMultiplier = 0;
	public float $temperature = 0;
	public int $order = 0;*/

	/**
	 * @return string
	 */
	public static function tableName() {
		return '{{%content_buddy_prompt}}';
	}
}