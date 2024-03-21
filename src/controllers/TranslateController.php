<?php
namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\queue\translateEntries;
use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use Craft;
use craft\elements\Entry;


class TranslateController extends \craft\web\Controller {

	private BuddyPlugin $_plugin;

	public function init(): void {
		$this->_plugin = BuddyPlugin::getInstance();
		parent::init(); // TODO: Change the autogenerated stub
	}

	public function actionIndex() {
		return $this->renderTemplate('convergine-contentbuddy/translate/_index',
			[
				'sites' => BuddyPlugin::getInstance()->translate->getSites(),
				'sections' => BuddyPlugin::getInstance()->translate->getSections(),
				'items' => TranslateRecord::find()->all(),
                'isCraft5' => version_compare(Craft::$app->getInfo()->version, '5.0', '>='),
			]);
	}

	public function actionGetFields(){
		$request = \Craft::$app->getRequest();
		$section = $request->getParam('section');

		return $this->renderTemplate('convergine-contentbuddy/translate/_fields',
			[
				'fields' => BuddyPlugin::getInstance()->translate->getSectionFields($section),
                'isCraft5' => version_compare(Craft::$app->getInfo()->version, '5.0', '>=')
			]);
	}

	public function actionProcess(){
		$request = \Craft::$app->getRequest();
		//Craft::dump($request->post());

		$section = $request->getParam('section');
		$translate_to = $request->getParam('translate_to');
		$enabledFields = $request->getParam('enabledFields');
		$instructions = $request->getParam('instructions');
		$override = $request->getParam('override');

		if(!$section || !$translate_to || !$enabledFields){
			Craft::$app->getSession()->setError('Please select section, site and fields to translate.');
			return $this->redirectToPostedUrl();

		}
		$_section = explode(':',$section);
		$sectionId = $_section[0];
		$sectionType = $_section[1];

		foreach ($enabledFields as $k=>$v){
			if($v==''){
				unset($enabledFields[$k]);
			}
		}
		$primarySiteId = Craft::$app->sites->getPrimarySite()->id;
		$translate_to_site = Craft::$app->sites->getSiteById($translate_to);
		//$lang = $translate_to_site->language;
		//Craft::dump($primarySiteId);
		//Craft::dump($enabledFields);

		$translateRecord = new TranslateRecord();
		$translateRecord->siteId = $translate_to;
		$translateRecord->instructions = $instructions;
		$translateRecord->fields = json_encode($enabledFields);
		$translateRecord->fieldsCount = 0;
		$translateRecord->sectionId = $sectionId;
		$translateRecord->sectionType = $sectionType;
		$translateRecord->fieldsProcessed = 0;
		$translateRecord->fieldsError = 0;
		$translateRecord->entriesSubmitted = 0;
		$translateRecord->fieldsSkipped = 0;
		$translateRecord->fieldsTranslated = 0;
		$translateRecord->override = $override?1:0;
		$translateRecord->jobIds = '';
		$translateRecord->save();
		//Craft::dump($translateRecord->id);
		$entries = Entry::find()
		                ->sectionId( $sectionId )
		                ->typeId($sectionType)
		                ->siteId( $primarySiteId );
		$entries = $this->_plugin->translate->setBatchLimit($entries);
		$items = $fields = 0;
		$jobIds = [];
		foreach ($entries as $entry){
			$batch = [];
			foreach ($entry as $b){
				$batch[]=$b->id;

				$fields += $this->_plugin->translate->getEntryFieldsCount($b,$enabledFields);

			}


			$items+=count($batch);
			$jobId = \craft\helpers\Queue::push(
				new translateEntries([
					'entriesIds' => $batch,
					'translateToSiteId' => $translate_to,
					'enabledFields' => $enabledFields,
					'instructions' => $instructions,
					'translationId'=>$translateRecord->id
				]),10,$this->_getDelay()
			);
			if($jobId){
				$jobIds[]=$jobId;
			}

		}
		$translateRecord->entriesSubmitted = $items;

		$translateRecord->fieldsCount = $fields;

		$translateRecord->jobIds = join(',',$jobIds);

		$translateRecord->save();
		
		Craft::$app->session->setNotice( Craft::t(
			'convergine-contentbuddy',
			'translationStarted' ) );
		return $this->redirectToPostedUrl();
	}

	public function actionRerun(){

		$request = \Craft::$app->getRequest();
		//Craft::dump($request->post());

		$translationId = $request->getParam('translationId');
		//Craft::dump($translationId);

		$translationRecord = TranslateRecord::findOne(['id'=>$translationId]);

		$translationLogs = TranslateLogRecord::find()
		                   ->where(['translationId'=>$translationId])
		                   ->groupBy(['entryId']);
		$translationLogs = $this->_plugin->translate->setBatchLimit($translationLogs);

		$jobIds = [];

		foreach ($translationLogs as $entry){
			$batch = [];
			foreach ($entry as $b){

				$batch[]=$b->entryId;

			}


			$jobId = \craft\helpers\Queue::push(
				new translateEntries([
					'entriesIds' => $batch,
					'translateToSiteId' => $translationRecord->siteId,
					'enabledFields' => json_decode($translationRecord->fields,true),
					'instructions' => $translationRecord->instructions,
					'translationId'=>$translationId,
					'isRerun'=>true
				]),10,$this->_getDelay()
			);
			if($jobId){
				$jobIds[]=$jobId;
			}
		}
		$translationRecord->jobIds = join(',',$jobIds);

		$translationRecord->save();

		Craft::$app->session->setNotice( Craft::t(
			'convergine-contentbuddy',
			'translationStarted' ) );
		return $this->redirectToPostedUrl();
	}

	public function actionLog(){
		$request = \Craft::$app->getRequest();
		$id = $request->getParam('id');
		$pageId = $request->getParam('pageId',1);
		$itemsPerPage = 5;
		$translationRecord = TranslateRecord::findOne($id);
		if(!$translationRecord){
			Craft::$app->session->setError('Record not found');
			return $this->redirect('convergine-contentbuddy/site-translate');
		}

		$translationLogsCount = (new TranslateLogRecord())->getTranslationLogsCount($translationRecord->id);
		$translationLogs = (new TranslateLogRecord())->getTranslationLogs($translationRecord->id,$pageId,$itemsPerPage);

		return $this->renderTemplate('convergine-contentbuddy/translate/_log',
			[
				'translationId'=>$translationRecord->id,
				'translationDate'=>$translationRecord->dateCreated,
				'translationSection'=> version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? Craft::$app->entries->getSectionById($translationRecord->sectionId)->name : Craft::$app->sections->getSectionById($translationRecord->sectionId)->name,
				'translationTo'=>Craft::$app->sites->getSiteById($translationRecord->siteId)->getName(),

				'translationLogs'=>$translationLogs,
				'itemsPerPage'=>$itemsPerPage,
				'pages'=>ceil($translationLogsCount/$itemsPerPage)
			]);
	}

	public function actionDelete(){
		$request = \Craft::$app->getRequest();
		$id = $request->getParam('id');
		$translation = TranslateRecord::findOne(['id'=>$id]);
		if($translation && (new TranslateLogRecord())->removeByTranslation($id) !== false){
			$translation->delete();
			if($request->getAcceptsJson()){
				return $this->asJson(['res'=>true]);
			}
			Craft::$app->session->setNotice('Record removed');
		}

		return $this->redirect('convergine-contentbuddy/site-translate');
	}

	private function _getDelay(){
		// set delay for GPT4 for 70 seconds to prevent limits
		$delay = $this->_plugin->base->isGTP4()?70:0;
		$delay = 0;
		return $delay;
	}
}