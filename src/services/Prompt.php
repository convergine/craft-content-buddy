<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 5/5/2023
 * Time: 1:56 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\models\PromptModel;
use \convergine\contentbuddy\records\BuddyPromptRecord;
use craft\helpers\StringHelper;

class Prompt extends \craft\base\Component {

	public function save(PromptModel $promptModel): bool{
		if($promptModel->id == 0){
			$promptRecord = new BuddyPromptRecord();

		}else{
			$promptRecord = BuddyPromptRecord::findOne($promptModel->id);
		}
		$promptRecord->dateCreated = $promptModel->getDateCreated();
		$promptRecord->dateUpdated = $promptModel->getDateUpdated();
		$promptRecord->uid = StringHelper::UUID();
		$promptRecord->label = $promptModel->label;
		$promptRecord->template = $promptModel->template;
		$promptRecord->active = $promptModel->active;
		$promptRecord->replaceText = $promptModel->replaceText;

		$promptRecord->temperature = $promptModel->temperature;
		$promptRecord->wordsType = $promptModel->wordsType;
		$promptRecord->wordsNumber = $promptModel->wordsNumber;
		$promptRecord->wordsMultiplier = $promptModel->wordsMultiplier;
		$promptRecord->order = $promptModel->order;

		return $promptRecord->save();

	}

	public function remove(int $id): int{
		$promptRecord = BuddyPromptRecord::findOne($id);
		if(!$promptRecord){
			return 0;
		}
		return BuddyPromptRecord::deleteAll(['id'=>$id]);
	}

	public function getPrompts(bool $enabled = false): array
	{

		$prompts =  BuddyPromptRecord::find();

		if ($enabled) {
			$prompts =  $prompts->where(['active' => true]);
		}

		$prompts =  $prompts->orderBy(['order'=>SORT_ASC])->all();

		return $prompts;
	}

}