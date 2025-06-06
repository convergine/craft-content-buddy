<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\TranslateRecord;
use Craft;
use craft\db\Migration;

/**
 * m240320_093059_translateEntries migration.
 */
class m240320_093059_translateEntries extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if(!$this->db->columnExists(TranslateRecord::tableName(),'idEntry')) {
            $this->addColumn(TranslateRecord::tableName(),'idEntry',$this->integer()->null());
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240320_093059_translateEntries cannot be reverted.\n";
        return false;
    }
}
