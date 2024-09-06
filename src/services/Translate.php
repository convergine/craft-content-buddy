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
use convergine\contentbuddy\records\TranslateLogRecord;
use convergine\contentbuddy\records\TranslateRecord;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\db\ActiveQuery;
use craft\db\ActiveRecord;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\models\FieldLayout;
use craft\queue\Queue;
use yii\db\BatchQueryResult;

class Translate extends Component {


	/**
	 * @var BuddyPlugin|null
	 */
	private BuddyPlugin $_plugin;

	public function init(): void {
		$this->_plugin = BuddyPlugin::getInstance();
	}

	public function getSectionFields( $id ) {
		$isCraft5 = version_compare(Craft::$app->getInfo()->version, '5.0', '>=');

		$_section     = explode( ":", $id );
		$section      = $isCraft5 ? Craft::$app->entries->getSectionById( $_section[0] ) : Craft::$app->sections->getSectionById( $_section[0] );
		$type         = $_section[1] ?? 0;
		if ( $type ) {
			$type = $isCraft5 ? Craft::$app->entries->getEntryTypeById( $type ) : Craft::$app->sections->getEntryTypeById( $type );
		} else {
			$type = $section->getEntryTypes()[0];
		}

		$layout = $type->getFieldLayout();

		return $this->_getLayoutFields($layout);
	}

	private function _getLayoutFields(FieldLayout $layout) {
        $matrixFields = $fields = [];
        $isCraft5 = version_compare(Craft::$app->getInfo()->version, '5.0', '>=');

        if($isCraft5) {
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

				        } elseif ( in_array( get_class( $field ), [
					        'craft\fields\Matrix'
				        ] ) ) {
					        /**
					         * @var craft\fields\Matrix $field
					         */
							  foreach($field->getEntryTypes() as $entryType) {
								  if ( ! isset( $matrixFields [ $field->handle ]['name'] ) ) {
									  $matrixFields [ $field->handle ]['name']   = $field->name;
									  $matrixFields [ $field->handle ]['handle'] = $entryType->handle;
									  $matrixFields [ $field->handle ]['type']   = 'craft\fields\Matrix';
									  $matrixFields [ $field->handle ]['fields'] = [];
								  }

								  //$entryType = Craft::$app->getEntries()->getEntryTypeByUid( $field->getSettings()['entryTypes'][0] );
								  foreach ( $entryType->getFieldLayout()->getTabs() as $typeTab ) {
									  foreach ( $typeTab->getElements() as $matrixLayoutElement ) {


										  if ( get_class( $matrixLayoutElement ) !== 'craft\fieldlayoutelements\entries\EntryTitleField' ) {
											  $matrixField = $matrixLayoutElement->getField();


											  $matrixFields [ $field->handle ]['fields'][] = [
												  'name'        => $matrixField->name,
												  'handle'      => $matrixField->handle,
												  'id'          => $matrixField->id,
												  'blockName'   => $field->name,
												  'blockHandle' => $field->handle,
												  'type'        => $this->_getClass( $matrixField ),
												  '_type'       => ( new \ReflectionClass( $matrixField ) )->getName()
											  ];
										  } elseif ( get_class( $matrixLayoutElement ) === 'craft\fieldlayoutelements\entries\EntryTitleField' ) {

											  $matrixFields [ $field->handle ]['fields'][] = [
												  'name'        => 'Title',
												  'handle'      => 'title',
												  'id'          => null,
												  'blockName'   => $field->name,
												  'blockHandle' => $field->handle,
												  'type'        => 'Title',
												  '_type'       => 'craft\fields\TextField'
											  ];

										  }

									  }
								  }
							  }
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

                        } elseif ( in_array( get_class( $field ), [
                            'craft\fields\Matrix'
                        ] ) ) {
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
                                        $matrixFields [ $field->handle ]['fields'][] = [
                                            'name'        => $blockField->name,
                                            'handle'      => $blockField->handle,
                                            'id'          => $blockField->id,
                                            'blockName'   => $block->name,
                                            'blockHandle' => $block->handle,
                                            'type'        => $this->_getClass( $blockField ),
                                            '_type'       => ( new \ReflectionClass( $blockField ) )->getName()
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

	private function _fillField($field) {

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

		return $sites;
	}

	public function getSections() {
		$sections = [ [ 'value' => '', 'label' => 'Please Select' ] ];
        $_sections = version_compare(Craft::$app->getInfo()->version, '5.0', '>=') ? Craft::$app->entries->getAllSections() : Craft::$app->sections->getAllSections();
		foreach ( $_sections as $section ) {
			if ( $section->type == 'channel' || $section->type == 'structure') {
				foreach ( $section->getEntryTypes() as $type ) {
					$sections[] = [
						'value' => $section->id . ":" . $type->id,
						'label' => $section->name . " - " . $type->name
					];
				}
			}
		}
		return $sections;
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
			Craft::$app->elements->saveElement( $entry, false );
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
					->send( $prompt . ": {$entry->title}", 30000, 0.7, true );

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
				$_field      = explode( ":", $field );
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
							->request->send( $prompt . ": {$entry->getFieldValue( $fieldHandle )}", 30000, 0.7, true );
                        Craft::info($prompt . ": {$entry->getFieldValue( $fieldHandle )}", 'content-buddy');
                        Craft::info($fieldHandle, 'content-buddy');
                        Craft::info($translated_text, 'content-buddy');
						$_entry->setFieldValue( $fieldHandle, $translated_text );
						$fieldsTranslated ++;
					} catch ( \Throwable $e ) {
						$fieldsError ++;
						$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field );
					}

				// process Craft4 Matrix field
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists('craft\elements\MatrixBlock') ) {

					$block            = $_field[2];
					$handle           = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $block )->type( $fieldHandle );

					$matrixBlockTarget = \craft\elements\MatrixBlock::find()
					                                ->field( $block )
					                                ->ownerId( $_entry->id )
					                                ->type( $fieldHandle )
					                                ->siteId( $translate_to )
					                                ->all();

					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						$fieldsProcessed ++;
						if(isset($matrixBlockTarget[ $k ])) {
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
								Craft::$app->elements->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
							} catch ( \Throwable $e ) {
								$fieldsError ++;
								$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
							}
						}
					}

				// process Craft5 Matrix field
				}elseif ($fieldType == 'craft\fields\Matrix'){
					$block            = $_field[2];
					$handle           = $_field[3];

					$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block );

					$matrixBlockTarget = \craft\elements\Entry::find()
					                                                ->field( $fieldHandle )
					                                                ->ownerId( $_entry->id )
					                                                ->type( $block )
					                                                ->siteId( $translate_to )
					                                                ->all();
					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						$fieldsProcessed ++;
						// process Matrix Title field
						if($handle == 'title'){

							try {
								$originalFieldValue = (string) $matrixField-> $handle ;
								$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->$handle ;
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

								$matrixBlockTarget[ $k ]->$handle = $translated_text ;
								Craft::$app->elements->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
							} catch ( \Throwable $e ) {
								$fieldsError ++;
								$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
							}
							// process Matrix Custom fields
						}elseif(isset($matrixBlockTarget[ $k ])) {
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
								Craft::$app->elements->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
							} catch ( \Throwable $e ) {
								$fieldsError ++;
								$this->_addLog( $translateId, $entry->id, $e->getMessage(), $field, $k );
							}
						}
					}
				}


			}

			if ( Craft::$app->elements->saveElement( $_entry, false ) ) {
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

							$this->_updateLog( $logRecord, $e->getMessage() );
						}
					}
				} elseif ( $fieldType == 'craft\fields\Matrix' && class_exists('craft\elements\MatrixBlock')) {

					$block            = $_field[2];
					$handle           = $_field[3];
					$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block );

					$matrixBlockTarget = \craft\elements\MatrixBlock::find()
					                                ->field( $fieldHandle )
					                                ->ownerId( $_entry->id )
					                                ->type( $block )
					                                ->siteId( $translate_to )
					                                ->all();
					foreach ( $matrixFieldQuery->all() as $k => $matrixField ) {
						if ( $logRecord->blockId == $k ) {
							try {
								$originalFieldValue = (string) $matrixField->getFieldValue( $handle );
								//$targetFieldValue   = (string) $matrixBlockTarget[ $k ]->getFieldValue( $handle );

								$translated_text = BuddyPlugin::getInstance()
									->request->send( $prompt . ": {$originalFieldValue}", 30000, 0.7, true );

								$matrixBlockTarget[ $k ]->setFieldValue( $handle, $translated_text );
								Craft::$app->elements->saveElement( $matrixBlockTarget[ $k ] );

								$fieldsTranslated ++;
								$fieldsError --;
								$logRecord->delete();
							} catch ( \Throwable $e ) {

								$this->_updateLog( $logRecord, $e->getMessage() );
							}
						}
					}

				}

			}
			if ( Craft::$app->elements->saveElement( $_entry, false ) ) {
				$translateRecord->fieldsTranslated = $fieldsTranslated;
				$translateRecord->fieldsError      = $fieldsError;

				$translateRecord->save();

				return true;

			}
		}

		return false;
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
			} elseif ( $_field[0] == 'craft\fields\Matrix' && class_exists('craft\elements\MatrixBlock')) {
				$fieldHandle      = $_field[2];
				$block            = $_field[1];
				$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block )->all();
				$fieldsCount      += count( $matrixFieldQuery );
			}else{
				$fieldHandle = $_field[1];
				$block            = $_field[2];
				$handle           = $_field[3];

				$matrixFieldQuery = $entry->getFieldValue( $fieldHandle )->type( $block )->all();

				$fieldsCount      += count( $matrixFieldQuery );
			}
		}

		return $fieldsCount;
	}

	public function getJobsInfo( $translateId ) {
		$jobsData = $this->_getJobsData($translateId);
		$text="";
		if($jobsData['completed']){
			$text.="<div style='color: #3b8134'>{$jobsData['completed']} Completed</div>";
		}
		if($jobsData['error']){
			$text.="<div style='color: #cf1124'>{$jobsData['error']} Error</div>";
		}
		if($jobsData['inProcess']){
			$text.="<div style='color: orange'>{$jobsData['inProcess']} In-Process</div>";
		}

		return $text;
	}

	public function getTranslationStatus( $translateId ) {
		$jobsData = $this->_getJobsData($translateId);
		$translateRecord = TranslateRecord::findOne( $translateId );
		if($jobsData['inProcess']){
			return "<div style='font-weight: bold;color: orange'>". Craft::t('convergine-contentbuddy','In Process')."</div>";
		}elseif ($jobsData['error'] || $translateRecord->fieldsError){
			return "<div style='font-weight: bold;color: red'>". Craft::t('convergine-contentbuddy','With Errors')."</div>";
		}elseif($jobsData['total'] == $jobsData['completed']){
			return "<div style='font-weight: bold;color: #3b8134'>". Craft::t('convergine-contentbuddy','Completed')."</div>";
		}

	}

	public function hasActiveJobs($translateId){
		$jobsData = $this->_getJobsData($translateId);
		return $jobsData['inProcess'];
	}

	public function getTranslatedFields(Element $entry) {
		$fieldLayout = $entry->getFieldLayout();

		return $this->_getLayoutFields($fieldLayout);
	}

	public function getEntryTranslateControl(Element $entry):string {
		$currentSite = $entry->siteId;
		$sites = [];
		foreach (Craft::$app->sites->getAllSites() as $site){
			if($site->id !=$currentSite){
				$sites[$site->id]=$site->name." : ".$site->language;
			}

		}
		return Craft::$app->view->renderTemplate('convergine-contentbuddy/translate/_control.twig',[
			'sites'=>$sites
		]);
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
			'total'=>count($jobs),
			'completed'=>$completed,
			'error'=>$error,
			'inProcess'=>$inProcess
		];
	}
}