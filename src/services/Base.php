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
use craft\errors\InvalidPluginException;
use ReflectionClass;

class Base {

	public function isGTP4(){
		$model = BuddyPlugin::getInstance()->getSettings()->preferredModel;
		return strpos( $model, 'gpt-4' ) === 0;
	}

	public function checkLicenseMsg(): string {
        $handle = 'convergine-contentbuddy';
		if(!$this->isPluginLicenseValid($handle)) {
			return '<div class="buddy-alert error-license" style="display:block;">'.Craft::t($handle,'licenseNotice').'</div>';
		}
		return '';
	}

    private function getPluginLicenseKeyStatus($handle): string {
        try {
            $info = Craft::$app->plugins->getStoredPluginInfo($handle);
            if(!isset($info['licenseKeyStatus'])) {
                $info = Craft::$app->plugins->getPluginInfo($handle);
            }
            return $info['licenseKeyStatus'] ?? $this->getLicenseKeyStatusString(LicenseKeyStatus::Unknown);
        } catch (InvalidPluginException) {}
        return $this->getLicenseKeyStatusString(LicenseKeyStatus::Unknown);
    }

    private function isPluginLicenseValid($handle): bool {
        $status = $this->getPluginLicenseKeyStatus($handle);
        return $this->isLicenseKeyStatusEqual($status, LicenseKeyStatus::Valid);
    }

    private function isLicenseKeyStatusEqual($status, $enumStatus) : bool {
        return version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? $status === $enumStatus->value : $status === $enumStatus;
    }

    private function getLicenseKeyStatusString($status) : string {
        return version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? $status->value : $status;
    }

	public function getSupportedFieldTypes(): array{
		return [
			'craft\fields\PlainText',
			'craft\redactor\Field',
			'craft\ckeditor\Field',
			'abmat\tinymce\Field',
            'ether\seo\fields\SeoField',
            'verbb\hyper\fields\HyperField'
		];
	}

    public function isSupportedFieldType($field) : bool {
        return in_array((new ReflectionClass($field))->getName(), $this->getSupportedFieldTypes());
    }
}