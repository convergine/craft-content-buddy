<?php
namespace convergine\contentbuddy\variables;


 use convergine\contentbuddy\BuddyPlugin;

 class BuddyVariable{

	 public function getPrompts(bool $enabled = false): array{
		 return BuddyPlugin::getInstance()->promptService->getPrompts($enabled);
	 }
 }