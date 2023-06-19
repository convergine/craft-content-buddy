<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 5/4/2023
 * Time: 3:32 PM
 */

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\PromptModel;
use convergine\contentbuddy\records\BuddyPromptRecord;

class PromptsController extends \craft\web\Controller {
	public function actionIndex() {
		return $this->renderTemplate( 'convergine-contentbuddy/prompts/_index', [
			'prompts' => BuddyPromptRecord::find()->all()
		] );
	}

	public function actionCreate() {
		return $this->renderTemplate( 'convergine-contentbuddy/prompts/_new', [
			'prompt' => new PromptModel()
		] );
	}

	public function actionEdit($id) {
		$prompt = BuddyPromptRecord::findOne( $id );
		if(!$prompt){
			throw new \yii\web\NotFoundHttpException("Prompt not found");
		}
		return $this->renderTemplate( 'convergine-contentbuddy/prompts/_edit', [
			'prompt' => $prompt
		] );
	}

	public function actionSave() {
		$request = \Craft::$app->getRequest();

		$promptModel = new PromptModel();
		$promptModel->id = $request->getParam( 'id' )??0;
		$promptModel->label = $request->getRequiredParam( 'label' );
		$promptModel->template = $request->getRequiredParam( 'template' );
		$promptModel->active = $request->getRequiredParam( 'active' );
		$promptModel->replaceText = $request->getRequiredParam( 'replaceText' );


		$promptModel->temperature = $request->getRequiredParam( 'temperature' );
		$promptModel->wordsType = $request->getRequiredParam( 'wordsType' );
		$promptModel->wordsNumber = $request->getRequiredParam( 'wordsNumber' );
		$promptModel->wordsMultiplier = $request->getRequiredParam( 'wordsMultiplier' );

		if ( ! $promptModel->validate() ) {
			return $this->renderTemplate( 'convergine-contentbuddy/prompts/_edit', [
				'prompt' => $promptModel
			] );
		}


		if ( BuddyPlugin::getInstance()->promptService->save($promptModel)) {
			\Craft::$app->getSession()->setNotice(\Craft::t('convergine-contentbuddy', "Prompt updated"));
			return $this->redirect( 'convergine-contentbuddy/prompts' );
		}

		return $this->renderTemplate( 'convergine-contentbuddy/prompts/_new', [
			'prompt' => $promptModel
		] );
	}

	public function actionRemove(int $id){
		if(BuddyPlugin::getInstance()->promptService->remove($id)){
			\Craft::$app->getSession()->setNotice(\Craft::t('convergine-contentbuddy', "Prompt removed"));
		}
		return $this->redirect( 'convergine-contentbuddy/prompts' );
	}
}