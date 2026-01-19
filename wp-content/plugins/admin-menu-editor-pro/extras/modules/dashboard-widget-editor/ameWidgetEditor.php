<?php

use YahnisElsts\AdminMenuEditor\DynamicStylesheets\Stylesheet;
use YahnisElsts\AjaxActionWrapper\v2\Action;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

require_once AME_ROOT_DIR . '/extras/exportable-module.php';

class ameWidgetEditor extends ameModule implements ameExportableModule {
	//Note: Class constants require PHP 5.3 or better.
	const OPTION_NAME = 'ws_ame_dashboard_widgets';
	const MAX_IMPORT_FILE_SIZE = 2097152; //2 MiB

	const HIDEABLE_ITEM_PREFIX = 'dw/';
	const HIDEABLE_WELCOME_ITEM_ID = 'dw/special:welcome';

	const PREVIEW_COLUMN_META_KEY = 'ws_ame_dashboard_preview_cols';

	const CUSTOMIZATION_COMPONENT = 'dashboard_widgets';

	protected $tabSlug = 'dashboard-widgets';
	protected $tabTitle = 'Dashboard Widgets';

	/**
	 * @var ameWidgetCollection
	 */
	private $dashboardWidgets;

	private $shouldRefreshWidgets = false;

	/**
	 * @var null|\YahnisElsts\AdminMenuEditor\DynamicStylesheets\Stylesheet
	 */
	private $columnStylesheet = null;

	/**
	 * @var ameCustomizationFeatureToggle
	 */
	private $customizationFeature;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		$this->customizationFeature = new ameCustomizationFeatureToggle(
			self::CUSTOMIZATION_COMPONENT,
			$this->menuEditor,
			$this->tabSlug,
			function () {
				return [
					'You will see the default dashboard widgets.',
					'Widget customization is disabled for your account.',
				];
			}
		);

		if ( is_network_admin() ) {
			//This module doesn't work in the network admin.
			return;
		}

		add_action('wp_dashboard_setup', [$this, 'setupDashboard'], 20000);

		add_action('admin_menu_editor-header', [$this, 'handleFormSubmission'], 10, 2);

		Action::builder('ws-ame-export-widgets')
			->requiredParam('widgetData')
			->permissionCallback([$this, 'userCanEditWidgets'])
			->handler([$this, 'ajaxExportWidgets'])
			->skipAutoExpose()
			->register();

		Action::builder('ws-ame-import-widgets')
			->permissionCallback([$this, 'userCanEditWidgets'])
			->handler([$this, 'ajaxImportWidgets'])
			->skipAutoExpose()
			->register();

		add_action(
			'admin_menu_editor-register_hideable_items',
			[$this, 'registerHideableItems']
		);
		add_filter(
			'admin_menu_editor-save_hideable_items-d-widgets',
			[$this, 'saveHideableItems'],
			10,
			2
		);

		$this->columnStylesheet = new Stylesheet(
			'ame-dashboard-column-override',
			function () {
				$settings = $this->loadSettings();
				$columns = $settings->getForcedColumnCount();
				if ( $columns === null ) {
					return ''; //No need to override the number of columns.
				}

				$templateFile = __DIR__ . '/custom-columns.css';
				if ( !is_file($templateFile) ) {
					return '/* CSS template not found. */';
				}

				//This is not a remote file.
				//phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
				$css = file_get_contents($templateFile);
				if ( empty($css) ) {
					return '/* Failed to load the CSS template from a file. */';
				}

				$breakpoint = $settings->getForcedColumnBreakpoint();
				if ( empty($breakpoint) ) {
					return $css;
				} else {
					//Wrap the CSS in a media query that only applies it above
					//the configured breakpoint (inclusive).
					$breakpoint = min(max(intval($breakpoint), 0), 3000);
					return (
						'@media screen and (min-width: ' . $breakpoint . 'px) {' . PHP_EOL .
						$css . PHP_EOL
						. '}'
					);
				}
			},
			function () {
				$settings = $this->loadSettings();
				return $settings->getLastModified();
			}
		);

		if ( defined('DOING_AJAX') ) {
			$this->columnStylesheet->addOutputHook();
		}
	}

	public function setupDashboard() {
		global $wp_meta_boxes;

		$this->loadSettings();
		$changesDetected = $this->dashboardWidgets->merge($wp_meta_boxes['dashboard']);

		//Store new widgets and changed defaults.
		//We want a complete list of widgets, so we only do this when an administrator is logged in.
		//Admins usually can see everything. Other roles might be missing specific widgets.
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ($changesDetected || !empty($_GET['ame-cache-buster'])) && $this->userCanEditWidgets() ) {
			//Remove wrapped widgets where the file no longer exists.
			foreach ($this->dashboardWidgets->getMissingWrappedWidgets() as $widget) {
				$callbackFileName = $widget->getCallbackFileName();
				if ( !empty($callbackFileName) && !is_file($callbackFileName) ) {
					$this->dashboardWidgets->remove($widget->getId());
				}
			}

			$this->dashboardWidgets->siteComponentHash = $this->generateComponentHash();
			$this->saveSettings();
		}

		//Exception: Skip the rest if custom widget settings are disabled for this user.
		if ( $this->customizationFeature->isCustomizationDisabled() ) {
			return;
		}

		//Remove all Dashboard widgets.
		//Important: Using remove_meta_box() would prevent widgets being re-added. Clearing the array does not.
		//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for the plugin to work.
		$wp_meta_boxes['dashboard'] = [];

		//Re-add all widgets, this time with custom settings.
		$currentUser = wp_get_current_user();
		foreach ($this->dashboardWidgets->getPresentWidgets() as $widget) {
			if ( $widget->isVisibleTo($currentUser, $this->menuEditor) ) {
				$widget->addToDashboard(
					$this->dashboardWidgets->isDefaultOrderOverrideEnabled()
				);
			} else {
				//Technically, this line is not required. It just ensures that other plugins can't recreate the widget.
				remove_meta_box($widget->getId(), 'dashboard', $widget->getOriginalLocation());
			}
		}

		//Optionally, hide the "Welcome to WordPress!" panel. It's technically not a widget, but users
		//assume that it is, it looks similar, and it shows up in the same place.
		$isWelcomePanelHidden = !ameDashboardWidget::userCanAccess(
			$currentUser,
			$this->dashboardWidgets->getWelcomePanelVisibility(),
			$this->menuEditor
		);
		if ( $isWelcomePanelHidden ) {
			remove_action('welcome_panel', 'wp_welcome_panel');
		}

		$orderOverrideEnabled = $this->dashboardWidgets->isOrderOverrideEnabledFor($currentUser);

		if ( $orderOverrideEnabled ) {
			//Optimization: Enable the user metadata filter only when order override is
			//enabled for the current user and when the user is viewing the dashboard.
			add_filter('get_user_metadata', [$this, 'filterUserWidgetOrder'], 10, 4);

			//Remove the dashed outline from empty widget containers and hide the "up"
			//and "down" buttons. The helper script will also handle some of this, but
			//doing it early and in CSS helps prevent FOUC.
			add_action(
				'admin_enqueue_scripts',
				function ($hookSuffix = null) {
					if ( $hookSuffix !== 'index.php' ) {
						return;
					}
					wp_add_inline_style(
						'dashboard',
						'#dashboard-widgets .postbox-container .empty-container { outline: none; }
						 #dashboard-widgets .postbox-container .empty-container:after { content: ""; }
						 #dashboard-widgets .postbox .handle-order-higher, 
						 #dashboard-widgets .postbox .handle-order-lower { display: none; }'
					);
				}
			);
		}

		if ( $orderOverrideEnabled ) {
			//Enqueue the helper script that overrides the widget order and column count.
			ScriptDependency::create(
				plugins_url('custom-widget-layout.js', __FILE__),
				'ame-dashboard-layout-override'
			)
				->addDependencies('jquery', 'jquery-ui-sortable')
				->addJsVariable(
					'wsAmeDashboardLayoutSettings',
					[
						'orderOverrideEnabled' => $orderOverrideEnabled,
					]
				)
				->autoEnqueue();
		}

		$columns = $this->dashboardWidgets->getForcedColumnCount();
		if ( !empty($columns) && $this->dashboardWidgets->isColumnOverrideEnabledFor($currentUser) ) {
			//It appears that the `wp_dashboard_setup` hook only runs on the "index.php" page,
			//so we don't need to worry about checking the hook suffix when adding the stylesheet.
			$this->columnStylesheet->addAdminEnqueueHook();

			add_filter('admin_body_class', function ($classes) use ($columns) {
				$classes .= ' ame-de-override-columns-' . $columns . ' ';
				return $classes;
			});
		}
	}

	public function enqueueTabScripts() {
		wp_register_auto_versioned_script(
			'ame-dashboard-widget',
			plugins_url('dashboard-widget.js', __FILE__),
			['ame-knockout', 'ame-lodash', 'ame-actor-manager', 'ame-pro-common-lib']
		);

		wp_register_auto_versioned_script(
			'ame-dashboard-widget-editor',
			plugins_url('dashboard-widget-editor.js', __FILE__),
			[
				'ame-lodash',
				'ame-dashboard-widget',
				'ame-knockout',
				'ame-actor-selector',
				'ame-jquery-form',
				'jquery-ui-dialog',
				'ame-ko-extensions',
				'ame-knockout-sortable',
			]
		);

		//Automatically refresh the list of available dashboard widgets.
		$this->loadSettings();
		$query = $this->menuEditor->get_query_params();
		$this->shouldRefreshWidgets = empty($query['ame-widget-refresh-done'])
			&& (
				//Refresh when the list hasn't been populated yet (usually on the first run).
				$this->dashboardWidgets->isEmpty()
				//Refresh when plugins/themes are activated or deactivated.
				|| ($this->dashboardWidgets->siteComponentHash !== $this->generateComponentHash())
			);

		if ( $this->shouldRefreshWidgets ) {
			$baseDeps = $this->menuEditor->get_base_dependencies();

			$refreshScript = $this->registerLocalScript(
				'ame-refresh-widgets',
				'refresh-widgets.js',
				['jquery', $baseDeps['ame-pro-common-lib']]
			);
			$refreshScript->addJsVariable(
				'wsWidgetRefresherData',
				[
					'editorUrl'    => $this->getEditorUrl(['ame-widget-refresh-done' => 1]),
					'dashboardUrl' => add_query_arg('ame-cache-buster', time() . '_' . wp_rand(), admin_url('index.php')),
				]
			);

			$refreshScript->enqueue();
			return;
		}

		wp_enqueue_script('jquery-qtip');
		wp_enqueue_script('ame-dashboard-widget-editor');

		$selectedActor = null;
		if ( isset($query['selected_actor']) ) {
			$selectedActor = strval($query['selected_actor']);
		}

		$previewColumns = get_user_meta(get_current_user_id(), self::PREVIEW_COLUMN_META_KEY, true);
		if ( is_numeric($previewColumns) ) {
			$previewColumns = max(min(intval($previewColumns), 4), 1);
		} else {
			$previewColumns = 1;
		}

		wp_localize_script(
			'ame-dashboard-widget-editor',
			'wsWidgetEditorData',
			[
				'widgetSettings' => $this->dashboardWidgets->toArray(),
				'selectedActor'  => $selectedActor,
				'isMultisite'    => is_multisite(),
				'previewColumns' => $previewColumns,
			]
		);
	}

	public function enqueueTabStyles() {
		wp_enqueue_auto_versioned_style(
			'ame-dashboard-widget-editor-css',
			plugins_url('dashboard-widget-editor.css', __FILE__)
		);
	}

	public function displaySettingsPage() {
		if ( $this->shouldRefreshWidgets ) {
			require dirname(__FILE__) . '/widget-refresh-template.php';
		} else {
			//After saving settings, show the "customization disabled" notice if it applies to the user.
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( !empty($_GET['updated']) ) {
				$this->customizationFeature->onSettingsSaved();
			}

			parent::displaySettingsPage();
		}
	}

	protected function getWrapClasses() {
		return array_merge(parent::getWrapClasses(), ['ame-tab-list-bottom-margin-disabled']);
	}

	public function handleFormSubmission($action, $post = []) {
		//Note: We don't need to check user permissions here because plugin core already did.
		if ( $action === 'save_widgets' ) {
			check_admin_referer($action);

			$this->dashboardWidgets = ameWidgetCollection::fromJSON($post['data']);
			$this->saveSettings();

			//Remember the preview column count.
			if ( isset($post['preview_columns']) && is_scalar($post['preview_columns']) ) {
				$columnCount = max(min(intval($post['preview_columns']), 4), 1);
				update_user_meta(get_current_user_id(), self::PREVIEW_COLUMN_META_KEY, $columnCount);
			}

			$params = ['updated' => 1];

			//Re-select the same actor.
			if ( !empty($post['selected_actor']) ) {
				$params['selected_actor'] = strval($post['selected_actor']);
			}

			wp_redirect($this->getEditorUrl($params));
			exit;
		}
	}

	private function getEditorUrl($queryParameters = []) {
		$queryParameters = array_merge(
			[
				'page'        => 'menu_editor',
				'sub_section' => 'dashboard-widgets',
			],
			$queryParameters
		);
		return add_query_arg($queryParameters, admin_url('options-general.php'));
	}

	public function ajaxExportWidgets($params) {
		$exportData = $params['widgetData'];

		//The widget data must be valid JSON.
		$json = json_decode($exportData);
		if ( $json === null ) {
			return new WP_Error('The widget data is not valid JSON.', 'invalid_json');
		}

		$fileName = sprintf(
			'%1$s dashboard widgets (%2$s).json',
			wp_parse_url(get_site_url(), PHP_URL_HOST),
			gmdate('Y-m-d')
		);

		//Force file download.
		header("Content-Description: File Transfer");
		header('Content-Disposition: attachment; filename="' . $fileName . '"');
		header("Content-Type: application/force-download");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . strlen($exportData));

		//The three lines below basically disable caching.
		header("Cache-control: private");
		header("Pragma: private");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The data is JSON, and is output as a file.
		echo $exportData;
		exit();
	}

	public function ajaxImportWidgets() {
		if ( empty($_FILES['widgetFile']) ) {
			return new WP_Error('no_file', 'No file specified');
		}

		//While this doesn't use wp_handle_upload() since we don't want to keep the file,
		//it does perform basic validation and error checking.
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$importFile = $_FILES['widgetFile'];

		//Check for general upload errors.
		if ( $importFile['error'] !== UPLOAD_ERR_OK ) {

			$knownErrorCodes = [
				UPLOAD_ERR_INI_SIZE   => sprintf(
					'The uploaded file exceeds the upload_max_filesize directive in php.ini. Limit: %s',
					ini_get('upload_max_filesize')
				),
				UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the internal file size limit. Please contact the developer.",
				UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded",
				UPLOAD_ERR_NO_FILE    => "No file was uploaded",
				UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
				UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
				UPLOAD_ERR_EXTENSION  => "File upload stopped by a PHP extension",
			];

			if ( array_key_exists($importFile['error'], $knownErrorCodes) ) {
				$message = $knownErrorCodes[$importFile['error']];
			} else {
				$message = 'Unknown upload error #' . $importFile['error'];
			}

			return new WP_Error('internal_upload_error', $message);
		}

		if ( !is_uploaded_file($importFile['tmp_name']) ) {
			return new WP_Error('invalid_upload', 'Invalid upload: not an uploaded file');
		}

		if ( filesize($importFile['tmp_name']) > self::MAX_IMPORT_FILE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					'Import file too large. Maximum allowed size: %s bytes',
					number_format_i18n(self::MAX_IMPORT_FILE_SIZE)
				)
			);
		}

		$fileContents = file_get_contents($importFile['tmp_name']);

		//Check if this file could plausibly contain an exported widget collection.
		if ( strpos($fileContents, ameWidgetCollection::FORMAT_NAME) === false ) {
			return new WP_Error('unknown_file_format', 'Unknown file format');
		}

		try {
			$collection = ameWidgetCollection::fromJSON($fileContents);
		} catch (ameInvalidJsonException $ex) {
			return new WP_Error($ex->getCode() ?: 'invalid_json', $ex->getMessage());
		} catch (ameInvalidWidgetDataException $ex) {
			return new WP_Error($ex->getCode() ?: 'invalid_widget_data', $ex->getMessage());
		}

		//Merge standard widgets from the existing config with the imported config.
		//Otherwise, we could end up with imported defaults that are incorrect for this site.
		$collection->mergeWithWrappersFrom($this->loadSettings());

		$collection->siteComponentHash = $this->generateComponentHash();

		return $collection->toArray();
	}

	private function loadSettings() {
		if ( isset($this->dashboardWidgets) ) {
			return $this->dashboardWidgets;
		}

		$settings = $this->getScopedOption(self::OPTION_NAME);
		if ( empty($settings) ) {
			$this->dashboardWidgets = new ameWidgetCollection();
		} else {
			$this->dashboardWidgets = ameWidgetCollection::fromDbString($settings);
		}
		return $this->dashboardWidgets;
	}

	private function saveSettings() {
		//Save per site or site-wide based on plugin configuration.
		$settings = $this->dashboardWidgets->toDbString();
		$this->setScopedOption(self::OPTION_NAME, $settings);
	}

	public function exportSettings() {
		$dashboardWidgets = $this->loadSettings();
		if ( !$dashboardWidgets || $dashboardWidgets->isEmpty() ) {
			return null;
		}
		return $dashboardWidgets->toArray();
	}

	public function importSettings($newSettings) {
		if ( empty($newSettings) ) {
			return;
		}

		$this->loadSettings();
		$collection = ameWidgetCollection::fromArray($newSettings);

		//Merge standard widgets from the existing config with the imported config.
		//Otherwise, we could end up with imported defaults that are incorrect for this site.
		$collection->mergeWithWrappersFrom($this->dashboardWidgets);

		$collection->siteComponentHash = $this->generateComponentHash();

		$this->dashboardWidgets = $collection;
		$this->saveSettings();
	}

	public function getExportOptionLabel() {
		return 'Dashboard widgets';
	}

	public function getExportOptionDescription() {
		return '';
	}

	public function userCanEditWidgets() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	/**
	 * Calculate a hash of site components: WordPress version, active theme, and active plugins.
	 *
	 * Any of these components can register dashboard widgets, so the hash is useful for detecting
	 * when widgets might have changed.
	 *
	 * @return string
	 */
	private function generateComponentHash() {
		$components = [];

		//WordPress.
		$components[] = 'WordPress ' . (isset($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : 'unknown');

		//Active theme.
		$theme = wp_get_theme();
		if ( $theme && $theme->exists() ) {
			$components[] = $theme->get_stylesheet() . ' : ' . $theme->get('Version');
		}

		//Active plugins.
		$activePlugins = wp_get_active_and_valid_plugins();
		if ( is_multisite() ) {
			$activePlugins = array_merge($activePlugins, wp_get_active_network_plugins());
		}
		//The hash shouldn't depend on the order of plugins.
		sort($activePlugins);
		$components = array_merge($components, $activePlugins);

		return md5(implode('|', $components));
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\EasyHide\HideableItemStore $store
	 */
	public function registerHideableItems($store) {
		$collection = $this->loadSettings();
		$widgets = $collection->getPresentWidgets();
		if ( empty($widgets) ) {
			return;
		}

		$cat = $store->getOrCreateCategory(
			'dashboard-widgets',
			'Dashboard Widgets',
			null,
			true,
			1,
			0
		);

		foreach ($widgets as $widget) {
			$store->addItem(
				self::HIDEABLE_ITEM_PREFIX . $widget->getId(),
				$this->sanitizeTitleForHiding($widget->getTitle()),
				[$cat],
				null,
				$widget->getGrantAccess(),
				'd-widgets',
				$widget->getId()
			);
		}

		//Register the special "Welcome" pseudo-widget.
		$store->addItem(
			self::HIDEABLE_WELCOME_ITEM_ID,
			'Welcome',
			[$cat],
			null,
			$collection->getWelcomePanelVisibility(),
			'd-widgets'
		);
	}

	private function sanitizeTitleForHiding($title) {
		if ( !is_string($title) ) {
			return strval($title);
		}

		/*$title = preg_replace(
			'@<span[^<>]+class=[\'"](hide-if-js|postbox).++>@i',
			'',
			$title
		);*/

		return trim(wp_strip_all_tags($title));
	}

	public function saveHideableItems($errors, $items) {
		$collection = $this->loadSettings();
		$wasAnyWidgetModified = false;

		//Handle the special "Welcome" panel.
		if ( isset($items[self::HIDEABLE_WELCOME_ITEM_ID]) ) {
			$welcomePanelEnabled = ameUtils::get(
				$items,
				[self::HIDEABLE_WELCOME_ITEM_ID, 'enabled'],
				[]
			);
			unset($items[self::HIDEABLE_WELCOME_ITEM_ID]);

			if ( !ameUtils::areAssocArraysEqual(
				$collection->getWelcomePanelVisibility(),
				$welcomePanelEnabled
			) ) {
				$collection->setWelcomePanelVisibility($welcomePanelEnabled);
				$wasAnyWidgetModified = true;
			}
		}

		foreach ($items as $id => $item) {
			$widgetId = substr($id, strlen(self::HIDEABLE_ITEM_PREFIX));
			$enabled = !empty($item['enabled']) ? $item['enabled'] : [];

			$widget = $collection->getWidgetById($widgetId);
			if ( $widget !== null ) {
				$modified = $widget->setGrantAccess($enabled);
				$wasAnyWidgetModified = $wasAnyWidgetModified || $modified;
			}
		}

		if ( $wasAnyWidgetModified ) {
			$this->saveSettings();
		}

		return $errors;
	}

	public function filterUserWidgetOrder($inputValue, $objectId = null, $metaKey = '') {
		if (
			($metaKey !== 'meta-box-order_dashboard')
			|| ($objectId !== get_current_user_id())
		) {
			return $inputValue;
		}
		if ( empty($this->dashboardWidgets) ) {
			return $inputValue;
		}
		$presentWidgets = $this->dashboardWidgets->getPresentWidgets();
		if ( empty($presentWidgets) ) {
			return $inputValue;
		}

		$columns = [
			'normal'  => [],
			'side'    => [],
			'column3' => [],
			'column4' => [],
		];
		foreach ($presentWidgets as $widget) {
			$location = $widget->getLocation();
			if ( isset($columns[$location]) ) {
				$columns[$location][] = $widget->getId();
			}
		}

		$orderedWidgets = [];
		foreach ($columns as $location => $widgets) {
			$orderedWidgets[$location] = implode(',', $widgets);
		}

		return [$orderedWidgets];
	}
}