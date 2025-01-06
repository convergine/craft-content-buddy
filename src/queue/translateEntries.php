<?php
namespace convergine\contentbuddy\queue;

use convergine\contentbuddy\BuddyPlugin;
use craft\elements\Category;
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

    public bool $translateSlugs = false;


	public function execute($queue):void
	{
		$this->_queue = $queue;
		$step=1;
		$total  = count($this->entriesIds);

        if($this->translateSlugs) {
            $total++;
        }

		$this->setProgress($this->_queue, 0);
		foreach ($this->entriesIds as $id){
			$entry = Entry::findOne($id);
			if($entry){

					BuddyPlugin::getInstance()->translate
						->translateEntry(
							$entry,
							$this->translateToSiteId,
							$this->enabledFields,
							$this->translationId,
							$this->instructions
						);

			}else{
				$category = Category::findOne($id);
				if($category){
					BuddyPlugin::getInstance()->translate
						->translateCategory(
							$category,
							$this->translateToSiteId,
							$this->enabledFields,
							$this->translationId,
							$this->instructions
						);
				}

			}
            $this->setProgress($this->_queue, $step / $total, \Craft::t('convergine-contentbuddy', 'Translate entries {step} of {total}', compact('step', 'total')));
			$step++;
		}

        if($this->translateSlugs) {
            foreach($this->entriesIds as $id) {
                $entry = Entry::findOne($id);
                if($entry) {
                    BuddyPlugin::getInstance()->translate->translateSlug(
                        $entry,
                        $this->translateToSiteId,
                        $this->instructions
                    );
                }
            }
            $this->setProgress($this->_queue, $step / $total, \Craft::t('convergine-contentbuddy', 'Translate entries {step} of {total}', compact('step', 'total')));
        }

		$this->setProgress($this->_queue, 1);
	}


	protected function defaultDescription(): string
	{
		$language = \Craft::$app->sites->getSiteById( $this->translateToSiteId )->language;
		return "Translate entries to '{$language}':".join(', ',$this->entriesIds);
	}
}