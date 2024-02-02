<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/17/2023
 * Time: 5:55 PM
 */

namespace convergine\contentbuddy\records;

/**
 * @property int $id
 * @property int $translationId
 * @property int $entryId
 * @property string $message
 * @property string $field
 * @property int $blockId
 *
 */
class TranslateLogRecord extends \craft\db\ActiveRecord {
	/**
	 * @return string
	 */
	public static function tableName() {
		return '{{%content_buddy_translate_log}}';
	}

	public function getTranslationLogs( $translationId, $pageId = 1, $itemsPerPage = 5 ) {
		$entries = self::find()
		               ->where( [ 'translationId' => $translationId ] )
		               ->offset( ( $pageId - 1 ) * $itemsPerPage )
		               ->limit( $itemsPerPage );


		return $entries->all();
	}

	public function getTranslationLogsCount( $translationId ) {
		return self::find()
		           ->where( [ 'translationId' => $translationId ] )
		           ->count( 'id' );

	}

	public function removeByTranslation($id){
		if($id){
			return self::deleteAll(['translationId'=>$id]);
		}
		return false;
	}

}