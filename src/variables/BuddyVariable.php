<?php
namespace convergine\contentbuddy\variables;


 use convergine\contentbuddy\BuddyPlugin;
 use convergine\contentbuddy\records\TranslateRecord;
 use craft\queue\Queue;
 use Craft;

 class BuddyVariable{

	 public function getPrompts(bool $enabled = false): array{
		 return BuddyPlugin::getInstance()->promptService->getPrompts($enabled);
	 }

	 public function checkLicense(){
		 return BuddyPlugin::getInstance()->base->checkLicenseMsg();
	 }

	 public function getJobsInfo($translateId){

		 return BuddyPlugin::getInstance()->translate->getJobsInfo($translateId);
	 }

	 public function getTranslationStatus($translateId){
		 return BuddyPlugin::getInstance()->translate->getTranslationStatus($translateId);
	 }

	 public function hasActiveJobs($translateId){
		 return BuddyPlugin::getInstance()->translate->hasActiveJobs($translateId);
	 }
 }