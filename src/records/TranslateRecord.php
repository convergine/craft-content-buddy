<?php

namespace convergine\contentbuddy\records;

/**
 * @property int $id
 * @property int $sectionId
 * @property int $sectionType
 * @property int $siteId
 * @property string $instructions
 * @property bool $override
 * @property string $fields
 * @property int $entriesSubmitted
 * @property int $fieldsProcessed
 * @property int $fieldsTranslated;
 * @property int $fieldsError
 * @property int $fieldsSkipped
 * @property int $fieldsCount
 * @property string $jobIds
 *
 */
class TranslateRecord extends \craft\db\ActiveRecord {
	/**
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%content_buddy_translate}}';
	}
}