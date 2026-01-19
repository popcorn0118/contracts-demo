<?php

namespace YahnisElsts\AdminMenuEditor\TableColumns;

use amePersistentProModule;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;
use YahnisElsts\AdminMenuEditor\EasyHide\HideableItemStore;

class TableColumnsModule extends amePersistentProModule implements \ameExportableModule {
	const SCREEN_STALENESS_THRESHOLD = 30 * 24 * 60 * 60;
	const SAVE_SETTINGS_ACTION = 'ame_save_table_column_settings';
	const FORCE_REFRESH_PARAM = 'ame-force-table-columns-refresh';
	const REFRESH_DONE_PARAM = 'ame-table-columns-refresh-done';

	const EXCLUDED_SCREENS = [
		//"Appearance -> Menus" uses the "manage_nav-menus_columns" filter to add "Show advanced menu properties"
		//options to Screen Options. It doesn't have an actual table with columns.
		'nav-menus' => true,
	];

	const HIDEABLE_ITEM_COMPONENT = 'tc';

	protected $optionName = 'ws_ame_table_columns';
	protected $tabSlug = 'table-columns';
	protected $tabTitle = 'Columns';

	/**
	 * @var array<string,bool>
	 */
	private $screenHooksAdded = [];

	/**
	 * @var null|\ameActorAccessEvaluator
	 */
	private $visibilityChecker = null;

	/**
	 * @var null|\ameActorAccessEvaluator
	 */
	private $customOrderChecker = null;

	protected $settingsFormAction = self::SAVE_SETTINGS_ACTION;

	private $shouldRefreshTables = false;

	public function __construct($menuEditor) {
		$this->settingsWrapperEnabled = true;
		parent::__construct($menuEditor);

		add_action('current_screen', [$this, 'addScreenHooks']);

		//Easy Hide integration.
		add_action(
			'admin_menu_editor-register_hideable_items',
			[$this, 'registerHideableItems'],
			10, 1
		);
		add_filter(
			'admin_menu_editor-save_hideable_items-' . self::HIDEABLE_ITEM_COMPONENT,
			[$this, 'saveHideableItems'],
			10, 2
		);
	}

	/**
	 * @param \WP_Screen|mixed $screen
	 * @return void
	 */
	public function addScreenHooks($screen = null) {
		if ( !($screen instanceof \WP_Screen) || empty($screen->id) ) {
			return;
		}

		if ( empty($this->screenHooksAdded[$screen->id]) ) {
			add_filter('manage_' . $screen->id . '_columns', [$this, 'processColumns'], PHP_INT_MAX - 1000, 1);
			$this->screenHooksAdded[$screen->id] = true;
		}
	}

	public function processColumns($columns = []) {
		$screen = get_current_screen();
		//Sanity check.
		if ( !($screen instanceof \WP_Screen) || !is_array($columns) ) {
			return $columns;
		}

		//Skip excluded screens.
		if ( !empty(self::EXCLUDED_SCREENS[$screen->id]) ) {
			return $columns;
		}

		if ( !empty($columns) || $this->hasSettingsForScreen($screen->id) ) {
			$changesDetected = $this->mergeScreenData($screen, $columns);
			if ( $changesDetected && $this->userCanEditColumns() ) {
				$this->saveSettings();
			}
		}

		return $this->getCustomizedColumns($screen->id, $columns);
	}

	private function hasSettingsForScreen($screenId) {
		$settings = $this->loadSettings();
		$screenSettings = $settings->get(['screens', $screenId], null);
		return !empty($screenSettings);
	}

	private function mergeScreenData(\WP_Screen $screen, $defaultColumns) {
		if ( empty($screen->id) ) {
			return false;
		}

		$settings = $this->loadSettings();
		$screenSettings = $settings->get(['screens', $screen->id], null);
		$changesDetected = false;

		if ( !is_array($screenSettings) ) {
			$screenSettings = [];
		}

		/** @noinspection PhpCastIsUnnecessaryInspection -- Defensive casts in case some plugin messes things up. */
		$newScreenData = [
			'postType'     => strval($screen->post_type),
			'taxonomy'     => strval($screen->taxonomy),
			'defaultOrder' => array_keys($defaultColumns),
			'menuUrl'      => null,
		];

		$currentMenuItem = $this->menuEditor->get_current_menu_item();
		if ( $currentMenuItem ) {
			$newScreenData['menuUrl'] = \ameUtils::get($currentMenuItem, 'url');

			//Save the menu title only once. This is because cleaning it could be relatively slow.
			//If the menu still exists when the user saves settings, the title should be updated then.
			if ( empty($screenSettings['menuTitle']) && !empty($currentMenuItem['full_title']) ) {
				$newScreenData['menuTitle'] = $this->convertFullMenuTitle($currentMenuItem['full_title']);
			}
		}

		foreach ($newScreenData as $key => $newValue) {
			$oldValue = \ameUtils::get($screenSettings, $key);
			if ( $newValue !== $oldValue ) {
				$screenSettings[$key] = $newValue;
				$changesDetected = true;
			}
		}

		$previousLastUpdated = \ameUtils::get($screenSettings, 'lastUpdated', 0);
		$screenSettings['lastUpdated'] = time();
		//Usually, it's not worth a database query just to update the timestamp. However, to help
		//identify screens that no longer exist, we'll update it occasionally for existing screens
		//before the screen data goes "stale".
		$timestampUpdateThreshold = self::SCREEN_STALENESS_THRESHOLD / 2;
		$timeSinceLastUpdate = $screenSettings['lastUpdated'] - $previousLastUpdated;
		if ( $timeSinceLastUpdate >= $timestampUpdateThreshold ) {
			$changesDetected = true;
		}

		$columns = \ameUtils::get($screenSettings, 'columns', []);
		//Add new columns and update column titles.
		foreach ($defaultColumns as $id => $title) {
			$newColumnData = ['title' => $title];

			if ( !isset($columns[$id]) ) {
				$columns[$id] = [];
			}

			foreach ($newColumnData as $key => $newValue) {
				$oldValue = \ameUtils::get($columns[$id], $key);
				if ( $newValue !== $oldValue ) {
					$columns[$id][$key] = $newValue;
					$changesDetected = true;
				}
			}
		}

		//Update presence flags. Basically, these track if the column was present in the default
		//column list when the screen info was last updated.
		foreach ($columns as $id => $column) {
			$wasPresent = \ameUtils::get($column, 'present', false);
			$isPresent = array_key_exists($id, $defaultColumns);
			if ( $wasPresent !== $isPresent ) {
				$columns[$id]['present'] = $isPresent;
				$changesDetected = true;
			}
		}

		$screenSettings['columns'] = $columns;
		$settings->set(['screens', $screen->id], $screenSettings);

		return $changesDetected;
	}

	private function getCustomizedColumns($screenId, $defaultColumns) {
		$settings = $this->loadSettings();
		$screenSettings = $settings->get(['screens', $screenId], null);
		if ( empty($screenSettings) ) {
			return $defaultColumns;
		}

		//Remove hidden columns.
		$columnSettings = \ameUtils::get($screenSettings, ['columns'], []);
		$result = array_filter($defaultColumns, function ($columnId) use ($columnSettings) {
			return $this->isColumnVisible(
				isset($columnSettings[$columnId]) ? $columnSettings[$columnId] : []
			);
		}, ARRAY_FILTER_USE_KEY);

		//Apply custom column order if enabled for the current user.
		if ( $this->isCustomOrderEnabled($screenSettings) ) {
			uksort($result, function ($a, $b) use ($columnSettings) {
				$aPosition = \ameUtils::get($columnSettings, [$a, 'position'], null);
				$bPosition = \ameUtils::get($columnSettings, [$b, 'position'], null);
				if ( ($aPosition === null) && ($bPosition === null) ) {
					return 0;
				} else if ( $aPosition === null ) {
					return 1;
				} else if ( $bPosition === null ) {
					return -1;
				}
				return $aPosition - $bPosition;
			});
		}

		return $result;
	}

	private function isColumnVisible($singleColumnSettings) {
		$enabledForActor = \ameUtils::get($singleColumnSettings, ['enabledForActor'], []);
		if ( empty($enabledForActor) ) {
			return true;
		}

		return $this->getVisibilityChecker()->isEnabled($enabledForActor);
	}

	private function getVisibilityChecker() {
		if ( $this->visibilityChecker === null ) {
			$this->visibilityChecker = \ameAccessEvaluatorBuilder::create($this->menuEditor)
				->roleDefault(true)       //All roles can see all columns by default.
				->superAdminDefault(true) //Super admins can see all columns. This overrides role settings.
				->defaultResult(true)     //If no other rules apply, the item is visible.
				->buildForUser(wp_get_current_user());
		}

		return $this->visibilityChecker;
	}

	private function isCustomOrderEnabled(array $screenSettings) {
		return $this->getCustomOrderChecker()->isEnabled(
			\ameUtils::get($screenSettings, ['customOrderEnabled'], [])
		);
	}

	private function getCustomOrderChecker() {
		if ( $this->customOrderChecker === null ) {
			$this->customOrderChecker = \ameAccessEvaluatorBuilder::create($this->menuEditor)
				->roleDefault(null)
				->superAdminDefault(null)
				->defaultResult(true)
				->buildForUser(wp_get_current_user());
		}

		return $this->customOrderChecker;
	}

	private function userCanEditColumns() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	private function convertFullMenuTitle($fullTitle) {
		$parts = explode('→', $fullTitle);
		return array_map(function ($title) {
			return trim(wp_strip_all_tags(\ameMenuItem::remove_update_count($title)));
		}, $parts);
	}

	public function createSettingInstances(ModuleSettings $settings) {
		$f = $settings->settingFactory();
		$s = new SchemaFactory();
		$parentSettings = parent::createSettingInstances($settings);

		return array_merge($parentSettings, $f->buildSettings([
			'screens'            => $s->record(
				$s->string()->min(1)->max(200),
				$s->struct([
					'postType'           => $s->string()->defaultValue(''),
					'taxonomy'           => $s->string()->defaultValue(''),
					'defaultOrder'       => $s->arr($s->string()),
					'customOrderEnabled' => $s->record(
						$s->string()->min(1)->max(250),
						$s->boolean()
					),
					'menuUrl'            => $s->string()->nullable()->defaultValue(null),
					'lastUpdated'        => $s->int()->defaultValue(0),

					'columns' => $s->record(
						$s->string()->min(1)->max(200),
						$s->struct([
							'title'           => $s->string()->defaultValue(''),
							'present'         => $s->boolean()->defaultValue(false),
							'position'        => $s->int()->defaultValue(null),
							'enabledForActor' => $s->record(
								$s->string()->min(1)->max(250),
								$s->boolean()
							),
						])
					),
				])
			),
			'isFirstRefreshDone' => $s->boolean()->defaultValue(false),
		]));
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();
		$settings = $this->loadSettings();

		$emptyObject = new \stdClass();
		$titleChangesFound = false;

		$screenData = [];
		foreach (\ameUtils::get($settings, ['screens'], []) as $screenId => $screenSettings) {
			$columnData = [];
			$lastColumnPosition = -1;

			foreach (\ameUtils::get($screenSettings, ['columns'], []) as $columnId => $columnSettings) {
				$columnDisplayTitle = $this->getColumnDisplayTitle($columnId, $columnSettings);

				$position = \ameUtils::get($columnSettings, 'position', null);
				if ( $position === null ) {
					$position = $lastColumnPosition + 1;
				}
				$lastColumnPosition = $position;

				$columnData[$columnId] = [
					'title'           => $columnDisplayTitle,
					'position'        => $position,
					'present'         => (bool)\ameUtils::get($columnSettings, 'present', false),
					'enabledForActor' => \ameUtils::get($columnSettings, ['enabledForActor'], $emptyObject),
				];
			}

			//Get the latest menu title(s) for the screen.
			$menuItemExists = false;
			if ( !empty($screenSettings['menuUrl']) ) {
				$item = $this->menuEditor->get_menu_item_by_url($screenSettings['menuUrl']);
				$menuItemExists = !empty($item);
				if ( $item && !empty($item['full_title']) ) {
					$currentTitle = $this->convertFullMenuTitle($item['full_title']);
					$oldTitle = \ameUtils::get($screenSettings, ['menuTitle'], []);
					if ( $currentTitle !== $oldTitle ) {
						$screenSettings['menuTitle'] = $currentTitle;
						$settings->set(['screens', $screenId, 'menuTitle'], $currentTitle);
						$titleChangesFound = true;
					}
				}
			}

			//Pick a display title for the screen.
			$screenTitle = $this->getScreenDisplayTitle($screenId, $screenSettings);

			//Does the screen still exist? Unfortunately, we can't determine this reliably, but
			//checking if the menu item/post type/taxonomy exists seems good enough.
			$screenExists = $menuItemExists;
			if ( !$menuItemExists ) {
				if ( !empty($screenSettings['postType']) ) {
					$screenExists = post_type_exists($screenSettings['postType']);
				} else if ( !empty($screenSettings['taxonomy']) ) {
					$screenExists = taxonomy_exists($screenSettings['taxonomy']);
				}
			}

			$screenData[$screenId] = [
				'title'              => $screenTitle,
				'columns'            => $columnData,
				'probablyExists'     => $screenExists,
				'defaultOrder'       => \ameUtils::get($screenSettings, ['defaultOrder'], []),
				'customOrderEnabled' => \ameUtils::get($screenSettings, ['customOrderEnabled'], $emptyObject),
			];
		}

		if ( $titleChangesFound ) {
			$settings->save();
		}

		$query = $this->menuEditor->get_query_params();
		$this->shouldRefreshTables = empty($query[self::REFRESH_DONE_PARAM])
			&& (
				empty($screenData)
				|| (!empty($query[self::FORCE_REFRESH_PARAM])/* && check_admin_referer(self::FORCE_REFRESH_PARAM)*/)
				|| (!$settings->get('isFirstRefreshDone', false))
			);

		$baseDeps = $this->menuEditor->get_base_dependencies();

		if ( $this->shouldRefreshTables ) {
			//Refresh mode.
			$refreshScript = $this->registerLocalScript(
				'ame-table-columns-refresh',
				'columns-refresh.js',
				[$baseDeps['ame-pro-common-lib']]
			);

			$pagesWithTables = [
				//Posts
				admin_url('edit.php'),
				//Pages
				admin_url('edit.php?post_type=page'),
				//Users
				admin_url('users.php'),
				//Plugins
				admin_url('plugins.php'),
				//Comments
				admin_url('edit-comments.php'),
			];
			//Categories
			if ( taxonomy_exists('category') ) {
				$pagesWithTables[] = admin_url('edit-tags.php?taxonomy=category');
			}
			//Tags
			if ( taxonomy_exists('post_tag') ) {
				$pagesWithTables[] = admin_url('edit-tags.php?taxonomy=post_tag');
			}

			$refreshScript->addJsVariable(
				'wsAmeTableColumnsRefreshData',
				[
					'pageUrls'    => $pagesWithTables,
					'redirectUrl' => $this->getTabUrl([self::REFRESH_DONE_PARAM => 1]),
				]
			);

			$refreshScript->enqueue();
		} else {
			//Editor mode.
			$script = $this->registerLocalScript(
				'ame-table-columns-ui',
				'table-columns.js',
				[
					$baseDeps['ame-actor-selector'],
					$baseDeps['ame-actor-manager'],
					$baseDeps['ame-knockout'],
					$baseDeps['ame-knockout-sortable'],
					$baseDeps['ame-ko-extensions'],
					$baseDeps['ame-free-ko-extensions'],
					$baseDeps['ame-lodash'],
					$baseDeps['ame-pro-common-lib'],
					$baseDeps['ame-jquery-cookie'],
					'jquery',
				]
			);

			//Tell the script to re-select the previously selected actor.
			$previouslySelectedActor = \ameUtils::get(
				$this->menuEditor->get_query_params(),
				\ameParsedKnockoutFormSubmission::SELECTED_ACTOR_FIELD,
				null
			);
			if ( !is_string($previouslySelectedActor) ) {
				$previouslySelectedActor = null;
			}

			$script
				->addJsVariable(
					'wsAmeTableColumnsSettingsData',
					[
						'screens'                  => $screenData,
						'orderStrategy'            => $this->getCustomOrderChecker()->configToJs(),
						'columnVisibilityStrategy' => $this->getVisibilityChecker()->configToJs(),
						'selectedActor'            => $previouslySelectedActor,
						'saveFormConfig'           => $this->getSaveFormHandler()->getSaveFormConfig(),
						'preferenceCookiePath'     => ADMIN_COOKIE_PATH,
					]
				)
				->enqueue();
		}
	}

	public function enqueueTabStyles() {
		parent::enqueueTabStyles();
		$this->enqueueLocalStyle('ame-table-columns-ui', 'table-columns.css');
	}

	public function displaySettingsPage() {
		if ( $this->shouldRefreshTables ) {
			$settings = $this->loadSettings();
			if ( !$settings->get('isFirstRefreshDone', false) ) {
				$settings->set('isFirstRefreshDone', true);
				$settings->save();
			}

			$this->outputTemplate('columns-refresh');
		} else {
			parent::displaySettingsPage();
		}
	}

	public function handleSettingsForm($post = array()) {
		if ( !$this->userCanEditColumns() ) {
			wp_die('You do not have permission to edit table columns.');
		}

		$formData = $this->getSaveFormHandler()->processSubmission($post);
		$newSettings = $formData->getSettings();

		$s = new SchemaFactory();
		$inputSchema = $s->struct([
			'screens' => $s->record(
				$s->string()->min(1)->max(250),
				$s->struct([
					'customOrderEnabled' => $s->record($s->string(), $s->boolean()),
					'columns'            => $s->record(
						$s->string()->min(1)->max(250),
						$s->struct([
							'position'        => $s->int()->defaultValue(null),
							'enabledForActor' => $s->record($s->string(), $s->boolean()),
						])
					),
				])
			),
		]);

		$parsed = $inputSchema->parse($newSettings);
		if ( is_wp_error($parsed) ) {
			wp_die(esc_html($parsed->get_error_message() . ' [' . $parsed->get_error_code() . ']'));
		}

		//Optionally, we'll remove settings associated with roles or users that no longer exist.
		if ( $this->menuEditor->get_plugin_option('delete_orphan_actor_settings') ) {
			$cleaner = new \ameActorAccessCleaner();
		} else {
			$cleaner = null;
		}

		$settings = $this->loadSettings();
		$screensSetting = $settings->getSetting('screens');

		//Merge new screen settings.
		$mergedScreens = $screensSetting->getValue();
		//Delete screens that are not in the submitted data.
		$mergedScreens = array_intersect_key($mergedScreens, $parsed['screens']);
		foreach ($mergedScreens as $screenId => $screen) {
			$submittedScreen = \ameUtils::get($parsed, ['screens', $screenId], []);
			if ( !empty($submittedScreen['customOrderEnabled']) ) {
				$screen['customOrderEnabled'] = $submittedScreen['customOrderEnabled'];
				if ( $cleaner ) {
					$screen['customOrderEnabled'] = $cleaner->cleanUpDictionary($screen['customOrderEnabled']);
				}
			} else {
				unset($screen['customOrderEnabled']);
			}

			$columns = \ameUtils::get($screen, 'columns', []);
			//Delete columns that are not in the submitted data.
			$columns = array_intersect_key($columns, \ameUtils::get($submittedScreen, ['columns'], []));

			//Merge column settings.
			foreach ($columns as $columnId => $column) {
				$submittedColumn = \ameUtils::get($submittedScreen, ['columns', $columnId], []);
				if ( !empty($submittedColumn['enabledForActor']) ) {
					$column['enabledForActor'] = $submittedColumn['enabledForActor'];
					if ( $cleaner ) {
						$column['enabledForActor'] = $cleaner->cleanUpDictionary($column['enabledForActor']);
					}
				} else {
					unset($column['enabledForActor']);
				}
				$column['position'] = \ameUtils::get($submittedColumn, 'position', null);

				$columns[$columnId] = $column;
			}

			$screen['columns'] = $columns;
			$mergedScreens[$screenId] = $screen;
		}

		//Save the new settings.
		$validationResult = $screensSetting->validate(new \WP_Error(), $mergedScreens, true);
		if ( is_wp_error($validationResult) ) {
			wp_die(esc_html($parsed->get_error_message() . ' [' . $parsed->get_error_code() . ']'));
		}

		$sanitizedValue = $validationResult;
		$screensSetting->update($sanitizedValue);
		$settings->save();

		wp_safe_redirect($this->getTabUrl($formData->withSelectedActor(['updated' => 1])));
		exit;
	}

	/**
	 * @param string $screenId
	 * @param array $screenSettings
	 * @return string
	 */
	private function getScreenDisplayTitle($screenId, $screenSettings) {
		$screenTitle = $screenId;
		if ( !empty($screenSettings['menuTitle']) ) {
			$screenTitle = implode(' → ', $screenSettings['menuTitle']);
		}
		return $screenTitle;
	}

	/**
	 * @param string $columnId
	 * @param array $columnSettings
	 * @return string
	 */
	private function getColumnDisplayTitle($columnId, $columnSettings) {
		$columnDisplayTitle = wp_strip_all_tags(\ameUtils::get($columnSettings, 'title', ''));
		if ( $columnId === 'cb' ) {
			return '[Checkbox]';
		} else if ( empty($columnDisplayTitle) ) {
			return $columnId;
		}
		return $columnDisplayTitle;
	}

	/**
	 * @var null|\ameKnockoutSaveForm
	 */
	private $saveForm = null;

	/**
	 * @return \ameKnockoutSaveForm
	 */
	private function getSaveFormHandler() {
		if ( $this->saveForm === null ) {
			$this->saveForm = new \ameKnockoutSaveForm(
				self::SAVE_SETTINGS_ACTION,
				$this->getTabUrl(['noheader' => 1])
			);
		}
		return $this->saveForm;
	}

	//region Export/import
	public function getExportOptionLabel() {
		return 'Table columns';
	}

	public function exportSettings() {
		$screens = $this->loadSettings()->get(['screens'], []);
		if ( empty($screens) ) {
			return null;
		}

		return ['screens' => $screens];
	}
	//endregion

	/**
	 * @param HideableItemStore $store
	 * @return void
	 */
	public function registerHideableItems($store) {
		$screens = $this->loadSettings()->get(['screens'], []);
		if ( empty($screens) ) {
			return;
		}

		$columnsCategory = $store->getOrCreateCategory('table-columns', 'Table Columns', null, false);
		foreach ($screens as $screenId => $screen) {
			$screenCategory = $store->getOrCreateCategory(
				'table-columns/s/' . $screenId,
				$this->getScreenDisplayTitle($screenId, $screen),
				$columnsCategory,
				true
			);

			foreach ($screen['columns'] as $columnId => $column) {
				$store->addItem(
					$this->makeHideableItemId($screenId, $columnId),
					$this->getColumnDisplayTitle($columnId, $column),
					[$screenCategory],
					null,
					!empty($column['enabledForActor']) ? $column['enabledForActor'] : [],
					self::HIDEABLE_ITEM_COMPONENT
				);
			}
		}
	}

	/**
	 * @param array $errors
	 * @param array $items
	 * @return array
	 */
	public function saveHideableItems($errors, $items) {
		$settings = $this->loadSettings();
		$screens = $settings->get(['screens'], []);
		if ( empty($screens) ) {
			return $errors;
		}

		$anySettingsModified = false;
		foreach ($screens as $screenId => $screen) {
			if ( empty($screen['columns']) ) {
				continue;
			}

			foreach ($screen['columns'] as $columnId => $column) {
				$id = $this->makeHideableItemId($screenId, $columnId);
				if ( isset($items[$id]) ) {
					$oldEnabled = \ameUtils::get($column, 'enabledForActor', []);
					$newEnabled = isset($items[$id]['enabled']) ? $items[$id]['enabled'] : [];
					if ( !\ameUtils::areAssocArraysEqual($newEnabled, $oldEnabled) ) {
						$settings->set(
							['screens', $screenId, 'columns', $columnId, 'enabledForActor'],
							$newEnabled
						);
						$anySettingsModified = true;
					}
				}
			}
		}

		if ( $anySettingsModified ) {
			$settings->save();
		}

		return $errors;
	}

	private function makeHideableItemId($screenId, $columnId) {
		$screenId = str_replace('/', '--', $screenId);
		$columnId = str_replace('/', '--', $columnId);

		return 'table-columns/s/' . $screenId . '/' . $columnId;
	}
}