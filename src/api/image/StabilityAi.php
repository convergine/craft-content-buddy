<?php

namespace convergine\contentbuddy\api\image;

use convergine\contentbuddy\api\ImageApi;
use Craft;
use GuzzleHttp\Client;

class StabilityAi extends ImageApi {
    function sendRequest($prompt, $folderUID) : array {
        $image_size = $this->settings->imageSizeStability;

        $_dimension = explode('x',$image_size);
        $width = (int)$_dimension[0];
        $height = (int)$_dimension[1];

        $client = new Client();

        $model = $this->settings->stabilityEngine;
        $apiEndpoint = $this->getStabilityApiEndpoint($model);
        $apiData = $this->getStabilityApiData($model,$width,$height,$prompt);

        $imageResponse = $client->request( 'POST', 'https://api.stability.ai'.$apiEndpoint, $apiData);

        $body = $imageResponse->getBody();
        $json = json_decode( $body, true );
        Craft::info("ContentBuddy Image Result: " . $body, __METHOD__);

        $assets = array();

        if($this->isStableDiffusion3($model)) {
            $image = $json['image'] ?? null;
            $asset = $this->uploadFileData( $folderUID, $image, $image_size, $prompt );
            if ( $asset ) {
                $assets[] = $asset;
            }
        } else {
            $data = $json['artifacts'] ?? [];
            foreach ( $data as $image ) {
                $asset = $this->uploadFileData( $folderUID, $image['base64'], $image_size, $prompt );
                if ( $asset ) {
                    $assets[] = $asset;
                }
            }
        }

        return $assets;
    }

    private function isStableDiffusion3($model) : bool {
        return in_array($model, ['sd3','core','ultra']);
    }

    private function getStabilityApiEndpoint($model) : string {
        if($this->isStableDiffusion3($model)) {
            return "/v2beta/stable-image/generate/$model";
        } else {
            return "/v1/generation/$model/text-to-image";
        }
    }

    private function getStabilityApiData($model,$width,$height,$prompt) : array {
        if($this->isStableDiffusion3($model)) {
            return [
                'multipart' => [
                    [
                        'name' => 'prompt',
                        'contents' => $prompt,
                    ],
                    [
                        'name' => 'width',
                        'contents' => $width,
                    ],
                    [
                        'name' => 'height',
                        'contents' => $height,
                    ],
                    [
                        'name' => 'sampler',
                        'contents' => $this->settings->stabilitySampler,
                    ],
                    [
                        'name' => 'steps',
                        'contents' => $this->settings->stabilitySteps,
                    ],
                    [
                        'name' => 'cfg_scale',
                        'contents' => $this->settings->stabilityScale,
                    ],
                    [
                        'name' => 'seed',
                        'contents' => 0,
                    ],
                    [
                        'name' => 'style_preset',
                        'contents' => $this->settings->stabilityStyle,
                    ],
                    [
                        'name' => 'samples',
                        'contents' => 1,
                    ],
                ],
                'headers' => [
                    'Authorization' => "Bearer ".$this->settings->getStabilityApiKey(),
                    'Accept'  => 'application/json',
                ],
            ];
        } else {
            return [
                'body'    => json_encode([
                    'samples' => 1,
                    'width' => $width,
                    'height' => $height,
                    'sampler' => $this->settings->stabilitySampler,
                    'steps' => $this->settings->stabilitySteps,
                    'cfg_scale' => $this->settings->stabilityScale,
                    'seed' => 0,
                    'style_preset' => $this->settings->stabilityStyle,
                    'text_prompts' => [
                        [
                            'text' => $prompt,
                            'weight' => 1,
                        ],
                    ]
                ]),
                'headers' => [
                    'Authorization' => $this->settings->getStabilityApiKey(),
                    'Content-Type'  => 'application/json',
                ],
            ];
        }
    }
}
