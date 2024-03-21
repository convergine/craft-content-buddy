<?php

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\variables\BuddyVariable;
use yii\web\Response;
use Craft;

class SettingsController extends \craft\web\Controller {
	/**
	 * @return Response
	 */
	public function actionGeneral(): Response {
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate( 'convergine-contentbuddy/settings/_general', [
			'settings' => $settings,
		] );
	}

	/**
	 * @return Response
	 */
	public function actionApi(): Response {
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate( 'convergine-contentbuddy/settings/_api', [
			'settings' => $settings,
		] );
	}

	/**
	 * @return Response
	 */
	public function actionFields(): Response {
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate( 'convergine-contentbuddy/settings/_fields', [
			'settings'     => $settings,
			'fields'       => $settings->getRegularFieldsList(),
			'matrixFields' => $settings->getMatrixFieldsList(),
            'isCraft5'     => version_compare(Craft::$app->getInfo()->version, '5.0', '>=')
		] );
	}

	/**
	 * @return Response
	 */
	public function actionImageGeneration(): Response {
		$settings       = BuddyPlugin::getInstance()->getSettings();
		$assets_folders = [ [ 'value' => '', 'label' => 'Please Select' ] ];
		$_volumes       = Craft::$app->getVolumes()->getAllVolumes();

		foreach ( $_volumes as $volume ) {
			$_assets_folders = Craft::$app->getAssets()->findFolders( [
				'volumeId' => $volume->id
			] );
			foreach ( $_assets_folders as $folder ) {
				$assets_folders[] = [ 'value' => $folder->id, 'label' => $folder->name ];
			}
		}

		return $this->renderTemplate( 'convergine-contentbuddy/settings/_image', [
			'settings' => $settings,
			'folders'  => $assets_folders
		] );
	}
}