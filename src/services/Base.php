<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/22/2023
 * Time: 1:38 PM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use Craft;
use craft\enums\LicenseKeyStatus;
use ReflectionClass;

class Base {

	public function isGTP4(){
		$model = BuddyPlugin::getInstance()->getSettings()->preferredModel;
		return strpos( $model, 'gpt-4' ) === 0;
	}

	public function checkLicenseMsg(){
		$status = \Craft::$app->plugins->getPluginLicenseKeyStatus('convergine-contentbuddy');
		// Handle different license statuses
		if (in_array($status,[
			LicenseKeyStatus::Trial,
			LicenseKeyStatus::Invalid,
			LicenseKeyStatus::Mismatched,
			LicenseKeyStatus::Astray,
			LicenseKeyStatus::Unknown
		])) {

			return '<div class="buddy-alert error" style="display:block;">
					'.Craft::t('convergine-contentbuddy','licenseNotice').'
				</div>';

		}
		return '';
	}

	public function getSupportedFieldTypes(): array{
		return [
            //'craft\fieldlayoutelements\entries\EntryTitleField',
			'craft\fields\PlainText',
			'craft\redactor\Field',
            'craft\ckeditor\Field'
		];
	}

    public function isSupportedFieldType($field) : bool {
        return in_array((new ReflectionClass($field))->getName(), $this->getSupportedFieldTypes());
    }
}