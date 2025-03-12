<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    chatgpt.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 8/16/2023
 * Time: 10:07 AM
 */

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\queue\translateEntries;
use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use craft\models\FieldLayout;
use craft\models\Site;
use craft\queue\Queue;
use yii\db\BatchQueryResult;

class Translate extends Component {


	/**
	 * @var BuddyPlugin|null
	 */
	private BuddyPlugin $_plugin;

	static array $matrixFields = [
		'craft\fields\Matrix',
		'benf\neo\Field',
		'verbb\supertable\fields\SuperTableField',
	];

	static array $textFields = [
		'craft\fields\PlainText',
		'craft\redactor\Field',
		'craft\ckeditor\Field',
		'abmat\tinymce\Field',
	];

	public function init(): void {
		$this->_plugin = BuddyPlugin::getInstance();
	}

	public function getSectionFields( $id ) {
		$isCraft5 = version_compare( Craft::$app->getInfo()->version, '5.0', '>=' );

		$_section = explode( ":", $id );
		$section  = $isCraft5 ? Craft::$app->entries->getSectionById( $_section[0] ) : Craft::$app->sections->getSectionById( $_section[0] );
		$type     = $_section[1] ?? 0;
		if ( $type ) {
			$type = $isCraft5 ? Craft::$app->entries->getEntryTypeById( $type ) : Craft::$app->sections->getEntryTypeById( $type );
		} else {
			$type = $section->getEntryTypes()[0];
		}

		$layout = $type->getFieldLayout();

		return $this->_getLayoutFields( $layout );
	}

	private function _getLayoutFields( FieldLayout $layout ) {
		$matrixFields = $fields = [];
		$isCraft5     = version_compare( Craft::$app->getInfo()->version, '5.0', '>=' );

		if ( $isCraft5 ) {
			foreach ( $layout->getTabs() as $tab ) {
				foreach ( $tab->getElements() as $fieldCont ) {
					if ( get_class( $fieldCont ) === 'craft\fieldlayoutelements\CustomField' ) {
						$field = $fieldCont->getField();

						if ( in_array(
							     get_class( $field ),
							     $this->_plugin->base->getSupportedFieldTypes()
						     )
						     && $field->translationMethod != 'none' ) {

							$fields[] = [
								'name'   => $field->name,
								'handle' => $field->handle,
								'id'     => $field->id,
								'group'  => '',
								'type'   => $this->_getClass( $field ),
								'_type'  => ( new \ReflectionClass( $field ) )->getName()
							];

						} else if ( $this->isMatrixField( $field ) ) {
							$this->prepareMatrixField( $field, $matrixFields );
						}
					}
				}
			}
		} else {
			foreach ( $layout->getTabs() as $tab ) {
				foreach ( $tab->getElements() as $fieldCont ) {
					if ( get_class( $fieldCont ) === 'craft\fieldlayoutelements\CustomField' ) {
						$field = $fieldCont->getField();

						if ( in_array(
							     get_class( $field ),
							     $this->_plugin->base->getSupportedFieldTypes()
						     )
						     && $field->translationMethod != 'none' ) {
							$fields[] = [
								'name'   => $field->name,
								'handle' => $field->handle,
								'id'     => $field->id,
								'group'  => $field->getGroup(),
								'type'   => $this->_getClass( $field ),
								'_type'  => ( new \ReflectionClass( $field ) )->getName()
							];

						} else if ( $this->isMatrixField( $field ) ) {
							foreach ( \Craft::$app->getMatrix()->getBlockTypesByFieldId( $field->id ) as $block ) {
								if ( ! isset( $matrixFields [ $field->handle ]['name'] ) ) {
									$matrixFields [ $field->handle ]['name']   = $field->name;
									$matrixFields [ $field->handle ]['handle'] = $field->handle;
									$matrixFields [ $field->handle ]['type']   = 'craft\fields\Matrix';
									$matrixFields [ $field->handle ]['fields'] = [];
								}
								foreach ( \Craft::$app->getFields()->getAllFields( "matrixBlockType:" . $block->uid ) as $blockField ) {

									if ( in_array(
										     ( new \ReflectionClass( $blockField ) )->getName(),
										     $this->_plugin->base->getSupportedFieldTypes() ) && $blockField->translationMethod != 'none'
									) {
										$fullHandle                                  = $block->handle . ':' . $field->handle . ':' . $blockField->handle;
										$matrixFields [ $field->handle ]['fields'][] = [
											'name'        => $blockField->name,
											'handle'      => $blockField->handle,
											'id'          => $blockField->id,
											'blockName'   => $block->name,
											'blockHandle' => $block->handle,
											'type'        => $this->_getClass( $blockField ),
											'_type'       => ( new \ReflectionClass( $blockField ) )->getName(),
											'_field'      => 'craft\\fields\\Matrix:' . $fullHandle,
										];
									}
								}
							}
						}
					}
				}
			}
		}

		return [ 'regular' => $fields, 'matrix' => $matrixFields ];
	}

	public function prepareMatrixField( craft\fields\Matrix $field, &$matrixFields = [], $parentHandle = '' ): void {
		foreach ( $field->getEntryTypes() as $entryType ) {
			if ( ! isset( $matrixFields [ $field->handle . "-" . $entryType->handle ]['name'] ) ) {
				$matrixFields [ $field->handle . "-" . $entryType->handle ]['name']   = $field->name;
				$matrixFields [ $field->handle . "-" . $entryType->handle ]['handle'] = $field->handle;
				$matrixFields [ $field->handle . "-" . $entryType->handle ]['type']   = 'craft\fields\Matrix';
				$matrixFields [ $field->handle . "-" . $entryType->handle ]['fields'] = [];
			}

			foreach ( $entryType->getFieldLayout()->getTabs() as $typeTab ) {
				foreach ( $typeTab->getElements() as $matrixLayoutElement ) {
					if ( get_class( $matrixLayoutElement ) === 'craft\fieldlayoutelements\CustomField' && $this->isMatrixField( $matrixField = $matrixLayoutElement->getField() ) ) {
						$_parentHandle = ( ! empty( $parentHandle ) ? $parentHandle . ':' : '' ) . $entryType->handle . ':' . $field->handle;
						$this->prepareMatrixField( $matrixField, $matrixFields, $_parentHandle );
					} else if ( get_class( $matrixLayoutElement ) !== 'craft\fieldlayoutelements\entries\EntryTitleField' && ! $this->isUIElement( $matrixLayoutElement ) ) {
						/** @var craft\base\Field $matrixField */
						$matrixField                                                            = $matrixLayoutElement->getField();
						$fullHandle                                                             = $entryType->handle . ':' . $field->handle . ":$matrixField->handle";
						$matrixFields [ $field->handle . "-" . $entryType->handle ]['fields'][] = [
							'name'        => $matrixField->name,
							'handle'      => $matrixField->handle,
							'id'          => $matrixField->id,
							'blockName'   => $entryType->name,
							'blockHandle' => $entryType->handle,
							'type'        => $this->_getClass( $matrixField ),
							'_type'       => ( new \ReflectionClass( $matrixField ) )->getName(),
							'_field'      => 'craft\\fields\\Matrix:' . ( ! empty( $parentHandle ) ? $parentHandle . ':' : '' ) . $fullHandle,
						];
					} else if ( get_class( $matrixLayoutElement ) === 'craft\fieldlayoutelements\entries\EntryTitleField' ) {
						$matrixFields [ $field->handle . "-" . $entryType->handle ]['fields'][] = [
							'name'        => 'Title',
							'handle'      => 'title',
							'id'          => null,
							'blockName'   => $entryType->name,
							'blockHandle' => $entryType->handle,
							'type'        => 'Title',
							'_type'       => 'craft\fields\TextField',
							'_field'      => 'craft\\fields\\Matrix:' . ( ! empty( $parentHandle ) ? $parentHandle . ':' : '' ) . $entryType->handle . ':' . $field->handle . ':title',
						];
					}
				}
			}
		}
	}

	private function _fillField( $field ) {

	}

	public function getSites() {
		$sites = [ [ 'value' => '', 'label' => 'Please Select' ] ];
		foreach ( Craft::$app->sites->getAllSites() as $site ) {
			if ( $site->primary ) {
				continue;
			}
			$sites[] = [
				'value' => $site->id,
				'label' => $site->getName() . " (" . $site->language . ")"
			];
		}
		if($this->_plugin->getSettings()->enableBulkTranslation) {
			$sites[] = [
				'value' => 'all',
				'label' => Craft::t( 'convergine-contentbuddy', "To All Languages" )
			];
		}
		return $sites;
	}

	public function getSections($enableBulkTranslation = false): array {
		$sections  = [ [ 'value' => '', 'label' => 'Please Select' ] ];
		$_sections = version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ? Craft::$app->entries->getAllSections() : Craft::$app->sections->getAllSections();
		foreach ( $_sections as $section ) {
			if ( $section->type == 'channel' || $section->type == 'structure' ) {
				foreach ( $section->getEntryTypes() as $type ) {
					$sections[] = [
						'value' => $section->id . ":" . $type->id,
						'label' => $section->name . " - " . $type->name
					];
				}
			}
		}
		if($enableBulkTranslation){
			$sections[] = [
				'value' => 'all',
				'label' => Craft::t( 'convergine-contentbuddy', "All Sections" )
			];
		}

		return $sections;
	}

	public function translateSection(
		$section,
		$translate_to,
		$instructions,
		$override,
        $translateSlugs
	):void {
		$primarySiteId = Craft::$app->sites->getPrimarySite()->id;
		$_section    = explode( ':', $section );
		$sectionId   = $_section[0];
		$sectionType = $_section[1];
		$_enabledFields = $this->getTranslatedFieldsBySectionType($sectionType);


		$translate_to_list = [];
		if($translate_to === 'all'){
			$sectionSites = version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? Craft::$app->entries->getSectionById($sectionId) : Craft::$app->sections->getSectionById($sectionId);
			foreach ( $sectionSites->getSiteSettings() as $site ) {
				if ( $site->siteId != $primarySiteId ) {
					$translate_to_list[] = $site->siteId;
				}

			}
		}else{
			$translate_to_list[] = $translate_to;
		}

		foreach ( $translate_to_list as $translate_to_site_id ) {

			$translateRecord                   = new TranslateRecord();
			$translateRecord->siteId           = $translate_to_site_id;
			$translateRecord->instructions     = $instructions;
			$translateRecord->fields           = json_encode( $_enabledFields );
			$translateRecord->fieldsCount      = 0;
			$translateRecord->sectionId        = $sectionId;
			$translateRecord->sectionType      = $sectionType;
			$translateRecord->fieldsProcessed  = 0;
			$translateRecord->fieldsError      = 0;
			$translateRecord->entriesSubmitted = 0;
			$translateRecord->fieldsSkipped    = 0;
			$translateRecord->fieldsTranslated = 0;
			$translateRecord->override         = $override ? 1 : 0;
			$translateRecord->jobIds           = '';
			$translateRecord->save();
			$entries = Entry::find()
			                ->sectionId( $sectionId )
			                ->typeId( $sectionType )
			                ->siteId( $primarySiteId );
			$entries = $this->_plugin->translate->setBatchLimit( $entries );
			$items   = $fields = 0;
			$jobIds  = [];
			foreach ( $entries as $index => $entry ) {
				$batch = [];
				foreach ( $entry as $b ) {
					$batch[] = $b->id;

					$fields += $this->_plugin->translate->getEntryFieldsCount( $b, $_enabledFields );

				}


				$items += count( $batch );
				$jobId = \craft\helpers\Queue::push(
					new translateEntries( [
						'entriesIds'        => $batch,
						'translateToSiteId' => $translate_to_site_id,
						'enabledFields'     => $_enabledFields,
						'instructions'      => $instructions,
						'translationId'     => $translateRecord->id,
                        'translateSlugs'    => $translateSlugs
					] ), 10 + $index, 0
				);
				if ( $jobId ) {
					$jobIds[] = $jobId;
				}

			}
			$translateRecord->entriesSubmitted = $items;

			$translateRecord->fieldsCount = $fields;

			$translateRecord->jobIds = join( ',', $jobIds );

			$translateRecord->save();
		}
	}

	public function translateCategory(
		Category $entry,
		int $translate_to,
		array $enabledFields,
		int $translateId,
		string $instructions = '',

	): bool {

		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;


		$_entry = Category::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$entry->siteId = $translate_to;
            Craft::info('Saving entry ('.$entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
            $this->saveElement($entry);
			$_entry = $entry;
		}

		if ( $_entry ) {
			$prompt = "Translate to {$lang}";
			if ( $instructions ) {
				$prompt .= ", {$instructions},";
			}
			$prompt .= " following text";
			try {
				$translated_text = BuddyPlugin::getInstance()->request
					->send( $prompt . ": {$entry->title}", 30000, 0.7, true ,$lang);

				$_entry->title = $translated_text;

				$fieldsTranslated ++;
			} catch ( \Throwable $e ) {

				$fieldsError ++;
				$this->_addLog( $translateId, $entry->id, $e->getMessage(), 'title' );
			}
			$fieldsProcessed ++;

			foreach ( $enabledFields as $field ) {
				if ( ! $field ) {
					continue;
				}
				$_field      = explode( ":", $field, 4 );
				$fieldType   = $_field[0];
				$fieldHandle = $_field[1];


				if ( in_array( $fieldType, $this->_plugin->base->getSupportedFieldTypes() ) ) {
					$fieldsProcessed ++;
					// heck field not empty
					if ( strlen( (string) $entry->getFieldValue( $fieldHandle ) ) == 0 ) {
						$fieldsSkipped ++;
						continue;
					}

					//check if field is already translated and selected NOT OVERRIDE
					if ( ! $override && (string) $entry->getFieldValue( $fieldHandle ) != (string) $_entry->getFieldValue( $fieldHandle ) ) {
						$fieldsSkipped ++;
						continue;
					}

					try {
						$translated_text = BuddyPlugin::getInstance()
							->request->send( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 30000, 0.7, true,$lang );
						Craft::info( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 'content-buddy' );
						Craft::info( $fieldHandle, 'content-buddy' );
						Craft::info( $translated_text, 'content-buddy' );
						$translated_text = trim( $translated_text, '```html' );
						$translated_text = rtrim( $translated_text, '```' );
						$_entry->setFieldValue( $fieldHandle, $translated_text );
						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) ) {

					$block  = $_field[2];
					$handle = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $block )->type( $fieldHandle );

					$matrixBlockTarget = \craft\elements\MatrixBlock::find()
					                                                ->field( $block )
					                                                ->ownerId( $_entry->id )
					                                                ->type( $fieldHandle )
					                                                ->siteId( $translate_to )
					                                                ->all();

					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						$fieldsProcessed ++;
						if ( isset( $matrixBlockTarget[ $k ] ) ) {
							try {
								$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );
								// heck field not empty
								if ( strlen( $originalFieldValue ) == 0 ) {
									$fieldsSkipped ++;
									continue;
								}
								//check if field is already translated and selected NOT OVERRIDE
								if ( ! $override && $originalFieldValue != $targetFieldValue ) {
									$fieldsSkipped ++;
									continue;
								}

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true,$lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement($matrixBlockTarget[ $k ]);

								$fieldsTranslated ++;
							} catch ( \Throwable $e ) {
								$fieldsError ++;
								$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
							}
						}
					}

					// process Craft5 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' ) {

					$block       = $_field[1];

					$fieldValues = $this->processMatrixFields($lang, $entry, $translate_to, $translateId, $prompt, $override);
					$_entry->setFieldValues($fieldValues);
				}


			}

            Craft::info('Saving entry ('.$_entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
			if ( $this->saveElement( $_entry ) ) {
				$translateRecord->fieldsTranslated = $translateRecord->fieldsTranslated + $fieldsTranslated;
				$translateRecord->fieldsError      = $translateRecord->fieldsError + $fieldsError;
				$translateRecord->fieldsSkipped    = $translateRecord->fieldsSkipped + $fieldsSkipped;
				$translateRecord->fieldsProcessed  = $translateRecord->fieldsProcessed + $fieldsProcessed;

				$translateRecord->save();

				return true;

			}
		}
		$translateRecord->save();

		return false;
	}

    public function translateAsset(
        Asset $entry,
        int $translate_to,
        array $enabledFields,
        int $translateId,
        string $instructions = '',

    ): bool {

        $translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
        $lang              = $translate_to_site->language;

        $translateRecord = TranslateRecord::findOne( $translateId );
        $override        = $translateRecord->override;
        $hasError        = false;

        $fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;


        $_entry = Asset::find()->id( $entry->id )->siteId( $translate_to )->one();
        if ( ! $_entry ) {
            $entry->siteId = $translate_to;
            Craft::info('Saving entry ('.$entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
            $this->saveElement($entry);
            $_entry = $entry;
        }

        if ( $_entry ) {
            $prompt = "Translate to {$lang}";
            if ( $instructions ) {
                $prompt .= ", {$instructions},";
            }
            $prompt .= " following text";
            try {
                $translated_text = BuddyPlugin::getInstance()->request
                    ->send( $prompt . ": {$entry->title}", 30000, 0.7, true ,$lang);

                $_entry->title = $translated_text;

                $fieldsTranslated ++;
            } catch ( \Throwable $e ) {

                $fieldsError ++;
                $this->_addLog( $translateId, $entry->id, $e->getMessage(), 'title' );
            }
            $fieldsProcessed ++;

            //alt text
            if(!empty($entry->alt)) {
                try {
                    $translated_text = BuddyPlugin::getInstance()->request->send( $prompt . ": {$entry->alt}", 30000, 0.7, true ,$lang);
                    $_entry->alt = $translated_text;
                } catch ( \Throwable $e ) {
                    $this->_addLog( $translateId, $entry->id, $e->getMessage(), 'alt' );
                }
            }

            foreach ( $enabledFields as $field ) {
                if ( ! $field ) {
                    continue;
                }
                $_field      = explode( ":", $field, 4 );
                $fieldType   = $_field[0];
                $fieldHandle = $_field[1];


                if ( in_array( $fieldType, $this->_plugin->base->getSupportedFieldTypes() ) ) {
                    $fieldsProcessed ++;
                    // heck field not empty
                    if ( strlen( (string) $entry->getFieldValue( $fieldHandle ) ) == 0 ) {
                        $fieldsSkipped ++;
                        continue;
                    }

                    //check if field is already translated and selected NOT OVERRIDE
                    if ( ! $override && (string) $entry->getFieldValue( $fieldHandle ) != (string) $_entry->getFieldValue( $fieldHandle ) ) {
                        $fieldsSkipped ++;
                        continue;
                    }

                    try {
                        $translated_text = BuddyPlugin::getInstance()
                            ->request->send( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 30000, 0.7, true,$lang );
                        Craft::info( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 'content-buddy' );
                        Craft::info( $fieldHandle, 'content-buddy' );
                        Craft::info( $translated_text, 'content-buddy' );
                        $translated_text = trim( $translated_text, '```html' );
                        $translated_text = rtrim( $translated_text, '```' );
                        $_entry->setFieldValue( $fieldHandle, $translated_text );
                        $fieldsTranslated ++;
                    } catch ( \Throwable $e ) {
                        $fieldsError ++;
                        $this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
                    }

                    // process Craft4 Matrix field
                } elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) ) {

                    $block  = $_field[2];
                    $handle = $_field[3];

                    $matrixFieldQuery = $entry->getFieldValue( $block )->type( $fieldHandle );

                    $matrixBlockTarget = \craft\elements\MatrixBlock::find()
                        ->field( $block )
                        ->ownerId( $_entry->id )
                        ->type( $fieldHandle )
                        ->siteId( $translate_to )
                        ->all();

                    foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
                        $fieldsProcessed ++;
                        if ( isset( $matrixBlockTarget[ $k ] ) ) {
                            try {
                                $originalFieldValue = (string) $matrixField->getFieldValue( $handle );
                                $targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );
                                // heck field not empty
                                if ( strlen( $originalFieldValue ) == 0 ) {
                                    $fieldsSkipped ++;
                                    continue;
                                }
                                //check if field is already translated and selected NOT OVERRIDE
                                if ( ! $override && $originalFieldValue != $targetFieldValue ) {
                                    $fieldsSkipped ++;
                                    continue;
                                }

                                $translated_text = BuddyPlugin::getInstance()
                                    ->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true,$lang );

                                $matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement($matrixBlockTarget[ $k ]);

                                $fieldsTranslated ++;
                            } catch ( \Throwable $e ) {
                                $fieldsError ++;
                                $this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
                            }
                        }
                    }

                    // process Craft5 Matrix field
                } elseif ( $fieldType == 'craft\fields\Matrix' ) {
                    $block       = $_field[1];
                    $fieldValues = $this->processMatrixFields($lang, $entry, $translate_to, $translateId, $prompt, $override);
                    $_entry->setFieldValues($fieldValues);
                }
            }

            Craft::info('Saving entry ('.$_entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
            if ( $this->saveElement( $_entry ) ) {
                $translateRecord->fieldsTranslated = $translateRecord->fieldsTranslated + $fieldsTranslated;
                $translateRecord->fieldsError      = $translateRecord->fieldsError + $fieldsError;
                $translateRecord->fieldsSkipped    = $translateRecord->fieldsSkipped + $fieldsSkipped;
                $translateRecord->fieldsProcessed  = $translateRecord->fieldsProcessed + $fieldsProcessed;

                $translateRecord->save();

                return true;

            }
        }
        $translateRecord->save();

        return false;
    }

	public function translateEntry(
		Entry $entry,
		int $translate_to,
		array $enabledFields,
		int $translateId,
		string $instructions = '',

	): bool {

		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;


		$_entry = Entry::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$entry->siteId = $translate_to;
            Craft::info('Saving entry ('.$entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
			$this->saveElement( $entry );
			$_entry = $entry;
		}

		if ( $_entry ) {
			$prompt = "Translate to {$lang}";
			if ( $instructions ) {
				$prompt .= ", {$instructions},";
			}
			$prompt .= " following text";
			try {
				$translated_text = BuddyPlugin::getInstance()->request
					->send( $prompt . ": {$entry->title}", 30000, 0.7, true,$lang );

				$_entry->title = $translated_text;

				$fieldsTranslated ++;
			} catch ( \Throwable $e ) {

				$fieldsError ++;
				$this->_addLog( $translateId, $entry->id, $e->getMessage(), 'title' );
			}
			$fieldsProcessed ++;

			foreach ( $enabledFields as $field ) {
				if ( ! $field ) {
					continue;
				}
				$_field      = explode( ":", $field, 4 );
				$fieldType   = $_field[0];
				$fieldHandle = $_field[1];


				if ( in_array( $fieldType, $this->_plugin->base->getSupportedFieldTypes() ) ) {
                    if($fieldType == 'craft\ckeditor\Field') {
                        $entry_value = $entry->getFieldValue( $fieldHandle )->getRawContent();
                    } else {
                        $entry_value = $entry->getFieldValue( $fieldHandle );
                    }

					$fieldsProcessed ++;
					// heck field not empty
					if ( strlen( (string) $entry_value ) == 0 ) {
						$fieldsSkipped ++;
						continue;
					}

					//check if field is already translated and selected NOT OVERRIDE
					if ( ! $override && (string) $entry_value != (string) $_entry->getFieldValue( $fieldHandle ) ) {
						$fieldsSkipped ++;
						continue;
					}

					try {
						$translated_text = BuddyPlugin::getInstance()
							->request->send( $prompt . ": {$entry_value}", 30000, 0.7, true,$lang );

                        Craft::info( $prompt . ": {$entry_value}", 'content-buddy' );
                        Craft::info( $fieldHandle, 'content-buddy' );
                        Craft::info( $translated_text, 'content-buddy' );
                        $translated_text = trim( $translated_text, '```html' );
                        $translated_text = rtrim( $translated_text, '```' );

                        if($fieldType == 'craft\ckeditor\Field') {
                            $translated_text = $this->translateEntriesInCKEditorField($translated_text,$translate_to_site,$prompt);
                            Craft::info('New CKEditor translated text: '.$translated_text, 'content-buddy');
                        }

						$_entry->setFieldValue( $fieldHandle, $translated_text );

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
                        Craft::error('Failed to translate field "'.$fieldHandle.'": '. $e->getMessage(), 'content-buddy' );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) ) {

					$block  = $_field[2];
					$handle = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $block )->type( $fieldHandle );

					$matrixBlockTarget = \craft\elements\MatrixBlock::find()
					                                                ->field( $block )
					                                                ->ownerId( $_entry->id )
					                                                ->type( $fieldHandle )
					                                                ->siteId( $translate_to )
					                                                ->all();

					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						$fieldsProcessed ++;
						if ( isset( $matrixBlockTarget[ $k ] ) ) {
							try {
								$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );
								// heck field not empty
								if ( strlen( $originalFieldValue ) == 0 ) {
									$fieldsSkipped ++;
									continue;
								}
								//check if field is already translated and selected NOT OVERRIDE
								if ( ! $override && $originalFieldValue != $targetFieldValue ) {
									$fieldsSkipped ++;
									continue;
								}

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true,$lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
							} catch ( \Throwable $e ) {
								$fieldsError ++;
								$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
							}
						}
					}

					// process Craft5 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' ) {

					$block       = $_field[1];

					$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $prompt, $override );
					$_entry->setFieldValues($fieldValues);
				}


			}

            Craft::info('Saving entry ('.$_entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
			if ( $this->saveElement( $_entry ) ) {
				$translateRecord->fieldsTranslated = $translateRecord->fieldsTranslated + $fieldsTranslated;
				$translateRecord->fieldsError      = $translateRecord->fieldsError + $fieldsError;
				$translateRecord->fieldsSkipped    = $translateRecord->fieldsSkipped + $fieldsSkipped;
				$translateRecord->fieldsProcessed  = $translateRecord->fieldsProcessed + $fieldsProcessed;

				$translateRecord->save();

				return true;

			}
		}
		$translateRecord->save();

		return false;
	}

	public function processMatrixFields(string $lang, Element $entry_from, int $translate_to, int $translateId, string $prompt, $override,$or_entry = true) : array {
		$target = [];
		$targetEntry = Entry::find()->id( $entry_from->id )->siteId( $translate_to )->one();
		if(!$or_entry && !empty($entry_from->title) && $entry_from->getIsTitleTranslatable()) {
			try {
				$translated_text = BuddyPlugin::getInstance()
					->request->send( $prompt . ": {$entry_from->title}", 30000, 0.7, true,$lang );
				$target['title'] = $translated_text;
			} catch ( \Throwable $e ) {
				$this->_addLog( $translateId, $entry_from->id, $e->getMessage() . "\n" . $e->getTraceAsString(), 'title', 0 );
			}
		}

		foreach ($entry_from->fieldLayout->getCustomFields() as $field) {
			$translatedValue   = null;
			$fieldTranslatable = $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
			$processField      = boolval( $fieldTranslatable ); // if translatable
			if (in_array(get_class($field), static::$textFields) && $processField && !$or_entry) {
				// normal text fields
				$originalFieldValue = $field->serializeValue($entry_from->getFieldValue($field->handle), $entry_from);

				$translatedValue = $originalFieldValue;
				if($originalFieldValue) {
					try {

						$_prompt         = $prompt . ": {$originalFieldValue}";
						$translatedValue = BuddyPlugin::getInstance()
							->request->send( $_prompt, 30000, 0.7, true, $lang );

						$translatedValue = trim( $translatedValue, '```html' );
						$translatedValue = rtrim( $translatedValue, '```' );

					} catch ( \Throwable $e ) {
						//$fieldsError ++;
						$this->_addLog( $translateId, $entry_from->id, $e->getMessage() . "\n" . $e->getTraceAsString(), $field, 0 );
					}
				}
			} elseif ( in_array( get_class( $field ), static::$matrixFields ) ) {
				// dig deeper in Matrix fields
				$translatedValue = $this->translateMatrixField( $lang,$entry_from, $field, $translate_to,  $translateId,  $prompt, $override );

			}
			if ($translatedValue) {
				$target[$field->handle] = $translatedValue;
			} else {
				if(!$or_entry && $targetEntry){
					$target[$field->handle] = $field->serializeValue($targetEntry->getFieldValue($field->handle), $targetEntry);
				}

			}
		}
		return $target;
	}

	public function translateMatrixField(string $lang, Element $element, FieldInterface $field,int $translate_to,  int $translateId, string $prompt, $override): array
	{
		$query = $element->getFieldValue($field->handle);

		// serialize current value
		$serialized = $element->getSerializedFieldValues([$field->handle])[$field->handle];

		foreach ($query->all() as $matrixElement) {
			$translatedMatrixValues = $this->processMatrixFields($lang,$matrixElement,$translate_to, $translateId, $prompt,$override,false);
			foreach ($translatedMatrixValues as $matrixFieldHandle => $value) {
				// only set translated values in matrix array
				if ($value && isset($serialized[$matrixElement->id])) {
					if ($matrixFieldHandle == 'title') {
						$serialized[$matrixElement->id][$matrixFieldHandle] = $value;
					} else {
						$serialized[$matrixElement->id]['fields'][$matrixFieldHandle] = $value;
					}
				}
			}
		}

		if (get_class($field) == 'benf\neo\Field' && $field->translationMethod == 'all') {
			// special case to avoid neo overwriting blocks in all languages
			return ['blocks' => $serialized];
		}

		return $serialized;
	}

	private function processMatrixField( Entry $entry_from, Entry $entry_to, $field, $translate_to, $translateId, $prompt, $override, $fieldHandle, $block, $handle, &$fieldsProcessed, &$fieldsSkipped, &$fieldsTranslated, &$fieldsError ): void {
		try {
			$matrixFieldQuery    = $entry_from->getFieldValue( $fieldHandle )->type( $block );
			$matrixFieldQuery_to = $entry_to->getFieldValue( $fieldHandle )->type( $block );
		} catch ( InvalidFieldException $e ) {
			$fieldsError ++;
			$this->_addLog( $translateId, $entry_from->id, $e->getMessage(), $field );

			return;
		}

		$matrixBlockTarget = \craft\elements\Entry::find()
		                                          ->field( $fieldHandle )
		                                          ->ownerId( $entry_to->id )
		                                          ->type( $block )
		                                          ->siteId( $translate_to )
		                                          ->all();
		foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
			$fieldsProcessed ++;

			if ( str_contains( $handle, ':' ) ) {
				$values = explode( ':', $handle, 3 );

				$block       = $values[0];
				$fieldHandle = $values[1];
				$handle      = $values[2];

				$matrixField_to = $matrixFieldQuery_to->all()[ $k ];

				$this->processMatrixField( $matrixField, $matrixField_to, $field, $translate_to, $translateId, $prompt, $override, $fieldHandle, $block, $handle, $fieldsProcessed, $fieldsSkipped, $fieldsTranslated, $fieldsError );
			} else {
				// process Matrix Title field
				if ( isset( $matrixBlockTarget[ $k ] ) && $handle == 'title' ) {

					try {
						$originalFieldValue = (string) $matrixField->$handle;
						$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->$handle;
						// heck field not empty
						if ( strlen( $originalFieldValue ) == 0 ) {
							$fieldsSkipped ++;
							continue;
						}
						//check if field is already translated and selected NOT OVERRIDE
						if ( ! $override && $originalFieldValue != $targetFieldValue ) {
							$fieldsSkipped ++;
							continue;
						}

						$translated_text = BuddyPlugin::getInstance()
							->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

						$matrixBlockTarget[ $k ]->$handle = $translated_text;

                        Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                        $this->saveElement( $matrixBlockTarget[ $k ] );

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry_from->id, $e->getMessage(), $field, $k );
					}
					// process Matrix Custom fields
				} elseif ( isset( $matrixBlockTarget[ $k ] ) ) {
					try {
						$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
						$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );
						// heck field not empty
						if ( strlen( $originalFieldValue ) == 0 ) {
							$fieldsSkipped ++;
							continue;
						}
						//check if field is already translated and selected NOT OVERRIDE
						if ( ! $override && $originalFieldValue != $targetFieldValue ) {
							$fieldsSkipped ++;
							continue;
						}

						$translated_text = BuddyPlugin::getInstance()
							->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

						$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );

                        Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                        $this->saveElement( $matrixBlockTarget[ $k ] );

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry_from->id, $e->getMessage(), $field, $k );
					}
				}
			}
		}
	}

	public function reTranslateEntry(
		Entry $entry,
		int $translate_to,
		int $translateId,
		string $instructions = '',

	): bool {

		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );

		$_entry = Entry::find()->id( $entry->id )->siteId( $translate_to )->one();


		$fieldsError      = $translateRecord->fieldsError;
		$fieldsTranslated = $translateRecord->fieldsTranslated;

		if ( $_entry ) {

			$titleErrorLog = TranslateLogRecord::find()
			                                   ->where( [
				                                   'entryId' => $_entry->id,
				                                   'field'   => 'title'
			                                   ] )->one();
			$prompt        = "Translate to {$lang}";
			if ( $instructions ) {
				$prompt .= ", {$instructions},";
			}
			$prompt .= " following text";
			if ( $titleErrorLog ) {

				try {
					$translated_text = BuddyPlugin::getInstance()->request
						->send( $prompt . ": {$entry->title}", 30000, 0.7, true );

					$_entry->title = $translated_text;

					$fieldsTranslated ++;
					$fieldsError --;
					$titleErrorLog->delete();
				} catch ( \Throwable $e ) {
					$fieldsError ++;

					$this->_updateLog( $titleErrorLog, $e->getMessage() );
				}
			}

			$fieldsFromLog = TranslateLogRecord::find()
			                                   ->where( [
				                                   'AND',
				                                   [ 'entryId' => $_entry->id ],
				                                   "field <> 'title'"
			                                   ] )->all();

			foreach ( $fieldsFromLog as $logRecord ) {
				$field       = $logRecord->field;
				$_field      = explode( ":", $field );
				$fieldType   = $_field[0];
				$fieldHandle = $_field[1];
				if ( in_array( $fieldType, $this->_plugin->base->getSupportedFieldTypes() ) ) {
					if ( $field && strlen( $entry->getFieldValue( $fieldHandle ) ) > 0 ) {

						try {
							$translated_text = BuddyPlugin::getInstance()
								->request->send( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 30000, 0.7, true );

							$_entry->setFieldValue( $fieldHandle, $translated_text );
							$fieldsTranslated ++;
							$fieldsError --;
							$logRecord->delete();
						} catch ( \Throwable $e ) {
							$fieldsError ++;

							$this->_updateLog( $logRecord, $e->getTraceAsString() );
						}
					}
					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) ) {

					$block  = $_field[2];
					$handle = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $block )->type( $fieldHandle );

					$matrixBlockTarget = \craft\elements\MatrixBlock::find()
					                                                ->field( $block )
					                                                ->ownerId( $_entry->id )
					                                                ->type( $fieldHandle )
					                                                ->siteId( $translate_to )
					                                                ->all();

					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {

						if ( isset( $matrixBlockTarget[ $k ] ) ) {
							try {
								$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );

                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
								$fieldsError --;
								$logRecord->delete();
							} catch ( \Throwable $e ) {
								$fieldsError ++;

								$this->_updateLog( $logRecord, $e->getTraceAsString() );
							}
						}
					}

					// process Craft5 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' ) {

					$fieldHandle = $_field[2];
					$block       = $_field[1];
					$handle      = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block );

					$matrixBlockTarget = \craft\elements\Entry::find()
					                                          ->field( $fieldHandle )
					                                          ->ownerId( $_entry->id )
					                                          ->type( $block )
					                                          ->siteId( $translate_to )
					                                          ->all();
					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						// process Matrix Title field
						if ( isset( $matrixBlockTarget[ $k ] ) && $handle == 'title' ) {

							try {
								$originalFieldValue = (string) $matrixField->$handle;
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->$handle;

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

								$matrixBlockTarget[ $k ]->$handle = $translated_text;

                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
								$fieldsError --;
								$logRecord->delete();
							} catch ( \Throwable $e ) {
								$fieldsError ++;

								$this->_updateLog( $logRecord, $e->getTraceAsString() );
							}
							// process Matrix Custom fields
						} elseif ( isset( $matrixBlockTarget[ $k ] ) ) {
							try {
								$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );
								// heck field not empty

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );

                                Craft::info('Saving entry ('.$matrixBlockTarget[ $k ]->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
                                $this->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
								$fieldsError --;
								$logRecord->delete();
							} catch ( \Throwable $e ) {
								$fieldsError ++;

								$this->_updateLog( $logRecord, $e->getTraceAsString() );
							}
						}
					}
				}

			}

            Craft::info('Saving entry ('.$_entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
			if ( $this->saveElement( $_entry ) ) {
				$translateRecord->fieldsTranslated = $fieldsTranslated;
				$translateRecord->fieldsError      = $fieldsError;

				$translateRecord->save();

				return true;

			}
		}

		return false;
	}

    public function translateSlug(Entry $entry, int $translate_to, string $instructions = ''): bool {
        $translate_to_site = Craft::$app->sites->getSiteById($translate_to);
        $lang = $translate_to_site->language;
        $hasError = false;

        $_entry = Entry::find()->id($entry->id)->siteId($translate_to)->one();
        if(!$_entry) {
            $entry->siteId = $translate_to;
            Craft::info('Saving entry ('.$entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
            $this->saveElement($entry);
            $_entry = $entry;
        }

        if($_entry) {
            $prompt = "Translate this URL slug to {$lang}";
            if($instructions) {
                $prompt .= ", {$instructions},";
            }
            $prompt .= " following URL slug";
            try {
                $translated_text = BuddyPlugin::getInstance()->request->send($prompt . ": {$entry->slug}", 30000, 0.7, true, $lang);

                $_entry->slug = $translated_text;

            } catch (\Throwable $e) {
                $hasError = true;
            }

            Craft::info('Saving entry ('.$_entry->id.') with site ('.$translate_to.') on line ('.__LINE__.')', 'content-buddy');
            if($this->saveElement($_entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $text string initially translated text
     * @param $site Site site to translate to
     * @param $prompt string prompt to use
     * @return string full translated text including craft-entries
     */
    private function translateEntriesInCKEditorField(string $text, Site $site, string $prompt): string {
        preg_match_all('/<craft-entry\s+[^>]*data-entry-id=["\'](\d+)["\'][^>]*>\s*(?:&nbsp;)?\s*<\/craft-entry>/', $text, $matches);
        $entryIds = $matches[1];

        $entryMap = [];

        Craft::info("CKEditor nested entries: ".json_encode($entryIds), 'content-buddy');

        foreach($entryIds as $id) {
            $entry = Entry::find()->id($id)->one();

            if($entry) {
                try {
                    Craft::info("Found nested Entry: ".$entry->title, 'content-buddy');

                    $newEntry = new Entry();
                    $newEntry->title = $this->translateText($entry->title, '', $site, $prompt);
                    $newEntry->slug = $entry->slug;
                    $newEntry->sectionId = $entry->sectionId;
                    $newEntry->ownerId = $entry->ownerId;
                    $newEntry->typeId = $entry->typeId;
                    $newEntry->fieldId = $entry->fieldId;
                    $newEntry->siteId = $site->id;
                    $newEntry->enabled = true;

                    Craft::info("Created new nested Entry: ".$newEntry->title.', sectionId: '.$newEntry->sectionId.', ownerId: '.$newEntry->ownerId.', typeId: '.$newEntry->typeId.', fieldId: '.$newEntry->fieldId, 'content-buddy');

                    foreach($entry->getFieldValues() as $fieldHandle => $value) {
                        $field = Craft::$app->fields->getFieldByHandle($fieldHandle);
                        $fieldType = $field ? get_class($field) : 'Unknown';
                        if(in_array($fieldType, $this->_plugin->base->getSupportedFieldTypes())) {
                            $newEntry->setFieldValue($fieldHandle, $this->translateText($value, $fieldType, $site, $prompt));
                            Craft::info("Saved field in new nested Entry: ".$fieldHandle, 'content-buddy');
                        }
                    }

                    if($this->saveElement($newEntry)) {
                        $entryMap[$id] = $newEntry->id;
                        Craft::info("Saved new nested Entry: ".$newEntry->title, 'content-buddy');
                    } else {
                        Craft::error("Failed to save translated entry for ID: $id", __METHOD__);
                    }
                } catch(\Exception $e) {
                    Craft::error("Failed to translate nested entry: ".$e->getMessage(), 'content-buddy');
                }
            }
        }

        Craft::info("CKEditor nested entries mapped: ".json_encode($entryMap), 'content-buddy');

        $translatedText = preg_replace_callback(
            '/(<craft-entry\s+[^>]*data-entry-id=["\'])(\d+)(["\'][^>]*>\s*(?:&nbsp;)?\s*<\/craft-entry>)/',
            function ($matches) use ($entryMap) {
                $originalId = $matches[2];
                $newId = $entryMap[$originalId] ?? $originalId;
                return $matches[1] . $newId . $matches[3];
            },
            $text
        );

        Craft::info("CKEditor translated text: $translatedText", 'content-buddy');

        return $translatedText;
    }

    private function translateText($text, $fieldType, Site $site, $prompt): string {
        $lang = $site->language;

        $translated_text = BuddyPlugin::getInstance()->request->send( $prompt . ": " . $text, 30000, 0.7, true,$lang );

        if($fieldType == 'craft\ckeditor\Field') {
            $translated_text = $this->translateEntriesInCKEditorField($translated_text,$site,$prompt);
        }

        $translated_text = trim( $translated_text, '```html' );
        $translated_text = rtrim( $translated_text, '```' );
        return $translated_text;
    }

	protected function _getClass( $object ): string {
		return str_replace( [
			'craft\fields\\',
			'craft\redactor\Field',
			'craft\ckeditor\Field',
			'abmat\tinymce\Field'
		],
			[
				'',
				'Redactor',
				'CK Editor',
				'Tinymce Editor'
			], ( new \ReflectionClass( $object ) )->getName() );
	}

	private function _addLog( $translationId, $entryId, $message, $field, $blockId = 0 ) {
        if($field instanceof FieldInterface) {
            $field = $field->handle;
        }
		$logRecord                = new TranslateLogRecord();
		$logRecord->translationId = $translationId;
		$logRecord->message       = $message;
		$logRecord->field         = $field;
		$logRecord->entryId       = $entryId;
		$logRecord->blockId       = $blockId;
		$logRecord->save();
	}

	private function _updateLog( ActiveRecord $logRecord, string $message ): void {
		$logRecord->message = $message;
		$logRecord->save();
	}

	public function setBatchLimit( ActiveQuery|EntryQuery $record ): BatchQueryResult {

		//Todo update limits
		if ( $this->_plugin->base->isGTP4() ) {
			$record = $record->batch( 1 );

			return $record;
		}
		$record = $record->batch( 4 );

		return $record;
	}

	public function getEntryFieldsCount( Entry $entry, array $fields ) {
		$fieldsCount = 1;
		foreach ( $fields as $field ) {
			$_field = explode( ":", $field );
			if ( in_array( $_field[0], $this->_plugin->base->getSupportedFieldTypes() ) ) {
				$fieldsCount ++;
			} elseif ( $_field[0] == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) ) {
				$fieldHandle      = $_field[2];
				$block            = $_field[1];
				$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block )->all();
				$fieldsCount      += count( $matrixFieldQuery );
			} else {
				if(isset($_field[2])) {
					$fieldHandle = $_field[2];
					$block       = $_field[1];
					$handle      = $_field[3];

					try {
						$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block )->all();
						$fieldsCount      += count( $matrixFieldQuery );
					} catch ( InvalidFieldException $e ) {
						continue;
					}
				}
			}
		}

		return $fieldsCount;
	}

	public function getJobsInfo( $translateId ) {
		$jobsData = $this->_getJobsData( $translateId );
		$text     = "";
		if ( $jobsData['completed'] ) {
			$text .= "<div style='color: #3b8134'>{$jobsData['completed']} Completed</div>";
		}
		if ( $jobsData['error'] ) {
			$text .= "<div style='color: #cf1124'>{$jobsData['error']} Error</div>";
		}
		if ( $jobsData['inProcess'] ) {
			$text .= "<div style='color: orange'>{$jobsData['inProcess']} In-Process</div>";
		}

		return $text;
	}

	public function getTranslationStatus( $translateId ) {
		$jobsData        = $this->_getJobsData( $translateId );
		$translateRecord = TranslateRecord::findOne( $translateId );
		if ( $jobsData['inProcess'] ) {
			return "<div style='font-weight: bold;color: orange'>" . Craft::t( 'convergine-contentbuddy', 'In Process' ) . "</div>";
		} elseif ( $jobsData['error'] || $translateRecord->fieldsError ) {
			return "<div style='font-weight: bold;color: red'>" . Craft::t( 'convergine-contentbuddy', 'With Errors' ) . "</div>";
		} elseif ( $jobsData['total'] == $jobsData['completed'] ) {
			return "<div style='font-weight: bold;color: #3b8134'>" . Craft::t( 'convergine-contentbuddy', 'Completed' ) . "</div>";
		}

	}

	public function hasActiveJobs( $translateId ) {
		$jobsData = $this->_getJobsData( $translateId );

		return $jobsData['inProcess'];
	}

	public function getTranslatedFields( Element $entry ) {
		$fieldLayout = $entry->getFieldLayout();
        Craft::info('Getting translated fields for entry ('.$entry->id.'): '.json_encode($fieldLayout), 'content-buddy');
		return $this->_getLayoutFields( $fieldLayout );
	}

	public function getTranslatedFieldsBySectionType($entryTypeId):array {
		$enabledFields =[];

		if(version_compare(Craft::$app->getInfo()->version, '5.0', '>=')){
			$fieldLayout = Craft::$app->entries->getEntryTypeById($entryTypeId)->getFieldLayout();
			$entryFields = $this->_getLayoutFields( $fieldLayout );
			foreach ($entryFields['regular'] as $f) {
				$enabledFields[]="{$f['_type']}:{$f['handle']}";
			}
			if(count($entryFields['matrix'])){
				$enabledFields[]="craft\\fields\\Matrix:fields";
			}
		}else{
			$fieldLayout = Craft::$app->sections->getEntryTypeById($entryTypeId)->getFieldLayout();
			$entryFields = $this->_getLayoutFields( $fieldLayout );
			foreach ($entryFields['regular'] as $f) {
				$enabledFields[]="{$f['_type']}:{$f['handle']}";
			}
			foreach ($entryFields['matrix'] as $f) {
				foreach ($f['fields'] as $mf) {
					$enabledFields[] = $mf['_field'];
				}
			}
		}
		return $enabledFields;
	}

	public function getEntryTranslateControl( Element $entry ): string {
		$currentSite = $entry->siteId;
		$sites       = [];
		$sectionSites = [];

		if($entry::class == 'craft\elements\Category' || $entry::class == 'craft\elements\Asset') {
			$sectionSites = $entry->getSupportedSites();
		} else if($entry::class == 'craft\elements\Entry') {
            if(!empty($entry->section)) { //not a headless entry
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '>=')) {
                    $sectionSites = $entry->getSection()->getSiteSettings();
                } else {
                    $section = \Craft::$app->sections->getSectionById($entry->sectionId);
                    $sectionSites = $section->getSiteSettings();
                }
            }
		}
		if(!$sectionSites){
			return '';
		}

		foreach ( $sectionSites as $site ) {
			$siteId = $site->siteId??$site;
			if ( $siteId != $currentSite ) {
				$siteObj = Craft::$app->sites->getSiteById($siteId);
				$sites[ $siteId] = $siteObj->name . " : " . $siteObj->language;
			}

		}

        $action = match($entry::class) {
            'craft\elements\Category' => 'convergine-contentbuddy/translate/process-category',
            'craft\elements\Asset' => 'convergine-contentbuddy/translate/process-asset',
            default => 'convergine-contentbuddy/translate/process-entry',
        };

		return Craft::$app->view->renderTemplate( 'convergine-contentbuddy/translate/_control.twig', [
			'sites' => $sites,
			'action' => $action,
			'enableBulkTranslation'=> $this->_plugin->getSettings()->enableBulkTranslation
		] );
	}

	protected function _getJobsData( $translateId ) {
		$translateRecord = TranslateRecord::findOne( $translateId );
		$jobs            = explode( ',', $translateRecord->jobIds );
		$inProcess       = $completed = $error = 0;
		foreach ( $jobs as $job ) {
			$job = Craft::$app->getQueue()->status( $job );
			switch ( $job ) {
				case Queue::STATUS_DONE:
					$completed ++;
					break;
				case Queue::STATUS_FAILED:
					$error ++;
					break;
				default:
					$inProcess ++;
			}
		}

		return [
			'total'     => count( $jobs ),
			'completed' => $completed,
			'error'     => $error,
			'inProcess' => $inProcess
		];
	}

	private function isUIElement( $field ): bool {
		return in_array( get_class( $field ), [
			'craft\fieldlayoutelements\Heading',
			'craft\fieldlayoutelements\Tip',
			'craft\fieldlayoutelements\Template',
			'craft\fieldlayoutelements\HorizontalRule',
			'craft\fieldlayoutelements\LineBreak'
		] );
	}

	private function isMatrixField( $field ): bool {
		return get_class( $field ) === 'craft\fields\Matrix';
	}

    private function saveElement($element) : bool {
        /** @var SettingsModel $settings */
        $settings = BuddyPlugin::getInstance()->getSettings();
        $maxAttempts = max($settings->maxAttempts,1);
        $attempts = 0;
        $success = false;
        while($attempts < $maxAttempts && !$success) {
            $attempts++;
            try {
                $success = Craft::$app->elements->saveElement($element, false, false);
                if($success) {
                    Craft::info('Element saved after '.$attempts.' attempts: ' . $element->id, 'content-buddy');
                }
            } catch(\Exception|\Throwable $e) {
                $success = false;
                if($attempts >= $maxAttempts) {
                    Craft::error('Failed to save element after '.$maxAttempts.' attempts: ' . $e->getMessage(), 'content-buddy');
                    throw $e;
                }
            }
        }
        return $success;
    }
}
