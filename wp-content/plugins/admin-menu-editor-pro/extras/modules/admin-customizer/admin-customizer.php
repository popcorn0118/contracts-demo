<?php

namespace YahnisElsts\AdminMenuEditor\AdminCustomizer;

use YahnisElsts\AdminMenuEditor\Customizable\Controls;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Options\None;
use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Controls\HorizontalSeparator;
use YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

require_once AME_ROOT_DIR . '/includes/ame-option.php';

class AmeAdminCustomizer extends \ameModule {
	const CHANGESET_POST_TYPE = 'ame_ac_changeset';
	const SAVE_CHANGESET_ACTION = 'ws_ame_ac_save_changeset';
	const TRASH_CHANGESET_ACTION = 'ws_ame_ac_trash_changeset';
	const REFRESH_PREVIEW_ACTION = 'ws_ame_ac_refresh_preview_frame';
	const CREATE_THEME_ACTION = 'ws_ame_ac_create_admin_theme';
	const DOWNLOAD_COOKIE_PREFIX = 'ameAcFileDownload_';

	const LAST_DOWNLOAD_META_KEY = 'ame_ac_last_download';

	//Note: Changeset names are used as slugs, and WP converts slugs to lowercase.
	const CHANGESET_NAME_CHARACTERS = 'abcdefghijklmnopqrtvwyz0123456789';
	const CHANGESET_NAME_BASE_LENGTH = 12;
	const CHANGESET_NAME_CHECKSUM_LENGTH = 2;

	/**
	 * Special changeset name that is used when previewing a changeset that hasn't been saved
	 * yet, or for an empty changeset created when opening the customizer.
	 *
	 * Note: Must only contain characters allowed in changeset names.
	 *
	 * @var string
	 */
	const TEMPORARY_CHANGESET_NAME = 'temporary000';

	const PROMPT_MODE_UNSAVED_CHANGES = 1;
	const PROMPT_MODE_UNPUBLISHED_CHANGES = 2;

	/**
	 * @var AbstractSetting[]
	 */
	protected $registeredSettings = [];
	/**
	 * @var null|\YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure
	 */
	protected $structure = null;
	protected $isRegistrationDone = false;

	/**
	 * @var array<string,boolean> Settings that support preview via postMessage.
	 */
	protected $supportsPostMessage = [];

	protected $changesetName = null;
	/**
	 * @var AcChangeset|null
	 */
	protected $currentChangeset = null;

	/**
	 * Setting preview values submitted to the current page in a POST request.
	 *
	 * This is used to preview setting changes that have not yet been saved
	 * to the changeset. The submitted values can override matching values
	 * in the current changeset.
	 *
	 * @see REFRESH_PREVIEW_ACTION
	 * @var array<string,mixed>
	 */
	protected $submittedPreviewValues = [];

	protected $isPreviewFrameInitialized = false;

	/**
	 * @var \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator[]
	 */
	protected $registeredStyleGenerators = [];

	private $menuRegistrationDone = false;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		if ( $this->isInPreviewFrame() ) {
			$this->initializePreviewFrame();
		}

		//Show the AC menu item below the main "Menu Editor Pro" item by default. Note that using
		//the custom hook means that only users who can access the menu editor will see this item.
		add_action('admin_menu_editor-editor_menu_registered', [$this, 'addAdminMenu']);
		//Alternatively, when using filters to change AC access requirements,
		//we may need to register the menu item independently of the menu editor.
		add_action(
			'admin_menu',
			[$this, 'maybeAddStandaloneAdminMenu'],
			$this->menuEditor->get_magic_hook_priority() - 1
		);

		add_action('init', [$this, 'registerChangesetPostType']);
		add_action('transition_post_status', [$this, 'applyChangesOnPublish'], 10, 3);

		add_action('wp_ajax_' . self::SAVE_CHANGESET_ACTION, [$this, 'ajaxSaveChangeset']);
		add_action('wp_ajax_' . self::TRASH_CHANGESET_ACTION, [$this, 'ajaxTrashChangeset']);
		add_action('wp_ajax_' . self::CREATE_THEME_ACTION, [$this, 'ajaxCreateAdminTheme']);
	}

	public function addAdminMenu() {
		//This method can be called from two different hooks, but it should only
		//run once.
		if ( $this->menuRegistrationDone ) {
			return;
		}
		$this->menuRegistrationDone = true;

		if ( !$this->userCanAccessModule() ) {
			return;
		}

		//Do not show this menu item inside the preview frame.
		if ( $this->isInPreviewFrame() ) {
			return;
		}

		$requiredCapability = $this->getBasicAccessCapability();

		$hook = add_options_page(
			'Admin Customizer',
			'Admin Customizer',
			$requiredCapability ?: 'manage_options',
			'ame-admin-customizer',
			[$this, 'outputAdminPage']
		);

		add_action('load-' . $hook, [$this, 'initCustomizerPage']);
		add_action('load-' . $hook, [$this, 'disableAdminHeader']);

		\ameMenuItem::add_class_to_submenu_item(
			'options-general.php',
			'ame-admin-customizer',
			'ws-ame-secondary-am-item'
		);
	}

	public function maybeAddStandaloneAdminMenu() {
		if ( !$this->menuEditor->current_user_can_edit_menu() && $this->userCanAccessModule() ) {
			$this->addAdminMenu();
		}
	}

	public function outputAdminPage() {
		$this->outputMainTemplate();

		//Stop execution to prevent the admin footer from being displayed.
		exit;
	}

	protected function getTemplateVariables($templateName) {
		$variables = parent::getTemplateVariables($templateName);
		$variables['settings'] = $this->registeredSettings;
		$variables['structure'] = $this->structure;
		$variables['currentChangeset'] = $this->currentChangeset;
		$variables['returnUrl'] = $this->menuEditor->get_plugin_page_url();
		return $variables;
	}

	public function initCustomizerPage() {
		//Disallow frame embedding: either in the preview frame, or anywhere else.
		header('X-Frame-Options: DENY');

		//Disallow recursion. This is slightly different from the above restriction
		//because a preview URL can also be opened in its own tab, not just in a frame.
		if ( $this->isInPreviewFrame() || $this->isPreviewFrameInitialized ) {
			//We also add a custom "back" link to the error page. We'll need to
			//add the AC preview params directly because the preview JS currently
			//doesn't run on the error page.
			$backUrl = add_query_arg(
				[
					'ame-ac-preview'   => 1,
					'ame-ac-changeset' => $this->currentChangeset->getName() ?: self::TEMPORARY_CHANGESET_NAME,
				],
				admin_url('index.php')
			);

			wp_die(
				'You cannot access this page in the preview frame.'
				. '<br><br>' . sprintf(
					'<a href="%s">Back to the Dashboard</a>',
					esc_attr($backUrl)
				),
				'',
				['back_link' => false] //Suppress the default "back" link.
			);
		}

		add_filter('admin_menu_editor-is_admin_customizer', '__return_true');

		$this->registerCustomizableItems();
		$this->initializeCustomizerChangeset();
		$this->initializeSettingsPreview();

		$this->disableAdminHeader();
		$this->enqueueDependencies();
	}

	public function disableAdminHeader() {
		$_GET['noheader'] = 1;
	}

	protected function enqueueDependencies() {
		$this->menuEditor->register_base_dependencies();
		$this->registerBaseScripts();

		wp_enqueue_auto_versioned_style(
			'ame-admin-customizer',
			plugins_url('admin-customizer.css', __FILE__),
			['wp-admin', 'colors']
		);

		$this->structure->enqueueKoComponentDependencies();

		//Enqueue wp-hooks separately in case it's not available for some reason.
		wp_enqueue_script('wp-hooks');

		$settingInfo = AbstractSetting::serializeSettingsForJs(
			$this->registeredSettings,
			AbstractSetting::SERIALIZE_INCLUDE_VALUE
			| AbstractSetting::SERIALIZE_INCLUDE_POST_MESSAGE_SUPPORT
			| AbstractSetting::SERIALIZE_INCLUDE_VALIDATION
			| AbstractSetting::SERIALIZE_LEAVES_ONLY,
			function ($data, AbstractSetting $setting) {
				//Backwards compatibility with older parts of the code that didn't
				//have access to the "supportsPostMessage" property on the setting.
				if ( !empty($this->supportsPostMessage[$setting->getId()]) ) {
					$data['supportsPostMessage'] = true;
				}
				return $data;
			}
		);

		$scriptData = [
			'ajaxUrl'                => admin_url('admin-ajax.php'),
			'changesetName'          => $this->currentChangeset->getName() ?: '',
			'changesetItemCount'     => count($this->currentChangeset),
			'changesetStatus'        => $this->currentChangeset->getStatus(),
			'changesetThemeMetadata' => $this->currentChangeset->getAdminThemeMetadata(),

			'saveChangesetNonce'  => wp_create_nonce(self::SAVE_CHANGESET_ACTION),
			'trashChangesetNonce' => wp_create_nonce(self::TRASH_CHANGESET_ACTION),
			'refreshPreviewNonce' => wp_create_nonce(self::REFRESH_PREVIEW_ACTION),
			'createThemeNonce'    => wp_create_nonce(self::CREATE_THEME_ACTION),
			'settings'            => $settingInfo,
			'interfaceStructure'  => $this->structure->serializeForJs(),

			'changesetPathTemplate'     => null,
			'changesetPushStateEnabled' => false,
			'customBasePath'            => null,

			'initialPreviewUrl'  => add_query_arg(
				[
					'ame-ac-preview'   => 1,
					'ame-ac-changeset' => $this->currentChangeset->getName() ?: self::TEMPORARY_CHANGESET_NAME,
				],
				apply_filters(
					'admin_menu_editor-ac_initial_preview_url',
					self_admin_url('options-general.php')
				)
			),
			'allowedPreviewUrls' => $this->getAllowedPreviewBaseUrls(),
			'allowedCommOrigins' => $this->getAllowedCommunicationOrigins(),
			'isWpDebugEnabled'   => defined('WP_DEBUG') && WP_DEBUG,
			'exitPromptMode'     => self::PROMPT_MODE_UNPUBLISHED_CHANGES,
		];

		$scriptData = apply_filters('admin_menu_editor-ac_script_data', $scriptData);
		if ( empty($scriptData) || !is_array($scriptData) ) {
			throw new \UnexpectedValueException(
				'Invalid script data returned by the filter "admin_menu_editor-ac_script_data".'
			);
		}

		$useBundles = defined('WS_AME_USE_BUNDLES') && WS_AME_USE_BUNDLES
			&& file_exists(AME_ROOT_DIR . '/dist/admin-customizer.bundle.js');

		if ( $useBundles ) {
			$customizerDep = $this->menuEditor
				->get_webpack_registry()
				->getWebpackEntryPoint('admin-customizer');

			//The bundled style generator depends on jquery-color.
			$customizerDep->addDependencies('jquery-color');
		} else {
			$customizerDep = ScriptDependency::create(
				plugins_url('admin-customizer.js', __FILE__),
				'ame-admin-customizer-js'
			);
			$customizerDep->addDependencies('ame-admin-customizer-base', 'ame-customizable-settings');
			//It's an ES6 module, but only when not when compiled with Webpack.
			$customizerDep->setTypeToModule();
		}

		$customizerDep->addDependencies(
			'jquery',
			'jquery-ui-menu',
			'jquery-ui-dialog',
			'ame-jszip',
			'ame-lodash',
			'ame-knockout',
			'ame-ko-extensions',
			'ame-jquery-cookie',
			'ame-mini-functional-lib',
			'ame-admin-customizer-communicator'
		)
			->addJsVariable('wsAmeAdminCustomizerData', $scriptData)
			->enqueue();

		do_action('admin_menu_editor-enqueue_ac_dependencies');
	}

	protected function registerCustomizableItems() {
		if ( $this->isRegistrationDone ) {
			return;
		}
		$this->isRegistrationDone = true;

		$this->structure = new Controls\InterfaceStructure(
			apply_filters('admin_menu_editor-ac_root_container_title', 'Admin Customizer')
		);
		do_action('admin_menu_editor-register_ac_items', $this);

		//Allow other modules to modify the structure more directly.
		//This filter should be used only when necessary. For simply adding new items,
		//use the "admin_menu_editor-register_ac_items" action instead.
		$this->structure = apply_filters('admin_menu_editor-ac_structure', $this->structure);

		//Register all settings used by controls. This way modules generally don't
		//have to do it explicitly.
		foreach ($this->structure->getAllDescendants() as $element) {
			if ( $element instanceof Controls\Control ) {
				$this->addSettings($element->getSettings());
			}
		}

		//Add a separator that will be used to separate frequently used sections
		//from other sections.
		$this->structure->add(new HorizontalSeparator(['id' => 'ame-ac-frequent-sections-separator']));

		//Sort top-level sections. The most frequently used (by assumption) sections
		//should be at the top, and the rest are sorted alphabetically.
		$this->structure->sortChildren(function (UiElement $a, UiElement $b) {
			$popularSectionOrder = [
				'ame-branding-color-scheme'          => 1,
				'ame-admin-menu'                     => 2,
				'ame-ds-toolbar'                     => 3,
				'ame-ac-frequent-sections-separator' => 4,
			];
			$aId = $a->getId();
			$bId = $b->getId();

			$aIsPopular = isset($popularSectionOrder[$aId]);
			$bIsPopular = isset($popularSectionOrder[$bId]);

			if ( $aIsPopular && $bIsPopular ) {
				return $popularSectionOrder[$aId] - $popularSectionOrder[$bId];
			} elseif ( $aIsPopular ) {
				return -1;
			} elseif ( $bIsPopular ) {
				return 1;
			} else {
				$aTitle = ($a instanceof Controls\Container) ? $a->getTitle() : $aId;
				$bTitle = ($b instanceof Controls\Container) ? $b->getTitle() : $bId;
				return strnatcasecmp($aTitle, $bTitle);
			}
		});
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting[] $settings
	 * @return void
	 */
	public function addSettings($settings) {
		//Note that for structs, we register both the struct itself and its children.
		//While the struct itself usually won't be updated or previewed, it's still
		//useful to have it for finding settings by tag. We can find the struct by
		//a tag, then find all its children even if they don't have the tag themselves.

		foreach (AbstractSetting::recursivelyIterateSettings($settings) as $instance) {
			$this->registeredSettings[$instance->getId()] = $instance;
		}
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Controls\Container $section
	 * @return void
	 */
	public function addSection($section) {
		if ( $section === null ) {
			return;
		}
		$this->structure->add($section);
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $styleGenerator
	 * @return void
	 */
	public function addPreviewStyleGenerator(StyleGenerator $styleGenerator) {
		$this->registeredStyleGenerators[] = $styleGenerator;
	}

	/**
	 * Enable postMessage support for a setting.
	 *
	 * @param string|AbstractSetting|null $settingOrId
	 * @return void
	 */
	public function enablePostMessage($settingOrId) {
		if ( $settingOrId instanceof AbstractSetting ) {
			$setting = $settingOrId;
		} else if ( is_string($settingOrId) ) {
			$setting = $this->getSettingById($settingOrId);
		} else {
			return;
		}
		if ( $setting === null ) {
			return;
		}

		$this->supportsPostMessage[$setting->getId()] = true;
	}

	/**
	 * Find an existing section by ID.
	 *
	 * @param $sectionId
	 * @return Option<\YahnisElsts\AdminMenuEditor\Customizable\Controls\Section>
	 */
	public function findSection($sectionId) {
		if ( !$this->structure ) {
			//Structure only gets initialized when registering items.
			return Option::none();
		}

		$child = $this->structure->findChildById($sectionId);
		if ( $child instanceof Controls\Section ) {
			return Option::some($child);
		}
		return Option::none();
	}

	/**
	 * @param string $settingId
	 * @return \YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting|null
	 */
	private function getSettingById($settingId) {
		if ( isset($this->registeredSettings[$settingId]) ) {
			return $this->registeredSettings[$settingId];
		}
		/** @noinspection PhpRedundantOptionalArgumentInspection */
		return \ameMultiDictionary::get($this->registeredSettings, $settingId, null);
	}

	private function isInPreviewFrame() {
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No action, just checking if param is set.
		return !empty($_GET['ame-ac-preview'])
			&& function_exists('is_user_logged_in')
			&& is_user_logged_in();
	}

	private function initializePreviewFrame() {
		add_filter('admin_menu_editor-is_preview_frame', '__return_true');

		$this->requirePreviewChangeset();
		$this->parseSubmittedPreviewValues();

		add_action('init', [$this, 'requirePreviewAccessPermissions'], 1);

		add_action('wp_loaded', [$this, 'registerAndPreviewSettings']);

		add_action('admin_enqueue_scripts', [$this, 'enqueuePreviewDependencies']);
		add_action('wp_enqueue_scripts', [$this, 'enqueuePreviewDependencies']);

		add_filter('admin_menu_editor-ac_add_preview_params', [$this, 'addPreviewParamsToUrl']);

		$this->isPreviewFrameInitialized = true;
	}

	/**
	 * @return void
	 * @internal
	 */
	public function enqueuePreviewDependencies() {
		foreach ($this->menuEditor->get_safe_js_libraries() as $library) {
			$library->register();
		}
		$this->registerBaseScripts();

		do_action('admin_menu_editor-register_ac_preview_deps', $this);

		wp_enqueue_auto_versioned_style(
			'ame-admin-customizer-preview-styles',
			plugins_url('preview.css', __FILE__)
		);

		$useBundles = defined('WS_AME_USE_BUNDLES') && WS_AME_USE_BUNDLES;
		if ( $useBundles ) {
			$script = $this->menuEditor
				->get_webpack_registry()
				->getWebpackScriptChunk('admin-customizer-preview')
				->addDependencies('jquery-color'); //Required by the bundled style generator.
		} else {
			$script = ScriptDependency::create(
				plugins_url('preview-handler.js', __FILE__),
				'ame-admin-customizer-preview'
			)
				->setTypeToModule() //Uses other modules.
				->setAsync() //Load sooner.
				->addDependencies(
					'ame-admin-customizer-base',
					'ame-customizable-settings',
					'ame-style-generator'
				);
		}

		$script
			->addDependencies(
				'jquery',
				'ame-admin-customizer-communicator'
			)
			->addJsVariable('wsAmeAcPreviewData', [
				'changesetName'       => $this->currentChangeset->getName() ?: self::TEMPORARY_CHANGESET_NAME,
				'allowedPreviewUrls'  => $this->getAllowedPreviewBaseUrls(),
				'allowedCommOrigins'  => $this->getAllowedCommunicationOrigins(),
				'isWpDebugEnabled'    => defined('WP_DEBUG') && WP_DEBUG,
				'settings'            => AbstractSetting::serializeSettingsForJs(
					$this->registeredSettings,
					AbstractSetting::SERIALIZE_INCLUDE_VALUE | AbstractSetting::SERIALIZE_LEAVES_ONLY
				),
				'stylePreviewConfigs' => array_map(
					function (StyleGenerator $generator) {
						return $generator->getJsPreviewConfiguration();
					},
					$this->registeredStyleGenerators
				),
			])
			->enqueue();

		do_action('admin_menu_editor-enqueue_ac_preview');
	}

	private function registerBaseScripts() {
		if ( function_exists('ws_ame_register_customizable_js_lib') ) {
			ws_ame_register_customizable_js_lib($this->menuEditor);
		}

		ScriptDependency::create(
			plugins_url('admin-customizer-base.js', __FILE__),
			'ame-admin-customizer-base'
		)
			->addDependencies('jquery', 'ame-customizable-settings')
			->setTypeToModule()
			->register();

		ScriptDependency::create(
			plugins_url('communicator.js', __FILE__),
			'ame-admin-customizer-communicator'
		)
			->addDependencies('jquery')
			->register();
	}

	private function getAllowedPreviewBaseUrls() {
		$results = self::generateAllowedUrls();
		if ( is_ssl() ) {
			$results = array_merge($results, self::generateAllowedUrls('https'));
		}
		return array_unique($results);
	}

	private static function generateAllowedUrls($scheme = null) {
		$params = ['/'];
		if ( $scheme !== null ) {
			$params[] = $scheme;
		}
		$results = [
			home_url(...$params),
			admin_url(...$params),
		];
		if ( is_multisite() ) {
			$results[] = network_admin_url(...$params);
			$results[] = user_admin_url(...$params);
		}
		return $results;
	}

	private function getAllowedCommunicationOrigins() {
		$previewUrls = $this->getAllowedPreviewBaseUrls();
		$results = [];
		foreach ($previewUrls as $url) {
			$scheme = wp_parse_url($url, PHP_URL_SCHEME);
			$host = wp_parse_url($url, PHP_URL_HOST);
			//Skip invalid URLs.
			if ( ($scheme === null) || ($host === null) ) {
				continue;
			}
			$results[] = $scheme . '://' . $host;
		}
		return array_unique($results);
	}

	/**
	 * Register customizable settings and enable preview.
	 *
	 * This needs to run after other modules have been loaded so that modules can
	 * register their settings. However, it also needs to run early enough so that
	 * we can set up the preview before those settings are actually *used*.
	 *
	 * @access private It's only public because it's a hook callback.
	 */
	public function registerAndPreviewSettings() {
		$this->registerCustomizableItems();
		$this->initializeSettingsPreview();
	}

	/**
	 * Initialize the preview of registered settings using values from the current
	 * changeset and the current POST request, if any.
	 */
	private function initializeSettingsPreview() {
		$previewValues = [];
		$changeset = $this->getCurrentChangeset();
		if ( !empty($changeset) ) {
			foreach ($changeset as $settingId => $value) {
				$previewValues[$settingId] = $value;
			}
		}
		$previewValues = array_merge($previewValues, $this->submittedPreviewValues);

		foreach ($previewValues as $settingId => $value) {
			if ( isset($this->registeredSettings[$settingId]) ) {
				$this->registeredSettings[$settingId]->preview($value);
			}
		}
	}

	/**
	 * Load the current changeset while inside the preview frame.
	 *
	 * Will exit with an error if the changeset name is specified but invalid.
	 *
	 * @return void
	 */
	private function requirePreviewChangeset() {
		//Let other modules retrieve the changeset once it's ready.
		add_filter('admin_menu_editor-ac_preview_frame_changeset', function () {
			return $this->currentChangeset;
		});

		//The changeset name can be passed as a query parameter.
		if ( empty($this->changesetName) ) {
			$this->changesetName = $this->getChangesetNameFromRequest()->getOrElse(null);
		}
		if ( empty($this->changesetName) || ($this->changesetName === self::TEMPORARY_CHANGESET_NAME) ) {
			//This can happen if the customizer has a created a new changeset,
			//but hasn't saved it yet. Let's use a temporary empty changeset.
			$this->currentChangeset = new AcChangeset();
			return;
		}

		//Changeset name must be valid.
		if ( !$this->isSyntacticallyValidChangesetName($this->changesetName) ) {
			wp_die('Invalid changeset name.', 400);
		}

		//Changeset must exist.
		$changeset = $this->loadChangeset($this->changesetName);
		if ( $changeset->isEmpty() ) {
			wp_die('The specified changeset does not exist.', 404);
		}
		$this->currentChangeset = $changeset->get();
	}

	/**
	 * @return Option<string>
	 */
	private function getChangesetNameFromRequest() {
		//phpcs:disable WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( !empty($_POST['ame-ac-changeset']) ) {
			return Option::fromValue($this->sanitizeChangesetName(strval($_POST['ame-ac-changeset'])));
		} else if ( isset($_GET['ame-ac-changeset']) ) {
			return Option::fromValue($this->sanitizeChangesetName(strval($_GET['ame-ac-changeset'])));
		}
		//phpcs:enable
		return None::getInstance();
	}

	private function parseSubmittedPreviewValues() {
		$this->submittedPreviewValues = [];
		if (
			empty($_POST['action'])
			|| ($_POST['action'] !== self::REFRESH_PREVIEW_ACTION)
			|| empty($_POST['nonce'])
			|| empty($_POST['modified'])
		) {
			return;
		}

		//Note: This method is expected to run before WordPress adds magic quotes
		//to $_POST. We don't need wp_unslash() here.

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$verified = wp_verify_nonce(strval($_POST['nonce']), self::REFRESH_PREVIEW_ACTION);
		if ( $verified === false ) {
			return;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- This is JSON.
		$modified = json_decode(strval($_POST['modified']), true);
		if ( !is_array($modified) ) {
			return;
		}
		$this->submittedPreviewValues = $modified;
	}

	/**
	 * Check if the current user can access the preview and exit with an error if not.
	 *
	 * While I'm not aware of any specific security issues with previewing
	 * settings, let's be safe and allow only users who have access to this
	 * module to load the site in preview mode.
	 *
	 * Preview gets initialized before WordPress authenticates the user,
	 * so this is implemented as a separate check that runs later.
	 *
	 * @return void
	 * @internal
	 */
	public function requirePreviewAccessPermissions() {
		if ( !$this->userCanAccessModule() ) {
			wp_die('You do not have permission to preview settings.', 403);
		}
	}

	/**
	 * Add query parameters that will enable preview mode to the specified URL.
	 *
	 * Only works while inside a fully initialized preview frame. In other contexts,
	 * this method returns the original URL unchanged.
	 *
	 * This is used as a filter callback that lets other modules add preview parameters
	 * to their own URLs without having to somehow get a reference to the admin customizer.
	 *
	 * @param string $url
	 * @return string
	 */
	public function addPreviewParamsToUrl($url) {
		if ( $this->isPreviewFrameInitialized && $this->currentChangeset ) {
			return add_query_arg(
				[
					'ame-ac-preview'   => '1',
					'ame-ac-changeset' => $this->currentChangeset->getName() ?: self::TEMPORARY_CHANGESET_NAME,
				],
				$url
			);
		}
		return $url;
	}

	/**
	 * Load or create a changeset for the admin customizer.
	 *
	 * This is for the customizer UI, not for the preview frame.
	 *
	 * @return void
	 */
	private function initializeCustomizerChangeset() {
		if ( $this->isInPreviewFrame() ) {
			throw new \LogicException('This method should not be called in the preview frame.');
		}
		if ( !empty($this->currentChangeset) ) {
			return; //Already loaded.
		}

		//Load the changeset specified in a query parameter, load the most recent
		//draft changeset, or create a new changeset.
		$strategies = [
			'requested'     => [$this, 'loadRequestedChangeset'],
			'new-on-demand' => [$this, 'createNewChangesetOnDemand'],
			'unfinished'    => [$this, 'getLatestUnfinishedChangeset'],
			'new'           => [$this, 'createNewChangeset'],
		];
		//Let other modules and plugins filter the strategies.
		$strategies = apply_filters('admin_menu_editor-ac_initial_changeset_strategies', $strategies);

		foreach ($strategies as $strategy) {
			/** @var Option<AcChangeset> $changeset */
			$changeset = call_user_func($strategy);
			if ( $changeset->isDefined() ) {
				$this->currentChangeset = $changeset->get();
				$this->changesetName = $this->currentChangeset->getName();
				break;
			}
		}

		if ( $this->currentChangeset === null ) {
			wp_die('Could not load or create a changeset.', 500);
		}
	}

	/**
	 * @return Option<AcChangeset>
	 */
	private function loadRequestedChangeset() {
		return $this->getChangesetNameFromRequest()->flatMap(function ($name) {
			if ( $name === self::TEMPORARY_CHANGESET_NAME ) {
				//Create a temporary changeset for this request.
				return Option::fromValue(new AcChangeset());
			}

			$result = $this->loadChangeset($name);
			if ( $result->isDefined() ) {
				//For optional stats. The base plugin doesn't track views for changesets,
				//but an add-on could.
				do_action('admin_menu_editor-ac_requested_changeset_loaded', $result->get());
			}

			return $result;
		});
	}

	/**
	 * @param $name
	 * @return Option<AcChangeset>
	 */
	private function loadChangeset($name) {
		$results = $this->findChangesets([
			'name'           => $name,
			'posts_per_page' => 1,
		]);
		if ( empty($results) ) {
			return Option::fromValue(null);
		}
		return Option::fromValue(reset($results));
	}

	/**
	 * @param array $args
	 * @return AcChangeset[]
	 */
	private function findChangesets($args) {
		$defaults = [
			'post_type'      => self::CHANGESET_POST_TYPE,
			'posts_per_page' => -1,
			//Allow all statuses. get_posts() would usually default to "publish" only.
			'post_status'    => get_post_stati(),
			'orderby'        => 'date',
			'order'          => 'DESC',

			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		$args = array_merge($defaults, $args);
		$posts = get_posts($args);
		if ( empty($posts) ) {
			return [];
		}

		$results = [];
		foreach ($posts as $post) {
			$changeset = AcChangeset::fromPost($post);
			if ( $changeset->isDefined() ) {
				$results[] = $changeset->get();
			}
		}

		return $results;
	}

	/**
	 * Get the most recent valid changeset that is not an auto-draft and is not yet published.
	 *
	 * @return Option<AcChangeset>
	 */
	private function getLatestUnfinishedChangeset() {
		$results = $this->findChangesets([
			'post_status'    => array_diff(
				get_post_stati(),
				['auto-draft', 'publish', 'trash', 'inherit',]
			),
			'posts_per_page' => 1,
			'author'         => get_current_user_id(),
		]);

		foreach ($results as $changeset) {
			return Option::fromValue($changeset);
		}
		return None::getInstance();
	}

	/**
	 * @return Option<AcChangeset>
	 */
	private function createNewChangesetOnDemand() {
		$isNewChangesetRequested = false;

		//phpcs:disable WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		if ( isset($_POST['_ame-ac-new-changeset']) ) {
			$isNewChangesetRequested = (strval($_POST['_ame-ac-new-changeset']) === '1');
		} else if ( isset($_GET['_ame-ac-new-changeset']) ) {
			$isNewChangesetRequested = (strval($_GET['_ame-ac-new-changeset']) === '1');
		}
		//phpcs:enable

		if ( $isNewChangesetRequested ) {
			return $this->createNewChangeset();
		}
		return None::getInstance();
	}

	private function createNewChangeset() {
		$name = $this->generateChangesetName();
		$changeset = new AcChangeset($name);

		$postId = $this->saveChangeset($changeset);
		if ( is_wp_error($postId) || empty($postId) ) {
			return None::getInstance();
		}

		return Option::fromValue($changeset);
	}

	/**
	 * Generate a random changeset name.
	 *
	 * @return string
	 */
	private function generateChangesetName() {
		$base = '';
		for ($i = 0; $i < self::CHANGESET_NAME_BASE_LENGTH; $i++) {
			$randomIndex = wp_rand(0, strlen(self::CHANGESET_NAME_CHARACTERS) - 1);
			$base .= self::CHANGESET_NAME_CHARACTERS[$randomIndex];
		}

		//Pre-sanitize the base name to avoid calculating the checksum on an invalid name.
		$base = $this->sanitizeChangesetName($base);

		$checksum = $this->generateChangesetNameChecksum($base);
		return $base . $checksum;
	}

	private function generateChangesetNameChecksum($baseName) {
		$hash = strtolower(sha1($baseName)); //Better algorithms are available, but require PHP 7.1+.
		return substr($hash, 0, self::CHANGESET_NAME_CHECKSUM_LENGTH);
	}

	/**
	 * Verify that a changeset name is syntactically valid. This does not check
	 * if the changeset actually exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	private function isSyntacticallyValidChangesetName($name) {
		if ( !is_string($name) ) {
			return false;
		}

		$expectedLength = self::CHANGESET_NAME_BASE_LENGTH + self::CHANGESET_NAME_CHECKSUM_LENGTH;
		if ( strlen($name) !== $expectedLength ) {
			return false;
		}

		$base = substr($name, 0, self::CHANGESET_NAME_BASE_LENGTH);
		$checksum = substr($name, self::CHANGESET_NAME_BASE_LENGTH);
		$expectedChecksum = $this->generateChangesetNameChecksum($base);

		return ($checksum === $expectedChecksum);
	}

	/**
	 * @param mixed $name
	 * @return string
	 */
	private function sanitizeChangesetName($name) {
		if ( is_scalar($name) ) {
			$name = strtolower((string)$name);
			return preg_replace('/[^a-z0-9]/', '', $name);
		}
		return '';
	}

	/**
	 * @param AcChangeset $changeset
	 * @param string|null $status
	 * @return int|\WP_Error Post ID on success, or an error object on failure.
	 */
	private function saveChangeset(AcChangeset $changeset, $status = null) {
		$postArray = [
			'post_content' => wp_json_encode($changeset->jsonSerialize(), JSON_PRETTY_PRINT),
		];

		$existingPostId = $changeset->getPostId();
		if ( $existingPostId !== null ) {
			$postArray['ID'] = $existingPostId;
		} else {
			//The new changeset must already have a name.
			$name = $changeset->getName();
			if ( empty($name) ) {
				return new \WP_Error('ame_changeset_missing_name', 'Changeset is missing a name.');
			}

			$postArray['post_type'] = self::CHANGESET_POST_TYPE;
			$postArray['post_status'] = apply_filters('admin_menu_editor-ac_new_changeset_status', 'auto-draft');
			$postArray['post_name'] = $name;
			$postArray['post_title'] = $name;
			$postArray['post_author'] = get_current_user_id();
		}

		//Optionally, change the status.
		if ( $status !== null ) {
			$postArray['post_status'] = $status;
		}

		//Apparently, updating the post date extends the life of the auto-draft
		//and prevents it from being automatically deleted.
		if (
			//Is this post already an auto-draft?
			$existingPostId && (get_post_status($existingPostId) === 'auto-draft')
			//Will it remain an auto-draft?
			&& (empty($postArray['post_status']) || ($postArray['post_status'] === 'auto-draft'))
		) {
			$postArray['post_date'] = current_time('mysql');
			$postArray['post_date_gmt'] = '';
		}

		//Prevent WordPress from corrupting JSON data by "sanitizing" it.
		add_filter('wp_insert_post_data', [$this, 'preserveUnsanitizedPostContent'], 20, 3);
		//Convert exceptions to WP_Error instances. Normally, exceptions should not happen,
		//but if they do, this will give the user an error message they can report to me.
		try {
			if ( $existingPostId ) {
				$postId = wp_update_post(wp_slash($postArray), true);
			} else {
				$postId = wp_insert_post(wp_slash($postArray), true);
			}
		} catch (\Exception $e) {
			$postId = new \WP_Error(
				'ame_changeset_save_failed',
				'Unexpected exception: ' . $e->getMessage()
			);
		}
		remove_filter('wp_insert_post_data', [$this, 'preserveUnsanitizedPostContent'], 20);

		if ( is_wp_error($postId) ) {
			return $postId;
		} else if ( $postId === 0 ) {
			return new \WP_Error('ame_changeset_save_failed', 'Could not save the changeset.');
		}

		if ( !$existingPostId ) {
			$changeset->setPostId($postId);
		}

		/**
		 * Fires after a changeset has been saved.
		 *
		 * @param AcChangeset $changeset The changeset that was saved.
		 * @param bool $isNew True if the changeset was just created, false if it was updated.
		 */
		do_action('admin_menu_editor-ac_changeset_saved', $changeset, empty($existingPostId));

		return $postId;
	}

	/**
	 *
	 * @param array $data
	 * @param array $postArr
	 * @param array $unsanitizedPostArr
	 * @return array
	 * @internal     This is a callback for the wp_insert_post_data filter.
	 *
	 * While the second argument is not used in this method, it is part of the filter
	 * signature and cannot be omitted because we need the third argument.
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function preserveUnsanitizedPostContent($data, $postArr = [], $unsanitizedPostArr = []) {
		//This is basically a reimplementation of what WP_Customize_Manager does.
		if (
			isset($unsanitizedPostArr['post_content'])
			&& isset($data['post_type'])
			&& (
				($data['post_type'] === self::CHANGESET_POST_TYPE)
				|| (
					($data['post_type'] === 'revision')
					&& !empty($data['post_parent'])
					&& (get_post_type($data['post_parent']) === self::CHANGESET_POST_TYPE)
				)
			)
		) {
			$data['post_content'] = $unsanitizedPostArr['post_content'];
		}
		return $data;
	}

	private function getCurrentChangeset() {
		return $this->currentChangeset;
	}

	/**
	 * @internal
	 */
	public function registerChangesetPostType() {
		$capability = $this->menuEditor->current_user_can_edit_menu()
			? 'manage_options'
			: 'do_not_allow';

		$cptCapabilities = [
			'create_posts'           => $capability,
			'delete_others_posts'    => $capability,
			'delete_posts'           => $capability,
			'delete_private_posts'   => $capability,
			'delete_published_posts' => $capability,
			'edit_others_posts'      => $capability,
			'edit_posts'             => $capability,
			'edit_private_posts'     => $capability,
			'edit_published_posts'   => 'do_not_allow',
			'publish_posts'          => $capability,
			'read'                   => 'read',
			'read_private_posts'     => $capability,

			/*
			The "edit_post", "delete_post", and "read_post" keys are intentionally
			omitted. Using these keys makes WordPress treat the specified capability
			as a meta capability.

			For example, if we set "delete_post" to "manage_options", every time
			something checks the "manage_options" capability, WordPress will try
			to map it to "delete_post", which will fail without a post ID. This
			would effectively make the "manage_options" capability useless.
			*/
		];

		$cptCapabilities = apply_filters('admin_menu_editor-ac_changeset_capabilities', $cptCapabilities);

		register_post_type(
			self::CHANGESET_POST_TYPE,
			[
				'label'       => 'AC Changesets',
				'description' => 'For internal use. Changesets for the Admin Customizer module'
					. ' that is part of the Admin Menu Editor Pro plugin.',

				'public'           => false,
				'hierarchical'     => false,
				'rewrite'          => false,
				'query_var'        => false,
				'can_export'       => false,
				'delete_with_user' => false,
				'supports'         => ['title', 'author'],

				/* AFAICT, the "map_meta_cap" argument needs to be set to TRUE
				 * for WordPress to map meta capabilities like "edit_post" to
				 * context-specific capabilities like "edit_published_posts".
				 *
				 * Without it, checking for "edit_post" might just use the provided
				 * "edit_post" capability for all items regardless of their status.
				 */
				'map_meta_cap'     => true,
				'capability_type'  => self::CHANGESET_POST_TYPE,
				'capabilities'     => $cptCapabilities,
			]
		);
	}

	public function ajaxSaveChangeset() {
		check_ajax_referer(self::SAVE_CHANGESET_ACTION);

		if ( !$this->userCanAccessModule() ) {
			wp_send_json_error(
				['message' => 'You are not allowed to edit Admin Customizer changesets.'],
				403
			);
			//Technically, we don't need to call "exit" since wp_send_json_error()
			//will die(), but this lets my IDE know that execution stops here.
			exit;
		}

		$post = $this->menuEditor->get_post_params();

		/** Should we save the changeset even if some values are invalid? */
		$partialUpdatesAllowed = true;

		$hasModifiedSettings = isset($post['modified']);
		$hasThemeMetadata = array_key_exists('adminThemeMetadata', $post);

		//At least one of the above must be present.
		if ( !($hasModifiedSettings || $hasThemeMetadata) ) {
			wp_send_json_error(
				[
					'message' => 'Missing parameters. At least one of "modified" or '
						. '"adminThemeMetadata" must be present.',
				],
				400
			);
			exit;
		}

		if ( $hasModifiedSettings ) {
			$modified = json_decode((string)($post['modified']), true);
			if ( !is_array($modified) ) {
				wp_send_json_error(
					['message' => 'Invalid "modified" parameter. It must be a JSON object.'],
					400
				);
				exit;
			}
		} else {
			$modified = [];
		}

		//We'll need the post type object to check user permissions.
		$postType = get_post_type_object(self::CHANGESET_POST_TYPE);
		if ( !$postType ) {
			//This should never happen unless there's a bug.
			wp_send_json_error(['message' => 'The changeset post type is missing.'], 500);
			exit;
		}

		$response = new AcSaveResponse();

		$createNewChangeset = empty($post['changeset']) && !empty($post['createNew']);
		if ( $createNewChangeset ) {
			if ( !current_user_can($postType->cap->create_posts) ) {
				wp_send_json_error(
					['message' => 'You are not allowed to create new changesets.'],
					403
				);
				exit;
			}

			$changeset = new AcChangeset($this->generateChangesetName());
		} else {
			$csOption = $this->loadChangeset((string)($post['changeset']));
			if ( $csOption->isEmpty() ) {
				wp_send_json_error(['message' => 'Changeset not found.'], 400);
				exit;
			}
			$changeset = $csOption->get();

			if ( !current_user_can('edit_post', $changeset->getPostId()) ) {
				//Optionally, if the user can't edit the current changeset, we can automatically
				//create a new copy that they can edit.
				$autoDuplicateIfInaccessible = apply_filters(
					'admin_menu_editor-ac_auto_dup_if_inaccessible',
					false
				);
				if ( $autoDuplicateIfInaccessible ) {
					if ( !current_user_can($postType->cap->create_posts) ) {
						wp_send_json_error(
							['message' => 'You do not have permission to edit this changeset or to create new changesets.'],
							403
						);
						exit;
					}
					//Make a copy of the changeset.
					$changeset = $changeset->duplicate($this->generateChangesetName());
					$createNewChangeset = true;
				} else {
					wp_send_json_error(
						$response->error('You do not have the required permissions to edit this changeset.'),
						403
					);
					exit;
				}
			}
		}
		/** @var AcChangeset $changeset */

		//We need the setting objects to validate submitted values.
		$this->registerCustomizableItems();

		//Validate the submitted values.
		$validationResults = [];
		$successfulChanges = 0;
		foreach ($modified as $settingId => $value) {
			//Skip unmodified settings.
			if (
				$changeset->hasValueFor($settingId)
				&& ($changeset->getSettingValue($settingId) === $value)
			) {
				$response->addNote(sprintf('%s: %s', $settingId, 'New value is the same as old value.'));
				continue;
			}

			$validity = new AcValidationState();
			$validationResults[$settingId] = $validity;

			//The setting must exist.
			$setting = $this->getSettingById($settingId);
			if ( $setting === null ) {
				$validity->addError(new \WP_Error(
					'ame_invalid_setting_id',
					'Setting not found: ' . $settingId
				));
				$response->addNote(sprintf('%s: %s', $settingId, 'Setting not found.'));
				continue;
			}

			//Check user permissions.
			if ( !$setting->isEditableByUser() ) {
				$validity->addError(new \WP_Error(
					'ame_permission_denied',
					'You do not have permission to change this setting.'
				));
				$response->addNote(sprintf('%s: %s', $settingId, 'You do not have permission to change this setting.'));
				continue;
			}

			//Validate.
			$validationResult = $setting->validate(new \WP_Error(), $value);
			if ( is_wp_error($validationResult) ) {
				$validity->addError($validationResult);
				$response->addNote(sprintf('%s: Validation error: %s', $settingId, $validationResult->get_error_message()));
				continue;
			}
			$sanitizedValue = $validationResult;

			//Finally, update the value.
			$changeset->setSettingValue($settingId, $sanitizedValue);
			$successfulChanges++;

			$response->addNote(sprintf('%s: %s', $settingId, 'Value updated.'));
			$validity->setValid(true);
		}

		$response->setValidationResults($validationResults);
		$response->setChangeset($changeset);

		$hasValidationErrors = false;
		foreach ($validationResults as $validity) {
			if ( !$validity->isValid() ) {
				$hasValidationErrors = true;
				break;
			}
		}

		if ( $hasValidationErrors && !$partialUpdatesAllowed ) {
			wp_send_json_error(
				$response->error('There were one or more validation errors. Changes were not saved.'),
				400
			);
			exit;
		}

		//Parse and validate adminThemeMetadata.
		$wasAdminThemeMetadataModified = false;
		if ( $hasThemeMetadata ) {
			$inputMetadata = json_decode((string)($post['adminThemeMetadata']), true);
			if ( !$inputMetadata && (json_last_error() !== JSON_ERROR_NONE) ) {
				$errorMsg = json_last_error_msg();
				wp_send_json_error(
					$response->error('Invalid "adminThemeMetadata" parameter: ' . $errorMsg),
					400
				);
				exit;
			}

			if ( !is_array($inputMetadata) && ($inputMetadata !== null) ) {
				wp_send_json_error(
					['message' => 'Invalid "adminThemeMetadata" parameter. It must be a JSON object or NULL.'],
					400
				);
				exit;
			}

			if ( is_array($inputMetadata) ) {
				try {
					$adminThemeMetadata = AcAdminThemeMetadata::parseArray($inputMetadata);
				} catch (\InvalidArgumentException $e) {
					wp_send_json_error(
						$response->error('Invalid "adminThemeMetadata" parameter: ' . $e->getMessage()),
						400
					);
					exit;
				}
			} else {
				$adminThemeMetadata = null;
			}

			//Store the metadata.
			$changeset->setAdminThemeMetadata($adminThemeMetadata);
			$wasAdminThemeMetadataModified = true;
			$response->addNote('adminThemeMetadata updated.');
		}

		//A published or trashed changeset cannot be modified.
		$currentStatus = $changeset->getStatus();
		if ( in_array($currentStatus, ['publish', 'future', 'trash']) ) {
			wp_send_json_error(
				$response->error('This changeset is already published or trashed, so it cannot be modified.'),
				400
			);
			exit;
		}

		//Will the status change?
		$newStatus = null;
		if (
			!empty($post['status'])
			&& AcChangeset::isSupportedStatus($post['status'])
			&& ($post['status'] !== $currentStatus)
		) {
			$newStatus = $post['status'];
		}

		//We don't support deleting changesets through this endpoint.
		if ( $newStatus === 'trash' ) {
			wp_send_json_error(
				$response->error('This endpoint does not support changeset deletion.'),
				400
			);
			exit;
		}

		//Is the user allowed to apply the new status?
		if ( $newStatus !== null ) {
			$postId = $changeset->getPostId();
			if ( ($newStatus === 'publish') || ($newStatus === 'future') ) {
				if ( $postId ) {
					$allowed = current_user_can('publish_post', $postId);
				} else {
					$allowed = current_user_can($postType->cap->publish_posts);
				}
				if ( !$allowed ) {
					wp_send_json_error(
						$response->error('You are not allowed to publish or schedule this changeset.'),
						403
					);
					exit;
				}
			}
		}

		$response->mergeWith(['updatedValues' => $successfulChanges]);

		if ( ($successfulChanges > 0) || $createNewChangeset || ($newStatus !== null) || $wasAdminThemeMetadataModified ) {
			$changeset->setLastModifiedToNow();
			$saved = $this->saveChangeset($changeset, $newStatus);
			if ( is_wp_error($saved) ) {
				wp_send_json_error(
					$response->error(
						'Error saving changeset: ' . $saved->get_error_message(),
						$saved->get_error_code()
					),
					500
				);
				exit;
			}

			//If the changeset was published or trashed, the customizer will need
			//a new changeset.
			if ( in_array($changeset->getStatus(), ['publish', 'future', 'trash']) ) {
				$this->createNewChangeset()->each(
					function (AcChangeset $newChangeset) use ($response) {
						$response->mergeWith(['nextChangeset' => $newChangeset->getName()]);
					}
				);
			}

			wp_send_json_success(
				$response->mergeWith([
					'message'               => 'Changeset saved.',
					'changesetWasPublished' => ($newStatus === 'publish'),
				]),
				200 //Requires WP 4.7+
			);
		} else {
			//We don't need to save the changeset if there are no actual changes,
			//but this is still technically a success.
			wp_send_json_success(
				$response->mergeWith(['message' => 'No changes were made.']),
				200 //Requires WP 4.7+
			);
		}
	}

	public function ajaxTrashChangeset() {
		check_ajax_referer(self::TRASH_CHANGESET_ACTION);

		if ( !$this->userCanAccessModule() ) {
			wp_send_json_error(
				['message' => 'You are not allowed to edit Admin Customizer changesets.'],
				403
			);
			exit;
		}

		$postType = get_post_type_object(self::CHANGESET_POST_TYPE);
		if ( !$postType ) {
			wp_send_json_error(['message' => 'The changeset post type is missing.'], 500);
			exit;
		}

		$post = $this->menuEditor->get_post_params();
		$csOption = $this->loadChangeset((string)($post['changeset']));
		if ( $csOption->isEmpty() ) {
			wp_send_json_error(['message' => 'Changeset not found.'], 400);
			exit;
		}
		$changeset = $csOption->get();

		$currentStatus = $changeset->getStatus();
		if ( $currentStatus === 'trash' ) {
			wp_send_json_error(['message' => 'This changeset is already trashed.'], 400);
			exit;
		}

		$postId = $changeset->getPostId();
		if ( isset($postType->cap->delete_post) ) {
			$allowed = current_user_can($postType->cap->delete_post, $postId);
		} else {
			$allowed = current_user_can('delete_post', $postId);
		}
		if ( !$allowed ) {
			wp_send_json_error(['message' => 'You are not allowed to trash this changeset.'], 403);
			exit;
		}

		if ( !wp_trash_post($postId) ) {
			wp_send_json_error(['message' => 'Unexpected error while moving the changeset to Trash.'], 500);
		} else {
			wp_send_json_success(['message' => 'Changeset trashed.'], 200);
		}
		exit;
	}

	/**
	 * @param string $newStatus
	 * @param string $oldStatus
	 * @param \WP_Post $post
	 * @return void
	 */
	public function applyChangesOnPublish($newStatus, $oldStatus, $post) {
		if ( $oldStatus === $newStatus ) {
			return; //No change.
		}

		$isChangesetBeingPublished = ($newStatus === 'publish')
			&& ($post instanceof \WP_Post)
			&& ($post->post_type === self::CHANGESET_POST_TYPE);
		if ( !$isChangesetBeingPublished ) {
			return;
		}

		//Instantiate a changeset from the post.
		$option = AcChangeset::fromPost($post);
		if ( !$option->isDefined() ) {
			return; //Not a valid changeset.
		}
		$changeset = $option->get();
		/** @var AcChangeset $changeset */

		//Ensure settings are registered.
		$this->registerCustomizableItems();

		//Validate, update settings, then save all updated settings.
		$affectedSettings = [];
		foreach ($changeset as $settingId => $value) {
			$setting = $this->getSettingById($settingId);
			if ( $setting === null ) {
				continue;
			}

			$validationResult = $setting->validate(new \WP_Error(), $value, true);
			if ( is_wp_error($validationResult) ) {
				continue;
			}
			$sanitizedValue = $validationResult;

			$setting->update($sanitizedValue);
			$affectedSettings[] = $setting;
		}

		AbstractSetting::saveAll($affectedSettings);

		//Trash the changeset after it has been applied. We don't want to keep old
		//changesets in the database - they take up space and serve no useful purpose.
		wp_trash_post($post->ID);
	}

	public function userCanAccessModule() {
		$requiredCapability = $this->getBasicAccessCapability();
		if ( !empty($requiredCapability) ) {
			return current_user_can($requiredCapability);
		}

		return $this->menuEditor->current_user_can_edit_menu();
	}

	/**
	 * @return string|null
	 */
	private function getBasicAccessCapability() {
		$result = apply_filters('admin_menu_editor-ac_access_capability', null);
		if ( !is_string($result) && !is_null($result) ) {
			throw new \RuntimeException('Invalid return value from the admin_menu_editor-ac_access_capability filter.');
		}
		return $result;
	}

	/**
	 * This method has side effects due to enabling preview for settings that
	 * are part of the changeset. It is not safe to call it multiple times with
	 * different changesets.
	 *
	 * @param \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcChangeset $changeset
	 * @param \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcAdminThemeMetadata $metadata
	 * @return \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcAdminTheme
	 */
	private function createAdminTheme(AcChangeset $changeset, AcAdminThemeMetadata $metadata) {
		//We need the setting objects to find out which settings are tagged
		//as part of the admin theme, and also to enable preview so that we
		//can generate admin theme CSS using the current values.
		$this->registerCustomizableItems();

		//Find admin theme settings.
		//Note: Even if some children of structs do not inherit the admin theme tag from
		//the parent, the children will still be included.
		$settings = array_filter(
			$this->registeredSettings,
			function (AbstractSetting $setting) {
				return $setting->hasTag(AbstractSetting::TAG_ADMIN_THEME);
			}
		);

		//Enable preview for all admin theme settings that are in the changeset.
		//This way CSS generation should use the current values.
		foreach ($changeset as $settingId => $value) {
			if ( !isset($settings[$settingId]) ) {
				continue;
			}
			$settings[$settingId]->preview($value);
		}

		$adminTheme = new AcAdminTheme($metadata, $settings);

		//Generate admin theme CSS.
		$themeCssParts = [];
		$addThemeCss = function ($css) use (&$themeCssParts) {
			if ( !is_string($css) || empty($css) ) {
				return;
			}
			$themeCssParts[] = $css;
		};

		//Modules can add CSS by using this action and calling $addThemeCss.
		do_action('admin_menu_editor-ac_admin_theme_css', $addThemeCss, $adminTheme);

		if ( empty($themeCssParts) ) {
			throw new \RuntimeException('The current settings did not generate any CSS for an admin theme.');
		}
		$adminTheme->setMainStylesheet(implode("\n", $themeCssParts));

		//Optionally, the admin theme can include an admin color scheme.
		$colorScheme = apply_filters('admin_menu_editor-ac_admin_theme_color_scheme', null, $adminTheme);
		if ( $colorScheme !== null ) {
			$adminTheme->setColorScheme($colorScheme);
		}

		return $adminTheme;
	}

	public function ajaxCreateAdminTheme() {
		check_ajax_referer(self::CREATE_THEME_ACTION);

		if ( !$this->userCanAccessModule() ) {
			wp_send_json_error(
				['message' => 'You do not have permission to create an admin theme.'],
				403
			);
			exit;
		}

		$post = $this->menuEditor->get_post_params();

		//The changeset name must be specified explicitly.
		if ( !isset($post['changeset']) ) {
			wp_send_json_error(
				['message' => 'The changeset name must be specified.'],
				400
			);
			exit;
		}

		//The request must specify a cookie that will be used to detect that
		//the download has started.
		if ( !isset($post['downloadCookieName']) ) {
			wp_send_json_error(
				['message' => 'The cookie name must be specified.'],
				400
			);
			exit;
		}

		//For security, the cookie name must start with a known prefix and must
		//only contain alphanumeric characters and underscores.
		$cookieName = strval($post['downloadCookieName']);
		if ( substr($cookieName, 0, strlen(self::DOWNLOAD_COOKIE_PREFIX)) !== self::DOWNLOAD_COOKIE_PREFIX ) {
			wp_send_json_error(['message' => 'The cookie name is invalid.'], 400);
			exit;
		}
		if ( strlen($cookieName) > 200 ) {
			wp_send_json_error(['message' => 'The cookie name is too long.'], 400);
			exit;
		}
		if ( preg_match('/[^a-zA-Z0-9_]/', substr($cookieName, strlen(self::DOWNLOAD_COOKIE_PREFIX))) ) {
			wp_send_json_error(['message' => 'The cookie name contains invalid characters.'], 400);
			exit;
		}

		//Load the changeset and check permissions.
		$csOption = $this->loadChangeset((string)($post['changeset']));
		if ( $csOption->isEmpty() ) {
			wp_send_json_error(['message' => 'Changeset not found.'], 400);
			exit;
		}
		$changeset = $csOption->get();

		if ( !current_user_can('read_post', $changeset->getPostId()) ) {
			wp_send_json_error(
				['message' => 'You do not have permission to read the specified changeset.'],
				403
			);
			exit;
		}

		//Note: Any pending changes to the changeset should be saved before
		//calling this AJAX action. Otherwise, the admin theme will be generated
		//without those changes.

		//Get the admin theme metadata.
		if ( !isset($post['metadata']) ) {
			wp_send_json_error(
				['message' => 'The admin theme metadata must be specified.'],
				400
			);
			exit;
		}
		try {
			$metadata = AcAdminThemeMetadata::parseJson((string)$post['metadata']);
		} catch (\Exception $e) {
			wp_send_json_error(
				['message' => $e->getMessage(), 'code' => 'metadata_parse_error'],
				400
			);
			exit;
		}

		//Required WP version must be at least 4.7.
		if ( empty($metadata->requiredWpVersion) || version_compare($metadata->requiredWpVersion, '4.7', '<') ) {
			$metadata->requiredWpVersion = '4.7';
		}
		//Tested WP version is at least the current WP version.
		try {
			$currentWpVersion = AcAdminThemeMetadata::parseVersionNumber(get_bloginfo('version'));
		} catch (\Exception $e) {
			$currentWpVersion = '';
		}
		if (
			!empty($currentWpVersion)
			&& (
				empty($metadata->testedWpVersion)
				|| version_compare($metadata->testedWpVersion, $currentWpVersion, '<')
			)
		) {
			$metadata->testedWpVersion = $currentWpVersion;
		}

		//Create the admin theme.
		try {
			$adminTheme = $this->createAdminTheme($changeset, $metadata);
			$zipFileContent = $adminTheme->toZipString();
		} catch (\Exception $e) {
			//There should be no exceptions here, but just in case.
			wp_send_json_error(
				['message' => $e->getMessage(), 'code' => 'admin_theme_generation_error'],
				500
			);
			exit;
		}

		//Set a cookie that will be used to detect that the download has started.
		$cookieValue = uniqid('', true);
		if ( version_compare(phpversion(), '7.3', '>=') ) {
			setcookie(
				$cookieName,
				$cookieValue,
				[
					'expires'  => 0, //Session cookie.
					'path'     => '/',
					'samesite' => 'Lax',
					'secure'   => is_ssl(),
					'httponly' => false, //Our JS needs to read the cookie.
				]
			);
		} else {
			setcookie($cookieName, $cookieValue, 0, '/', '', is_ssl(), false);
		}

		//Output the file as a download.
		$filename = $adminTheme->getZipFileName();
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($zipFileContent));
		header('Content-Transfer-Encoding: binary');

		header('Cache-Control: private, no-transform, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $zipFileContent is binary data.
		echo $zipFileContent;

		//Remember when this changeset/admin theme was most recently downloaded.
		$postId = $changeset->getPostId();
		if ( $postId ) {
			update_post_meta($postId, self::LAST_DOWNLOAD_META_KEY, time());
		}
		exit;
	}
}

class AcChangeset implements \IteratorAggregate, \JsonSerializable, \Countable {
	/**
	 * @var array<string,mixed>
	 */
	private $settingValues = [];

	private $postId = null;
	private $name = null;
	private $lastModified;

	/**
	 * @var null|AcAdminThemeMetadata
	 */
	private $adminThemeMetadata = null;

	private $status = null;

	public function __construct($name = null) {
		if ( $name !== null ) {
			$this->name = $name;
		}
		$this->lastModified = time();
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator($this->settingValues);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'settings'           => $this->settingValues,
			'lastModified'       => $this->lastModified,
			'adminThemeMetadata' => $this->adminThemeMetadata,
		];
	}

	/**
	 * @param \WP_Post $post
	 * @return Option<AcChangeset>
	 */
	public static function fromPost(\WP_Post $post) {
		return static::fromJson($post->post_content)->each(
			function ($changeset) use ($post) {
				$changeset->postId = $post->ID;
				$changeset->name = $post->post_name;
			}
		);
	}

	/**
	 * @param string $jsonString
	 * @return Option<AcChangeset>
	 */
	public static function fromJson($jsonString) {
		$data = json_decode($jsonString, true);
		if ( !is_array($data) ) {
			return None::getInstance();
		}
		return Option::fromValue(static::fromArray($data));
	}

	/**
	 * @param $data
	 * @return static
	 */
	public static function fromArray($data) {
		$changeset = new static();

		$changeset->settingValues = $data['settings'];
		$changeset->lastModified = isset($data['lastModified']) ? intval($data['lastModified']) : null;
		if ( isset($data['adminThemeMetadata']) ) {
			$changeset->adminThemeMetadata = AcAdminThemeMetadata::parseArray($data['adminThemeMetadata']);
		}

		return $changeset;
	}

	/**
	 * @param null|int $postId
	 * @return AcChangeset
	 */
	public function setPostId($postId) {
		if ( $this->postId !== null ) {
			throw new \RuntimeException('Cannot change the post ID of an existing changeset.');
		}
		$this->postId = $postId;
		return $this;
	}

	/**
	 * @return null|int
	 */
	public function getPostId() {
		return $this->postId;
	}

	/**
	 * @return string|null
	 */
	public function getName() {
		return $this->name;
	}

	public function setSettingValue($settingId, $value) {
		$this->settingValues[$settingId] = $value;
	}

	/**
	 * @param string $settingId
	 * @return mixed|null
	 */
	public function getSettingValue($settingId) {
		if ( array_key_exists($settingId, $this->settingValues) ) {
			return $this->settingValues[$settingId];
		}
		return null;
	}

	/**
	 * @param string $settingId
	 * @return bool
	 */
	public function hasValueFor($settingId) {
		return array_key_exists($settingId, $this->settingValues);
	}

	public static function isSupportedStatus($status) {
		return in_array($status, [
			'draft',
			'auto-draft',
			'publish',
			'future',
			'trash',
			'private',
			'pending',
		]);
	}

	/**
	 * @return string|null
	 */
	public function getStatus() {
		if ( !empty($this->postId) ) {
			$status = get_post_status($this->postId);
			if ( $status ) {
				return $status;
			}
		}
		return $this->status;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function count() {
		return count($this->settingValues);
	}

	/**
	 * @return int|null
	 */
	public function getLastModified() {
		return $this->lastModified;
	}

	public function setLastModifiedToNow() {
		$this->lastModified = time();
	}

	/**
	 * @param AcAdminThemeMetadata|null $metadata
	 * @return void
	 */
	public function setAdminThemeMetadata($metadata) {
		$this->adminThemeMetadata = $metadata;
	}

	/**
	 * @return \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcAdminThemeMetadata|null
	 */
	public function getAdminThemeMetadata() {
		return $this->adminThemeMetadata;
	}

	/**
	 * Create a new changeset with the same settings as this one.
	 *
	 * Does NOT save the new changeset.
	 *
	 * @param string|null $name
	 * @return static
	 */
	public function duplicate($name = null) {
		$newChangeset = new static($name);
		$newChangeset->settingValues = $this->settingValues;
		$newChangeset->adminThemeMetadata = $this->adminThemeMetadata;
		$newChangeset->lastModified = $this->lastModified;
		return $newChangeset;
	}
}

class AcValidationState implements \JsonSerializable {
	/**
	 * @var bool
	 */
	protected $isValid = true;
	/**
	 * @var \WP_Error[]
	 */
	protected $errors = [];

	public function addError(\WP_Error $error, $markAsInvalid = true) {
		$this->errors[] = $error;
		if ( $markAsInvalid ) {
			$this->isValid = false;
		}
	}

	public function setValid($valid) {
		$this->isValid = $valid;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		//Convert WP_Error instances to JSON-compatible associative arrays.
		$serializableErrors = [];
		foreach ($this->errors as $error) {
			foreach ($error->errors as $code => $messages) {
				foreach ($messages as $message) {
					$serializableErrors[] = [
						'code'    => $code,
						'message' => $message,
					];
				}
			}
		}

		return [
			'isValid' => $this->isValid,
			'errors'  => $serializableErrors,
		];
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return $this->isValid;
	}
}

class AcSaveResponse implements \JsonSerializable {
	private $fields = [];
	private $notes = [];
	/**
	 * @var AcChangeset|null
	 */
	private $changeset = null;

	/**
	 * @param array $additionalFields
	 * @return $this
	 */
	public function mergeWith($additionalFields) {
		$this->fields = array_merge($this->fields, $additionalFields);
		return $this;
	}

	public function error($message, $code = null) {
		$this->fields['message'] = $message;
		if ( $code !== null ) {
			$this->fields['code'] = $code;
		}
		return $this;
	}

	/**
	 * @param array $validationResults
	 * @return $this
	 */
	public function setValidationResults($validationResults) {
		$this->fields['validationResults'] = $validationResults;
		return $this;
	}

	public function setChangeset(AcChangeset $changeset) {
		$this->changeset = $changeset;
	}

	/**
	 * @param string $message
	 * @return $this
	 */
	public function addNote($message) {
		$this->notes[] = $message;
		return $this;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$result = $this->fields;
		if ( $this->changeset !== null ) {
			$result = array_merge($result, [
				'changeset'          => $this->changeset->getName(),
				'changesetItemCount' => count($this->changeset),
				'changesetStatus'    => $this->changeset->getStatus(),
			]);
		}
		if ( !empty($this->notes) ) {
			$result['notes'] = $this->notes;
		}
		return $result;
	}
}

class AcAdminThemeMetadata implements \JsonSerializable {
	const MAX_FIELD_LENGTH = 1024;
	const MIN_ID_PREFIX_LENGTH = 10;
	const DESIRED_ID_PREFIX_LENGTH = 16;
	const ID_PREFIX_HASH_LENGTH = 6;

	public $pluginName = 'Custom Admin Theme';
	public $pluginSlug = '';
	public $pluginVersion = '1.0';
	public $pluginUrl = '';
	public $authorName = '';
	public $authorUrl = '';
	public $requiredWpVersion = '4.7'; //Current required version for AME itself.
	public $testedWpVersion = '6.2';
	public $shortDescription = ' ';
	public $identifierPrefix = '';

	/**
	 * @var bool Whether the user has ever confirmed the metadata settings
	 *           (e.g. by clicking "OK" or "Download" in the metadata editor).
	 */
	public $wasEverConfirmed = false;

	/**
	 * @param string $jsonString
	 * @return self
	 */
	public static function parseJson($jsonString) {
		$parsed = json_decode($jsonString, true);
		if ( $parsed === null ) {
			throw new \InvalidArgumentException('Invalid JSON string');
		}
		if ( !is_array($parsed) ) {
			throw new \InvalidArgumentException('JSON string does not represent an object');
		}
		return self::parseArray($parsed);
	}

	/**
	 * @param array $inputArray
	 * @return self
	 */
	public static function parseArray($inputArray) {
		$result = new self();

		//Tested WP version defaults to the current version.
		$result->testedWpVersion = get_bloginfo('version');
		if ( !empty($result->testedWpVersion) ) {
			//Keep only the major and minor version numbers.
			$result->testedWpVersion = preg_replace('/^(\d+\.\d+).*$/i', '$1', $result->testedWpVersion);
		}

		$parsers = [
			'pluginName'        => [__CLASS__, 'parsePluginName'],
			'pluginSlug'        => [__CLASS__, 'parsePluginSlug'],
			'requiredWpVersion' => [__CLASS__, 'parseVersionNumber'],
			'testedWpVersion'   => [__CLASS__, 'parseVersionNumber'],
			'pluginVersion'     => [__CLASS__, 'parseVersionNumber'],
			'authorName'        => [__CLASS__, 'parseAuthorName'],
			'authorUrl'         => 'esc_url_raw',
			'pluginUrl'         => 'esc_url_raw',
			'shortDescription'  => [__CLASS__, 'parseShortDescription'],
			'identifierPrefix'  => [__CLASS__, 'parseIdentifierPrefix'],
			'wasEverConfirmed'  => 'boolval',
		];

		foreach ($parsers as $key => $parser) {
			if ( isset($inputArray[$key]) ) {
				if ( is_string($inputArray[$key]) && (strlen($inputArray[$key]) > self::MAX_FIELD_LENGTH) ) {
					throw new \InvalidArgumentException(sprintf(
						'Field "%s" is too long (max %d characters)',
						$key,
						self::MAX_FIELD_LENGTH
					));
				}

				$result->$key = is_callable($parser)
					? call_user_func($parser, $inputArray[$key])
					: $inputArray[$key];
			}
		}

		//Fallback for the prefix.
		if ( empty($result->identifierPrefix) ) {
			$result->identifierPrefix = self::generateIdentifierPrefix($result);
		} else if ( strlen($result->identifierPrefix) < self::MIN_ID_PREFIX_LENGTH ) {
			//Prefix should be long enough to be unique.
			$result->identifierPrefix = (
				$result->identifierPrefix
				. self::generateHashForPrefix(
					$result,
					self::MIN_ID_PREFIX_LENGTH - strlen($result->identifierPrefix)
				)
			);
		}

		//Plugin version defaults to 1.0.
		if ( empty($result->pluginVersion) ) {
			$result->pluginVersion = '1.0';
		}

		return $result;
	}

	public static function parseVersionNumber($input) {
		if ( !is_string($input) || empty($input) ) {
			return null;
		}
		if ( preg_match('/^(\d{1,3}+)((?:\.\d{1,5}+){0,2})/i', trim($input), $matches) ) {
			$parts = array_slice($matches, 1, 3);
			return implode('', $parts);
		} else {
			throw new \InvalidArgumentException('Invalid version number');
		}
	}

	private static function parsePluginName($name) {
		if ( !is_string($name) || empty($name) ) {
			throw new \InvalidArgumentException('Invalid plugin name');
		}

		//Strip out any HTML tags.
		$name = wp_strip_all_tags($name);

		//Limit the length to 100 characters.
		return substr($name, 0, 100);
	}

	private static function parseShortDescription($description) {
		if ( !is_string($description) ) {
			throw new \InvalidArgumentException('Invalid short description');
		}

		$description = sanitize_text_field($description);

		return substr($description, 0, 500);
	}

	private static function parseAuthorName($name) {
		if ( !is_string($name) ) {
			throw new \InvalidArgumentException('Invalid author name');
		}

		$name = sanitize_text_field($name);

		return substr($name, 0, 100);
	}

	private static function parseIdentifierPrefix($prefix) {
		if ( !is_string($prefix) ) {
			throw new \InvalidArgumentException('Invalid identifier prefix');
		}

		$prefix = self::sanitizeIdPrefix($prefix);

		return substr($prefix, 0, 20);
	}

	protected static function generateIdentifierPrefix(AcAdminThemeMetadata $meta) {
		$prefix = sanitize_title($meta->pluginName);

		$prefix = str_replace('-', ' ', $prefix);
		$prefix = ucwords($prefix);
		$prefix = self::sanitizeIdPrefix($prefix);

		//Prefix should always start with a letter.
		if ( !preg_match('/^[a-z]/i', $prefix) ) {
			$prefix = 'At' . $prefix; //At = Admin theme
		}

		//Truncate the prefix so that there's space for a hash.
		$prefix = substr($prefix, 0, max(self::DESIRED_ID_PREFIX_LENGTH - self::ID_PREFIX_HASH_LENGTH, 1));

		//Add a hash to make the prefix more likely to be unique.
		$prefix .= self::generateHashForPrefix(
			$meta,
			max(self::ID_PREFIX_HASH_LENGTH, self::DESIRED_ID_PREFIX_LENGTH - strlen($prefix))
		);
		return $prefix;
	}

	protected static function generateHashForPrefix(AcAdminThemeMetadata $meta, $length) {
		$hash = sha1($meta->pluginName . '|' . $meta->authorName . '|' . wp_rand());
		return substr($hash, 0, $length);
	}

	private static function sanitizeIdPrefix($prefix) {
		//Allow alphanumeric characters and underscores. No dashes since the prefix
		//is also used in class names.
		return preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
	}

	public function getEffectivePluginSlug() {
		if ( !empty($this->pluginSlug) ) {
			return $this->pluginSlug;
		} else {
			//Fallback to the plugin name converted to a slug.
			return self::parsePluginSlug($this->pluginName);
		}
	}

	private static function parsePluginSlug($slug) {
		if ( $slug === null ) {
			return '';
		}
		if ( !is_string($slug) ) {
			throw new \InvalidArgumentException('Invalid plugin slug');
		}

		$slug = sanitize_title($slug);

		//Slug should not be longer than, say, 64 characters.
		return substr($slug, 0, 64);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'pluginName'        => $this->pluginName,
			'pluginSlug'        => $this->pluginSlug,
			'pluginVersion'     => $this->pluginVersion,
			'pluginUrl'         => $this->pluginUrl,
			'authorName'        => $this->authorName,
			'authorUrl'         => $this->authorUrl,
			'requiredWpVersion' => $this->requiredWpVersion,
			'testedWpVersion'   => $this->testedWpVersion,
			'shortDescription'  => $this->shortDescription,
			'identifierPrefix'  => $this->identifierPrefix,
			'wasEverConfirmed'  => $this->wasEverConfirmed,
		];
	}
}

class AcAdminColorSchemeData {
	public $mainCss = '';
	public $adminBarCss = '';
	public $colorMeta = [
		'demo'  => [],
		'icons' => [],
	];

	public function __construct($mainCss, $adminBarCss, $colorMeta) {
		$this->mainCss = $mainCss;
		$this->adminBarCss = $adminBarCss;
		$this->colorMeta = $colorMeta;
	}
}

class AcAdminTheme {
	/**
	 * @var AcAdminThemeMetadata
	 */
	public $meta;

	/**
	 * @var array<string,string>
	 */
	protected $files = [];

	/**
	 * @var array<string,mixed>
	 */
	public $settings = [];

	/**
	 * @param \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcAdminThemeMetadata $metadata
	 * @param AbstractSetting[] $settings
	 */
	public function __construct(AcAdminThemeMetadata $metadata, $settings = []) {
		$this->meta = $metadata;
		foreach (AbstractSetting::recursivelyIterateSettings($settings, true) as $setting) {
			$this->settings[$setting->getId()] = $setting->getValue();
		}
	}

	public function toZipString() {
		if ( !class_exists('ZipArchive') ) {
			throw new \RuntimeException('ZipArchive class is not available on this server.');
		}

		$files = $this->files;
		$files['admin-theme.php'] = $this->populateTemplate('admin-theme.php');
		$files['readme.txt'] = $this->populateTemplate('readme.txt');
		$files['settings.json'] = wp_json_encode($this->settings, JSON_PRETTY_PRINT);
		$files['metadata.json'] = wp_json_encode($this->meta, JSON_PRETTY_PRINT);

		$tempFileName = get_temp_dir() . uniqid('ac-cat-') . '.zip';
		$directoryName = $this->meta->getEffectivePluginSlug();

		$zip = new \ZipArchive();
		if ( $zip->open($tempFileName, \ZipArchive::CREATE) !== true ) {
			throw new \RuntimeException('Failed to create temporary ZIP file.');
		}
		$zip->addEmptyDir($directoryName);
		foreach ($files as $fileName => $fileContents) {
			$zip->addFromString($directoryName . '/' . $fileName, $fileContents);
		}
		$zip->close();

		//phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local temp file.
		$content = file_get_contents($tempFileName);
		//phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- This is a temporary file.
		unlink($tempFileName);

		return $content;
	}

	public function getZipFileName() {
		return $this->meta->getEffectivePluginSlug() . '.zip';
	}

	public function setMainStylesheet($css) {
		$this->files['custom-admin-theme.css'] = $css;
	}

	public function setColorScheme(AcAdminColorSchemeData $colorScheme) {
		$this->files['color-scheme.css'] = $colorScheme->mainCss;
		if ( !empty($colorScheme->adminBarCss) ) {
			$this->files['admin-bar-colors.css'] = $colorScheme->adminBarCss;
		}
		$this->files['color-scheme.json'] = wp_json_encode($colorScheme->colorMeta, JSON_PRETTY_PRINT);
	}

	protected function populateTemplate($relativeFileName) {
		$template = file_get_contents(__DIR__ . '/admin-theme-template/' . $relativeFileName);
		return $this->replacePlaceholders($template);
	}

	protected function replacePlaceholders($content) {
		$placeholders = [
			'pluginName',
			'pluginUrl',
			'pluginSlug',
			'pluginVersion',
			'authorName',
			'authorUrl',
			'requiredWpVersion',
			'testedWpVersion',
			'shortDescription',
			'identifierPrefix',
			'optionalPluginHeaders',
		];

		$values = [];
		foreach ($placeholders as $placeholder) {
			if ( isset($this->meta->$placeholder) ) {
				$values[$placeholder] = $this->meta->$placeholder;
			}
		}

		$values['randomHash'] = substr(sha1(time() . '|' . wp_rand() . '|' . $this->meta->pluginVersion), 0, 8);

		if ( empty($values['optionalPluginHeaders']) ) {
			$optionalHeaderNames = [
				'Plugin URI' => 'pluginUrl',
				'Author'     => 'authorName',
				'Author URI' => 'authorUrl',
			];
			$optionalHeaders = [];
			foreach ($optionalHeaderNames as $headerName => $metaKey) {
				if ( isset($this->meta->$metaKey) && !empty($this->meta->$metaKey) ) {
					$optionalHeaders[] = ' * ' . $headerName . ': ' . $this->meta->$metaKey;
				}
			}
			$values['optionalPluginHeaders'] = implode("\n", $optionalHeaders);
		}

		//The syntax is `{placeholder}`.
		foreach ($values as $placeholder => $value) {
			$content = str_replace('{' . $placeholder . '}', $value, $content);
		}

		//acIdentPrefix is a special case, it's not in curly braces.
		return str_replace('acIdentPrefix', $this->meta->identifierPrefix, $content);
	}
}