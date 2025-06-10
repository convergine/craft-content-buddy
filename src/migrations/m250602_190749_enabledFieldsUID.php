<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use Craft;
use craft\db\Migration;

/**
 * m250602_190749_enabledFieldsUID migration.
 */
class m250602_190749_enabledFieldsUID extends Migration {
    /**
     * @inheritdoc
     */
    public function safeUp(): bool {
        /** @var SettingsModel $settings */
        $settings = BuddyPlugin::getInstance()->getSettings();

        //convert enabled fields handle to UID
        if(!empty($settings->enabledFields)) {
            $fields = [];
            foreach($settings->enabledFields as $handle => $value) {
                if(preg_match('/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/', $handle) || $handle == 'title') {
                    $fields[$handle] = $value;
                } else {
                    $field = Craft::$app->getFields()->getFieldByHandle($handle);
                    if($field) {
                        $fields[$field->uid] = $value;
                    }
                }
            }
            $settings->enabledFields = $fields;
        }

        $settings->saveSettings($settings->toArray());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool {
        echo "m250602_190749_enabledFieldsUID cannot be reverted.\n";
        return false;
    }
}
