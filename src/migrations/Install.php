<?php
namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\BuddyPromptRecord;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{
		$this->createTables();
		$this->insertDefaultRows();
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool
	{
		$this->removeTables();

		return true;
	}

	/**
	 * @return void
	 */
	protected function createTables(): void
	{

		$this->archiveTableIfExists(BuddyPromptRecord::tableName());
		$this->createTable(BuddyPromptRecord::tableName(), [
			'id' => $this->primaryKey(),
			'label' => $this->string()->notNull(),
			'template' => $this->string()->notNull(),
			'active' => $this->boolean()->notNull()->defaultValue(true),

			'replaceText' => $this->integer()->notNull()->defaultValue(1),
			'wordsType' => $this->integer()->notNull()->defaultValue(1),
			'wordsNumber' => $this->integer()->notNull(),
			'wordsMultiplier' => $this->float()->notNull(),
			'temperature' => $this->float()->notNull(),

			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid' => $this->uid(),
		]);
	}

	/**
	 * @return void
	 */
	protected function insertDefaultRows(): void {
		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Write a paragraph on this',
			'template' => 'Write a paragraph on this topic:

[[text]]

----
Written paragraph:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Continue this text',
			'template' => 'Continue this text:

[[text]]

----
Continued text:',
			'wordsNumber'=>800,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Generate ideas on this',
			'template' => 'Generate a few ideas on that as bullet points:

[[text]]

----
Generated ideas in bullet points:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Write an article about this',
			'template' => 'Write a complete article about this:

[[text]]

----
Written article:',
			'wordsNumber'=>1500,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Generate a TL;DR',
			'template' => 'Generate a TL;DR of this text:

[[text]]

----
TL;DR:',
			'wordsNumber'=>300,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Paraphrase',
			'template' => 'Paraphrase this text:

[[text]]

----
Paraphrased text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>2,
			'temperature'=>0.7,
			'wordsType'=>2,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Paraphrase',
			'template' => 'Paraphrase this text in a sarcastic way:

[[text]]

----
Paraphrased text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>2,
			'temperature'=>0.7,
			'wordsType'=>2,
			'replaceText'=>1,
			'active'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Paraphrase',
			'template' => 'Paraphrase this text in a humorous way:

[[text]]

----
Paraphrased text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>2,
			'temperature'=>0.7,
			'wordsType'=>2,
			'replaceText'=>1,
			'active'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Summarize',
			'template' => 'Summarize this text:

[[text]]

----
Summarized text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Summarize',
			'template' => 'Summarize this text in a concise way:

[[text]]

----
Summarized text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
			'active'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Summarize',
			'template' => 'Summarize this text into bullet points:

[[text]]

----
Summarized text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
			'active'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Generate subtitle',
			'template' => 'Generate a title for this text:

[[text]]

----
Title:',
			'wordsNumber'=>200,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Turn into advertisement',
			'template' => 'Turn the following text into a creative advertisement:

[[text]]

----
Advertisement text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Explain to a 5 years old kid',
			'template' => 'Explain to a 5 years old kid:

[[text]]

----
Explaining text:',
			'wordsNumber'=>800,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Find a matching quote',
			'template' => 'Find a matching quote:

[[text]]

----
Quote text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Generate image idea',
			'template' => 'Describe an image that would match this text:

[[text]]

----
Generated idea text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Scientific language',
			'template' => 'Explain in scientific language:

[[text]]

----
Explaining text:',
			'wordsNumber'=>800,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
			'active'=>0
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Use multiple adjectives',
			'template' => 'Use multiple adjectives:

[[text]]

----
Adjectives text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Write a product description',
			'template' => 'Write a product description:

[[text]]

----
Product description text:',
			'wordsNumber'=>800,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));

		$this->insert(BuddyPromptRecord::tableName(), array(
			'label' => 'Generate catchy subtitles',
			'template' => 'Generate catchy subtitles:

[[text]]

----
Subtitles text:',
			'wordsNumber'=>400,
			'wordsMultiplier'=>1,
			'temperature'=>0.7,
			'replaceText'=>1,
		));
	}

	/**
	 * @return void
	 */
	protected function removeTables()
	{
		$tables = [
			BuddyPromptRecord::tableName()
		];
		foreach ($tables as $table) {
			$this->dropTableIfExists($table);
		}
	}
}
