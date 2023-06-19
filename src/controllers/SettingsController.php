<?php

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use yii\web\Response;

class SettingsController extends \craft\web\Controller
{
	/**
	 * @return Response
	 */
	public function actionGeneral(): Response
	{
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate('convergine-contentbuddy/settings/_general', [
			'settings' => $settings,
		]);
	}

	/**
	 * @return Response
	 */
	public function actionApi(): Response
	{
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate('convergine-contentbuddy/settings/_api', [
			'settings' => $settings,
		]);
	}

	/**
	 * @return Response
	 */
	public function actionFields(): Response
	{
		$settings = BuddyPlugin::getInstance()->getSettings();

		return $this->renderTemplate('convergine-contentbuddy/settings/_fields', [
			'settings' => $settings,
		]);
	}
}