<?php

namespace convergine\contentbuddy\records;

/**
 * @property int $id
 * @property int $name
 * @property int $value
 *
 */
class SettingsRecord extends \craft\db\ActiveRecord {
	/**
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%content_buddy_settings}}';
	}
}