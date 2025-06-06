<?php

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\api\text\DeepL;
use convergine\contentbuddy\api\text\OpenAi;
use convergine\contentbuddy\api\text\XAi;
use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use Craft;

class Request {
	protected SettingsModel $settings;

	public function __construct() {
		/** @var SettingsModel $settings */
		$settings       = BuddyPlugin::getInstance()->getSettings();
		$this->settings = $settings;
	}

	public function send( $prompt, $maxTokens, $temperature, $isTranslate = false, $instructions = '', $lang='') {
		$textAi = $isTranslate ? $this->settings->translationAi:$this->settings->textAi;
		if ( $textAi == 'deepl' ) {
			$textApi = new DeepL();
		}elseif ( $textAi == 'xai' ) {
			$textApi = new XAi();
		} else { //default to openai
			$textApi = new OpenAi();
		}

        if(is_array($prompt)) {
            if($this->hasHtmlTags($prompt[1])) {
                $chunks = $this->splitHtmlBySentences($prompt[1], $this->getMinSentencesPerChunk($lang));
                Craft::info('Splitting request into chunks ('.count($chunks).')', 'content-buddy');
                $return = '';
                foreach($chunks as $i => $chunk) {
                    Craft::info('Chunk '.$i.': '.$chunk, 'content-buddy');
                    $return .= $textApi->sendRequest($prompt[0].$chunk, $maxTokens, $temperature, $isTranslate, $instructions, $lang);
                }
                return $return;
            } else {
                return $textApi->sendRequest($prompt[0].$prompt[1], $maxTokens, $temperature, $isTranslate, $instructions, $lang);
            }
        } else {
            return $textApi->sendRequest($prompt, $maxTokens, $temperature, $isTranslate, $instructions, $lang);
        }
	}

    private function hasHtmlTags($text): bool {
        return preg_match('/<([a-zA-Z0-9-]+)(\s|>)/', $text) === 1;
    }

    private function getMinSentencesPerChunk($lang): int {
        return match ($lang) {
            'gv' => 3,
            'nl-NL', 'nl', 'sl' => 100,
            default => 40,
        };
    }

    private function splitHtmlBySentences(string $html, int $minSentencesPerChunk): array {
        if(!class_exists('DOMDocument')) {
            return [$html];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $chunks = [];
        $sentenceBuffer = '';
        $sentenceCount = 0;

        foreach($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $htmlNode = $dom->saveHTML($node);

            if($node->nodeType === XML_ELEMENT_NODE && in_array($node->nodeName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $text = $node->textContent;
                $sentences = $this->splitIntoSentences($text);
                $sentenceBuffer .= $htmlNode;
                $sentenceCount += count($sentences);
            } else {
                $sentenceBuffer .= $htmlNode;
            }

            if($sentenceCount >= $minSentencesPerChunk) {
                $chunks[] = $sentenceBuffer;
                $sentenceBuffer = '';
                $sentenceCount = 0;
            }
        }

        if(!empty(trim($sentenceBuffer))) {
            $chunks[] = $sentenceBuffer;
        }

        return $chunks;
    }

    private function splitIntoSentences($text): array {
        return preg_split('/(?<=[.?!])\s+(?=[A-Z0-9])/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}
