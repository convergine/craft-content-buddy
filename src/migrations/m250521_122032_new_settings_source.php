<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\records\SettingsRecord;
use Craft;
use craft\db\Migration;
use craft\services\ProjectConfig;

/**
 * m250521_122032_new_settings_source migration.
 */
class m250521_122032_new_settings_source extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Place migration code here...
	    if(!$this->db->tableExists(SettingsRecord::tableName())) {
		    $this->createTable( SettingsRecord::tableName(), [
			    'id'       => $this->primaryKey(),
			    'name'    => $this->string(100),
			    'value' => $this->text(),
			    'dateCreated' => $this->dateTime()->notNull(),
			    'dateUpdated' => $this->dateTime()->notNull(),
			    'uid'         => $this->uid(),
		    ] );
	    }
	    $settings = Craft::$app->getProjectConfig()->get(ProjectConfig::PATH_PLUGINS . '.convergine-contentbuddy.settings');
	    (new SettingsModel())->saveSettings($settings);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250521_122032_new_settings_source cannot be reverted.\n";
        return false;
    }
}
