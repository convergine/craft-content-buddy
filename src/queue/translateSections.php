<?php
namespace convergine\contentbuddy\queue;

use convergine\contentbuddy\BuddyPlugin;
use craft\queue\BaseJob;

class translateSections extends BaseJob
{
	/**
	 * @var string Section : Section Type
	 */
	public $sectionId;
	public $translateToSiteId;

	public $override;
	/**
	 * @var string Additional Instructions
	 */
	public $instructions='';


	/**
	 * @var
	 */
	private $_queue;

    public bool $translateSlugs = false;


	public function execute($queue):void
	{
		$this->_queue = $queue;
		$step=1;

		$this->setProgress($this->_queue, 0);

		BuddyPlugin::getInstance()->translate
			->translateSection(
				$this->sectionId,
				$this->translateToSiteId,
				$this->instructions,
				$this->override,
                $this->translateSlugs
			);

		$this->setProgress($this->_queue, 1);
	}


	protected function defaultDescription(): string
	{
		$_section = explode(":",$this->sectionId);
		if(version_compare(\Craft::$app->getInfo()->version, '5.0', '>=')){
			$section = \Craft::$app->entries->getSectionById($_section[0])->name;
			if($_section[1]) {
				$sectionType = \Craft::$app->entries->getEntryTypeById( $_section[1] )->name;
			}
		}else{
			$section = \Craft::$app->sections->getSectionById($_section[0])->name;
			if($_section[1]) {
				$sectionType = \Craft::$app->sections->getEntryTypeById( $_section[1] )->name;
			}
		}
		$language = $this->translateToSiteId ==='all'?"All Languages": \Craft::$app->sites->getSiteById( $this->translateToSiteId )->language;
		return "Translate {$section}:{$sectionType} to '{$language}'";
	}
}