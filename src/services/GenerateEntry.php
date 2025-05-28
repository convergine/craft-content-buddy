<?php

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\models\EntryType;
use craft\models\Section;
use Exception;

class GenerateEntry extends Component {
    public function generate(Element $mainElement, string $entriesHandle, string $entriesName) : array {
        $nestedElementsIds = [];
        $nestedElementsTitles = [];
        $query = $mainElement->getFieldValue($entriesHandle);
        foreach($query->all() as $nestedElement) {
            $nestedElementsIds[] = $nestedElement->id;
            $nestedElementsTitles[] = $nestedElement->title;
        }

        $section = $this->getSection($entriesHandle);
        $entryType = $this->getEntryType($section);

        $titlePrompt = 'Give me a new title for a "'.$entriesName.'" "'.$section->name.'" entry, ';
        if(!empty($nestedElementsTitles)) {
            $titlePrompt .= 'not related to any of these existing values ('.implode(', ', $nestedElementsTitles).'), ';
        }
        $titlePrompt .= 'only return the title text, based on these parent fields:';

        $hasParentFields = false;

        if($mainElement->title) {
            $titlePrompt .= "\n\nTitle:\n".$mainElement->title;
            $hasParentFields = true;
        }

        $mainElementFields = $mainElement->getFieldLayout()->getCustomFields();
        foreach($mainElementFields as $mainElementField) {
            if(in_array(get_class($mainElementField), BuddyPlugin::getInstance()->base->getSupportedFieldTypes())) {
                $value = $mainElement->getFieldValue($mainElementField->handle);
                if(!empty($value)) {
                    $titlePrompt .= "\n\n".$mainElementField->name.":\n".$value;
                    $hasParentFields = true;
                }
            }
        }

        if(!$hasParentFields) {
            throw new Exception(Craft::t('convergine-contentbuddy', "All fields are empty"));
        }

        $responses = [];

        $titleResponse = BuddyPlugin::getInstance()->request->send($titlePrompt, $this->countWords($titlePrompt), 0.7, false, "Only return the text, no other information");
        $titleResponse = $this->trim($titleResponse);
        $responses[] = $titleResponse;

        $newNestedElement = new Entry();
        $newNestedElement->sectionId = $section->id;
        $newNestedElement->typeId = $entryType->id;
        $newNestedElement->siteId = $mainElement->siteId;
        $newNestedElement->postDate = $mainElement->postDate;
        $newNestedElement->expiryDate = $mainElement->expiryDate;
        $newNestedElement->enabled = $mainElement->enabled;
        $newNestedElement->authorId = $mainElement->authorId;
        $newNestedElement->title = $titleResponse;
        $newNestedElement->slug = ElementHelper::generateSlug($titleResponse);

        foreach($entryType->getFieldLayout()->getCustomFields() as $field) {
            if(in_array(get_class($field), BuddyPlugin::getInstance()->base->getSupportedFieldTypes())) {
                $prompt = 'Write me a paragraph or more for a "'.$section->name.'" "'.$field->name.'" field based on this text: '.$titleResponse;
                $response = BuddyPlugin::getInstance()->request->send($prompt, 2000, 0.7, false, "Only return the text, no other information");
                $response = $this->trim($response);
                $newNestedElement->setFieldValue($field->handle, $response);
                $responses[] = $response;
            }
        }

        Craft::$app->elements->saveElement($newNestedElement, false, false);

        $nestedElementsIds[] = $newNestedElement->id;
        $mainElement->setFieldValue($entriesHandle, $nestedElementsIds);
        Craft::$app->elements->saveElement($mainElement, false, false);

        return [
            'prompt' => $titlePrompt,
            'responses' => $responses,
        ];
    }

    private function getSection($entriesHandle) : Section {
        $field = Craft::$app->fields->getFieldByHandle($entriesHandle);

        if(version_compare( Craft::$app->getInfo()->version, '5.0', '>=' )) {
            $sections = Craft::$app->entries;
        } else {
            $sections = Craft::$app->sections;
        }

        $sources = $field->sources;
        $firstSource = $sources[0];

        if($firstSource == '*') {
            $section = $sections->getAllSections()[0];
        } else {
            $sectionUid = explode(':', $firstSource)[1];
            $section = $sections->getSectionByUid($sectionUid);
        }

        if (!$section) {
            throw new Exception(Craft::t('convergine-contentbuddy', "Section not found"));
        }
        return $section;
    }

    private function getEntryType(Section $section) : EntryType {
        $entryTypes = $section->getEntryTypes();
        if (count($entryTypes) === 0) {
            throw new Exception(Craft::t('convergine-contentbuddy', "No entry types found for section"));
        }
        return $entryTypes[0];
    }

    private function countWords($str) : int {
        return count(preg_split('~[^\p{L}\p{N}\']+~u', $str));
    }

    private function trim($response) : string {
        return preg_replace('/^[\s\n\r\t"\'“”]+|[\s\n\r\t"\'“”]+$/u', '', $response);
    }
}
