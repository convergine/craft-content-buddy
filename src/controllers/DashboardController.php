<?php
namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\services\ContentGenerator;
use Craft;
use craft\web\Controller;

class DashboardController extends Controller {

	public function actionIndex() {
		$sections = [];
        $_sections = version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? Craft::$app->entries->getAllSections() : Craft::$app->sections->getAllSections();

		foreach ($_sections as $section){
			if($section->type == 'channel'){

				foreach ($section->getEntryTypes() as $type){
					$entry_data = [
						'entry_name'=>$section->name,
						'entry_id'=>$section->id,
						'entry_type_name'=>$type->name,
						'entry_type_id'=>$type->id,
						'fields'=>[],
						'image_fields'=>[],
					];
					$layout = $type->getFieldLayout();

					foreach($layout->getTabs() as $tab){
						foreach ($tab->getElements() as $fieldCont){

							if(get_class($fieldCont)==='craft\fieldlayoutelements\CustomField'){
								$field = $fieldCont->getField();

								if(in_array(get_class($field),[
									'craft\fields\PlainText',
									'craft\redactor\Field',
                                    'craft\ckeditor\Field'
								])){
									$entry_data['fields'][]=[
										'name'=>$field->name,
										'handle'=>$field->handle,
									];

								}elseif (in_array(get_class($field),[
									'craft\fields\Assets'
								])){
									$entry_data['image_fields'][]=[
										'name'=>$field->name,
										'handle'=>$field->handle,
									];
								}

							}

						}
					}
					$sections[]=$entry_data;
				}

			}
		}
		$assets_folders=[['value'=>'','label'=>'Please Select']];
		$_volumes = Craft::$app->getVolumes()->getAllVolumes();

		foreach ($_volumes as $volume){
			$_assets_folders = Craft::$app->getAssets()->findFolders([
				'volumeId'=>$volume->id
			]);
			foreach ($_assets_folders as $folder){
				$assets_folders[]=['value'=>$folder->id,'label'=>$folder->name];
			}
		}
		$sites = [];
		foreach (Craft::$app->sites->getAllSites() as $site){
			$sites[$site->id] = $site->name;
		}

		return $this->renderTemplate('convergine-contentbuddy/dashboard/_index',
			[
				'sections'=>$sections,
				'folders'=>$assets_folders,
				'sites'=>$sites,
				'settings'=>BuddyPlugin::getInstance()->getSettings(),
				'min_execution_time_alert'=>ContentGenerator::getTimeLimitAlert()
			]);
	}

	public function actionGenerate() {
		$this->requirePostRequest();
		$data = $this->request->post();

		$result = BuddyPlugin::getInstance()->contentGenerator->generateEntry($data);

		$request = Craft::$app->getRequest();
		if ($request->getAcceptsJson()) {
			return $this->asJson($result);
		}

		exit();
	}
}
