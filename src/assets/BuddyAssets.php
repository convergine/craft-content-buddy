<?php
namespace convergine\contentbuddy\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BuddyAssets extends AssetBundle
{
	public function init() {
		/* define asset bundle and files */

		$this->sourcePath = '@convergine/contentbuddy/assets/dist';
		$this->depends = [CpAsset::class];
		$this->js = ['contentbuddy.js'];
		$this->css = ['contentbuddy.css'];
		parent::init();
	}
}