<?php
namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\api\text\DeepL;
use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use Craft;
use craft\web\Controller;

class DeepLController extends Controller {
	public function actionGlossaries() {
        //$this->requirePostRequest();
        try {
            $deepl = new DeepL();
            $glossaries = $deepl->getGlossaries();
            return $this->asJson(['success' => true, 'glossaries' => $glossaries]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
	}
}
