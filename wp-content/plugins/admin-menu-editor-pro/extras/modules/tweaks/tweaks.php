<?php

/*
 * Idea: Show tweaks as options in menu properties, e.g. in a "Tweaks" section styled like the collapsible
 * property sheets in Delphi.
 */

require_once __DIR__ . '/configurables.php';
require_once __DIR__ . '/ameBaseTweak.php';
require_once __DIR__ . '/ameTweakAlias.php';
require_once __DIR__ . '/ameHideSelectorTweak.php';
require_once __DIR__ . '/ameHideJquerySelectorTweak.php';
require_once __DIR__ . '/ameHideSidebarTweak.php';
require_once __DIR__ . '/ameHideSidebarWidgetTweak.php';
require_once __DIR__ . '/ameDelegatedTweak.php';
require_once __DIR__ . '/ameJqueryTweak.php';
require_once __DIR__ . '/ameTinyMceButtonManager.php';
require_once __DIR__ . '/ameAdminCssTweakManager.php';
require_once __DIR__ . '/ameGutenbergBlockManager.php';
require_once __DIR__ . '/ameMediaRestrictionsManager.php';
require_once __DIR__ . '/ameProfileFieldTweakManager.php';

/** @noinspection PhpUnused The class is actually used in extras.php */

//TODO: When importing tweak settings, pick the largest of lastUserTweakSuffix. See mergeSettingsWith().

class ameTweakManager extends amePersistentModule {
	const APPLY_TWEAK_AUTO = 'auto';
	const APPLY_TWEAK_MANUALLY = 'manual';

	const HIDEABLE_ITEM_COMPONENT = 'tw';
	const HIDEABLE_ITEM_PREFIX = 'tweaks/';

	const BASIC_TWEAK_PROPERTIES = ['id' => true, 'enabledForActor' => true];

	protected $tabSlug = 'tweaks';
	protected $tabTitle = 'Tweaks';
	protected $optionName = 'ws_ame_tweak_settings';

	protected $settingsFormAction = 'ame-save-tweak-settings';

	/**
	 * @var ameBaseTweak[]
	 */
	private $tweaks = [];

	/**
	 * @var ameBaseTweak[]
	 */
	private $pendingTweaks = [];

	/**
	 * @var ameBaseTweak[]
	 */
	private $postponedTweaks = [];

	/**
	 * @var ameTweakSection[]
	 */
	private $sections = [];

	/**
	 * @var ameTweakAlias[]
	 */
	private $aliases = [];

	private $adminCssManager;

	/**
	 * @var null|array
	 */
	private $cachedEnabledTweakSettings = null;

	/**
	 * @var callable[]
	 */
	private $tweakBuilders = [];

	private $earlyInitDone = false;
	private $earlyTweaksRegistered = false;
	private $defaultTweakFactories = [];
	private $screenHookAdded = false;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		new ameTinyMceButtonManager();
		new ameMediaRestrictionsManager();
		new ameGutenbergBlockManager($menuEditor);
		new ameProfileFieldTweakManager($menuEditor);

		$this->adminCssManager = new ameAdminCssTweakManager();
		$this->tweakBuilders['admin-css'] = [$this->adminCssManager, 'createTweak'];

		//Some tweaks may need to run early, before the "init" hook. For example, the tweak that disables
		//admin menu customizations affects virtual caps, and some plugins trigger cap checks very early.
		//The earliest we can process tweaks is on "set_current_user" because we need to know the current
		//user to figure out which tweaks to apply.
		if ( did_action('set_current_user') ) {
			$this->earlyInit();
		} else {
			add_action('set_current_user', [$this, 'earlyInit'], 1);
		}

		add_action('init', [$this, 'onInit'], PHP_INT_MAX - 1000);

		//We need to process widgets after they've been registered (usually priority 10)
		//but before WordPress has populated the $wp_registered_widgets global (priority 95 or 100).
		add_action('widgets_init', [$this, 'processSidebarWidgets'], 50);
		//Sidebars are simpler: we can just use a really late priority.
		add_action('widgets_init', [$this, 'processSidebars'], 1000);

		add_action('admin_menu_editor-register_hideable_items', [$this, 'registerHideableItems'], 20);
		add_filter(
			'admin_menu_editor-save_hideable_items-' . self::HIDEABLE_ITEM_COMPONENT,
			[$this, 'saveHideableItems'],
			10, 2
		);
	}

	public function earlyInit() {
		//"set_current_user" can potentially be triggered multiple times, so we need to make sure
		//we only run this once.
		if ( $this->earlyInitDone ) {
			return;
		}

		$userAvailable = function_exists('is_user_logged_in') && is_user_logged_in();
		if ( !$userAvailable ) {
			return;
		}
		$this->registerEarlyTweaks();

		$earlyTweaks = [];
		foreach ($this->pendingTweaks as $id => $tweak) {
			if ( $tweak->wantsToRunEarly() ) {
				$earlyTweaks[$id] = $tweak;
				unset($this->pendingTweaks[$id]);
			}
		}

		if ( !empty($earlyTweaks) ) {
			$this->processTweaks($earlyTweaks);
		}

		$this->earlyInitDone = true;
	}

	private function registerEarlyTweaks() {
		if ( $this->earlyTweaksRegistered ) {
			return;
		}

		$this->addSection('general', 'General');

		$tweakData = require(__DIR__ . '/default-tweaks.php');

		foreach (ameUtils::get($tweakData, 'sections', []) as $id => $section) {
			$sectionInstance = $this->addSection(
				$id,
				ameUtils::get($section, 'label', $id),
				ameUtils::get($section, 'priority', 10)
			);
			if ( !empty($section['description']) ) {
				$sectionInstance->setDescription($section['description']);
			}
		}

		$defaultTweaks = ameUtils::get($tweakData, 'tweaks', []);
		$this->addDefaultTweaks($this->filterTweaksToRegister($defaultTweaks));

		if ( !empty($tweakData['definitionFactories']) ) {
			$this->defaultTweakFactories = array_merge($this->defaultTweakFactories, $tweakData['definitionFactories']);
		}

		$this->earlyTweaksRegistered = true;
	}

	private function addDefaultTweaks($defaultTweaks) {
		foreach ($defaultTweaks as $id => $properties) {
			if ( isset($properties['selector']) ) {
				$tweak = new ameHideSelectorTweak(
					$id,
					isset($properties['label']) ? $properties['label'] : null,
					$properties['selector']
				);

				if ( isset($properties['screens']) ) {
					$tweak->setScreens($properties['screens']);
				}
			} else if ( isset($properties['className']) ) {
				if ( isset($properties['includeFile']) ) {
					require_once $properties['includeFile'];
				}

				$className = $properties['className'];
				$tweak = new $className(
					$id,
					isset($properties['label']) ? $properties['label'] : null
				);
			} else if ( isset($properties['factory']) ) {
				if ( isset($properties['includeFile']) ) {
					require_once $properties['includeFile'];
				}
				$tweak = call_user_func($properties['factory'], $id, $properties);
			} else if ( isset($properties['jquery-js']) ) {
				$tweak = new ameJqueryTweak(
					$id,
					isset($properties['label']) ? $properties['label'] : null,
					$properties['jquery-js']
				);
			} else if ( isset($properties['callback']) ) {
				$tweak = new ameDelegatedTweak(
					$id,
					isset($properties['label']) ? $properties['label'] : null,
					$properties['callback']
				);
			} else if ( !empty($properties['isGroup']) ) {
				$tweak = new ameDelegatedTweak(
					$id,
					isset($properties['label']) ? $properties['label'] : null,
					'__return_false'
				);
			} else {
				throw new LogicException(esc_html('Unknown tweak type in default-tweaks.php for tweak "' . $id . '"'));
			}

			if ( isset($properties['parent']) ) {
				$tweak->setParentId($properties['parent']);
			}
			if ( isset($properties['section']) ) {
				$tweak->setSectionId($properties['section']);
			}
			if ( isset($properties['description']) ) {
				$tweak->setDescription($properties['description']);
			}

			if ( isset($properties['hideableLabel']) ) {
				$tweak->setHideableLabel($properties['hideableLabel']);
			}
			if ( isset($properties['hideableCategory']) ) {
				$tweak->setHideableCategoryId($properties['hideableCategory']);
			}

			$this->addTweak($tweak);
		}
	}

	private function filterTweaksToRegister($tweaksById) {
		$tweakFilter = $this->getTweakRegistrationFilter();
		if ( $tweakFilter !== null ) {
			$tweaksById = array_intersect_key($tweaksById, $tweakFilter);
		}
		return $tweaksById;
	}

	private function getTweakRegistrationFilter() {
		//We may be able to improve performance by only registering tweaks that are enabled
		//for the current user. However, we still need to show all tweaks in the "Tweaks" tab.
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		//-- This is not processing form data, it's just checking which page the user is on.
		$isTweaksTab = is_admin()
			&& isset($_GET['page'], $_GET['sub_section'])
			&& ($_GET['page'] === 'menu_editor')
			&& ($_GET['sub_section'] === $this->tabSlug);
		$isEasyHidePage = is_admin() && isset($_GET['page'])
			&& ($_GET['page'] === 'ame-easy-hide');
		//phpcs:enable

		if ( $isTweaksTab || $isEasyHidePage ) {
			$tweakFilter = null;
		} else {
			$tweakFilter = $this->getEnabledTweakSettings();
		}
		return $tweakFilter;
	}

	public function onInit() {
		$this->registerTweaks();

		$tweaksToProcess = $this->pendingTweaks;
		$this->pendingTweaks = [];
		$this->processTweaks($tweaksToProcess);
	}

	private function registerTweaks() {
		$this->registerEarlyTweaks();

		foreach ($this->defaultTweakFactories as $factoryCallback) {
			$tweakDefinitions = call_user_func($factoryCallback);
			$this->addDefaultTweaks($this->filterTweaksToRegister($tweakDefinitions));
		}

		$tweakFilter = $this->getTweakRegistrationFilter();

		do_action('admin-menu-editor-register_tweaks', $this, $tweakFilter);

		//Register user-defined tweaks.
		$settings = $this->loadSettings();
		$userDefinedTweakIds = ameUtils::get($settings, 'userDefinedTweaks', []);
		if ( !empty($userDefinedTweakIds) ) {
			$tweakSettings = isset($settings['tweaks']) ? $settings['tweaks'] : [];
			foreach ($userDefinedTweakIds as $id => $unused) {
				if ( !isset($tweakSettings[$id]['typeId']) ) {
					continue;
				}
				$properties = $tweakSettings[$id];
				if ( isset($this->tweakBuilders[$properties['typeId']]) ) {
					$tweak = call_user_func($this->tweakBuilders[$properties['typeId']], $properties);
					if ( $tweak ) {
						$this->addTweak($tweak);
					}
				}
			}
		}
	}

	/**
	 * @param ameBaseTweak $tweak
	 * @param string $applicationMode
	 */
	public function addTweak($tweak, $applicationMode = self::APPLY_TWEAK_AUTO) {
		$this->tweaks[$tweak->getId()] = $tweak;
		if ( $applicationMode === self::APPLY_TWEAK_AUTO ) {
			$this->pendingTweaks[$tweak->getId()] = $tweak;
		}
	}

	/**
	 * @param ameTweakAlias $alias
	 * @return void
	 */
	public function addAlias($alias) {
		$this->aliases[] = $alias;
	}

	/**
	 * @param ameBaseTweak[] $tweaks
	 */
	protected function processTweaks($tweaks) {
		$settings = $this->getEnabledTweakSettings();

		foreach ($tweaks as $tweak) {
			if ( empty($settings[$tweak->getId()]) ) {
				continue; //This tweak is not enabled for the current user.
			}

			if ( $tweak->hasScreenFilter() ) {
				if ( !did_action('current_screen') ) {
					$this->postponedTweaks[$tweak->getId()] = $tweak;
					continue;
				} else if ( !$tweak->isEnabledForCurrentScreen() ) {
					continue;
				}
			}

			$settingsForThisTweak = null;
			if ( $tweak->supportsUserInput() ) {
				$settingsForThisTweak = ameUtils::get($settings, [$tweak->getId()], []);
			}
			$tweak->apply($settingsForThisTweak);
		}

		if ( !empty($this->postponedTweaks) && !$this->screenHookAdded ) {
			add_action('current_screen', [$this, 'processPostponedTweaks']);
			$this->screenHookAdded = true;
		}
	}

	/**
	 * Get settings associated with tweaks that are enabled for the current user.
	 */
	protected function getEnabledTweakSettings() {
		if ( $this->cachedEnabledTweakSettings !== null ) {
			return $this->cachedEnabledTweakSettings;
		}

		$settings = ameUtils::get($this->loadSettings(), 'tweaks');
		if ( !is_array($settings) ) {
			$settings = [];
		}

		$currentUser = wp_get_current_user();
		$roles = $this->menuEditor->get_user_roles($currentUser);
		$isSuperAdmin = is_multisite() && is_super_admin($currentUser->ID);

		$results = [];
		foreach ($settings as $id => $tweakSettings) {
			$enabledForActor = ameUtils::get($tweakSettings, 'enabledForActor', []);
			if ( !$this->appliesToUser($enabledForActor, $currentUser, $roles, $isSuperAdmin) ) {
				continue;
			}

			$results[$id] = $tweakSettings;
		}

		$this->cachedEnabledTweakSettings = $results;
		return $results;
	}

	/**
	 * @param array $enabledForActor
	 * @param WP_User $user
	 * @param array $roles
	 * @param bool $isSuperAdmin
	 * @return bool
	 */
	private function appliesToUser($enabledForActor, $user, $roles, $isSuperAdmin = false) {
		//User-specific settings have priority over everything else.
		$userActor = 'user:' . $user->user_login;
		if ( isset($enabledForActor[$userActor]) ) {
			return $enabledForActor[$userActor];
		}

		//The "Super Admin" flag has priority over regular roles.
		if ( $isSuperAdmin && isset($enabledForActor['special:super_admin']) ) {
			return $enabledForActor['special:super_admin'];
		}

		//If it's enabled for any role, it's enabled for the user.
		foreach ($roles as $role) {
			if ( !empty($enabledForActor['role:' . $role]) ) {
				return true;
			}
		}

		//By default, all tweaks are disabled.
		return false;
	}

	/**
	 * @param WP_Screen $screen
	 */
	public function processPostponedTweaks($screen = null) {
		if ( empty($screen) && function_exists('get_current_screen') ) {
			$screen = get_current_screen();
		}
		$screenId = isset($screen, $screen->id) ? $screen->id : null;

		foreach ($this->postponedTweaks as $tweak) {
			if ( !$tweak->isEnabledForScreen($screenId) ) {
				continue;
			}
			$tweak->apply();
		}

		$this->postponedTweaks = [];
	}

	public function processSidebarWidgets() {
		global $wp_widget_factory;
		global $pagenow;
		if ( !isset($wp_widget_factory, $wp_widget_factory->widgets) || !is_array($wp_widget_factory->widgets) ) {
			return;
		}

		$widgetTweaks = [];
		foreach ($wp_widget_factory->widgets as $widget) {
			$tweak = new ameHideSidebarWidgetTweak($widget);
			$widgetTweaks[$tweak->getId()] = $tweak;
		}

		//Sort the tweaks in alphabetic order.
		uasort(
			$widgetTweaks,
			/**
			 * @param ameBaseTweak $a
			 * @param ameBaseTweak $b
			 * @return int
			 */
			function ($a, $b) {
				return strnatcasecmp($a->getLabel(), $b->getLabel());
			}
		);

		foreach ($widgetTweaks as $tweak) {
			$this->addTweak($tweak, self::APPLY_TWEAK_MANUALLY);
		}

		if ( is_admin() && ($pagenow === 'widgets.php') ) {
			$this->processTweaks($widgetTweaks);
		}
	}

	public function processSidebars() {
		global $wp_registered_sidebars;
		global $pagenow;
		if ( !isset($wp_registered_sidebars) || !is_array($wp_registered_sidebars) ) {
			return;
		}

		$sidebarTweaks = [];
		foreach ($wp_registered_sidebars as $sidebar) {
			$tweak = new ameHideSidebarTweak($sidebar);
			$this->addTweak($tweak, self::APPLY_TWEAK_MANUALLY);
			$sidebarTweaks[$tweak->getId()] = $tweak;
		}

		if ( is_admin() && ($pagenow === 'widgets.php') ) {
			$this->processTweaks($sidebarTweaks);
		}
	}

	public function addSection($id, $label, $priority = null) {
		$section = new ameTweakSection($id, $label);
		if ( $priority !== null ) {
			$section->setPriority($priority);
		}
		$this->sections[$section->getId()] = $section;

		return $section;
	}

	protected function getTemplateVariables($templateName) {
		$variables = parent::getTemplateVariables($templateName);
		$variables['tweaks'] = $this->tweaks;
		return $variables;
	}

	protected function getWrapClasses() {
		return array_merge(parent::getWrapClasses(), ['ame-tab-list-bottom-margin-disabled']);
	}

	public function enqueueTabScripts() {
		$codeEditorSettings = null;
		if ( function_exists('wp_enqueue_code_editor') ) {
			$codeEditorSettings = wp_enqueue_code_editor(['type' => 'text/html']);
		}

		wp_register_auto_versioned_script(
			'ame-tweak-manager',
			plugins_url('tweak-manager.js', __FILE__),
			[
				'ame-lodash',
				'ame-knockout',
				'ame-actor-selector',
				'ame-jquery-cookie',
				'ame-ko-extensions',
			]
		);
		//Enqueue in the footer.
		wp_enqueue_script('ame-tweak-manager', '', [], false, true);

		//Reselect the same actor.
		$query = $this->menuEditor->get_query_params();
		$selectedActor = null;
		if ( isset($query['selected_actor']) ) {
			$selectedActor = strval($query['selected_actor']);
		}

		$scriptData = $this->getScriptData();
		$scriptData['selectedActor'] = $selectedActor;
		$scriptData['defaultCodeEditorSettings'] = $codeEditorSettings;
		wp_localize_script('ame-tweak-manager', 'wsTweakManagerData', $scriptData);

		//Enqueue tooltip script.
		wp_enqueue_script('jquery-qtip');
	}

	protected function getScriptData() {
		$settings = $this->loadSettings();
		$tweakSettings = ameUtils::get($settings, 'tweaks', []);

		$tweakData = [];
		foreach ($this->tweaks as $id => $tweak) {
			$item = $tweak->toArray();
			$item = array_merge(ameUtils::get($tweakSettings, $id, []), $item);
			$tweakData[] = $item;
		}

		$sectionData = [];
		foreach ($this->sections as $section) {
			$sectionData[] = $section->toArray();
		}

		$aliasData = [];
		foreach ($this->aliases as $alias) {
			$aliasData[] = $alias->toArray();
		}

		return [
			'tweaks'              => $tweakData,
			'sections'            => $sectionData,
			'aliases'             => $aliasData,
			'isProVersion'        => $this->menuEditor->is_pro_version(),
			'lastUserTweakSuffix' => ameUtils::get($settings, 'lastUserTweakSuffix', 0),
		];
	}

	public function enqueueTabStyles() {
		parent::enqueueTabStyles();
		wp_enqueue_auto_versioned_style(
			'ame-tweak-manager-css',
			plugins_url('tweaks.css', __FILE__)
		);
	}

	public function handleSettingsForm($post = []) {
		parent::handleSettingsForm($post);

		$submittedSettings = json_decode($post['settings'], true);

		//To save space, filter out tweaks that are not enabled for anyone and have no other settings.
		//Most tweaks only have "id" and "enabledForActor" properties.
		$submittedSettings['tweaks'] = array_filter(
			$submittedSettings['tweaks'],
			function ($settings) {
				if ( !empty($settings['enabledForActor']) ) {
					return true;
				}
				$additionalProperties = array_diff_key($settings, $this::BASIC_TWEAK_PROPERTIES);
				return !empty($additionalProperties);
			}
		);

		//User-defined tweaks must have a type.
		$submittedSettings['tweaks'] = array_filter(
			$submittedSettings['tweaks'],
			function ($settings) {
				return empty($settings['isUserDefined']) || !empty($settings['typeId']);
			}
		);

		//TODO: Give other components an opportunity to validate and sanitize tweak settings. E.g. a filter.
		//Sanitize CSS with FILTER_SANITIZE_FULL_SPECIAL_CHARS if unfiltered_html is not enabled. Always strip </style>.

		//Build a lookup array of user-defined tweaks so that we can register them later
		//without iterating through the entire list.
		$userDefinedTweakIds = [];
		foreach ($submittedSettings['tweaks'] as $properties) {
			if ( !empty($properties['isUserDefined']) && !empty($properties['id']) ) {
				$userDefinedTweakIds[$properties['id']] = true;
			}
		}

		//We use an incrementing suffix to ensure each user-defined tweak gets a unique ID.
		$lastUserTweakSuffix = ameUtils::get($this->loadSettings(), 'lastUserTweakSuffix', 0);
		$newSuffix = ameUtils::get($submittedSettings, 'lastUserTweakSuffix', 0);
		if ( is_scalar($newSuffix) && is_numeric($newSuffix) ) {
			$newSuffix = max(intval($newSuffix), 0);
			if ( $newSuffix < 10000000 ) {
				$lastUserTweakSuffix = $newSuffix;
			}
		}

		$this->settings['tweaks'] = $submittedSettings['tweaks'];
		$this->settings['userDefinedTweaks'] = $userDefinedTweakIds;
		$this->settings['lastUserTweakSuffix'] = $lastUserTweakSuffix;
		$this->saveSettings();

		$params = ['updated' => 1];
		if ( !empty($post['selected_actor']) ) {
			$params['selected_actor'] = strval($post['selected_actor']);
		}

		wp_redirect($this->getTabUrl($params));
		exit;
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\EasyHide\HideableItemStore $store
	 */
	public function registerHideableItems($store) {
		$settings = ameUtils::get($this->loadSettings(), 'tweaks');

		$enabledSections = [
			ameGutenbergBlockManager::SECTION_ID => 'Gutenberg Blocks',
			ameTinyMceButtonManager::SECTION_ID  => 'TinyMCE Buttons',
			'profile'                            => null,
			'sidebar-widgets'                    => null,
			'sidebars'                           => null,
			'gutenberg-general'                  => 'Gutenberg Block Editor',
		];
		$enabledSections = apply_filters('admin_menu_editor-hideable_tweak_sections', $enabledSections);

		$postEditorCategory = $store->getOrCreateCategory('post-editor', 'Editor', null, false, 0, 0);
		$parentCategories = [
			ameGutenbergBlockManager::SECTION_ID => $postEditorCategory,
			ameTinyMceButtonManager::SECTION_ID  => $postEditorCategory,
			'gutenberg-general'                  => $postEditorCategory,
		];

		$categoriesBySection = [];
		foreach ($enabledSections as $sectionId => $customLabel) {
			if ( !isset($this->sections[$sectionId]) ) {
				continue;
			}
			$section = $this->sections[$sectionId];

			$parent = null;
			if ( isset($parentCategories[$sectionId]) ) {
				$parent = $parentCategories[$sectionId];
			}

			$category = $store->getOrCreateCategory(
				'tw/' . $sectionId,
				!empty($customLabel) ? $customLabel : str_replace('Hide ', '', $section->getLabel()),
				$parent,
				false,
				0,
				0
			);

			$description = $section->getDescription();
			if ( !empty($description) ) {
				$category->setTooltip($description);
			}

			$categoriesBySection[$sectionId] = $category;
		}

		$generalCat = $store->getOrCreateCategory('admin-ui', 'General', null, true);
		$generalCat->setSortPriority(1);

		foreach ($this->tweaks as $tweak) {
			$sectionCategoryExists = isset($categoriesBySection[$tweak->getSectionId()]);

			$isHideable = ($sectionCategoryExists || $tweak->isIndependentlyHideable());
			if ( !$isHideable ) {
				continue;
			}

			$tweakParent = $tweak->getParentId();
			if ( !empty($tweakParent) ) {
				$parent = $store->getItemById(self::getHideableIdForTweak($tweakParent));
			} else {
				$parent = null;
			}

			$enabled = ameUtils::get($settings, [$tweak->getId(), 'enabledForActor'], []);
			$inverted = null;

			$categories = [];
			if ( $sectionCategoryExists ) {
				$categories[] = $categoriesBySection[$tweak->getSectionId()];
			}
			$customCategoryId = $tweak->getHideableCategoryId();
			if ( $customCategoryId ) {
				$customCategory = $store->getCategory($customCategoryId);
				if ( $customCategory ) {
					$categories[] = $customCategory;
					//Tweak state should not be inverted, so if the category does that,
					//we'll need to override that setting.
					if ( $customCategory->isInvertingItemState() ) {
						$inverted = false;
					}
				}
			}

			$store->addItem(
				self::getHideableIdForTweak($tweak->getId()),
				$tweak->getHideableLabel(),
				$categories,
				$parent,
				$enabled,
				self::HIDEABLE_ITEM_COMPONENT,
				null,
				$inverted
			);
		}
	}

	private static function getHideableIdForTweak($tweakId) {
		return self::HIDEABLE_ITEM_PREFIX . $tweakId;
	}

	public function saveHideableItems($errors, $items) {
		$tweakSettings = ameUtils::get($this->loadSettings(), 'tweaks', []);
		$prefixLength = strlen(self::HIDEABLE_ITEM_PREFIX);
		$anyTweaksModified = false;

		foreach ($items as $id => $item) {
			$tweakId = substr($id, $prefixLength);

			$enabled = isset($item['enabled']) ? $item['enabled'] : [];
			$oldEnabled = ameUtils::get($tweakSettings, [$tweakId, 'enabledForActor'], []);

			if ( !ameUtils::areAssocArraysEqual($enabled, $oldEnabled) ) {
				if ( !empty($enabled) ) {
					if ( !isset($tweakSettings[$tweakId]) ) {
						$tweakSettings[$tweakId] = [];
					}
					$tweakSettings[$tweakId]['enabledForActor'] = $enabled;
				} else {
					//To save space, we can simply remove the array if it's empty.
					if ( isset($tweakSettings[$tweakId]['enabledForActor']) ) {
						unset($tweakSettings[$tweakId]['enabledForActor']);
					}
				}
				$anyTweaksModified = true;
			}
		}

		if ( $anyTweaksModified ) {
			$this->settings['tweaks'] = $tweakSettings;
			$this->saveSettings();
		}

		return $errors;
	}
}

class ameTweakSection {
	private $id;
	private $label;

	private $priority = 0;

	private $description;

	public function __construct($id, $label, $description = '') {
		$this->id = $id;
		$this->label = $label;
		$this->description = $description;
	}

	public function getId() {
		return $this->id;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getPriority() {
		return $this->priority;
	}

	public function setPriority($priority) {
		$this->priority = $priority;
		return $this;
	}

	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}

	public function getDescription() {
		return $this->description;
	}

	public function toArray() {
		$sectionData = [
			'id'       => $this->getId(),
			'label'    => $this->getLabel(),
			'priority' => $this->getPriority(),
		];

		if ( !empty($this->description) ) {
			$sectionData['description'] = $this->getDescription();
		}

		return $sectionData;
	}
}
