<?php

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\api\text\OpenAiAssistant;
use convergine\contentbuddy\api\text\OpenRouter;
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
	public function actionTextGeneration(): Response {
		$settings = BuddyPlugin::getInstance()->getSettings();


		return $this->renderTemplate( 'convergine-contentbuddy/settings/_api', [
			'settings' => $settings,
			'openRouteModels'=>$this->_getOpenRouteModels(false)
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
				$assets_folders[] = [ 'value' => $folder->uid, 'label' => $folder->name ];
			}
		}

		return $this->renderTemplate( 'convergine-contentbuddy/settings/_image', [
			'settings' => $settings,
			'folders'  => $assets_folders,
			'openRouterModels' => $this->_getOpenRouteModels(true)
		] );
	}

    /**
     * @return Response
     */
    public function actionTranslation(): Response {
        $settings       = BuddyPlugin::getInstance()->getSettings();
		  $assistants = [[
			  'label'=>'--',
			  'value'=>''
		  ]];
		  $assistantsSupported = $this->_assistantsSupported($settings->preferredTranslationModel);
		  if($settings->apiToken && $assistantsSupported){
			  try {
				  $_assistants = (new OpenAiAssistant())->getAssistants();
				  if(isset($_assistants['data'])) {
					  foreach ( $_assistants['data'] as $as ) {
						  $name = $as['name']?:$as['id'];
						  $assistants[] = [
							  'label' => $name,
							  'value' => $as['id']
						  ];
					  }
				  }
			  } catch (\Throwable $e) {
				  Craft::warning('Failed to load OpenAI assistants: ' . $e->getMessage(), 'content-buddy');
			  }
		  }
        return $this->renderTemplate( 'convergine-contentbuddy/settings/_translation', [
            'settings' => $settings,
	         'assistants'=>$assistants,
	         'assistantsSupported'=>$assistantsSupported,
            'openRouteModels'=>$this->_getOpenRouteModels(false)
        ] );
    }

    /**
     * The Assistants API does not support the GPT-5 family.
     */
    private function _assistantsSupported(string $model): bool {
        return !str_starts_with($model, 'gpt-5');
    }

    /**
     * @return Response
     */
    public function actionNewsletter(): Response {
        $settings = BuddyPlugin::getInstance()->getSettings();
        return $this->renderTemplate( 'convergine-contentbuddy/settings/_newsletter', [
            'settings'     => $settings,
            'isCraft5'     => version_compare(Craft::$app->getInfo()->version, '5.0', '>=')
        ] );
    }

	public function actionSaveSettings() : ?Response {
		$post     = $this->request->post();
		$settings = BuddyPlugin::getInstance()->getSettings();
		$success = $settings->saveSettings( $post['settings'] );
		return $success ?
			$this->asSuccess(Craft::t('app', 'Plugin settings saved.')) :
			$this->asFailure(
				Craft::t('app', 'Couldn’t save plugin settings.')/*,
				routeParams: ['plugin' => $plugin]*/
			);
	}

	private function _getOpenRouteModels($withImage = false) {
		$settings = BuddyPlugin::getInstance()->getSettings();
		$openRouteModels = [[
			'label'=>'--',
			'value'=>''
		]];
		if($settings->openrouterApiKey){
			$models = (new OpenRouter())->getModels();

			if(isset($models)) {
				foreach ( $models as $as ) {
					if($withImage &&
					   (!isset($as['architecture']['output_modalities'])
					   || !is_array($as['architecture']['output_modalities'])
						|| !in_array('image',$as['architecture']['output_modalities'])
					    )
					){
						continue;
					}else{
						$name = $as['name']?:$as['id'];
						$openRouteModels[] = [
							'label' => $name,
							'value' => $as['id']
						];
					}

				}
			}

		}
		return $openRouteModels;
	}
}
