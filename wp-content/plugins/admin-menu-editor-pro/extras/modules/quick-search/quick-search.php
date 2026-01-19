<?php

namespace YahnisElsts\AdminMenuEditor\QuickSearch;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaFactory;
use YahnisElsts\AdminMenuEditor\Customizable\SettingCondition;
use YahnisElsts\AdminMenuEditor\Customizable\SettingsForm;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;
use YahnisElsts\AjaxActionWrapper\v2\Action;

require_once __DIR__ . '/../../../includes/reflection-callable.php';

class SearchModule extends \amePersistentModule implements \ameExportableModule {
	const DEFAULT_SHORTCUT = 'shift shift';

	const RECENCY_TRACKING_COOKIE = 'ame-qs-recency-tracking';
	const USED_ITEM_COOKIE = 'ame-qs-used-db-items';
	const NAVIGATION_SELECTOR_PARAM = 'ame-qs-target-selector';

	const DB_CLEANUP_CRON_HOOK = 'ame_qs_database_cleanup';
	const STALENESS_THRESHOLD_IN_DAYS = 56;

	const ENGINE_DASHBOARD = 'dashboard';
	const ENGINE_POSTS = 'postType';
	const ENGINE_USERS = 'user';

	protected $optionName = 'ws_ame_quick_search';
	protected $tabOrder = 25;

	protected $defaultSettings = [
		'keyboardShortcut'  => 'shift shift',
		'recencyTracking'   => 'enableOnFirstUse',
		'crawlerEnabled'    => 'ask',
		'crawlerTabVisible' => false,
		'toolbarButton'     => true,
		'toolbarButtonType' => 'iconAndText',
	];

	/**
	 * @var null|SettingsForm
	 */
	protected $settingsForm = null;
	protected $settingsFormAction = 'ame_save_quick_search_settings';

	protected $tabSlug = 'quick-search-settings';
	protected $tabTitle = 'Quick Search';

	/**
	 * @var DbAdapter|null
	 */
	private $dbAdapter = null;
	/**
	 * @var AjaxApi|null
	 */
	private $ajaxApi = null;

	public function __construct($menuEditor) {
		$this->settingsWrapperEnabled = true;
		parent::__construct($menuEditor);

		add_action('admin_enqueue_scripts', [$this, 'enqueueGlobalAdminDependencies'], 30);

		if ( is_admin() ) {
			add_action('admin_bar_menu', [$this, 'addToolbarSearchButton']);

			$this->ajaxApi = new AjaxApi($this, [$this, 'maybeScheduleCleanupEvent']);
			$this->ajaxApi->registerAjaxActions();
		}

		add_action('wp_loaded', function () {
			$this->storeDataFromCookies();
		});

		add_action(self::DB_CLEANUP_CRON_HOOK, [$this, 'cleanupDatabase']);
	}

	public function enqueueGlobalAdminDependencies($hookSuffix = '') {
		if ( $this->isSearchDisabledForRequest() ) {
			return;
		}

		if ( !$this->userCanSearch() ) {
			return;
		}

		$baseDeps = $this->menuEditor->get_base_dependencies();

		//Mousetrap library for keyboard shortcuts.
		$hotkeyLibrary = $this->registerLocalScript('ame-mousetrap', 'mousetrap.min.js', [], true);
		//Preserve the original "Mousetrap" global variable in case another plugin uses
		//a different version of this library.
		$hotkeyLibrary->addInlineScript(
			'var wsAmeOriginalMousetrap = window.Mousetrap;',
			'before'
		);
		$hotkeyLibrary->addInlineScript(
			'var wsAmeMousetrap = Mousetrap;
			 if (typeof wsAmeOriginalMousetrap !== "undefined") { window.Mousetrap = wsAmeOriginalMousetrap; }',
			'after'
		);

		//The main script only works with Webpack because it uses NPM packages.
		try {
			$mainScript = $this->menuEditor->get_webpack_registry()->getWebpackEntryPoint('quick-search');
		} catch (\Exception $e) {
			//Bail if the script is not available.
			return;
		}

		$this->storeDataFromCookies();
		$settings = $this->loadSettings();

		$removableQueryArgs = wp_removable_query_args();
		$removableQueryArgs[] = 'return'; //For Theme Customizer links.

		$crawlerEnabledCode = \ameUtils::get($settings, 'crawlerEnabled', 'ask');
		$detectComponents = $crawlerEnabledCode !== 'disabled';
		$menuUrlsToComponents = $this->getNormalizedAdminMenuUrls($removableQueryArgs, $detectComponents);
		$menuUrls = array_keys($menuUrlsToComponents);

		//If navigation to a selector has been requested, verify the nonce and pass the selector
		//to the client-side code.
		$selector = null;
		if ( isset($_GET[self::NAVIGATION_SELECTOR_PARAM]) ) {
			//phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce gets verified below.
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Custom JSON data, cannot sanitize with WP functions.
			$encodedTarget = stripslashes((string)$_GET[self::NAVIGATION_SELECTOR_PARAM]);
			$parsedTarget = json_decode($encodedTarget, true);
			if (
				isset($parsedTarget['selector'], $parsedTarget['nonce'])
				&& is_string($parsedTarget['nonce'])
			) {
				if ( wp_verify_nonce($parsedTarget['nonce'], self::NAVIGATION_SELECTOR_PARAM) ) {
					$selector = $parsedTarget['selector'];
				}
			}
			//phpcs:enable
		}

		$keyboardShortcut = \ameUtils::get($settings, 'keyboardShortcut', self::DEFAULT_SHORTCUT);
		if ( $keyboardShortcut === '_custom' ) {
			$keyboardShortcut = \ameUtils::get($settings, 'customShortcut', self::DEFAULT_SHORTCUT);
		}
		if ( empty($keyboardShortcut) || !is_string($keyboardShortcut) ) {
			$keyboardShortcut = self::DEFAULT_SHORTCUT;
		}

		//Get the icons for post types. Currently, we only support Dashicons.
		$contentTypeIcons = [];
		$postTypeIcons = [];
		$postTypes = get_post_types(['show_ui' => true, 'public' => true], 'objects');
		foreach ($postTypes as $postType) {
			if (
				!empty($postType->menu_icon)
				&& is_string($postType->menu_icon)
				&& (strpos($postType->menu_icon, 'dashicons-') !== false)
			) {
				$postTypeIcons[$postType->name] = 'dashicons ' . $postType->menu_icon;
			}
		}
		if ( !empty($postTypeIcons) ) {
			$contentTypeIcons['postType'] = (object)$postTypeIcons;
		}
		$contentTypeIcons = (object)$contentTypeIcons;

		$mainScript
			->addDependencies(
				$hotkeyLibrary,
				$baseDeps['ame-knockout'], $baseDeps['ame-ko-extensions'],
				$baseDeps['ame-mini-functional-lib'],
				'jquery', 'jquery-ui-position', 'jquery-ui-resizable',
				$this->ajaxApi->searchAction->getRegisteredScriptHandle()
			)
			->setInFooter()
			->addJsVariable('wsAmeQuickSearchData', [
				'jsLogLevel'       => defined('WP_DEBUG') && WP_DEBUG ? 'debug' : 'warn',
				'keyboardShortcut' => $keyboardShortcut,

				'ajaxUrl'                => admin_url('admin-ajax.php'),
				'searchNonce'            => wp_create_nonce(AjaxApi::AJAX_RUN_SEARCH),
				'indexUpdateNonce'       => wp_create_nonce(AjaxApi::AJAX_UPDATE_INDEX),
				'setCrawlerEnabledNonce' => wp_create_nonce(AjaxApi::AJAX_SET_CRAWLER_ENABLED),
				'ajaxActions'            => Action::serializeActionMap([
					'search'            => $this->ajaxApi->searchAction,
					'updateIndex'       => $this->ajaxApi->updateIndexAction,
					'setCrawlerEnabled' => $this->ajaxApi->setCrawlerEnabledAction,
					'getCrawlRecords'   => $this->ajaxApi->getCrawlRecordsAction,
					'setCrawlRecords'   => $this->ajaxApi->setCrawlRecordsAction,
				]),

				'adminUrl'        => self_admin_url(),
				'siteCookiePath'  => SITECOOKIEPATH,
				'adminCookiePath' => ADMIN_COOKIE_PATH,
				'currentUserId'   => get_current_user_id(),

				'navigationNonce'          => wp_create_nonce(self::NAVIGATION_SELECTOR_PARAM),
				'navigationTargetSelector' => $selector,

				'removableQueryArgs' => $removableQueryArgs,
				'preloadedItems'     => $this->preloadSearchableItems($menuUrls),
				'contentTypeIcons'   => $contentTypeIcons,

				'recencyTracking' => $settings['recencyTracking'],

				'crawlerConfig' => [
					'enabled'                             => $crawlerEnabledCode,
					'ajaxNonces'                          => [
						AjaxApi::AJAX_GET_CRAWL_RECORDS => wp_create_nonce(AjaxApi::AJAX_GET_CRAWL_RECORDS),
						AjaxApi::AJAX_SET_CRAWL_RECORDS => wp_create_nonce(AjaxApi::AJAX_SET_CRAWL_RECORDS),
					],
					'preloadedRecords'                    => $this->getDbAdapter()->fetchCrawlRecords($menuUrls),
					'menuComponents'                      => $detectComponents ? $menuUrlsToComponents : (new \stdClass()),
					'unknownComponentCrawlIntervalInDays' => 14,
					'knownComponentCrawlIntervalInDays'   => 28,
					'minCrawlIntervalInHours'             => 24,
					'crawlerTabVisible'                   => \ameUtils::get($settings, 'crawlerTabVisible', false),
				],
			])
			->enqueue();

		$this->enqueueLocalStyle('ame-quick-search', 'quick-search-styles.css');

		//Add the Knockout templates to the admin footer.
		add_action('admin_footer', [$this, 'printKnockoutTemplates']);

		if ( $hookSuffix ) {
			//Provide stats like memory usage and page generation time to the client-side code.
			add_action('admin_footer-' . $hookSuffix, [$this, 'printPageStatsForJs']);

			//We're using the "admin_footer-{hook_suffix}" hook because it's nearly the last action
			//that fires during an admin page load. Technically, there's also "shutdown", but that
			//happens after the closing </html> tag, so we probably shouldn't output anything there.
		}
	}

	/**
	 * Generate a list of items that should be preloaded into the client-side search index
	 * for the current admin page.
	 *
	 * These items will be available for searching as soon as the user opens the quick search box,
	 * without any additional AJAX requests. However, preloading too many items could slow down
	 * the page load.
	 *
	 * @param string[] $menuUrls
	 * @return array
	 */
	private function preloadSearchableItems(array $menuUrls): array {
		$menuUrlLookup = array_flip($menuUrls);
		$engines = $this->createSearchEngines($menuUrlLookup);
		$usedItemStore = $this->getUsedItemStore();

		$desiredRecentItems = 30;
		$storedRefs = $usedItemStore->getRecentItemRefs(
			get_current_user_id(),
			array_keys($engines),
			$desiredRecentItems
		);

		//Group by engine.
		$itemRefsByEngine = [];
		foreach ($storedRefs as $parsedRef) {
			if ( !empty($parsedRef['engine']) ) {
				$engine = $parsedRef['engine'];
				if ( !isset($itemRefsByEngine[$engine]) ) {
					$itemRefsByEngine[$engine] = [];
				}
				$itemRefsByEngine[$engine][] = $parsedRef['item'];
			}
		}

		$recentItems = [];
		foreach ($engines as $engineKey => $engine) {
			$itemRefs = !empty($itemRefsByEngine[$engineKey]) ? $itemRefsByEngine[$engineKey] : [];
			$recentEngineItems = $engine->getRecentItems($itemRefs);
			$recentItems = array_merge($recentItems, $recentEngineItems);
		}

		$shortcuts = $this->generatePredefinedMenuShortcuts();

		return array_merge($recentItems, $shortcuts);
	}

	/**
	 * Get the normalized, relative URLs of the admin menu items that are currently present
	 * in the admin menu.
	 *
	 * This method only returns URLs that lead to local admin pages. It excludes external URLs
	 * and URLs that lead to the front end. Absolute URLs are converted to relative URLs,
	 * and known temporary query parameters like "updated" are removed.
	 *
	 * Optionally, the method can also detect the component (e.g. a plugin or theme) that renders
	 * the content of the admin page. The component ID includes the version number.
	 *
	 * @param string[] $removableQueryArgs
	 * @param bool $withComponents
	 * @return array<string, string|null> [relative URL => component]
	 */
	private function getNormalizedAdminMenuUrls($removableQueryArgs, $withComponents = false) {
		//todo: Maybe refactor this and related methods into a separate class. AdminMenuDataCollector/Detector or something.
		static $cachedUrls = null;
		if ( $cachedUrls !== null ) {
			return $cachedUrls;
		}

		$adminUrlParsed = wp_parse_url(self_admin_url());
		if ( empty($adminUrlParsed) || empty($adminUrlParsed['path']) ) {
			return [];
		}

		if ( did_action('admin_menu') || did_action('network_admin_menu') ) {
			$tree = $this->menuEditor->get_active_admin_menu_tree();
		} else {
			$tree = [];
		}
		$outputs = [];

		\ameMenu::for_each($tree, function ($menu) use ($adminUrlParsed, $removableQueryArgs, $withComponents, &$outputs) {
			$url = \ameMenuItem::get($menu, 'url');
			if ( empty($url) ) {
				return;
			}

			$parsingResult = $this->getRelativeAdminPageUrl($url, $adminUrlParsed, $removableQueryArgs);
			if ( $parsingResult ) {
				list($relativeUrl, $parsedUrl) = $parsingResult;

				if ( $withComponents ) {
					$outputs[$relativeUrl] = $this->detectComponentThatRendersMenuContent($menu, $parsedUrl);
				} else {
					$outputs[$relativeUrl] = null;
				}
			}
		});

		return $outputs;
	}

	/**
	 * @param string $inputUrl
	 * @param array $parsedAdminUrl
	 * @param string[] $removableQueryArgs
	 * @return array{0: string, 1:array}|null
	 */
	private function getRelativeAdminPageUrl($inputUrl, $parsedAdminUrl, $removableQueryArgs) {
		$parsedInputUrl = wp_parse_url($inputUrl);
		if ( empty($parsedInputUrl) ) {
			return null;
		}

		//Is the input an absolute-ish URL?
		if (
			!empty($parsedInputUrl['scheme'])
			|| !empty($parsedInputUrl['host'])
			|| !empty($parsedInputUrl['port'])
		) {
			//Scheme, host, and port must match the admin URL.
			if (
				(\ameUtils::get($parsedInputUrl, 'scheme') !== \ameUtils::get($parsedAdminUrl, 'scheme'))
				|| (\ameUtils::get($parsedInputUrl, 'host') !== \ameUtils::get($parsedAdminUrl, 'host'))
				|| (\ameUtils::get($parsedInputUrl, 'port') !== \ameUtils::get($parsedAdminUrl, 'port'))
			) {
				return null;
			}

			//Remove the scheme, host, and port. This effectively converts the URL to a relative
			//URL (relative to the root of the site).
			unset($parsedInputUrl['scheme'], $parsedInputUrl['host'], $parsedInputUrl['port']);
		}

		if ( !empty($parsedInputUrl['path']) ) {
			//If the path starts at the root, it must start with the admin URL path.
			if ( substr($parsedInputUrl['path'], 0, 1) === '/' ) {
				$adminPath = $parsedAdminUrl['path'];
				if ( strpos($parsedInputUrl['path'], $adminPath) !== 0 ) {
					return null;
				}
				//Remove the admin path from the URL.
				$parsedInputUrl['path'] = substr($parsedInputUrl['path'], strlen($adminPath));
			}
		}

		//Remove known temporary query parameters.
		if ( !empty($parsedInputUrl['query']) ) {
			$query = wp_parse_args($parsedInputUrl['query']);
			$query = array_diff_key($query, array_flip($removableQueryArgs));
			if ( empty($query) ) {
				unset($parsedInputUrl['query']);
			} else {
				$parsedInputUrl['query'] = http_build_query($query);
			}
		}

		//Rebuild the URL.
		$relativeUrl = '';
		if ( !empty($parsedInputUrl['path']) ) {
			$relativeUrl .= $parsedInputUrl['path'];
		}
		if ( !empty($parsedInputUrl['query']) ) {
			$relativeUrl .= '?' . $parsedInputUrl['query'];
		}
		//Note that we don't include the fragment.

		return [$relativeUrl, $parsedInputUrl];
	}

	private function detectComponentThatRendersMenuContent($menuItem, $parsedUrl) {
		static $wordPressVersion = null;
		if ( $wordPressVersion === null ) {
			$wordPressVersion = get_bloginfo('version');
		}

		$isWpAdminFile = !empty($parsedUrl['path']) && \ameMenuItem::is_wp_admin_file($parsedUrl['path']);

		//If the URL points to a PHP file in wp-admin and there's no query string, it's normally
		//a built-in WordPress admin page.
		if ( $isWpAdminFile && empty($parsedUrl['query']) ) {
			return 'wordpress:' . $wordPressVersion;
		}

		if ( !\ameUtils::get($menuItem, 'is_plugin_page') ) {
			//We can detect which plugin or theme created an admin page by getting the callback
			//that renders the page and looking at the file path.

			$defaults = \ameUtils::get($menuItem, 'defaults');
			$pageHook = get_plugin_page_hook(
				(string)\ameUtils::get($defaults, 'file', ''),
				(string)\ameUtils::get($defaults, 'parent', '')
			);

			if ( !empty($pageHook) ) {
				$reflections = \ameReflectionCallable::getHookReflections($pageHook);

				//Only look at the first hook callback. Technically, there can be multiple callbacks
				//for any hook, but a menu item will normally have only one. If there are multiple,
				//we cannot determine which one actually renders the page.
				if ( !empty($reflections[0]) ) {
					$path = $reflections[0]->getFileName();
					$component = \ameUtils::getComponentFromPath($path);

					if ( $component ) {
						$version = $this->getComponentVersion($component['type'], $component['path']);
						if ( $version ) {
							return $component['type'] . ':' . $component['path'] . ':' . $version;
						}
					}
				}
			}
		}

		//Technically, we may also be able to detect a component from custom post types and taxonomies,
		//but that's more complicated. We don't do that right now.

		return null;
	}

	private function getComponentVersion($type, $directoryOrFile) {
		static $cachedPluginVersions = null;
		static $cachedThemeVersions = [];

		switch ($type) {
			case 'wordpress':
				return get_bloginfo('version');
			case 'plugin':
				if ( $cachedPluginVersions === null ) {
					$plugins = get_plugins();
					//The $plugins array is indexed by the relative path to the main plugin file,
					//not by the directory name. We only get the directory, so we need to do search
					//the whole array. For performance, we cache the results.
					$cachedPluginVersions = [];
					foreach ($plugins as $path => $plugin) {
						$version = \ameUtils::get($plugin, 'Version');
						if ( $version ) {
							//Note: In rare cases, a plugin might be a single file in the root directory.
							//In that case, we use the file name as the component path.
							$parts = explode('/', $path);
							$path = $parts[0];
							$cachedPluginVersions[$path] = $version;
						}
					}
				}
				return \ameUtils::get($cachedPluginVersions, $directoryOrFile);
			case 'theme':
				if ( !array_key_exists($directoryOrFile, $cachedThemeVersions) ) {
					$theme = wp_get_theme($directoryOrFile);
					if ( $theme->exists() ) {
						$cachedThemeVersions[$directoryOrFile] = $theme->get('Version');
					} else {
						$cachedThemeVersions[$directoryOrFile] = null;
					}
				}
				return $cachedThemeVersions[$directoryOrFile];
		}

		return null;
	}

	private function generatePredefinedMenuShortcuts() {
		$makePluginsShortcut = function ($item) {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			$filters = [
				'Active Plugins'          => 'active',
				'Inactive Plugins'        => 'inactive',
				'Recently Active Plugins' => 'recently_activated',
				': Must-Use Plugins'      => 'mustuse',
				': Drop-ins'              => 'dropins',
				': Update Available'      => 'upgrade',

				': Auto-updates Enabled'  => 'auto-update-enabled',
				': Auto-updates Disabled' => 'auto-update-disabled',
			];

			$menuTitle = \ameMenuTemplateBuilder::sanitizeMenuTitle(
				\ameMenuItem::get($item, 'menu_title', 'Plugins')
			);

			foreach ($filters as $label => $filter) {
				$params = ['plugin_status' => $filter];
				$relativeUrl = add_query_arg($params, 'plugins.php');

				if ( substr($label, 0, 1) === ':' ) {
					$label = $menuTitle . $label;
				}

				yield new DashboardItemDefinition(
					$label,
					new DashboardItemOrigin('plugins.php'),
					new DashboardItemTarget('filter', $relativeUrl),
					'f:url=' . $relativeUrl,
					[]
				);
			}
		};

		$generators = [
			'plugins.php>plugins.php' => $makePluginsShortcut,
			'>plugins.php'            => $makePluginsShortcut,
		];

		$shortcuts = [];

		if ( did_action('admin_menu') || did_action('network_admin_menu') ) {
			$tree = $this->menuEditor->get_active_admin_menu_tree();
		} else {
			$tree = [];
		}
		\ameMenu::for_each($tree, function ($menu) use ($generators, &$shortcuts) {
			$id = \ameMenuItem::get($menu, 'template_id');
			if ( isset($generators[$id]) ) {
				foreach ($generators[$id]($menu) as $link) {
					$shortcuts[] = $link;
				}
			}
		});

		return $shortcuts;
	}

	private function storeDataFromCookies() {
		if ( !$this->userCanUpdateIndex() ) {
			return;
		}

		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		//Update recency tracking settings based on the cookie. This setting is modified by the UI
		//and stored in a cookie, then we persist it in the database during the next request.
		if ( isset($_COOKIE[self::RECENCY_TRACKING_COOKIE]) ) {
			$settings = $this->loadSettings();
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a list of known values below.
			$recencyTracking = (string)($_COOKIE[self::RECENCY_TRACKING_COOKIE]);
			$validValues = ['enableOnFirstUse', 'disabled', 'enabled'];
			if (
				in_array($recencyTracking, $validValues, true)
				&& ($recencyTracking !== $settings['recencyTracking'])
			) {
				$this->settings['recencyTracking'] = $recencyTracking;
				$this->saveSettings();
			}
			//Remove the cookie.
			setcookie(self::RECENCY_TRACKING_COOKIE, '', time() - 24 * 3600, ADMIN_COOKIE_PATH, COOKIE_DOMAIN);
		}

		//Store recently used items so they can be preloaded next time.
		if ( isset($_COOKIE[self::USED_ITEM_COOKIE]) ) {
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated & sanitized below.
			$usageData = json_decode(stripslashes($_COOKIE[self::USED_ITEM_COOKIE]), true);
			if (
				is_array($usageData)
				&& isset($usageData['_v'])
				&& ($usageData['_v'] === 2)
				&& !empty($usageData['items'])
				&& is_array($usageData['items'])
			) {
				$now = time();
				$minTimestamp = $now - 30 * 24 * 3600;
				$maxTimestamp = $now + 3600;

				$usageStore = $this->getUsedItemStore();
				$userId = get_current_user_id();
				$enabledEngines = array_flip($this->getEnabledSearchEngines());
				$dashboardUpdates = [];

				foreach ($usageData['items'] as $serializedRef => $timestamp) {
					//The ref should be JSON-encoded. It should have a valid "engine" property
					//and a non-empty "item" property.
					$parsedRef = json_decode($serializedRef, true);
					if (
						!is_array($parsedRef)
						|| empty($parsedRef['engine'])
						|| !is_string($parsedRef['engine'])
						|| !isset($enabledEngines[$parsedRef['engine']])
						|| empty($parsedRef['item'])
					) {
						continue;
					}

					//Value should be a reasonable Unix timestamp.
					if ( !is_int($timestamp) || ($timestamp < $minTimestamp) || ($timestamp > $maxTimestamp) ) {
						continue;
					}

					$usageStore->updateItemTimestamp($userId, $serializedRef, $timestamp);

					if ( $parsedRef['engine'] === 'dashboard' ) {
						if ( !empty($parsedRef['item']['url']) && !empty($parsedRef['item']['id']) ) {
							$dashboardUpdates[] = [
								'menuUrl'    => (string)$parsedRef['item']['url'],
								'relativeId' => (string)$parsedRef['item']['id'],
								'timestamp'  => $timestamp,
							];
						}
					}
				}

				$usageStore->save();
				$this->getDbAdapter()->updateRecentlyUsedDashboardItems($dashboardUpdates);
			}

			//Remove the cookie.
			setcookie(self::USED_ITEM_COOKIE, '', time() - 24 * 3600, ADMIN_COOKIE_PATH, COOKIE_DOMAIN);
		}
	}

	public function printKnockoutTemplates() {
		$settingsPageUrl = $this->getTabUrl();
		require __DIR__ . '/ko-templates.php';
	}

	public function getDbAdapter() {
		if ( $this->dbAdapter === null ) {
			require_once __DIR__ . '/database.php';
			$this->dbAdapter = new DbAdapter();
		}
		return $this->dbAdapter;
	}

	/**
	 * @return ItemSearchEngine[]
	 */
	public function createSearchEngines(array $menuUrlLookup = []): array {
		$dbAdapter = $this->getDbAdapter();
		require_once __DIR__ . '/entities.php';

		$enabledEngines = array_flip($this->getEnabledSearchEngines());
		$engines = [];

		if ( isset($enabledEngines[self::ENGINE_DASHBOARD]) ) {
			$engines[self::ENGINE_DASHBOARD] = new DashboardSearchEngine($dbAdapter, $menuUrlLookup);
		}
		if ( isset($enabledEngines[self::ENGINE_POSTS]) ) {
			$settings = $this->loadSettings();
			$engines[self::ENGINE_POSTS] = new PostSearchEngine(
				\ameUtils::get($settings, ['postTypeEnabled'], [])
			);
		}
		if ( isset($enabledEngines[self::ENGINE_USERS]) ) {
			$engines[self::ENGINE_USERS] = new UserSearchEngine();
		}

		return $engines;
	}

	private function getEnabledSearchEngines(): array {
		$settings = $this->loadSettings();
		$supportedEngines = [self::ENGINE_DASHBOARD, self::ENGINE_POSTS, self::ENGINE_USERS];
		$enabledEngines = [];
		foreach ($supportedEngines as $engine) {
			if ( \ameUtils::get($settings, ['searchScope', $engine], true) ) {
				$enabledEngines[] = $engine;
			}
		}
		return $enabledEngines;
	}

	/**
	 * @var RecentlyUsedItemStore|null
	 */
	private $usedItemStore = null;

	private function getUsedItemStore(): RecentlyUsedItemStore {
		if ( $this->usedItemStore === null ) {
			$this->usedItemStore = new RecentlyUsedItemStore();
		}
		return $this->usedItemStore;
	}

	public function printPageStatsForJs() {
		$pageGenerationTime = timer_stop(0);
		if ( isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT']) ) {
			$pageGenerationTime = microtime(true) - floatval($_SERVER['REQUEST_TIME_FLOAT']);
		}

		$stats = [
			'phpPeakMemoryUsage' => memory_get_peak_usage(),
			'phpMemoryLimit'     => ini_get('memory_limit'),
			'pageGenerationTime' => $pageGenerationTime,
		];

		?>
		<script>
			window.wsAmeQuickSearchPageStats = window.wsAmeQuickSearchPageStats || [];
			<?php foreach ($stats as $key => $value): ?>
			window.wsAmeQuickSearchPageStats.push(<?php echo wp_json_encode([$key, $value]); ?>);
			<?php endforeach; ?>
		</script>
		<?php
	}

	public function createSettingInstances(ModuleSettings $settings) {
		$f = $settings->settingFactory();
		$s = new SchemaFactory();

		return $f->buildSettings([
			'keyboardShortcut'  => $s->enum(
				['shift shift', 'ctrl+k', '/', '_custom'],
				'Keyboard shortcut'
			)
				->describeValue('shift shift', 'Press <kbd>Shift</kbd> twice')
				->describeValue('ctrl+k', '<kbd>Ctrl+K</kbd>')
				->describeValue('/', '<kbd>/</kbd>')
				->describeValue('_custom', 'Custom'),
			'customShortcut'    => $s->string('Custom keyboard shortcut')->max(50),
			'recencyTracking'   => $s->enum(
				['enableOnFirstUse', 'enabled', 'disabled',],
				'Remember recently used items'
			)
				->describeValue('enableOnFirstUse', 'Automatically enable on first search'),
			'crawlerEnabled'    => $s->enum(
				['ask', 'enabled', 'disabled',],
				'Automatic indexing'
			)
				->describeValue('ask', 'Ask in the search screen'),
			'crawlerTabVisible' => $s->boolean(
				'Show the "Crawler" tab in the search panel (for debugging)'
			)->settingParams(['groupTitle' => '"Crawler" tab']),

			'toolbarButton'     => $s->boolean(
				'Add a search button to the Toolbar in the admin dashboard'
			)->settingParams(['groupTitle' => 'Toolbar button']),
			'toolbarButtonType' => $s->enum(
				['iconAndText', 'iconOnly'],
				'Button style'
			)
				->describeValue('iconAndText', 'Icon and text')
				->describeValue('iconOnly', 'Just an icon'),

			'searchScope' => $s->struct([
				'adminMenu'            => $s->boolean('Admin menu (always enabled)')->defaultValue(true)
					->settingParams(['isEditable' => '__return_false']),
				self::ENGINE_DASHBOARD => $s->boolean('Indexed dashboard content')->defaultValue(true),
				self::ENGINE_POSTS     => $s->boolean('Posts and pages')->defaultValue(true),
				self::ENGINE_USERS     => $s->boolean('Users')->defaultValue(true),
			], 'Search scope'),

			'postTypeEnabled' => $s->record(
				$s->computedEnum(function () {
					//This should match the basic post type filter used by PostSearchEngine.
					//That class isn't used here directly because entities.php only gets loaded
					//when actually needed.
					$postTypes = get_post_types(['public' => true, 'show_ui' => true], 'objects', 'or');
					$suitablePostTypes = [];
					foreach ($postTypes as $postType) {
						//Note: We don't check user CPT permissions here because the user who edits
						//the settings may have different permissions than the user(s) who actually
						//use the search box. The list gets filtered further before searching.

						$suitablePostTypes[] = [
							$postType->name,
							[
								'label' => esc_html($postType->label),
							],
						];
					}
					return $suitablePostTypes;
				}),
				$s->boolean()->defaultValue(true),
				'Enabled post types'
			),
		]);
	}

	protected function getInterfaceStructure() {
		$settings = $this->loadSettings();
		$b = $settings->elementBuilder();

		$shortcutSetting = $settings->getSetting('keyboardShortcut');
		$toolbarButtonSetting = $settings->getSetting('toolbarButton');

		$structure = $b->structure(
			$b->group(
				'Keyboard shortcut',
				$b->radioGroup('keyboardShortcut'),
				$b->auto('customShortcut')->enabled(
					new SettingCondition($shortcutSetting, '==', '_custom')
				)->inputClasses('ame-qs-custom-shortcut'),
				$b->html(
					'<p class="description ame-qs-shortcut-syntax">
						Modifiers: <kbd>ctrl</kbd>, <kbd>alt</kbd>, <kbd>shift</kbd>, <kbd>meta</kbd><br>
						Special keys: <kbd>space</kbd>, <kbd>tab</kbd>, <kbd>esc</kbd>, <kbd>ins</kbd>,
						              <kbd>left</kbd>, <kbd>up</kbd>, <kbd>right</kbd>, etc.<br>
						Examples: <kbd>ctrl+shift+k</kbd>, <kbd>a+b</kbd>, <kbd>a b c</kbd> (key sequence)
					 </p>'
				),
				$b->html(
					'<p class="ame-qs-shortcut-test-container">
						<button type="button" class="button button-secondary" id="ame-qs-test-shortcut"
							data-bind="click: $root.toggleHotkeyTest.bind($root), 
							text: hotkeyTestingInProgress() ? \'Stop Test\' : \'Test Shortcut\'">Test Shortcut</button>
						<span class="ame-qs-shortcut-test-status" data-bind="text: hotkeyTestStatus">...</span>
					</p>'
				)
			),
			$b->group(
				'Toolbar button',
				$b->auto($toolbarButtonSetting),
				$b->radioGroup('toolbarButtonType')
					->classes('ame-qs-toolbar-button-type')
					->enabled(
						new SettingCondition($toolbarButtonSetting, 'truthy', null)
					)
			),
			$b->group(
				'Search scope',
				$b->auto('searchScope.adminMenu'),
				$b->auto('searchScope.' . self::ENGINE_DASHBOARD),
				$b->auto('searchScope.' . self::ENGINE_POSTS),
				$b->checkBoxGroup('postTypeEnabled')
					->classes('ame-qs-post-type-list')
					->enabled(
						new SettingCondition($settings->getSetting('searchScope.postType'), 'truthy', null)
					),
				$b->auto('searchScope.' . self::ENGINE_USERS)
			)->stacked(),
			$b->auto('crawlerEnabled'),
			$b->auto('crawlerTabVisible'),
			$b->auto('recencyTracking')
		);
		return $structure->build();
	}

	protected function getSettingsForm() {
		if ( $this->settingsForm === null ) {
			$this->settingsForm = SettingsForm::builder($this->settingsFormAction)
				->id('ame-quick-search-settings-form')
				->settings($this->loadSettings()->getRegisteredSettings())
				->structure($this->getInterfaceStructure())
				->submitUrl($this->getTabUrl(['noheader' => 1]))
				->redirectAfterSaving($this->getTabUrl(['updated' => 1]))
				->skipMissingFields()
				->build();
		}
		return $this->settingsForm;
	}

	public function handleSettingsForm($post = []) {
		if ( !$this->userCanChangeSettings() ) {
			wp_die('You do not have permission to change Quick Search settings');
		}

		$this->getSettingsForm()->handleUpdateRequest($post);
	}

	protected function getTemplateVariables($templateName) {
		$variables = parent::getTemplateVariables($templateName);
		$variables['settingsForm'] = $this->getSettingsForm();
		return $variables;
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();
		$settings = $this->loadSettings();

		ScriptDependency::create(
			plugins_url('qs-settings-tab.js', __FILE__),
			'ame-quick-search-settings'
		)
			->addDependencies('jquery', 'ame-knockout', 'ame-jquery-cookie')
			->setTypeToModule()
			->addJsVariable(
				'wsAmeQuickSearchSettingsData',
				[
					'settings' => array_merge($this->defaultSettings, $settings->toArray()),
					'idPrefix' => $settings->getIdPrefix(),
				]
			)
			->enqueue();
	}

	public function addToolbarSearchButton($adminBar) {
		if ( $this->isSearchDisabledForRequest() ) {
			return;
		}

		if ( !$this->userCanSearch() ) {
			return;
		}

		$settings = $this->loadSettings();
		if ( !$settings->get('toolbarButton') ) {
			return;
		}

		$toolbarButtonType = $settings->get('toolbarButtonType');
		$title = '<span class="ab-icon"></span>';
		if ( $toolbarButtonType === 'iconAndText' ) {
			$title .= ' Search';
		}

		$classes = [];
		if ( $toolbarButtonType === 'iconOnly' ) {
			$classes[] = 'ame-qs-tb-icon-only';
		}

		$adminBar->add_node([
			'id'     => 'ame-quick-search-tb',
			'title'  => $title,
			'parent' => 'top-secondary',
			'meta'   => [
				'title' => 'Open the Quick Search box',
				'class' => implode(' ', $classes),
			],
		]);
	}

	/**
	 * Schedule a periodic Cron event to clean up the crawl database.
	 *
	 * Does nothing if the event is already scheduled.
	 */
	public function maybeScheduleCleanupEvent() {
		if ( wp_next_scheduled(self::DB_CLEANUP_CRON_HOOK) === false ) {
			wp_schedule_event(
				time() + 2 * DAY_IN_SECONDS, //No need to run this immediately.
				'weekly',
				self::DB_CLEANUP_CRON_HOOK
			);
		}
	}

	public function cleanupDatabase() {
		$this->getDbAdapter()->deleteStaleEntries(
			self::STALENESS_THRESHOLD_IN_DAYS,
			self::STALENESS_THRESHOLD_IN_DAYS
		);
	}

	/**
	 * Is the search script disabled for the current request or admin page?
	 *
	 * Searching doesn't work in some contexts, e.g. in AJAX requests, and can conflict with
	 * certain plugins or themes.
	 *
	 * @return bool
	 */
	private function isSearchDisabledForRequest() {
		//Don't load the search script on AJAX requests. This usually won't come up, but there are
		//circumstances where the "admin_enqueue_scripts" hook is fired during AJAX requests, e.g. if
		//the request calls wp_iframe().
		if ( function_exists('wp_doing_ajax') && wp_doing_ajax() ) {
			return true;
		}

		//Did someone else already enqueue Knockout? Their script may conflict with ours if they
		//apply bindings to the entire document, so we'll disable search for this request.
		if ( function_exists('wp_script_is') && did_action('admin_enqueue_scripts') ) {
			if ( wp_script_is('knockout', 'enqueued') ) {
				return true;
			}
		}

		global $pagenow;
		$queryParams = $this->menuEditor->get_query_params();

		//Compatibility fix for Toolset Types 3.5.2 and Toolset Blocks 1.6.18.
		//Toolset Blocks uses Knockout on its "Edit Content Template" page when in "Classic Editor"
		//mode. It applies KO bindings to the entire document. This causes a JS error when our KO
		//template is added to the page footer because the Toolset Blocks view model obviously doesn't
		//have the properties used in our template.
		//Similar issues apply to "Toolset -> Custom Fields", "Toolset -> Edit Group", etc.
		$unsafePages = [
			'ct-editor'           => true,
			'types-custom-fields' => true,
			'wpcf-edit'           => true,
			'types-relationships' => true,
		];
		if (
			($pagenow === 'admin.php')
			&& isset($queryParams['page'])
			&& isset($unsafePages[$queryParams['page']])
		) {
			return true;
		}

		return false;
	}

	//region Permission checks
	public function userCanSearch() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	public function userCanUpdateIndex() {
		return $this->menuEditor->current_user_can_edit_menu() && current_user_can('activate_plugins');
	}

	public function userCanChangeSettings() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	//endregion

	//region Export/import
	public function getExportOptionLabel() {
		return 'Quick Search settings'; //Does not include the search index.
	}

	public function getExportOptionDescription() {
		return '';
	}

	public function exportSettings() {
		if ( $this->settingsWrapperEnabled ) {
			$settings = $this->loadSettings();
			if ( $settings instanceof AbstractSettingsDictionary ) {
				return $settings->toArray();
			} else {
				return null;
			}
		} else {
			return $this->loadSettings();
		}
	}

	public function importSettings($newSettings) {
		if ( !is_array($newSettings) || empty($newSettings) ) {
			return;
		}

		$this->mergeSettingsWith($newSettings);
		$this->saveSettings();
	}
	//endregion
}

abstract class SearchableItemDefinition implements \JsonSerializable {
	/**
	 * @var string
	 */
	protected $label;
	/**
	 * @var string[]
	 */
	protected $location;

	public function __construct($label, $location = []) {
		$this->label = $label;
		$this->location = $location;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'label'    => $this->label,
			'location' => $this->location,
		];
	}
}

class DashboardItemOrigin implements \JsonSerializable {
	private $pageUrl;
	private $menuUrl;

	public function __construct($menuUrl, $pageUrl = null) {
		$this->menuUrl = $menuUrl;
		$this->pageUrl = $pageUrl;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = [
			'menuUrl' => $this->menuUrl,
		];
		if ( $this->pageUrl !== null ) {
			$data['pageUrl'] = $this->pageUrl;
		}
		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getMenuUrl() {
		return $this->menuUrl;
	}
}

class DashboardItemTarget implements \JsonSerializable {
	private $type;
	private $url;
	private $selector;

	public function __construct($type, $url = '', $selector = '') {
		$this->url = $url;
		$this->type = $type;
		$this->selector = $selector;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = [
			'type' => $this->type,
		];
		if ( $this->url !== '' ) {
			$data['url'] = $this->url;
		}
		if ( $this->selector !== '' ) {
			$data['selector'] = $this->selector;
		}
		return $data;
	}
}

class DashboardItemDefinition extends SearchableItemDefinition {
	/**
	 * @var DashboardItemOrigin
	 */
	private $origin;
	/**
	 * @var DashboardItemTarget
	 */
	private $target;
	private $relativeId;

	public function __construct(
		$label,
		DashboardItemOrigin $origin,
		DashboardItemTarget $target,
		$relativeId,
		$location = []
	) {
		parent::__construct($label, $location);
		$this->origin = $origin;
		$this->target = $target;
		$this->relativeId = $relativeId;
	}

	public function getMenuUrl() {
		return $this->origin->getMenuUrl();
	}

	public function getUniqueId(): string {
		return 'p:' . $this->getMenuUrl() . ':' . $this->relativeId;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = parent::jsonSerialize();
		$data['origin'] = $this->origin;
		$data['target'] = $this->target;
		$data['relativeId'] = $this->relativeId;
		$data['type'] = 'dashboardItem';
		return $data;
	}
}

interface ItemSearchEngine {
	/**
	 * @param array $itemRefs
	 * @param int $desiredResults
	 * @return SearchableItemDefinition[]
	 */
	public function getRecentItems(array $itemRefs, int $desiredResults = 20): array;

	/**
	 * Search for items matching the query.
	 *
	 * @param string $query The search query.
	 * @param int $maxResults The maximum number of results to return.
	 * @return SearchableItemDefinition[]
	 */
	public function searchItems(string $query, int $maxResults = 100): array;
}

class RecentlyUsedItemStore {
	const USER_META_KEY = 'ame_qs_recently_used_items';

	private $pendingUpdates = [];
	/**
	 * @var null|array
	 */
	private $perUserCache = [];

	private $hardSizeLimit;
	private $softSizeLimit;

	public function __construct($softSizeLimit = 30) {
		$this->softSizeLimit = $softSizeLimit;
		$this->hardSizeLimit = $this->softSizeLimit * 2;
	}

	public function getRecentItemRefs(int $userId, array $engineKeys, int $maxItems): array {
		if ( $userId <= 0 ) {
			throw new \InvalidArgumentException('User ID must be a positive integer');
		}

		$userData = $this->lazyLoad($userId);
		if ( empty($userData) ) {
			return [];
		}

		//Data is already sorted by timestamp, from newest to oldest.
		//We just need to find the first N items that match the requested engines.
		$results = [];
		foreach ($userData as $serializedRef => $timestamp) {
			$ref = json_decode($serializedRef, true);
			if (
				!empty($ref['engine'])
				&& !empty($ref['item'])
				&& in_array($ref['engine'], $engineKeys, true)
			) {
				$results[] = $ref;
			}

			if ( count($results) >= $maxItems ) {
				break;
			}
		}

		return $results;
	}

	public function updateItemTimestamp(int $userId, string $serializedRef, int $timestamp) {
		if ( $userId <= 0 ) {
			throw new \InvalidArgumentException('User ID must be a positive integer');
		}

		if ( !isset($this->pendingUpdates[$userId]) ) {
			$this->pendingUpdates[$userId] = [];
		}

		$oldTimestamp = \ameUtils::get($this->pendingUpdates[$userId], [$serializedRef], 0);
		$this->pendingUpdates[$userId][$serializedRef] = max($timestamp, $oldTimestamp);
	}

	public function save() {
		if ( empty($this->pendingUpdates) ) {
			return;
		}

		foreach ($this->pendingUpdates as $userId => $userUpdates) {
			//Let's try to avoid overwriting newer updates accidentally.
			//Load the latest data immediately before saving updates.
			$rawData = $this->loadUserData($userId);

			foreach ($userUpdates as $serializedRef => $timestamp) {
				$oldTimestamp = \ameUtils::get($rawData, [$serializedRef], 0);
				$rawData[$serializedRef] = max($timestamp, $oldTimestamp);
			}

			//Sort the items by timestamp, descending.
			uasort($rawData, function ($a, $b) {
				if ( !is_int($a) || !is_int($b) ) {
					return 0;
				}
				return $b <=> $a;
			});

			//Truncate old items if there are too many.
			if ( count($rawData) > $this->hardSizeLimit ) {
				$rawData = array_slice($rawData, 0, $this->softSizeLimit, true);
			}

			if ( !empty($rawData) ) {
				update_user_meta($userId, self::USER_META_KEY, $rawData);
			} else {
				delete_user_meta($userId, self::USER_META_KEY);
			}
			unset($this->perUserCache[$userId]); //Clear the cache for this user.
		}
	}

	private function lazyLoad(int $userId): array {
		if ( !isset($this->perUserCache[$userId]) ) {
			$this->perUserCache[$userId] = $this->loadUserData($userId);
		}
		return $this->perUserCache[$userId];
	}

	private function loadUserData(int $userId) {
		$rawData = get_user_meta($userId, self::USER_META_KEY, true);
		if ( !is_array($rawData) ) {
			$rawData = [];
		}
		return $rawData;
	}
}

class AjaxApi {
	const AJAX_GET_CRAWL_RECORDS = 'ws-ame-qs-get-crawl-records';
	const AJAX_SET_CRAWL_RECORDS = 'ws-ame-qs-set-crawl-records';
	const AJAX_UPDATE_INDEX = 'ws-ame-qs-update-dashboard-index';
	const AJAX_RUN_SEARCH = 'ws-ame-qs-quick-search';

	const AJAX_SET_CRAWLER_ENABLED = 'ws-ame-qs-set-crawler-enabled';

	private $module;
	private $actionsRegistered = false;

	/**
	 * @var callable|null
	 */
	private $afterIndexUpdate;

	/**
	 * @var Action
	 */
	public $searchAction;
	/**
	 * @var Action
	 */
	public $updateIndexAction;
	/**
	 * @var Action
	 */
	public $setCrawlerEnabledAction;
	/**
	 * @var Action
	 */
	public $getCrawlRecordsAction;
	/**
	 * @var Action
	 */
	public $setCrawlRecordsAction;

	/**
	 * @param SearchModule $module
	 * @param callable|null $afterIndexUpdate
	 */
	public function __construct(SearchModule $module, $afterIndexUpdate = null) {
		$this->module = $module;
		$this->afterIndexUpdate = $afterIndexUpdate;
	}

	public function registerAjaxActions() {
		if ( $this->actionsRegistered ) {
			return;
		}
		$module = $this->module;

		$this->updateIndexAction = Action::builder(self::AJAX_UPDATE_INDEX)
			->requiredParam('updates')
			->method('post')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxUpdateIndex'])
			->register();

		$this->getCrawlRecordsAction = Action::builder(self::AJAX_GET_CRAWL_RECORDS)
			->method('post')
			->requiredParam('urls')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxGetCrawlRecords'])
			->register();

		$this->setCrawlRecordsAction = Action::builder(self::AJAX_SET_CRAWL_RECORDS)
			->requiredParam('records')
			->method('post')
			->permissionCallback([$module, 'userCanUpdateIndex'])
			->handler([$this, 'ajaxSetCrawlRecords'])
			->register();

		$this->searchAction = Action::builder(self::AJAX_RUN_SEARCH)
			->method('post')
			->requiredParam('query', Action::PARSE_STRING)
			->requiredParam('presentMenuUrls', Action::PARSE_STRING)
			->permissionCallback([$module, 'userCanSearch'])
			->handler([$this, 'ajaxSearch'])
			->register();

		$this->setCrawlerEnabledAction = Action::builder(self::AJAX_SET_CRAWLER_ENABLED)
			->method('post')
			->requiredParam('enabled', Action::PARSE_STRING)
			->permissionCallback([$module, 'userCanChangeSettings'])
			->handler([$this, 'ajaxSetCrawlerEnabled'])
			->register();

		$this->actionsRegistered = true;
	}

	public function ajaxUpdateIndex($params) {
		$serializedUpdates = $params['updates'];
		$updates = json_decode($serializedUpdates, true);
		if ( !is_array($updates) ) {
			wp_send_json_error(new \WP_Error('invalid_updates_param', 'Invalid updates - array expected'), 400);
			exit;
		}

		$results = [];
		$dbAdapter = $this->module->getDbAdapter();

		foreach ($updates as $menuUrl => $items) {
			if ( !is_array($items) ) {
				wp_send_json_error(new \WP_Error('invalid_update', 'Invalid update list for menu - array expected'), 400);
				exit;
			}

			list($inserted, $error) = $dbAdapter->setFoundDashboardItemsFor($menuUrl, $items, true);
			/** @var \WP_Error $error */
			if ( $error && $error->has_errors() ) {
				wp_send_json_error($error, 500);
				exit;
			}

			$results[$menuUrl] = $inserted;
		}

		if ( $this->afterIndexUpdate ) {
			call_user_func($this->afterIndexUpdate);
		}

		wp_send_json_success($results);
		exit;
	}

	public function ajaxGetCrawlRecords($params) {
		$serializedUrls = $params['urls'];
		$urls = json_decode($serializedUrls, true);
		if ( !is_array($urls) ) {
			wp_send_json_error(new \WP_Error('invalid_urls_param', 'Invalid URLs - array expected'), 400);
			exit; //wp_send_json_error() already exits, but the IDE doesn't know that.
		}

		if ( count($urls) > 200 ) {
			wp_send_json_error(new \WP_Error('too_many_urls', 'Too many URLs for one request'), 400);
			exit;
		}

		$sanitizedUrls = array_map(function ($input) {
			if ( !is_string($input) ) {
				return null;
			}
			return substr($input, 0, 2048);
		}, $urls);
		$sanitizedUrls = array_filter($sanitizedUrls);

		$records = $this->module->getDbAdapter()->fetchCrawlRecords($sanitizedUrls);

		wp_send_json_success($records);
		exit;
	}

	public function ajaxSetCrawlRecords($params) {
		$serializedRecords = $params['records'];
		$records = json_decode($serializedRecords, true);
		if ( !is_array($records) ) {
			wp_send_json_error(new \WP_Error('invalid_records_param', 'Invalid records - array expected'), 400);
			exit;
		}

		$result = $this->module->getDbAdapter()->updateCrawlRecords($records, true);

		if ( !empty($result['errors']) && empty($result['inserted']) && empty($result['updated']) ) {
			wp_send_json_error($result['errors'], 500);
			exit;
		}

		wp_send_json_success([
			'inserted' => $result['inserted'],
			'updated'  => $result['updated'],
			'errors'   => $this->serializeWpErrorForJson($result['errors']),
		]);
		exit;
	}

	/**
	 * @param \WP_Error|mixed $error
	 * @return array
	 */
	private function serializeWpErrorForJson($error) {
		if ( !is_wp_error($error) ) {
			return [];
		}

		//Same format as used by wp_send_json_error().
		$result = [];
		foreach ($error->errors as $code => $messages) {
			foreach ($messages as $message) {
				$result[] = [
					'code'    => $code,
					'message' => $message,
				];
			}
		}
		return $result;
	}

	public function ajaxSearch($params) {
		$query = $params['query'];

		$presentMenuUrls = json_decode($params['presentMenuUrls'], true);
		if ( !is_array($presentMenuUrls) ) {
			wp_send_json_error(new \WP_Error(
				'invalid_present_menu_urls_param',
				'Invalid presentMenuUrls - array expected'),
				400
			);
			exit;
		}
		$menuUrlLookup = array_flip($presentMenuUrls);

		$maxResults = 100;
		$resultSets = [];
		$totalResults = 0;
		foreach ($this->module->createSearchEngines($menuUrlLookup) as $engine) {
			$engineResults = $engine->searchItems($query, $maxResults);
			if ( !empty($engineResults) ) {
				$resultSets[] = $engineResults;
				$totalResults += count($engineResults);
			}
		}

		if ( ($totalResults < 1) || empty($resultSets) ) {
			wp_send_json_success([
				'items'   => [],
				'hasMore' => false,
			]);
			exit;
		}

		$results = $this->fairMerge($resultSets, $maxResults);

		//Reindex the array. If we somehow end up with sparse keys, the results would be sent
		//as an object instead of an array. The client expects an array.
		$results = array_values($results);

		wp_send_json_success([
			'items'   => $results,
			'hasMore' => ($totalResults > count($results)),
		]);
		exit;
	}

	/**
	 * Merge multiple arrays into a single array of a maximum size.
	 *
	 * This method tries to fairly distribute the available space by recursively taking the same
	 * number of items from each input array.
	 *
	 * @param array[] $arrays
	 * @param int $availableSpace
	 * @return array
	 */
	private function fairMerge(array $arrays, int $availableSpace): array {
		if ( empty($arrays) || ($availableSpace < 1) ) {
			return [];
		}

		$equalShare = max(1, floor($availableSpace / count($arrays)));
		$results = [];
		$remainingArrays = [];

		foreach ($arrays as $array) {
			//Try to take the same number of items from each array.
			$takenItems = array_splice($array, 0, min($equalShare, $availableSpace), []);
			$results = array_merge($results, $takenItems);
			$availableSpace -= count($takenItems);

			//Due to $equalShare always being at least 1, we can run out of space partway through
			//the loop when there are less than count($arrays) spots left.
			if ( $availableSpace <= 0 ) {
				return $results;
			}

			//Any items left in this array?
			if ( !empty($array) ) {
				$remainingArrays[] = $array;
			}
		}

		//Recursively merge the remaining arrays if we still have space left.
		if ( ($availableSpace >= 1) && !empty($remainingArrays) ) {
			$results = array_merge($results, $this->fairMerge($remainingArrays, $availableSpace));
		}

		return $results;
	}


	public function ajaxSetCrawlerEnabled($params) {
		$validValues = ['enabled', 'disabled'];
		if ( !in_array($params['enabled'], $validValues, true) ) {
			wp_send_json_error(new \WP_Error('invalid_value', 'Invalid value'), 400);
			exit;
		}

		$settings = $this->module->loadSettings();
		$settings->set('crawlerEnabled', $params['enabled']);
		$this->module->saveSettings();
		wp_send_json_success();
		exit;
	}
}