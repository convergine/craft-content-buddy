<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use Craft;
use craft\db\Migration;

/**
 * m240903_212340_projectConfig migration.
 */
class m240903_212340_projectConfig extends Migration {
    /**
     * @inheritdoc
     */
    public function safeUp(): bool {
        /** @var SettingsModel $settings */
        $settings = BuddyPlugin::getInstance()->getSettings();

        //convert enabled fields id to handle
        if(!empty($settings->enabledFields)) {
            $fields = [];
            foreach($settings->enabledFields as $id => $value) {
                if(is_numeric($id)) {
                    $field = Craft::$app->getFields()->getFieldById($id);
                      if($field) {
                          $fields[$field->handle] = $value;
                      }
                } else {
                    $fields[$id] = $value;
                }
            }
            $settings->enabledFields = $fields;
        }

        //convert generateImageAssetId to UID
        if(!empty($settings->generateImageAssetId) && is_numeric($settings->generateImageAssetId)) {
            $folder = Craft::$app->getAssets()->getFolderById($settings->generateImageAssetId);
            if($folder) {
                $settings->generateImageAssetId = $folder->uid;
            }
        }

        Craft::$app->getPlugins()->savePluginSettings(BuddyPlugin::getInstance(), $settings->toArray());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool {
        echo "m240903_212340_projectConfig cannot be reverted.\n";
        return false;
    }
}
