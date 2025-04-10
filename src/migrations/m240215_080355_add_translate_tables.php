<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use craft\db\Migration;

/**
 * m240215_080355_add_translate_tables migration.
 */
class m240215_080355_add_translate_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool {
        if(!$this->db->tableExists(TranslateRecord::tableName())) {
            $this->createTable( TranslateRecord::tableName(), [
                'id'       => $this->primaryKey(),
                'sectionId'    => $this->integer()->notNull(),
                'sectionType' => $this->integer()->notNull(),
                'siteId'   => $this->integer()->notNull(),

                'instructions'     => $this->string(255),
                 'override'=> $this->integer()->notNull(),
                'fields'=> $this->text(),
                 'fieldsCount'=>$this->integer()->notNull(),
                 'entriesSubmitted'=>$this->integer()->notNull(),
                 'fieldsProcessed'=>$this->integer()->notNull(),
                 'fieldsTranslated'=>$this->integer()->notNull(),
                 'fieldsError'=>$this->integer()->notNull(),
                 'fieldsSkipped'=>$this->integer()->notNull(),
                 'jobIds'=> $this->text(),

                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ] );
        }

        if(!$this->db->tableExists(TranslateLogRecord::tableName())) {
            $this->createTable( TranslateLogRecord::tableName(), [
                'id'       => $this->primaryKey(),
                'translationId'    => $this->integer()->notNull(),
                'entryId' => $this->integer()->notNull(),
                'message'=> $this->text(),
                'field'=>$this->string(255),
                'blockId'=>$this->integer()->notNull(),

                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ] );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240215_080355_add_translate_tables cannot be reverted.\n";
        return false;
    }
}
