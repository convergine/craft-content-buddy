<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/8/2023
 * Time: 12:52 PM
 */

namespace convergine\contentbuddy\controllers;

use convergine\contentbuddy\BuddyPlugin;
use Craft;

class PromptProcessorController extends \craft\web\Controller{

	public function actionProcess(){
		$this->requirePostRequest();
		$data = $this->request->post();

		try{
			$result = BuddyPlugin::getInstance()->promptProcessor->process($data);
			return $this->asJson(['res'=>true, 'result'=>$result]);
		}catch (\Throwable $e){
			return $this->asJson(['res'=>false, 'msg'=>$e->getMessage()]);
		}
	}
}