<?php
namespace convergine\contentbuddy;

use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\services\Base;
use convergine\contentbuddy\services\ContentGenerator;
use convergine\contentbuddy\services\GenerateEntry;
use convergine\contentbuddy\services\Prompt;
use convergine\contentbuddy\services\PromptProcessor;
use convergine\contentbuddy\services\Request;
use convergine\contentbuddy\services\Translate;
use convergine\contentbuddy\variables\BuddyVariable;
use craft\events\ModelEvent;
use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\base\Plugin;
use craft\controllers\ElementsController;
use craft\elements\Entry;
use craft\events\DefineElementEditorHtmlEvent;
use craft\events\DefineFieldHtmlEvent;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * @property Prompt $promptService;
 * @property ContentGenerator $contentGenerator;
 * @property Request $request;
 * @property PromptProcessor $promptProcessor;
 * @property Translate $translate;
 * @property GenerateEntry $generateEntry;
 * @property Base $base;
 */
class BuddyPlugin extends Plugin {
	public static string $plugin;
	public ?string $name = 'Content Buddy';
    public string $schemaVersion = '1.2.8';

    public function init() {
        /* plugin initialization */
		$this->hasCpSection = true;
		$this->hasCpSettings = true;
		parent::init();

		$this->_setComponents();
		$this->_setRoutes();
		$this->_setEvents();
	}

	protected function _setComponents() {
		$this->setComponents([
			'promptService' => Prompt::class,
			'contentGenerator' => ContentGenerator::class,
			'request' => Request::class,
			'promptProcessor' => PromptProcessor::class,
			'translate' => Translate::class,
            'generateEntry' => GenerateEntry::class,
			'base' => Base::class,
		]);
	}

	protected function _setRoutes() {
		// Register CP routes
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
                $event->rules['convergine-contentbuddy'] = 'convergine-contentbuddy/dashboard/index';

				$event->rules['convergine-contentbuddy/settings/text-generation'] = 'convergine-contentbuddy/settings/text-generation';
				$event->rules['convergine-contentbuddy/settings/image-generation'] = 'convergine-contentbuddy/settings/image-generation';
				$event->rules['convergine-contentbuddy/settings/fields'] = 'convergine-contentbuddy/settings/fields';
				$event->rules['convergine-contentbuddy/settings/general'] = 'convergine-contentbuddy/settings/general';
				$event->rules['convergine-contentbuddy/settings/translation'] = 'convergine-contentbuddy/settings/translation';
                $event->rules['convergine-contentbuddy/settings/newsletter'] = 'convergine-contentbuddy/settings/newsletter';

				$event->rules['convergine-contentbuddy/content-generator'] = 'convergine-contentbuddy/content-generator/index';

				$event->rules['convergine-contentbuddy/prompts'] = 'convergine-contentbuddy/prompts/index';
				$event->rules['convergine-contentbuddy/prompts/add'] = 'convergine-contentbuddy/prompts/create';
				$event->rules['convergine-contentbuddy/prompts/edit/<id:\d+>'] = 'convergine-contentbuddy/prompts/edit';
				$event->rules['convergine-contentbuddy/prompts/delete/<id:\d+>'] = 'convergine-contentbuddy/prompts/remove';

				$event->rules['convergine-contentbuddy/content/generate'] = 'convergine-contentbuddy/content-generator/generate';

				$event->rules['convergine-contentbuddy/site-translate'] = 'convergine-contentbuddy/translate/index';
				$event->rules['convergine-contentbuddy/site-translate/log'] = 'convergine-contentbuddy/translate/log';

                $event->rules['convergine-contentbuddy/deepl/glossaries'] = 'convergine-contentbuddy/deep-l/glossaries';
			}
		);
	}

	protected function _setEvents() {
		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				$variable = $event->sender;
				$variable->set('contentbuddy', BuddyVariable::class);
			}
		);

		/**
		 * Attach button to selected fields.
		 */
		Event::on(
			Field::class,
			Field::EVENT_DEFINE_INPUT_HTML,
			static function (DefineFieldHtmlEvent $event) {
				/** @var SettingsModel $settings */
				$settings = BuddyPlugin::getInstance()->getSettings();

                if(get_class($event->sender) == 'craft\fields\Entries') {
                    $id = $event->element->id;
                    $type = match(get_class($event->element)) {
                        'craft\elements\Category' => 'category',
                        'craft\elements\Asset' => 'asset',
                        'craft\elements\Entry' => 'entry',
                        default => ''
                    };
                    if(!empty($type)) {
                        $replacement = '</button><button type="button" class="btn add cb-btn-generate-entries icon dashed wrap" aria-label="Generate" data-id="'.$id.'" data-type="'.$type.'" data-handle="'.$event->sender->handle.'" data-name="'.$event->sender->name.'">'.self::getIcon('#3F4D5A').' <span class="cb-text">Generate</span></button>';

                        $pos = strrpos($event->html, '</button>');
                        if($pos !== false) {
                            $event->html = substr_replace($event->html, $replacement, $pos, strlen('</button>'));
                        }
                    }
                }

				if (
					array_key_exists($event->sender->uid, $settings->enabledFields)
					&& $settings->enabledFields[$event->sender->uid]
					&& (($settings->textAi == 'openai' && $settings->apiToken) || ($settings->textAi == 'xai' && $settings->xAiApiKey))
				){
                    $select = Craft::$app->view->renderTemplate('convergine-contentbuddy/_select.twig', [ 'event' => $event, 'hash' => StringHelper::UUID()] );
                    $ckeditor_padding = Craft::$app->view->renderTemplate('convergine-contentbuddy/_ckeditor_padding.twig');
					$event->html .= $select . "<script>".$ckeditor_padding."</script>";
				}
			}
		);

		/**
		 * Warn user in case there are no selected fields.
		 */
		Event::on(
			BuddyPlugin::class,
			BuddyPlugin::EVENT_AFTER_SAVE_SETTINGS,
			function (Event $event) {

				/** @var SettingsModel $settings */
				$settings = BuddyPlugin::getInstance()->getSettings();
				if (Craft::$app->getRequest()->getIsCpRequest()) {
					if ( ! in_array( true, $settings->enabledFields, false ) ) {
						Craft::$app->getSession()->setError( Craft::t( 'convergine-contentbuddy', 'Content Buddy fields are not selected in settings. Please select fields in plugin settings under \'Fields Settings\' tab.' ) );
					}

                    if (($settings->textAi === 'openai' && $settings->apiToken === '') || ($settings->textAi === 'xai' && $settings->xAiApiKey === '')){
                        Craft::$app->getSession()->setError(Craft::t('convergine-contentbuddy', 'API Access Token required.'));
					}
				}
			}
		);

		if (Craft::$app->getRequest()->getIsCpRequest()) {
			// Load JS before page template is rendered
			Event::on(
				View::class,
				View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
				function (TemplateEvent $event) {
					/** @var SettingsModel $settings */
					$settings = BuddyPlugin::getInstance()->getSettings();

					// Get view
					$view = Craft::$app->getView();

					// Load additional JS
					$js = Craft::$app->view->renderTemplate('convergine-contentbuddy/_scripts.twig');
					if ($js) {
						$view->registerJs($js, View::POS_END);
					}

					if($settings->titleFieldEnabled()){
						// Load JS for title field
						$js = Craft::$app->view->renderTemplate('convergine-contentbuddy/_title_field_script.twig',[
							'hash' => StringHelper::UUID()
						]);
						if ($js) {
							$view->registerJs($js, View::POS_END);
						}
					}

				}
			);

			Event::on(
				ElementsController::class,
				ElementsController::EVENT_DEFINE_EDITOR_CONTENT,
				function (DefineElementEditorHtmlEvent $event) {
					$js = Craft::$app->view->renderTemplate('convergine-contentbuddy/_title_field_scripts_external.twig',[
						'hash' => 'title-hash-'.time()
					]);
					$event->html = $event->html."<script>".$js."</script>";
				}
			);
		}

		Event::on(
			Element::class,
			Element::EVENT_DEFINE_ADDITIONAL_BUTTONS,
			function (DefineHtmlEvent $event) {
				if (($event->sender->enabled && $event->sender->getEnabledForSite())) {
					$event->html.=$this->translate->getEntryTranslateControl($event->sender);
				}
			}
		);

		if(version_compare( Craft::$app->getInfo()->version, '5.0', '<' )) {
			Craft::$app->view->hook( 'cp.commerce.product.edit.content', function ( array &$context ) {

				return $this->translate->getEntryTranslateControl( $context['product'] );

			} );
		}

		Event::on(
			Entry::class,
			Entry::EVENT_DEFINE_SIDEBAR_HTML,
			static function(DefineHtmlEvent $event) {
				/** @var craft\models\Site $isDefaultSite */
				$isDefaultSite = $event->sender->getSite()->primary;

				$html = Craft::$app->view->renderTemplate('convergine-contentbuddy/_sidebars/entry-preview.twig',[
					'element' => $event->sender,
					'isDefaultSite' => $isDefaultSite,
					'excludedSites' => BuddyPlugin::getInstance()->translate->getExcludedEntries($event->sender->id)
				]);

				$event->html .= $html;
			}
		);

		Event::on(
			Entry::class,
			Entry::EVENT_AFTER_PROPAGATE,
			function (ModelEvent $event) {
					if($event->sender->id) {
						$this->translate->saveExcludeBulkSites( $event->sender );
					}
			}
		);

	}

	protected function createSettingsModel(): SettingsModel {
		/* plugin settings model */
		return new SettingsModel();
	}

	public function setSettings(array $settings):void {

	}

	/**
	 * @return string|null
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\Exception
	 */
	protected function settingsHtml(): ?string {
		return \Craft::$app->getView()->renderTemplate(
			'convergine-contentbuddy/settings',
			[ 'settings' => $this->getSettings() ]
		);
	}

	/**
	 * @return string
	 */
	public function getPluginName(): string {
		return $this->name;
	}


	/**
	 * @return array|null
	 */
	public function getCpNavItem(): ?array {
		$nav = parent::getCpNavItem();

		$nav['label'] = \Craft::t('convergine-contentbuddy', $this->getPluginName());
		$nav['url'] = 'convergine-contentbuddy';

		if (Craft::$app->getUser()->getIsAdmin()) {
            $nav['subnav']['dashboard'] = [
                'label' => Craft::t('convergine-contentbuddy', 'Dashboard'),
                'url' => 'convergine-contentbuddy',
            ];
			$nav['subnav']['content-generator'] = [
				'label' => Craft::t('convergine-contentbuddy', 'Content Generator'),
				'url' => 'convergine-contentbuddy/content-generator',
			];
			$nav['subnav']['site-translation'] = [
				'label' => Craft::t('convergine-contentbuddy', 'Site Translation'),
				'url' => 'convergine-contentbuddy/site-translate',
			];
			$nav['subnav']['prompts'] = [
				'label' => Craft::t('convergine-contentbuddy', 'Prompts Templates'),
				'url' => 'convergine-contentbuddy/prompts',
			];
			$nav['subnav']['settings'] = [
				'label' => Craft::t('convergine-contentbuddy', 'Settings'),
				'url' => 'convergine-contentbuddy/settings/text-generation',
			];
		}

		return $nav;
	}

	/**
	 * @return mixed
	 */
	public function getSettingsResponse(): mixed {
		return Craft::$app->controller->redirect(UrlHelper::cpUrl('convergine-contentbuddy/settings/text-generation'));
	}

    private static function getIcon($fill = '#666666') : string {
        return '<svg xmlns="http://www.w3.org/2000/svg" style="
margin-right: 5px;" width="18" height="18" viewBox="0 0 18 18" fill="none">
    <path d="M11.2106 2.76256C11.5573 2.64509 11.9211 2.58446 12.2906 2.58256C13.5032 2.58067 14.6249 3.22487 15.2312 4.27455C15.6215 4.95666 15.7674 5.75434 15.6329 6.52928C15.6083 6.51034 15.5609 6.4857 15.5287 6.46865L14.964 6.14276L12.6089 4.78424H12.6051L11.9344 4.39582C11.7525 4.29161 11.527 4.29161 11.3432 4.39582L7.12934 6.82107V5.04192L10.6081 3.0354C10.8013 2.92361 11.0022 2.83266 11.2106 2.76256ZM4.58092 4.5474C4.58092 4.36361 4.59608 4.1855 4.6245 4.00929C4.88029 2.40256 6.27481 1.17288 7.96112 1.17099L7.95544 1.17667C8.74933 1.17667 9.51291 1.44951 10.1249 1.9573C10.1003 1.96867 10.051 1.99898 10.015 2.01793L9.36891 2.3893L6.42071 4.08508C6.23692 4.1874 6.12702 4.38255 6.12702 4.59666V9.44717L4.58092 8.55854V4.5474ZM1.63841 5.90592C2.03441 5.22192 2.65777 4.69897 3.40051 4.42424V8.68548C3.40051 8.89769 3.5104 9.08717 3.69419 9.19706L7.8986 11.6166L6.34681 12.5128L2.87377 10.5139C2.72788 10.4305 2.59146 10.3377 2.46451 10.2354C1.17041 9.21412 0.789568 7.37433 1.63841 5.90592ZM13.8234 13.3674H13.236V13.4526C13.236 13.6951 13.2095 13.932 13.1622 14.1594C13.2758 14.0665 13.3857 13.9718 13.4918 13.8714C13.5544 13.8145 13.615 13.7577 13.6737 13.697C13.6775 13.6933 13.6813 13.6876 13.687 13.6838C13.776 13.5947 13.8613 13.5038 13.9428 13.409C13.9542 13.3977 13.9636 13.3863 13.9731 13.3749C13.9238 13.3693 13.8746 13.3674 13.8234 13.3674ZM16.6371 7.37054V7.36486C17.0464 6.13897 16.9061 4.7975 16.2525 3.6815C15.2672 1.97625 13.2872 1.09709 11.3546 1.50635C10.4906 0.541936 9.25522 -0.00564037 7.96112 4.38179e-05C5.98681 4.38179e-05 4.23229 1.26762 3.6203 3.13961C2.35083 3.40108 1.25567 4.19308 0.613359 5.31666C-0.377585 7.02191 -0.152112 9.16864 1.17609 10.6314C0.764937 11.8554 0.905147 13.1968 1.56072 14.3071C2.54409 16.0181 4.52598 16.8972 6.46239 16.4899C6.48513 16.5145 6.50786 16.541 6.53439 16.5657C6.86218 16.9181 7.24112 17.2137 7.65607 17.4391C7.8247 17.5339 7.99902 17.6153 8.17902 17.6854C8.70575 17.8939 9.27227 18.0019 9.85017 18C10.4318 18 10.9946 17.8901 11.5156 17.6854C12.7605 17.2004 13.759 16.181 14.191 14.8604C14.676 14.76 15.1384 14.5819 15.5571 14.3356C15.6518 14.2825 15.7447 14.2238 15.8337 14.1613C16.3889 13.7842 16.855 13.2802 17.1922 12.6853C18.1889 10.98 17.9634 8.83327 16.6371 7.37054ZM16.1691 12.1074L16.1748 12.1131C15.9114 12.564 15.5457 12.9467 15.1156 13.229C14.9489 13.3408 14.7689 13.4356 14.5813 13.5152C14.5245 13.5398 14.4676 13.5625 14.4089 13.5834V13.4545C14.2706 13.4109 14.1228 13.3844 13.9731 13.3749C13.9238 13.3693 13.8746 13.3674 13.8234 13.3674H13.2285V13.464C13.2285 13.7065 13.2019 13.9434 13.1527 14.1707C12.823 15.6941 11.4607 16.8347 9.8388 16.8309V16.8252C9.05059 16.8252 8.28133 16.5448 7.67691 16.037C7.70154 16.0257 7.75839 15.9954 7.7887 15.9783L8.42154 15.6164L11.3887 13.9225C11.5725 13.8183 11.6881 13.625 11.6824 13.4128V13.3674H9.96764L7.8247 14.5914L7.18997 14.9532C7.00807 15.0556 6.8186 15.1408 6.62723 15.2071C6.59313 15.2185 6.55713 15.2318 6.52302 15.2413C5.03755 15.7074 3.37966 15.1067 2.57251 13.7065H2.57819C2.18409 13.0301 2.04388 12.2248 2.1822 11.4518C2.20493 11.4688 2.25419 11.4935 2.28451 11.5124L3.04809 11.9539L5.87313 13.5966C6.05692 13.6989 6.28239 13.6989 6.46618 13.5966L9.10743 12.0827C8.55796 11.5958 8.28701 11.0463 8.15628 10.603L7.13312 10.008L7.13881 7.96738L8.51628 7.17728L8.67354 7.08633H8.67733L8.91796 6.94612L9.92406 6.37202L10.4622 6.06318L11.15 5.67097L11.474 5.48529L11.9306 5.75055L13.5354 6.67897L14.947 7.49749C15.1592 7.62065 15.3563 7.76465 15.5325 7.9257C16.6826 8.97159 16.9857 10.7091 16.1691 12.1074Z" fill="'.$fill.'"></path>
    <path d="M14.0205 9.85837C14.0188 9.9215 14.0172 9.98298 14.0122 10.0461C13.9856 10.39 13.8876 10.7124 13.7348 11.0015C13.6252 11.2075 13.489 11.3952 13.3295 11.5614C13.2648 11.6278 13.1967 11.691 13.1252 11.7508C12.7997 12.02 12.4044 12.206 11.9709 12.2791C11.9543 12.2841 11.9377 12.2858 11.9211 12.2891C11.8015 12.3057 11.6753 12.3157 11.549 12.3157C11.4095 12.3157 11.2733 12.3024 11.1388 12.2808C11.1205 12.2775 11.1023 12.2742 11.0856 12.2692C10.9395 12.2426 10.7983 12.2011 10.6638 12.1462C10.594 12.1213 10.5276 12.0914 10.4611 12.0582C10.2253 11.9402 10.0127 11.7874 9.82833 11.6046C9.81006 11.5863 9.79179 11.568 9.77352 11.5481C9.34999 11.1061 9.09089 10.5063 9.09421 9.84341C9.09421 9.37155 9.2304 8.93126 9.46127 8.55743C9.52439 8.45442 9.5958 8.35639 9.67387 8.26501C9.92467 7.96428 10.2469 7.72669 10.6139 7.57715C10.8897 7.46085 11.1903 7.39605 11.5059 7.3894C11.5258 7.3894 11.5441 7.3894 11.564 7.3894C11.7517 7.3894 11.936 7.411 12.1121 7.45254C12.5987 7.56386 13.0306 7.81973 13.3578 8.17362C13.7497 8.59398 13.9989 9.15224 14.0188 9.76865C14.0205 9.79855 14.0205 9.83012 14.0205 9.86003V9.85837Z" fill="'.$fill.'"></path>
    <path d="M15.5362 13.9535C15.1145 14.2255 14.6488 14.4221 14.1603 14.533C13.7252 15.9914 12.7195 17.1171 11.4657 17.6527C10.9409 17.8787 10.3741 18 9.78825 18C9.2062 18 8.6356 17.8829 8.10508 17.6527C7.92378 17.5753 7.74821 17.4853 7.57837 17.3807V15.1335C7.57837 14.8155 7.63944 14.51 7.74821 14.2359C8.06309 13.4408 8.78636 12.8843 9.62986 12.8843H13.7901C13.8416 12.8843 13.8912 12.8843 13.9408 12.8926C14.0916 12.9031 14.2405 12.9324 14.3798 12.9805C14.4027 12.9847 14.4237 12.9931 14.4466 13.0014C14.4485 13.0014 14.4504 13.0014 14.4523 13.0014C14.4866 13.014 14.521 13.0286 14.5534 13.0454C14.7404 13.127 14.9122 13.2379 15.0668 13.3739C15.2519 13.5329 15.4122 13.7296 15.5362 13.9514V13.9535Z" fill="'.$fill.'"></path>
    </svg>';
    }
}
