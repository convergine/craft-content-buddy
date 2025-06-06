<?php
namespace convergine\contentbuddy\queue;

use convergine\contentbuddy\BuddyPlugin;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\queue\BaseJob;

class translateProducts extends BaseJob
{
	/**
	 * @var array Entry ID
	 */
	public $productsIds;

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

    public string $type = 'products';

	public function execute($queue):void
	{
		$this->_queue = $queue;
		$step=1;
		$total  = count($this->productsIds);

        if($this->translateSlugs) {
            $total++;
        }

		$this->setProgress($this->_queue, 0);

		foreach($this->productsIds as $id) {
			$product = Product::findOne($id);


			if($product) {
                BuddyPlugin::getInstance()->translate->translateProduct(
                    $product,
                    $this->translateToSiteId,
                    $this->enabledFields,
                    $this->translationId,
                    $this->instructions
                );
			}


            $this->setProgress($this->_queue, $step / $total, \Craft::t('convergine-contentbuddy', 'Translate products {step} of {total}', compact('step', 'total')));
			$step++;
		}

        if($this->translateSlugs) {
            foreach($this->productsIds as $id) {
                $entry = Product::findOne($id);
                if($entry) {
                    BuddyPlugin::getInstance()->translate->translateSlug(
                        $product,
                        $this->translateToSiteId,
                        $this->instructions
                    );
                }
            }
            $this->setProgress($this->_queue, $step / $total, \Craft::t('convergine-contentbuddy', 'Translate products {step} of {total}', compact('step', 'total')));
        }

		$this->setProgress($this->_queue, 1);
	}


	protected function defaultDescription(): string {
		$language = \Craft::$app->sites->getSiteById( $this->translateToSiteId )->language;
        $type = match($this->type) {
            'products' => 'products',
            default => 'elements',
        };
		return "Translate $type to '$language': ".join(', ',$this->productsIds);
	}
}
