<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\BuddyPromptRecord;
use Craft;
use craft\db\Migration;

/**
 * m241129_095109_promptsOrder migration.
 */
class m241129_095109_promptsOrder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if(!$this->db->columnExists(BuddyPromptRecord::tableName(), 'order')) {
            $this->addColumn(BuddyPromptRecord::tableName(),'order',$this->integer()->defaultValue(99));
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241129_095109_promptsOrder cannot be reverted.\n";
        return false;
    }
}
