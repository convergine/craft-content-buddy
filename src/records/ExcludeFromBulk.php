<?php
namespace convergine\contentbuddy\records;

use craft\db\ActiveRecord;

/**
 * @property int $elementId
 * @property int $siteId
 */
class ExcludeFromBulk extends ActiveRecord
{
	/**
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%content_buddy_exclude_from_bulk}}';
	}
}