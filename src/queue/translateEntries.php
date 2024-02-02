<?php
namespace convergine\contentbuddy\queue;

use convergine\contentbuddy\BuddyPlugin;
use craft\elements\Entry;
use craft\queue\BaseJob;

class translateEntries extends BaseJob
{
	/**
	 * @var array Entry ID
	 */
	public $entriesIds;

	/**
	 * @var int Site ID
	 */
	public $translateToSiteId;

	/**
	 * @var array Fields array
	 */
	public $enabledFields = [];

	/**
	 * @var string Additional Instructions
	 */
	public $instructions='';

	/**
	 * @var int
	 */
	public $translationId;

	/**
	 * @var bool
	 */
	public $isRerun=false;

	/**
	 * @var
	 */
	private $_queue;


	public function execute($queue):void
	{
		$this->_queue = $queue;
		$step=1;
		$total  = count($this->entriesIds);

		$this->setProgress($this->_queue, 0);
		foreach ($this->entriesIds as $id){
			$this->setProgress($this->_queue, $step / $total, \Craft::t('convergine-contentbuddy', 'Translate entries {step} of {total}', compact('step', 'total')));
			$entry = Entry::findOne($id);
			if($entry){
				if($this->isRerun){
					BuddyPlugin::getInstance()->translate
						->reTranslateEntry(
							$entry,
							$this->translateToSiteId,
							$this->translationId,
							$this->instructions
						);
				}else{
					BuddyPlugin::getInstance()->translate
						->translateEntry(
							$entry,
							$this->translateToSiteId,
							$this->enabledFields,
							$this->translationId,
							$this->instructions
						);
				}


			}

			$step++;
		}

		$this->setProgress($this->_queue, 1);
	}


	protected function defaultDescription(): string
	{
		return 'Translate entries:'.join(', ',$this->entriesIds);
	}
}