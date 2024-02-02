<?php

namespace convergine\contentbuddy\migrations;

use Craft;
use craft\db\Migration;

/**
 * m231019_094737_update_template_field migration.
 */
class m231019_094737_update_template_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Place migration code here...
	    $this->alterColumn('{{%content_buddy_prompt}}', 'template', $this->text());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231019_094737_update_template_field cannot be reverted.\n";
        return false;
    }
}
