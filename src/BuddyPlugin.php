<?php
namespace convergine\contentbuddy;

use convergine\contentbuddy\models\SettingsModel;
use convergine\contentbuddy\services\Base;
use convergine\contentbuddy\services\ContentGenerator;
use convergine\contentbuddy\services\Prompt;
use convergine\contentbuddy\services\PromptProcessor;
use convergine\contentbuddy\services\Request;
use convergine\contentbuddy\services\Translate;
use convergine\contentbuddy\variables\BuddyVariable;
use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\base\Plugin;
use craft\controllers\ElementsController;
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
 * @property Base $base;
 */
class BuddyPlugin extends Plugin
{
	public static $plugin;
	public ?string $name = 'Content Buddy';

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

				$event->rules['convergine-contentbuddy/content-generator'] = 'convergine-contentbuddy/content-generator/index';

				$event->rules['convergine-contentbuddy/prompts'] = 'convergine-contentbuddy/prompts/index';
				$event->rules['convergine-contentbuddy/prompts/add'] = 'convergine-contentbuddy/prompts/create';
				$event->rules['convergine-contentbuddy/prompts/edit/<id:\d+>'] = 'convergine-contentbuddy/prompts/edit';
				$event->rules['convergine-contentbuddy/prompts/delete/<id:\d+>'] = 'convergine-contentbuddy/prompts/remove';

				$event->rules['convergine-contentbuddy/content/generate'] = 'convergine-contentbuddy/content-generator/generate';

				$event->rules['convergine-contentbuddy/site-translate'] = 'convergine-contentbuddy/translate/index';
				$event->rules['convergine-contentbuddy/site-translate/log'] = 'convergine-contentbuddy/translate/log';
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

				if (
					array_key_exists($event->sender->handle, $settings->enabledFields)
					&& $settings->enabledFields[$event->sender->handle]
					&& (($settings->textAi == 'openai' && $settings->apiToken) || ($settings->textAi == 'xai' && $settings->xAiApiKey))
				){
					$event->html .= Craft::$app->view->renderTemplate('convergine-contentbuddy/_select.twig',
						[ 'event' => $event, 'hash' => StringHelper::UUID()] );
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
	}

	protected function createSettingsModel(): SettingsModel {
		/* plugin settings model */
		return new SettingsModel();
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
}
