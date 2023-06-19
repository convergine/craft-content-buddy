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
class BuddyPromptRecord extends ActiveRecord
{
	/**
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%content_buddy_prompt}}';
	}
}