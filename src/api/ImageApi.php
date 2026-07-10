<?php

namespace convergine\contentbuddy\api;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use Craft;
use craft\elements\Asset;
use Exception;

abstract class ImageApi {
    protected SettingsModel $settings;

    public function __construct() {
        /** @var SettingsModel $settings */
        $settings = BuddyPlugin::getInstance()->getSettings();
        $this->settings = $settings;
    }

    function sendRequest($prompt, $folderUID) {}

    protected function uploadFileData($folderUID, $imageData, $dimensions, $imagePrompt ): Asset {
        $imagePromptWithOnlyLetters = preg_replace( '/[^A-Za-z0-9\- ]/', '', $imagePrompt );
        $imagePromptWithOnlyLetters = str_replace( ' ', '-', $imagePromptWithOnlyLetters );
        $imagePromptWithOnlyLetters = substr( $imagePromptWithOnlyLetters, 0, 40 );
        $imagePromptWithOnlyLetters = strtolower( $imagePromptWithOnlyLetters );

        $folder = is_numeric($folderUID)?Craft::$app->getAssets()->getFolderById($folderUID):Craft::$app->getAssets()->getFolderByUid( $folderUID );
	    if(strpos($imageData,'base64')!== false){
		    $imageData = explode(',',$imageData)[1];
	    }
        // Stage the image in the system temp dir rather than inside the volume. Only
        // craft\fs\Local exposes a 'path' setting; any external filesystem (S3,
        // DigitalOcean Spaces) has none. Craft uploads Asset::$tempFilePath through
        // the volume's filesystem, so this works for every filesystem type.
        $tmpFilePath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'image' . rand( 9999, 9999999 );
        $outputStream = fopen( $tmpFilePath, 'wb' );
        fwrite( $outputStream, base64_decode( $imageData ) );

        fclose( $outputStream );
        $mime_type = mime_content_type( $tmpFilePath );
        $extension = explode( '/', $mime_type )[1];

        $newFilename                   = $imagePromptWithOnlyLetters . '-' . $dimensions . '-' . rand( 0, 99999999 ) . '.' . $extension;
        $asset                         = new Asset();
        $asset->tempFilePath           = $tmpFilePath;
        $asset->filename               = $newFilename;
        $asset->newFolderId            = $folder->id;
        $asset->volumeId               = $folder->volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario( Asset::SCENARIO_CREATE );

        $result = Craft::$app->getElements()->saveElement( $asset );

        // In case of error, let user know about it.
        if ( $result === false ) {
            throw new Exception( 'Error while uploading asset' );
        }

        return $asset;
    }
}
