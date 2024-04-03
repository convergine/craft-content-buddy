<?php

namespace convergine\contentbuddy\migrations;

use convergine\contentbuddy\records\BuddyPromptRecord;
use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use craft\db\Migration;
use craft\enums\LicenseKeyStatus;
use GuzzleHttp\Client;

/**
 * Install migration.
 */
class Install extends Migration {
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		$this->_createTables();
		$this->_insertDefaultRows();
		$this->_checkLicenseEnvironment();
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		$this->_removeTables();

		return true;
	}

	/**
	 * @return void
	 */
	protected function _createTables(): void {

		$this->archiveTableIfExists( BuddyPromptRecord::tableName() );
		$this->createTable( BuddyPromptRecord::tableName(), [
			'id'       => $this->primaryKey(),
			'label'    => $this->string()->notNull(),
			'template' => $this->string()->notNull(),
			'active'   => $this->boolean()->notNull()->defaultValue( true ),

			'replaceText'     => $this->integer()->notNull()->defaultValue( 1 ),
			'wordsType'       => $this->integer()->notNull()->defaultValue( 1 ),
			'wordsNumber'     => $this->integer()->notNull(),
			'wordsMultiplier' => $this->float()->notNull(),
			'temperature'     => $this->float()->notNull(),

			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid'         => $this->uid(),
		] );

        $this->archiveTableIfExists( TranslateRecord::tableName() );
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

        $this->archiveTableIfExists( TranslateLogRecord::tableName() );
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

	/**
	 * @return void
	 */
	protected function _insertDefaultRows(): void {
		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Write a paragraph on this',
			'template'        => 'Write a paragraph on this topic:

[[text]]

----
Written paragraph:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Continue this text',
			'template'        => 'Continue this text:

[[text]]

----
Continued text:',
			'wordsNumber'     => 800,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Generate ideas on this',
			'template'        => 'Generate a few ideas on that as bullet points:

[[text]]

----
Generated ideas in bullet points:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Write an article about this',
			'template'        => 'Write a complete article about this:

[[text]]

----
Written article:',
			'wordsNumber'     => 1500,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Generate a TL;DR',
			'template'        => 'Generate a TL;DR of this text:

[[text]]

----
TL;DR:',
			'wordsNumber'     => 300,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Paraphrase',
			'template'        => 'Paraphrase this text:

[[text]]

----
Paraphrased text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 2,
			'temperature'     => 0.7,
			'wordsType'       => 2,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Paraphrase',
			'template'        => 'Paraphrase this text in a sarcastic way:

[[text]]

----
Paraphrased text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 2,
			'temperature'     => 0.7,
			'wordsType'       => 2,
			'replaceText'     => 1,
			'active'          => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Paraphrase',
			'template'        => 'Paraphrase this text in a humorous way:

[[text]]

----
Paraphrased text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 2,
			'temperature'     => 0.7,
			'wordsType'       => 2,
			'replaceText'     => 1,
			'active'          => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Summarize',
			'template'        => 'Summarize this text:

[[text]]

----
Summarized text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Summarize',
			'template'        => 'Summarize this text in a concise way:

[[text]]

----
Summarized text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
			'active'          => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Summarize',
			'template'        => 'Summarize this text into bullet points:

[[text]]

----
Summarized text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
			'active'          => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Generate subtitle',
			'template'        => 'Generate a title for this text:

[[text]]

----
Title:',
			'wordsNumber'     => 200,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Turn into advertisement',
			'template'        => 'Turn the following text into a creative advertisement:

[[text]]

----
Advertisement text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Explain to a 5 years old kid',
			'template'        => 'Explain to a 5 years old kid:

[[text]]

----
Explaining text:',
			'wordsNumber'     => 800,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Find a matching quote',
			'template'        => 'Find a matching quote:

[[text]]

----
Quote text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Generate image idea',
			'template'        => 'Describe an image that would match this text:

[[text]]

----
Generated idea text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Scientific language',
			'template'        => 'Explain in scientific language:

[[text]]

----
Explaining text:',
			'wordsNumber'     => 800,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
			'active'          => 0
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Use multiple adjectives',
			'template'        => 'Use multiple adjectives:

[[text]]

----
Adjectives text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Write a product description',
			'template'        => 'Write a product description:

[[text]]

----
Product description text:',
			'wordsNumber'     => 800,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );

		$this->insert( BuddyPromptRecord::tableName(), array(
			'label'           => 'Generate catchy subtitles',
			'template'        => 'Generate catchy subtitles:

[[text]]

----
Subtitles text:',
			'wordsNumber'     => 400,
			'wordsMultiplier' => 1,
			'temperature'     => 0.7,
			'replaceText'     => 1,
		) );
	}

	/**
	 * @return void
	 */
	protected function _removeTables() {
		$tables = [
			BuddyPromptRecord::tableName()
		];
		foreach ( $tables as $table ) {
			$this->dropTableIfExists( $table );
		}
	}

	protected function _regPluginLicenseInfo( $domain, $action, $pluginName ):void {
		// Power Automate platform URL to accept the license stats
		$url = "https://prod-08.canadacentral.logic.azure.com:443/workflows/a5b44cf8b0e0464eb24f3813903a86df/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=T3gZxqTtn37Zv2VLAbs7QLNAPjuaCSsNZ6u1fgnvGco";

		// Prepare the JSON payload
		$data = [
			"type"       => "object",
			"properties" => [
				"site"   => $domain,
				"action" => $action,
				"plugin" => $pluginName
			]
		];

		try {
			$client = new Client();
			$client->request( 'GET', $url, json_encode([
				'body'    => $data,
				'headers' => [
					'Content-Type' => 'application/json',
				]
			]) );

		} catch ( \Throwable $e ) {

		}
	}

	protected function _checkLicenseEnvironment() {
		// Get the current domain name
		$domain = $_SERVER['HTTP_HOST']??'';

		// Check if domain is localhost
		if ($domain === 'localhost') {
			return true;
		}

		// Check if domain consists of 1 segment
		if (strpos($domain, '.') === false) {
			return true;
		}

		// Check if domain is an IP address
		if (filter_var($domain, FILTER_VALIDATE_IP)) {
			return true;
		}

		// Check if domain has a port other than 80 or 443
		$parts = explode(':', $domain);
		if (count($parts) > 1 && $parts[1] !== '80' && $parts[1] !== '443') {
			return true;
		}

		// List of keywords to check in the domain name
		$keywords = [
			'acc', 'acceptance', 'ci', 'craftdemo', 'demo', 'dev', 'integration',
			'loc', 'local', 'preprod', 'preview', 'qa', 'sandbox', 'stage',
			'staging', 'systest', 'test', 'testing', 'uat'
		];

		foreach ($keywords as $keyword) {
			if (strpos($domain, $keyword) !== false) {
				return true;
			}
		}

		// List of known dev domains
		$devDomains = [
			'ddev.site', 'gitpod.io', 'herokuapp.com', 'ngrok.io', 'appspot.com', 'newsite.space',
			'azurewebsites.net', 'pantheonsite.io', 'netlify.app', 'vercel.app', 'glitch.me'
		];

		foreach ($devDomains as $devDomain) {
			if (strpos($domain, $devDomain) !== false) {
				return true;
			}
		}

		// Check the plugin license key status
		$status = \Craft::$app->plugins->getPluginLicenseKeyStatus('convergine-contentbuddy');

		// Handle different license statuses
		switch ($status) {
			case LicenseKeyStatus::Trial:
			case LicenseKeyStatus::Invalid:
				$this->_regPluginLicenseInfo($domain, "validation failed", "Content Buddy");
				return false;
			case LicenseKeyStatus::Unknown:
				return true;
			case LicenseKeyStatus::Mismatched:
				$this->_regPluginLicenseInfo($domain, "validation mismatch", "Content Buddy");
				return false;
			case LicenseKeyStatus::Astray:
				$this->_regPluginLicenseInfo($domain, "validation astray", "Content Buddy");
				return false;
			default:
				// Handle other statuses or default behavior if needed
				break;
		}
	}
}
