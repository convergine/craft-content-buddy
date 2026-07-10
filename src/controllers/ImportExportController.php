<?php

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\services\ImportExport;
use Craft;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use craft\web\UploadedFile;

class ImportExportController extends Controller {
	/**
	 * The export file contains every plugin setting in plain text, including API
	 * keys, so import/export is restricted to admins. requireAdmin(false) still
	 * allows it when allowAdminChanges is disabled, matching how the plugin lets
	 * admins manage settings from the database in that mode.
	 */
	public function beforeAction( $action ): bool {
		if ( ! parent::beforeAction( $action ) ) {
			return false;
		}

		$this->requireAdmin( false );

		return true;
	}

	public function actionIndex() {

		return $this->renderTemplate( 'convergine-contentbuddy/import_export/_index',
			[
				'settings_list' => ImportExport::SETTINGS_LIST,
			] );
	}

	public function actionExport() {
		$settings = $this->request->post( 'export' );

		$exportData = BuddyPlugin::getInstance()->importExport->export( $settings );
		$json       = json_encode( $exportData );
		$fileName = "content-buddy-settings-".ImportExport::$version."_".DateTimeHelper::now()->format('YmdHi').".json";
		return $this->response->sendContentAsFile($json,$fileName);

	}

	public function actionImport() {
		$uploadedFile = UploadedFile::getInstanceByName('importFile');
		if(!$uploadedFile){
			Craft::$app->getSession()->setFlash('errorsList',['Please, select file to import']);
			return $this->asFailure('Settings not imported');
		}
		if($uploadedFile && $uploadedFile->type!='application/json'){
			Craft::$app->getSession()->setFlash('errorsList',['Incorrect import file extension']);
			return $this->asFailure('Settings not imported');
		}
		$fileContent = file_get_contents($uploadedFile->tempName);
		if($fileContent && ($json = json_decode($fileContent,true))){
			try{
				BuddyPlugin::getInstance()->importExport->import( $json );
			}catch (\Throwable $e){

				Craft::$app->getSession()->setFlash('errorsList',[$e->getMessage()]);
				return $this->asFailure('Settings not imported');
			}

		}
		return $this->asSuccess('Settings have been successfully imported',[]);
	}
}
