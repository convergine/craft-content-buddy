<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\BuddyPromptRecord;
use craft\db\Migration;

/**
 * m240905_112318_promptTemplate migration.
 */
class m240905_112318_promptTemplate extends Migration {
    /**
     * @inheritdoc
     */
    public function safeUp(): bool {
        $this->alterColumn(BuddyPromptRecord::tableName(),'template',$this->text()->notNull());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool {
        echo "m240905_112318_promptTemplate cannot be reverted.\n";
        return false;
    }
}
