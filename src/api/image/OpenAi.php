<?php

namespace convergine\contentbuddy\api\image;

use convergine\contentbuddy\api\ImageApi;
use convergine\contentbuddy\BuddyPlugin;
use GuzzleHttp\Client;

class OpenAi extends ImageApi {
    function sendRequest($prompt, $folderUID) : array {
        $model = $this->settings->dalleModel;
        $image_size = $model == 'dall-e-3' ? $this->settings->imageSizeDalle3 : $this->settings->imageSize;

        $client = new Client();

        $imagePrompt = $this->applyImageStylesToPrompt( $prompt );

        $imageResponse = $client->request( 'POST', 'https://api.openai.com/v1/images/generations', [
            'body'    => json_encode( [
                'model'           => $model,
                'prompt'          => $imagePrompt,
                'n'               => 1,
                'size'            => $image_size,
                'response_format' => 'b64_json',
            ] ),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->settings->getOpenAiApiKey(),
                'Content-Type'  => 'application/json',
            ],
        ] );

        $body = $imageResponse->getBody();
        $json = json_decode( $body, true );
        $data = $json['data'] ?? [];

        $assets = array();
        foreach ( $data as $image ) {
            $asset = $this->uploadFileData( $folderUID, $image['b64_json'], $image_size, $imagePrompt );
            if ( $asset ) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    private function applyImageStylesToPrompt($prompt): array|string {
        $stylesArray = $this->settings->imagesStyles;
        $stylesArray = explode( "\n", $stylesArray );
        $imagePrompt = rtrim( rtrim( $prompt ), '.' );

        if ( ! empty( $stylesArray ) ) {
            $style       = $stylesArray[ array_rand( $stylesArray ) ];
            $imagePrompt .= ', ' . $style;
        }

        $imagePrompt = str_replace( '"', '', $imagePrompt );

        return str_replace( "'", '', $imagePrompt );
    }
}
