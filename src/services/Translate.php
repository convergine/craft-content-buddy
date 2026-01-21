<?php

namespace convergine\contentbuddy\services;

use convergine\contentbuddy\BuddyPlugin;
use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\queue\translateEntries;
use convergine\contentbuddy\records\ExcludeFromBulk;
use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;
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
use ether\seo\models\data\SeoData;
use nystudio107\seomatic\models\MetaBundle;
use verbb\hyper\models\LinkCollection;
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

		$type = $_section[1] ?? 0;
		if ( $_section[0] == 'product' ) {
			$type = Commerce::getInstance()->getProductTypes()->getProductTypeById( $type );
		} else {
			$section = $isCraft5 ? Craft::$app->entries->getSectionById( $_section[0] ) : Craft::$app->sections->getSectionById( $_section[0] );
			if ( $type ) {
				$type = $isCraft5 ? Craft::$app->entries->getEntryTypeById( $type ) : Craft::$app->sections->getEntryTypeById( $type );
			} else {
				$type = $section->getEntryTypes()[0];
			}
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
		if ( $this->_plugin->getSettings()->enableBulkTranslation ) {
			$sites[] = [
				'value' => 'all',
				'label' => Craft::t( 'convergine-contentbuddy', "To All Languages" )
			];
		}

		return $sites;
	}

	public function getSections( $enableBulkTranslation = false ): array {
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


		if ( self::isCommerceInstalled() ) {
			foreach ( Commerce::getInstance()->getProductTypes()->getAllProductTypes() as $type ) {
				$sections[] = [
					'value' => "product:" . $type->id,
					'label' => "Product - " . $type->name
				];
			}
		}

		if ( $enableBulkTranslation ) {
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
	): void {
		$primarySiteId  = Craft::$app->sites->getPrimarySite()->id;
		$_section       = explode( ':', $section );
		$sectionId      = $_section[0];
		$sectionType    = $_section[1];
		$_enabledFields = $this->getTranslatedFieldsBySectionType( $sectionType );


		$translate_to_list = [];
		if ( $translate_to === 'all' ) {
			if($sectionId == 'product'){
				//TODO add support for products
			}else{
				$sectionSites = version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ? Craft::$app->entries->getSectionById( $sectionId ) : Craft::$app->sections->getSectionById( $sectionId );
				foreach ( $sectionSites->getSiteSettings() as $site ) {
					if ( $site->siteId != $primarySiteId ) {
						$translate_to_list[] = $site->siteId;
					}

				}
			}

		} else {
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
			if($sectionId == 'product'){
				$entries = Product::find()
				                  ->typeId( $sectionType )
				                  ->siteId( $primarySiteId );
			}else {
				$entries = Entry::find()
				                ->sectionId( $sectionId )
				                ->typeId( $sectionType )
				                ->siteId( $primarySiteId )
					//check if entry exits in exclude list
					             ->leftJoin(
						ExcludeFromBulk::tableName() . ' bulk',
						'[[elements.id]] = [[bulk.elementId]] AND [[bulk.siteId]]=' . $translate_to_site_id
					)
				                ->where( [ 'bulk.elementId' => null ] );
			}
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

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;

		$_entry = Category::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$_entry = $this->cloneElement( $entry, $translate_to );
		}

		if ( $_entry ) {
			try {
				$prompt          = $this->getPrompt( $translate_to_site, $entry->title );
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

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
					if ( $fieldType == 'craft\ckeditor\Field' ) {
						$fieldValue  = $entry->getFieldValue( $fieldHandle );
						$entry_value = $fieldValue !== null ? $fieldValue->getRawContent() : $fieldValue;
                        $entry_value = $this->fixAssets($entry_value,$translate_to_site->id);
					} else {
						$entry_value = $entry->getFieldValue( $fieldHandle );
					}

					$fieldsProcessed ++;

                    if (!in_array($fieldType, [
                        'verbb\hyper\fields\HyperField'
                    ])) {
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
                    }

					try {
                        if ($fieldType == 'verbb\hyper\fields\HyperField' && $entry_value instanceof LinkCollection) {
                            $translatedValue = $this->translateHyper($entry_value, $translate_to_site, $translate_from_site, $instructions);
                            $_entry->setFieldValue($fieldHandle, $translatedValue);
                        } else {
                            $prompt          = $this->getPrompt( $translate_to_site, $entry_value );
                            $translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

                            Craft::info( $prompt, 'content-buddy' );
                            Craft::info( $fieldHandle . ' (' . $fieldType . ')', 'content-buddy' );
                            Craft::info( $translated_text, 'content-buddy' );

                            $translated_text = $this->_sanitizeText($translated_text);

                            if ( $fieldType == 'craft\ckeditor\Field' ) {
                                $translated_text = $this->translateEntriesInCKEditorField( $translated_text, $translate_to_site, $translate_from_site, $instructions );
                                Craft::info( 'New CKEditor translated text: ' . $translated_text, 'content-buddy' );
                            }

                            $_entry->setFieldValue( $fieldHandle, $translated_text );
                        }

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
						Craft::error( 'Failed to translate field "' . $fieldHandle . '": ' . $e->getMessage(), 'content-buddy' );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {

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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

					$block = $_field[1];

					$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $override, $instructions );
					$_entry->setFieldValues( $fieldValues );
				}


			}

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;

		$_entry = Asset::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$_entry = $this->cloneElement( $entry, $translate_to );
		}

		if ( $_entry ) {
			try {
				$prompt          = $this->getPrompt( $translate_to_site, $entry->title );
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

				$_entry->title = $translated_text;

				$fieldsTranslated ++;
			} catch ( \Throwable $e ) {

				$fieldsError ++;
				$this->_addLog( $translateId, $entry->id, $e->getMessage(), 'title' );
			}
			$fieldsProcessed ++;

			//alt text
			if ( ! empty( $entry->alt ) ) {
				try {
					$prompt          = $this->getPrompt( $translate_to_site, $entry->alt );
					$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );
					$_entry->alt     = $translated_text;
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
					if ( $fieldType == 'craft\ckeditor\Field' ) {
						$fieldValue  = $entry->getFieldValue( $fieldHandle );
						$entry_value = $fieldValue !== null ? $fieldValue->getRawContent() : $fieldValue;
                        $entry_value = $this->fixAssets($entry_value,$translate_to_site->id);
					} else {
						$entry_value = $entry->getFieldValue( $fieldHandle );
					}

					$fieldsProcessed ++;

                    if (!in_array($fieldType, [
                        'verbb\hyper\fields\HyperField'
                    ])) {
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
                    }

					try {
                        if ($fieldType == 'verbb\hyper\fields\HyperField' && $entry_value instanceof LinkCollection) {
                            $translatedValue = $this->translateHyper($entry_value, $translate_to_site, $translate_from_site, $instructions);
                            $_entry->setFieldValue($fieldHandle, $translatedValue);
                        } else {
                            $prompt          = $this->getPrompt( $translate_to_site, $entry_value );
                            $translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

                            Craft::info( $prompt, 'content-buddy' );
                            Craft::info( $fieldHandle . ' (' . $fieldType . ')', 'content-buddy' );
                            Craft::info( $translated_text, 'content-buddy' );

                            $translated_text = $this->_sanitizeText($translated_text);

                            if ( $fieldType == 'craft\ckeditor\Field' ) {
                                $translated_text = $this->translateEntriesInCKEditorField( $translated_text, $translate_to_site, $translate_from_site, $instructions );
                                Craft::info( 'New CKEditor translated text: ' . $translated_text, 'content-buddy' );
                            }

                            $_entry->setFieldValue( $fieldHandle, $translated_text );
                        }

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
						Craft::error( 'Failed to translate field "' . $fieldHandle . '": ' . $e->getMessage(), 'content-buddy' );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {

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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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
					$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $override, $instructions );
					$_entry->setFieldValues( $fieldValues );
				}
			}

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

	public function translateProduct(
		Product|Variant $entry,
		int $translate_to,
		array $enabledFields,
		int $translateId,
		string $instructions = '',

	): bool {
		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;

		$_entry = Product::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$_entry = Variant::find()->id( $entry->id )->siteId( $translate_to )->one();
		}

		// TODO check if wee need clone product
		/*if(!$_entry) {
			$_entry = $this->cloneElement($entry, $translate_to);
		}*/

		if ( $_entry ) {
			try {
				$prompt          = $this->getPrompt( $translate_to_site, $entry->title );
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

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
					if ( $fieldType == 'craft\ckeditor\Field' ) {
						$fieldValue  = $entry->getFieldValue( $fieldHandle );
						$entry_value = $fieldValue !== null ? $fieldValue->getRawContent() : $fieldValue;
                        $entry_value = $this->fixAssets($entry_value,$translate_to_site->id);
					} else {
						$entry_value = $entry->getFieldValue( $fieldHandle );
					}

					$fieldsProcessed ++;

                    if (!in_array($fieldType, [
                        'verbb\hyper\fields\HyperField'
                    ])) {
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
                    }

					try {
                        if ($fieldType == 'verbb\hyper\fields\HyperField' && $entry_value instanceof LinkCollection) {
                            $translatedValue = $this->translateHyper($entry_value, $translate_to_site, $translate_from_site, $instructions);
                            $_entry->setFieldValue($fieldHandle, $translatedValue);
                        } else {
                            $prompt          = $this->getPrompt( $translate_to_site, $entry_value );
                            $translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

                            Craft::info( $prompt, 'content-buddy' );
                            Craft::info( $fieldHandle . ' (' . $fieldType . ')', 'content-buddy' );
                            Craft::info( $translated_text, 'content-buddy' );

                            $translated_text = $this->_sanitizeText($translated_text);

                            if ( $fieldType == 'craft\ckeditor\Field' ) {
                                $translated_text = $this->translateEntriesInCKEditorField( $translated_text, $translate_to_site, $translate_from_site, $instructions );
                                Craft::info( 'New CKEditor translated text: ' . $translated_text, 'content-buddy' );
                            }

                            $_entry->setFieldValue( $fieldHandle, $translated_text );
                        }

						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
						Craft::error( 'Failed to translate field "' . $fieldHandle . '": ' . $e->getMessage(), 'content-buddy' );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {

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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

					$block = $_field[1];

					$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $override, $instructions );
					$_entry->setFieldValues( $fieldValues );
				} elseif ( $fieldType == 'craft\commerce\fieldlayoutelements\VariantsField' ) {
					Craft::dump( $_field );
					$block = $_field[1];

					/*$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $override, $instructions );
					$_entry->setFieldValues($fieldValues);*/

					foreach ( $entry->getVariants() as $variant ) {
						$variantFields        = $this->getTranslatedFields( $variant );
						$variantEnabledFields = array();
						foreach ( $variantFields['regular'] as $f ) {
							$variantEnabledFields[] = "{$f['_type']}:{$f['handle']}";
						}
						if ( version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ) {
							if ( count( $variantFields['matrix'] ) ) {
								$variantEnabledFields[] = "craft\\fields\\Matrix:fields";
							}
						} else {
							foreach ( $variantFields['matrix'] as $f ) {
								foreach ( $f['fields'] as $mf ) {
									$variantEnabledFields[] = $mf['_field'];
								}
							}
						}
						Craft::dump( $variantFields );
						Craft::dump( $variantEnabledFields );
						$this->translateProduct( $variant, $translate_to, $variantEnabledFields, $translateId, $instructions );
					}
				}
			}

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

		$translateRecord = TranslateRecord::findOne( $translateId );
		$override        = $translateRecord->override;
		$hasError        = false;

		$fieldsProcessed = $fieldsSkipped = $fieldsError = $fieldsTranslated = 0;

		$_entry = Entry::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$_entry = $this->cloneElement( $entry, $translate_to );
		}

		if ( $_entry ) {
			try {
				$prompt          = $this->getPrompt( $translate_to_site, $entry->title );
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

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
					if ( $fieldType == 'craft\ckeditor\Field' ) {
						$fieldValue  = $entry->getFieldValue( $fieldHandle );
						$entry_value = $fieldValue !== null ? $fieldValue->getRawContent() : $fieldValue;
                        $entry_value = $this->fixAssets($entry_value,$translate_to_site->id);
					} else {
						$entry_value = $entry->getFieldValue( $fieldHandle );
					}

					$fieldsProcessed ++;

					if (!in_array($fieldType, [
                        'ether\seo\fields\SeoField',
                        'verbb\hyper\fields\HyperField',
                        'nystudio107\seomatic\fields\SeoSettings'
                    ])) {
						// check field not empty
						if ( strlen( (string) $entry_value ) == 0 ) {
							$fieldsSkipped ++;
							continue;
						}

						//check if field is already translated and selected NOT OVERRIDE
						if ( ! $override && (string) $entry_value != (string) $_entry->getFieldValue( $fieldHandle ) ) {
							$fieldsSkipped ++;
							continue;
						}
					}

					try {
						if ( $fieldType == 'ether\seo\fields\SeoField' && $entry_value instanceof SeoData ) {
							$site            = Craft::$app->sites->getSiteById( $translate_to );
							$source_site     = Craft::$app->sites->getSiteById( $entry->siteId );
							$translatedValue = $this->translateSeoData( $entry_value, $site, $source_site, $instructions );
							$_entry->setFieldValue( $fieldHandle, $translatedValue );
						} else if ($fieldType == 'verbb\hyper\fields\HyperField' && $entry_value instanceof LinkCollection) {
                            $translatedValue = $this->translateHyper($entry_value, $translate_to_site, $translate_from_site, $instructions);
                            $_entry->setFieldValue($fieldHandle, $translatedValue);
                        } else if ($fieldType == 'nystudio107\seomatic\fields\SeoSettings' && $entry_value instanceof MetaBundle) {
                            $translatedValue = $this->translateSeomatic($entry_value, $translate_to_site, $translate_from_site, $instructions);
                            $_entry->setFieldValue($fieldHandle, $translatedValue);
                        } else {
							$prompt          = $this->getPrompt( $translate_to_site, $entry_value );
							$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

							Craft::info( $prompt, 'content-buddy' );
							Craft::info( $fieldHandle . ' (' . $fieldType . ')', 'content-buddy' );
							Craft::info( $translated_text, 'content-buddy' );

							$translated_text = $this->_sanitizeText($translated_text);

							if ( $fieldType == 'craft\ckeditor\Field' ) {
								$translated_text = $this->translateEntriesInCKEditorField( $translated_text, $translate_to_site, $translate_from_site, $instructions );
								Craft::info( 'New CKEditor translated text: ' . $translated_text, 'content-buddy' );
							}

							$_entry->setFieldValue( $fieldHandle, $translated_text );
						}
						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
						Craft::error( 'Failed to translate field "' . $fieldHandle . '": ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'content-buddy' );
					}

					// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {

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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

					$block = $_field[1];

					$fieldValues = $this->processMatrixFields( $lang, $entry, $translate_to, $translateId, $override, $instructions );
					$_entry->setFieldValues( $fieldValues );
				}


			}

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

	private function cloneElement( Element $originalEntry, $siteId ): ?Element {
		$newEntry         = clone $originalEntry;
		$newEntry->siteId = $siteId;
		$this->saveElement( $newEntry );

		foreach ( $originalEntry->getFieldLayout()->getCustomFields() as $field ) {
			if ( in_array( get_class( $field ), static::$matrixFields ) ) {
				$query = $originalEntry->getFieldValue( $field->handle );
				foreach ( $query->all() as $matrixElement ) {
					$newMatrixElement           = clone $matrixElement;
					$newMatrixElement->siteId   = $siteId;
					$newMatrixElement->parentId = $newEntry->id;
					$this->saveElement( $newMatrixElement );
				}
			}
		}

		return $newEntry;
	}

	public function processMatrixFields( string $lang, Element $entry_from, int $translate_to, int $translateId, $override, $instructions = '', $or_entry = true ): array {
		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );

		$translate_from_site = Craft::$app->sites->getSiteById( $entry_from->siteId );
		$source_lang         = $translate_from_site->language;

		$target      = [];
		$targetEntry = Entry::find()->id( $entry_from->id )->siteId( $translate_to )->one();
		if ( ! $or_entry && ! empty( $entry_from->title ) && $entry_from->getIsTitleTranslatable() ) {
			try {
				$prompt          = $this->getPrompt( $translate_to_site, $entry_from->title );
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );
				$target['title'] = $translated_text;
			} catch ( \Throwable $e ) {
				$this->_addLog( $translateId, $entry_from->id, $e->getMessage() . "\n" . $e->getTraceAsString(), 'title', 0 );
			}
		}

		foreach ( $entry_from->fieldLayout->getCustomFields() as $field ) {
			$translatedValue   = null;
			$fieldTranslatable = $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
			$processField      = boolval( $fieldTranslatable ); // if translatable
			$fieldType         = get_class( $field );
            $fieldValue        = $entry_from->getFieldValue( $field->handle );

			if ( in_array( $fieldType, static::$textFields ) && $processField && ! $or_entry ) {
				if ( $fieldType == 'craft\ckeditor\Field' ) {
					//$originalFieldValue = $field->serializeValue( $fieldValue !== null ? $this->fixAssets($fieldValue->getRawContent(),$translate_to) : $fieldValue, $entry_from );
					$originalFieldValue = $fieldValue !== null ? $this->fixAssets($fieldValue->getRawContent(),$translate_to) : $fieldValue;
				} else {
					$originalFieldValue = $field->serializeValue($fieldValue, $entry_from );
				}

				$translatedValue = $originalFieldValue;
				if ( $originalFieldValue ) {
					try {
						$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
						$translatedValue = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

						Craft::info( $prompt, 'content-buddy' );
						Craft::info( $field->handle . ' (' . $fieldType . ')', 'content-buddy' );
						Craft::info( $translatedValue, 'content-buddy' );

						$translatedValue = $this->_sanitizeText($translatedValue);
						if ( $fieldType == 'craft\ckeditor\Field' ) {
							$translatedValue = $this->translateEntriesInCKEditorField( $translatedValue, $translate_to_site, $translate_from_site, $instructions );
							Craft::info( 'New CKEditor translated text: ' . $translatedValue, 'content-buddy' );
						} else {
							Craft::info( 'Not a CKEditor field, skipping', 'content-buddy' );
						}

					} catch ( \Throwable $e ) {
						//$fieldsError ++;
						$this->_addLog( $translateId, $entry_from->id, $e->getMessage() . "\n" . $e->getTraceAsString(), $field, 0 );
					}
				}
			} elseif ( in_array( get_class( $field ), static::$matrixFields ) ) {
				// dig deeper in Matrix fields
				$translatedValue = $this->translateMatrixField( $lang, $entry_from, $field, $translate_to, $translateId, $override, $instructions );

			} elseif ( get_class( $field ) == 'ether\seo\fields\SeoField' ) {
				$seo = $field->serializeValue($fieldValue, $entry_from );
				//$seo = $entry_from->getFieldValue($field->handle);
				if ( $seo instanceof SeoData ) {
					$site            = Craft::$app->sites->getSiteById( $translate_to );
					$source_site     = Craft::$app->sites->getSiteById( $entry_from->siteId );
					$translatedValue = $this->translateSeoData( $seo, $site, $source_site, $instructions );
				}
			} elseif ($fieldType == 'verbb\hyper\fields\HyperField' and $fieldValue instanceof LinkCollection) {
                $translatedValue = $this->translateHyper($fieldValue, $translate_to_site, $translate_from_site, $instructions);
            }

			if ( $translatedValue ) {
				$target[ $field->handle ] = $translatedValue;
			} else {
				if ( ! $or_entry && $targetEntry ) {
					$target[ $field->handle ] = $field->serializeValue( $targetEntry->getFieldValue( $field->handle ), $targetEntry );
				}

			}
		}

		return $target;
	}

	public function translateMatrixField( string $lang, Element $element, FieldInterface $field, int $translate_to, int $translateId, $override, $instructions = '' ): array {
		$query = $element->getFieldValue( $field->handle );

		// serialize current value
		$serialized = $element->getSerializedFieldValues( [ $field->handle ] )[ $field->handle ];

		foreach ( $query->all() as $matrixElement ) {
			$translatedMatrixValues = $this->processMatrixFields( $lang, $matrixElement, $translate_to, $translateId, $override, $instructions, false );
			foreach ( $translatedMatrixValues as $matrixFieldHandle => $value ) {
				// only set translated values in matrix array
				if ( $value && isset( $serialized[ $matrixElement->id ] ) ) {
					if ( $matrixFieldHandle == 'title' ) {
						$serialized[ $matrixElement->id ][ $matrixFieldHandle ] = $value;
					} else {
						$serialized[ $matrixElement->id ]['fields'][ $matrixFieldHandle ] = $value;
					}
				}
			}
		}

		if ( get_class( $field ) == 'benf\neo\Field' && $field->translationMethod == 'all' ) {
			// special case to avoid neo overwriting blocks in all languages
			return [ 'blocks' => $serialized ];
		}

		return $serialized;
	}

	public function reTranslateEntry(
		Entry $entry,
		int $translate_to,
		int $translateId,
		string $instructions = '',

	): bool {

		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

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
			if ( $titleErrorLog ) {

				try {
					$prompt          = $this->getPrompt( $translate_to_site, $entry->title );
					$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

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
							$prompt          = $this->getPrompt( $translate_to_site, $entry->getFieldValue( $fieldHandle ) );
							$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

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
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {

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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );

								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->$handle = $translated_text;

								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

								$prompt          = $this->getPrompt( $translate_to_site, $originalFieldValue );
								$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );

								Craft::info( 'Saving entry (' . $matrixBlockTarget[ $k ]->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
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

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
			if ( $this->saveElement( $_entry ) ) {
				$translateRecord->fieldsTranslated = $fieldsTranslated;
				$translateRecord->fieldsError      = $fieldsError;

				$translateRecord->save();

				return true;

			}
		}

		return false;
	}

	public function translateSlug( Entry $entry, int $translate_to, string $instructions = '' ): bool {
		$translate_to_site = Craft::$app->sites->getSiteById( $translate_to );
		$lang              = $translate_to_site->language;

		$translate_from_site = Craft::$app->sites->getSiteById( $entry->siteId );
		$source_lang         = $translate_from_site->language;

		$hasError = false;

		$_entry = Entry::find()->id( $entry->id )->siteId( $translate_to )->one();
		if ( ! $_entry ) {
			$entry->siteId = $translate_to;
			Craft::info( 'Saving entry (' . $entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
			$this->saveElement( $entry );
			$_entry = $entry;
		}

		if ( $_entry ) {
			$prompt = "Translate this URL slug to $lang. Only return the URL slug for the following text: $entry->slug";
			try {
				$translated_text = BuddyPlugin::getInstance()->request->send( $prompt, 30000, 0.7, true, $instructions, $lang, $source_lang );

				$_entry->slug = $translated_text;

			} catch ( \Throwable $e ) {
				$hasError = true;
			}

			Craft::info( 'Saving entry (' . $_entry->id . ') with site (' . $translate_to . ') on line (' . __LINE__ . ')', 'content-buddy' );
			if ( $this->saveElement( $_entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $text string initially translated text
	 * @param $site Site site to translate to
	 * @param $source_site Site source site to translate from
	 *
	 * @return string full translated text including craft-entries
	 */
	private function translateEntriesInCKEditorField( string $text, Site $site, Site $source_site, $instructions = '' ): string {
		preg_match_all( '/<craft-entry[^>]*data-entry-id=["\'](\d+)["\'][^>]*>.*?<\/craft-entry>/', $text, $matches );
		$entryIds = $matches[1];

		$entryMap = [];

		Craft::info( "CKEditor nested entries: " . json_encode( $entryIds ), 'content-buddy' );

		foreach ( $entryIds as $id ) {
			$entry = Entry::find()->id( $id )->one();

			if ( $entry ) {
				try {
					Craft::info( "Found nested Entry: " . $entry->title, 'content-buddy' );

					$newEntry            = new Entry();
					$newEntry->title     = $this->translateText( $entry->title, '', $site, $source_site, $instructions );
					$newEntry->slug      = $entry->slug;
					$newEntry->sectionId = $entry->sectionId;
					$newEntry->ownerId   = $entry->ownerId;
					$newEntry->typeId    = $entry->typeId;
					$newEntry->fieldId   = $entry->fieldId;
					$newEntry->siteId    = $site->id;
					$newEntry->enabled   = true;

					Craft::info( "Created new nested Entry: " . $newEntry->title . ', sectionId: ' . $newEntry->sectionId . ', ownerId: ' . $newEntry->ownerId . ', typeId: ' . $newEntry->typeId . ', fieldId: ' . $newEntry->fieldId, 'content-buddy' );

					foreach ( $entry->getFieldValues() as $fieldHandle => $value ) {
						$field     = Craft::$app->fields->getFieldByHandle( $fieldHandle );
						$fieldType = $field ? get_class( $field ) : 'Unknown';
						if ( in_array( $fieldType, $this->_plugin->base->getSupportedFieldTypes() ) ) {
							$newText = $this->translateText( $value, $fieldType, $site, $source_site, $instructions );
							$newEntry->setFieldValue( $fieldHandle, $newText );
							Craft::info( "Saved field in new nested Entry: " . $fieldHandle . ": $newText", 'content-buddy' );
						}
					}

					if ( $this->saveElement( $newEntry ) ) {
						$entryMap[ $id ] = $newEntry->id;
						Craft::info( "Saved new nested Entry: " . $newEntry->title, 'content-buddy' );
					} else {
						Craft::error( "Failed to save translated entry for ID: $id", __METHOD__ );
					}
				} catch ( \Exception $e ) {
					Craft::error( "Failed to translate nested entry: " . $e->getMessage(), 'content-buddy' );
				}
			}
		}

		Craft::info( "CKEditor nested entries mapped: " . json_encode( $entryMap ), 'content-buddy' );

		$translatedText = preg_replace_callback(
			'/(<craft-entry[^>]*data-entry-id=["\'])(\d+)(["\'][^>]*>.*?<\/craft-entry>)/',
			function ( $matches ) use ( $entryMap ) {
				$originalId = $matches[2];
				$newId      = $entryMap[ $originalId ] ?? $originalId;

				return $matches[1] . $newId . $matches[3];
			},
			$text
		);

		Craft::info( "CKEditor translated text: $translatedText", 'content-buddy' );

		return $translatedText;
	}

	private function translateText( $text, $fieldType, Site $site, Site $source_site, $instructions = '' ): string {
		if ( trim( $text ) == '' ) {
			return '';
		}

		$lang        = $site->language;
		$source_lang = $source_site->language;

		$request = $this->getPrompt( $site, $text );
		$input   = $request['prompt'] ?? $text;

		Craft::info( "translateText() - input: " . $input, 'content-buddy' );
		$translated_text = BuddyPlugin::getInstance()->request->send( $request, 30000, 0.7, true, $instructions, $lang, $source_lang );
		Craft::info( "translateText() - output: " . $translated_text, 'content-buddy' );

		if ( $fieldType == 'craft\ckeditor\Field' ) {
			$translated_text = $this->translateEntriesInCKEditorField( $translated_text, $site, $source_site, $instructions );
		}

		return $this->_sanitizeText($translated_text);
	}

	private function translateSeoData( SeoData $input, Site $site, Site $source_site, $instructions = '' ): SeoData {
		$seo = clone $input;

		Craft::info( "Translating SEO data: " . json_encode( $input ), 'content-buddy' );

		foreach ( $input->titleRaw as $key => $title ) {
			Craft::info( "Translating SEO text: " . gettype( $title ) . " " . json_encode( $title ), 'content-buddy' );
			$seo->titleRaw[ $key ] = $this->translateText( $title, 'craft\fields\PlainText', $site, $source_site, $instructions );
		}

		Craft::info( "Translating SEO text: " . gettype( $input->descriptionRaw ) . " " . json_encode( $input->descriptionRaw ), 'content-buddy' );
		$seo->descriptionRaw = $this->translateText( $input->descriptionRaw, 'craft\fields\PlainText', $site, $source_site, $instructions );

		foreach ( $input->keywords as $key => $keyword ) {
			Craft::info( "Translating SEO text: " . gettype( $keyword['keyword'] ) . " " . json_encode( $keyword['keyword'] ), 'content-buddy' );
			$seo->keywords[ $key ]['keyword'] = $this->translateText( $keyword['keyword'], 'craft\fields\PlainText', $site, $source_site, $instructions );
		}

		Craft::info( "Translating SEO social: " . gettype( $input->social ) . " " . json_encode( $input->social ), 'content-buddy' );
		foreach ( $input->social as $key => $social ) {
			Craft::info( "Translating SEO text: " . gettype( $social->title ) . " " . json_encode( $social->title ), 'content-buddy' );
			$seo->social[ $key ]->title = $this->translateText( $social->title, 'craft\fields\PlainText', $site, $source_site, $instructions );
			Craft::info( "Translating SEO text: " . gettype( $social->description ) . " " . json_encode( $social->description ), 'content-buddy' );
			$seo->social[ $key ]->description = $this->translateText( $social->description, 'craft\fields\PlainText', $site, $source_site, $instructions );
		}

		Craft::info( "Translated SEO data: " . json_encode( $seo ), 'content-buddy' );

		return $seo;
	}

    private function translateHyper(LinkCollection $hyperLinks, Site $site, Site $sourceSite, $instructions = ''): LinkCollection
    {
        Craft::info( "Translating Hyper links: " . json_encode( $hyperLinks ), 'content-buddy' );

        $links = [];
        foreach ($hyperLinks->getLinks() as $hyperLink) {
            if (!empty($hyperLink->linkText)) {
                $hyperLink->linkText = $this->translateText($hyperLink->linkText, 'craft\fields\PlainText', $site, $sourceSite, $instructions);
            }
            if (!empty($hyperLink->linkTitle)) {
                $hyperLink->linkTitle = $this->translateText($hyperLink->linkTitle, 'craft\fields\PlainText', $site, $sourceSite, $instructions);
            }

            $links[] = $hyperLink;
        }

        $hyperLinks->setLinks($links);
        return $hyperLinks;
    }

    private function translateSeomatic(MetaBundle $bundle, Site $site, Site $sourceSite, $instructions = ''): MetaBundle
    {
        foreach ($bundle->metaGlobalVars->overrides as $property => $override) {
            $sourceProperty = $property . 'Source';
            if (!$override                                                              // Skip if override is disabled
                || in_array($property, ['siteNamePosition', 'twitterSiteNamePosition']) // Skip properties that can be custom, but can only be set to predefined values
                || empty($bundle->metaBundleSettings->$sourceProperty)                  // Skip if source property can't be verified
                || $bundle->metaBundleSettings->$sourceProperty !== 'fromCustom'        // Only properties that have been set to custom
                || str_contains($bundle->metaGlobalVars->$property, '{{')               // Skip properties containing twig
            ) {
                continue;
            }

            $bundle->metaGlobalVars->$property = $this->translateText($bundle->metaGlobalVars->$property, 'craft\fields\PlainText', $site, $sourceSite, $instructions);
        }

        return $bundle;
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
		if ( $field instanceof FieldInterface ) {
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

	public function setBatchLimit( ActiveQuery|EntryQuery|ProductQuery $record ): BatchQueryResult {

		//Todo update limits
		if ( $this->_plugin->base->isGTP4() ) {
			$record = $record->batch( 1 );

			return $record;
		}
		$record = $record->batch( 4 );

		return $record;
	}

	public function getEntryFieldsCount( Entry|Product $entry, array $fields ) {
		$fieldsCount = 1;
		foreach ( $fields as $field ) {
			$_field = explode( ":", $field );
			if ( in_array( $_field[0], $this->_plugin->base->getSupportedFieldTypes() ) ) {
				$fieldsCount ++;
			} elseif ( $_field[0] == 'craft\fields\Matrix' && class_exists( 'craft\elements\MatrixBlock' ) && version_compare( Craft::$app->getInfo()->version, '5.0', '<' ) ) {
				$fieldHandle      = $_field[2];
				$block            = $_field[1];
				$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block )->all();
				$fieldsCount      += count( $matrixFieldQuery );
			} else {
				if ( isset( $_field[2] ) ) {
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
		Craft::info( 'Getting translated fields for entry (' . $entry->id . '): ' . json_encode( $fieldLayout ), 'content-buddy' );

		return $this->_getLayoutFields( $fieldLayout );
	}

	public function getTranslatedFieldsBySectionType( $entryTypeId ): array {
		$enabledFields = [];

		if ( version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ) {
			$fieldLayout = Craft::$app->entries->getEntryTypeById( $entryTypeId )->getFieldLayout();
			$entryFields = $this->_getLayoutFields( $fieldLayout );
			foreach ( $entryFields['regular'] as $f ) {
				$enabledFields[] = "{$f['_type']}:{$f['handle']}";
			}
			if ( count( $entryFields['matrix'] ) ) {
				$enabledFields[] = "craft\\fields\\Matrix:fields";
			}
		} else {
			$fieldLayout = Craft::$app->sections->getEntryTypeById( $entryTypeId )->getFieldLayout();
			$entryFields = $this->_getLayoutFields( $fieldLayout );
			foreach ( $entryFields['regular'] as $f ) {
				$enabledFields[] = "{$f['_type']}:{$f['handle']}";
			}
			foreach ( $entryFields['matrix'] as $f ) {
				foreach ( $f['fields'] as $mf ) {
					$enabledFields[] = $mf['_field'];
				}
			}
		}

		return $enabledFields;
	}

	public function getEntryTranslateControl( Element $entry ): string {
		$currentSite  = $entry->siteId;
		$sites        = [];
		$sectionSites = [];

		if ( $entry::class == 'craft\elements\Category' || $entry::class == 'craft\elements\Asset' || $entry::class == 'craft\commerce\elements\Product' ) {
			$sectionSites = $entry->getSupportedSites();
		} else if ( $entry::class == 'craft\elements\Entry' ) {
			if ( ! empty( $entry->section ) ) { //not a headless entry
				if ( version_compare( Craft::$app->getInfo()->version, '5.0', '>=' ) ) {
					$sectionSites = $entry->getSection()->getSiteSettings();
				} else {
					$section      = \Craft::$app->sections->getSectionById( $entry->sectionId );
					$sectionSites = $section->getSiteSettings();
				}
			}
		}
		if ( ! $sectionSites ) {
			return '';
		}

		foreach ( $sectionSites as $site ) {
			if ( isset( $site['siteId'] ) ) {
				$siteId = $site['siteId'];
			} elseif ( isset( $site->siteId ) ) {
				$siteId = $site->siteId;
			} else {
				$siteId = $site;
			}

			if ( $siteId != $currentSite ) {
				$siteObj          = Craft::$app->sites->getSiteById( $siteId );
				$sites[ $siteId ] = $siteObj->name . " : " . $siteObj->language;
			}

		}


		$action = match ( $entry::class ) {
			'craft\elements\Category' => 'convergine-contentbuddy/translate/process-category',
			'craft\elements\Asset' => 'convergine-contentbuddy/translate/process-asset',
			'craft\commerce\elements\Product' => 'convergine-contentbuddy/translate/process-product',
			default => 'convergine-contentbuddy/translate/process-entry',
		};

		return Craft::$app->view->renderTemplate( 'convergine-contentbuddy/translate/_control.twig', [
			'sites'                 => $sites,
			'action'                => $action,
			'enableBulkTranslation' => $this->_plugin->getSettings()->enableBulkTranslation,
			'is5version'            => version_compare( Craft::$app->getInfo()->version, '5.0', '>=' )
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

	private function getPrompt( ?Site $site, $text ): array|bool {
		if ( ! $site ) {
			return false;
		}
		$language = Craft::$app->getI18n()->getLocaleById( $site->language )->getDisplayName() . ' (' . $site->language . ')';
		$prompt   = "Translate to $language. ";

		$tags = $this->getHtmlTags( $text );
		if ( $tags ) {
			$prompt .= "Preserve all existing HTML tags, including <" . implode( ">, <", $tags ) . ">. Do not add or remove any HTML tags. Preserve link URLs and filenames to not break the functionality of the link or HTML code. ";
		}

		if ( str_contains( $text, '%20' ) ) {
			$prompt .= "Do NOT translate or alter any URLs in the text. Example: 'https://www.example.com/files/This%20Sentence%20Should%20Not%20Be%20Translated.png' should remain exactly as it appears in the input. ";
		}

		$prompt .= "Return the full translation for the following text: \n\n";

		return [ $prompt, $text ];
	}

	private function getHtmlTags( $text ): array {
		preg_match_all( '/<([a-zA-Z0-9-]+)(\s|>)/', $text, $matches );

		return array_unique( $matches[1] );
	}

    public function fixAssets($text,$targetSiteId): string {
        $_text =  preg_replace_callback(
            '/\{asset:\d+:url\|\|(.*?)\}/',
            function($matches) {
                return $matches[1];
            },
            $text
        );

	    $rewritten = preg_replace_callback(
		    '/\{entry:(\d+)(?:@(\d+))?:([^|}]+)(?:\|\|([^}]*))?\}/',
		    function ($m) use ($targetSiteId) {
			    $id   = $m[1];
			    $prop = $m[3];
			    $fb   = isset($m[4]) ? $m[4] : '';

			    return "{$fb}#entry:{$id}@{$targetSiteId}:{$prop}";
		    },
		    $_text
	    );

		  return $rewritten;
    }

	private function saveElement( $element ): bool {
		/** @var SettingsModel $settings */
		$settings    = BuddyPlugin::getInstance()->getSettings();
		$maxAttempts = max( $settings->maxAttempts, 1 );
		$attempts    = 0;
		$success     = false;
		while ( $attempts < $maxAttempts && ! $success ) {
			$attempts ++;
			try {
				$success = Craft::$app->elements->saveElement( $element, false, false );
				if ( $success ) {
					Craft::info( 'Element saved after ' . $attempts . ' attempts: ' . $element->id, 'content-buddy' );
				}
			} catch ( \Exception|\Throwable $e ) {
				$success = false;
				if ( $attempts >= $maxAttempts ) {
					Craft::error( 'Failed to save element after ' . $maxAttempts . ' attempts: ' . $e->getMessage(), 'content-buddy' );
					throw $e;
				}
			}
		}

		return $success;
	}

	public static function isCommerceInstalled(): bool {
		$installed           = false;
		$isCommerceInstalled = Craft::$app->getPlugins()->isPluginInstalled( 'commerce' );
		$isCommerceEnabled   = Craft::$app->getPlugins()->isPluginEnabled( 'commerce' );

		if ( $isCommerceInstalled && $isCommerceEnabled ) {
			//available on Commerce >=4
			$installed = version_compare( Craft::$app->getPlugins()->getPluginInfo( 'commerce' )['version'], '4.0', '>=' );
		}

		return $installed;
	}

	public function saveExcludeBulkSites( $element ): void {
        $request = Craft::$app->getRequest();
        if(!($request instanceof \craft\web\Request)) {
            return;
        }
        $params = $request->post();
		if(isset($params['elementId']) && $params['elementId'] == $element->id) {
			Craft::info( 'saveExcludeBulkSites', 'content-buddy' );
			Craft::info( $params, 'content-buddy' );
			if ( isset( $params['buddyExcludeType'] ) && $params['buddyExcludeType'] == 'primary' ) {
				Craft::info( 'Process all', 'content-buddy' );
				Craft::$app->getDb()
				           ->createCommand()
				           ->delete( ExcludeFromBulk::tableName(), [ 'elementId' => $element->id ] )
				           ->execute();
				$sites = $params['buddyExcludeSites'] ?? [];
				foreach ( $sites as $siteId => $flag ) {
					if ( $flag == 1 ) {
						$record            = new ExcludeFromBulk();
						$record->elementId = $element->id;
						$record->siteId    = $siteId;
						$record->save();
					}

				}
			} elseif ( isset( $params['buddyExcludeType'] ) && $params['buddyExcludeType'] == 'single' ) {
				Craft::info( 'Process single', 'content-buddy' );
				Craft::$app->getDb()
				           ->createCommand()
				           ->delete( ExcludeFromBulk::tableName(), [ 'elementId' => $element->id,'siteId'=>$element->siteId ] )
				           ->execute();
				if(isset($params['buddyExcludeSite']) && $params['buddyExcludeSite'] == 1){
					$record            = new ExcludeFromBulk();
					$record->elementId = $element->id;
					$record->siteId    = $element->siteId;
					$record->save();
				}
			}
		}
	}

	public function getExcludedEntries($elementId):array {
		$items = [];
		foreach (ExcludeFromBulk::find()->where(['elementId'=>$elementId])->all() as $row){
			$items[]=$row->siteId;
		}
		return $items;
	}

	private function _sanitizeText($text) {
		return preg_replace(
			'/^(?:```html\s*)?(.*?)(?:\s*```)?$/s',
			'$1',
			$text
		);
	}
}
