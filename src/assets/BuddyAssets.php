<?php
namespace convergine\contentbuddy\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BuddyAssets extends AssetBundle {
    public function init() : void {
        $enabled = getenv('WEBPACK_DEV_SERVER_ENABLED');
        $port = getenv('WEBPACK_DEV_SERVER_PORT');
        if($enabled && !empty($port)) {
            $this->sourcePath = null;
            $this->baseUrl = 'http://localhost:'.getenv('WEBPACK_DEV_SERVER_PORT');
        } else {
            $this->sourcePath = '@convergine/contentbuddy/assets/dist';
        }
        $this->depends = [CpAsset::class];
        $this->js = ['contentbuddy.js'];
        $this->css = ['contentbuddy.css'];
        parent::init();
    }
}
