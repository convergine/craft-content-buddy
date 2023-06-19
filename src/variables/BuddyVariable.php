<?php
namespace convergine\contentbuddy\variables;


 use convergine\contentbuddy\BuddyPlugin;

 class BuddyVariable{
	 /**
	  * @throws \ReflectionException
	  */
	 public function getClass($object)
	 {
		 return str_replace([
			 'craft\fields\\',
			 'craft\redactor\\'
		 ],'',(new \ReflectionClass($object))->getName());
	 }

	 public function getPrompts(bool $enabled = false): array{
		 return BuddyPlugin::getInstance()->promptService->getPrompts($enabled);
	 }
 }