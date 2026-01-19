<?php

namespace YahnisElsts\AdminMenuEditor\AdminMenuColors;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\ContentToggle;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Control;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\TabbedPanelRenderer;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\StructSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\LazyArrayStorage;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\MenuConfigurationWrapper;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\DynamicStylesheets\MenuScopedStylesheetHelper;
use YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SingularSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class MenuColorsModule extends \ameModule {
	const COLOR_STYLE_HANDLE = 'ame-custom-menu-colors';

	protected $settings = null;

	const mainScriptHandle = 'ame-mc-menu-colors';

	const MENU_COLOR_VARIABLES = [
		'base-color'      => 'Background',
		'text-color'      => 'Text',
		'highlight-color' => 'Highlight',
		'icon-color'      => 'Icon',

		'menu-highlight-text'       => 'Highlight text',
		'menu-highlight-icon'       => 'Highlight icon',
		'menu-highlight-background' => 'Highlight background',

		'menu-current-text'       => 'Current text',
		'menu-current-icon'       => 'Current icon',
		'menu-current-background' => 'Current background',

		'menu-submenu-text'               => 'Submenu text',
		'menu-submenu-background'         => 'Submenu background',
		'menu-submenu-focus-text'         => 'Submenu highlight text',
		'menu-submenu-focus-background'   => 'Submenu highlight background',
		'menu-submenu-current-text'       => 'Submenu current text',
		'menu-submenu-current-background' => 'Submenu current background',

		'menu-bubble-text'               => 'Bubble text',
		'menu-bubble-background'         => 'Bubble background',
		'menu-bubble-current-text'       => 'Bubble current text',
		'menu-bubble-current-background' => 'Bubble current background',
	];

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		add_filter('admin_menu_editor-editor_script_dependencies', [$this, 'addEditorDependencies']);
		add_action('admin_menu_editor-enqueue_styles-editor', [$this, 'enqueueEditorStyles']);
		add_action('admin_menu_editor-footer-editor', [$this, 'outputDialog']);

		add_filter('ame_pre_set_custom_menu', [$this, 'postProcessMenuColors']);
		add_action('admin_enqueue_scripts', [$this, 'maybeSetIconColorHook']);
		add_action('init', [$this, 'registerMenuColorStyle']);

		add_filter('admin_menu_editor-aux_data_config', [$this, 'addAuxDataConfig']);

		add_action('admin_menu_editor-ms_ui_structure', [$this, 'addColorsToMenuStyler']);
		add_action('admin_menu_editor-ms_ui_setting_defaults', [$this, 'addDefaultsToMenuStyler']);
		add_action('admin_menu_editor-ms_ui_style_generators', [$this, 'addStyleGeneratorMenuStyler']);
	}

	protected function isEnabledForRequest() {
		return is_admin() && parent::isEnabledForRequest();
	}

	public function outputDialog() {
		$structure = $this->getInterfaceStructure();
		$renderer = new TabbedPanelRenderer(['ame-tp-height-100']);
		require __DIR__ . '/color-dialog.php';
		$renderer->enqueueDependencies();
	}

	protected function getInterfaceStructure($globalPresetVisible = true, $context = '') {
		$settings = $this->getSettings();
		$b = $settings->elementBuilder();

		$structure = $b->structure();
		$colorSection = $b->section('Colors')
			->classes('ame-mc-color-section')
			->id($context . 'ame-mc-menuColors-section');

		$colorSection->add(
			$b->group(
				'Color Presets',
				new ColorPresetDropdown(
					[$settings->getSetting('colorPresets')],
					[
						'globalPresetVisible' => $globalPresetVisible,
						'id'                  => $context . 'ame-mc-colorPresets',
					]
				)
			)
				->params(['fullWidth' => true])
				->classes('ame-mc-color-presets-group')
		);

		$colorGroup = $b->group()
			->params(['fullWidth' => true])
			->classes('ame-mc-color-group');

		$basicColors = [
			'base-color'      => true,
			'text-color'      => true,
			'highlight-color' => true,
			'icon-color'      => true,
		];
		foreach (array_keys(self::MENU_COLOR_VARIABLES) as $variable) {
			$colorGroup->add(
				$b->colorPicker('activePreset.' . $variable)
					->inputAttr(['data-color-variable' => $variable])
					->asGroup()
					->conditionalClasses(['ame-mc-advanced-color' => !isset($basicColors[$variable])])
			);
		}
		$colorSection->add($colorGroup);

		$colorSection->add(
			$b->control(ContentToggle::class)
				->params([
					'itemSelector'         => '.ame-mc-advanced-color',
					'toggleParentSelector' => '.ame-mc-color-section',
					'visibleStateText'     => 'Hide advanced options',
					'hiddenStateText'      => 'Show advanced options',
					'hiddenByDefault'      => true,
				])
				->asGroup()
				->params(['fullWidth' => true])
				->classes('ame-mc-color-toggle-group')
		);

		$structure->add($colorSection);
		return $structure->build();
	}

	protected function getSettings($menuConfigId = null) {
		if ( $this->settings !== null ) {
			//Note: Be careful not to load two different configs in the same request.
			//Only the first one will be used.
			return $this->settings;
		}

		if ( $menuConfigId !== null ) {
			$helper = MenuScopedStylesheetHelper::getInstance($this->menuEditor);
			$menuConfigId = $helper->getConfigIdFromAjaxRequest();
		}

		$this->settings = new MenuColorSettings(MenuConfigurationWrapper::getStore($menuConfigId));
		return $this->settings;
	}

	public function addEditorDependencies($dependencies) {
		ScriptDependency::create(
			plugins_url('admin-menu-colors.js', __FILE__),
			self::mainScriptHandle
		)
			->addDependencies('jquery', 'ame-customizable-settings')
			->setTypeToModule()
			->register();

		$dependencies[] = self::mainScriptHandle;
		return $dependencies;
	}

	public function enqueueEditorStyles() {
		wp_enqueue_auto_versioned_style(
			'ame-menu-colors-editor-css',
			plugins_url('menu-colors-ui.css', __FILE__)
		);
	}

	public function addAuxDataConfig($config) {
		$childKey = MenuColorSettings::CONFIG_CHILD_KEY;
		$prefix = MenuColorSettings::SETTING_ID_PREFIX;

		$config['keys'][$childKey] = $prefix;
		$config['settingIdMap'][$prefix . 'colorPresets'] = $childKey;
		$config['prefixMap'][$prefix . 'activePreset'] = [$childKey, '[global]'];
		return $config;
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Builders\InterfaceBuilder $structure
	 * @return void
	 */
	public function addColorsToMenuStyler($structure) {
		$myStructure = $this->getInterfaceStructure(false, 'ms-');
		$colorSection = $myStructure->findChildById('ms-ame-mc-menuColors-section');
		if ( $colorSection ) {
			$structure->addAfter($colorSection, 'ame-ms-menuBar-section');
		}
	}

	public function addDefaultsToMenuStyler($defaults) {
		return array_merge($defaults, $this->getSettings()->getRecursiveDefaultsForJs());
	}

	public function addStyleGeneratorMenuStyler($generators) {
		$generators[] = $this->getGlobalColorGenerator($this->getSettings());
		return $generators;
	}

	private function getGlobalColorGenerator(MenuColorSettings $settings) {
		$g = $this->getPartialStyleGenerator();

		//Disable the color stylesheet while in preview mode.
		$g->setStylesheetsToDisableOnPreview(['link#ame-custom-menu-colors-css']);

		$this->setColorVariablesOn($g, function ($variableName) use ($settings) {
			return $settings->getSetting('activePreset.' . $variableName);
		});

		return $g;
	}

	/**
	 * @param StyleGenerator $g
	 * @param callable $colorValueGetter $variableName => string|Setting|null
	 * @return void
	 */
	private function setColorVariablesOn(StyleGenerator $g, $colorValueGetter) {
		//For individual items, the closure could return the color value.
		//Setting for the global admin menu, string for individual items.
		$g->setVariables([
			'base-color'      => [$colorValueGetter('base-color')],
			'text-color'      => [$colorValueGetter('text-color')],
			'highlight-color' => [$colorValueGetter('highlight-color')],
			'icon-color'      => [
				$colorValueGetter('icon-color'),
				$g->editHexAsHsl($g->variable('base-color'), null, 0.07, 0.95),
			],

			'menu-highlight-text'       => [
				$colorValueGetter('menu-highlight-text'),
				$g->variable('text-color'),
			],
			'menu-highlight-icon'       => [
				$colorValueGetter('menu-highlight-icon'),
				$g->variable('text-color'),
			],
			'menu-highlight-background' => [
				$colorValueGetter('menu-highlight-background'),
				$g->variable('highlight-color'),
			],

			'menu-current-text'       => [
				$colorValueGetter('menu-current-text'),
				$g->variable('menu-highlight-text'),
			],
			'menu-current-icon'       => [
				$colorValueGetter('menu-current-icon'),
				$g->variable('menu-highlight-icon'),
			],
			'menu-current-background' => [
				$colorValueGetter('menu-current-background'),
				$g->variable('menu-highlight-background'),
			],

			'menu-submenu-text'               => [
				$colorValueGetter('menu-submenu-text'),
				$g->mixColors(
					$g->variable('base-color'),
					$g->variable('text-color'),
					//WP uses a 30% mixing weight, but due to apparent implementation differences
					//that corresponds to 29.5% when using phpColor or jquery-color.
					29.5
				),
			],
			'menu-submenu-background'         => [
				$colorValueGetter('menu-submenu-background'),
				$g->darken($g->variable('base-color'), 7),
			],
			'menu-submenu-focus-text'         => [
				$colorValueGetter('menu-submenu-focus-text'),
				$g->variable('highlight-color'),
			],
			'menu-submenu-focus-background'   => [
				$colorValueGetter('menu-submenu-focus-background'),
			],
			'menu-submenu-current-text'       => [
				$colorValueGetter('menu-submenu-current-text'),
				$g->variable('text-color'),
			],
			'menu-submenu-current-background' => [
				$colorValueGetter('menu-submenu-current-background'),
			],

			'menu-bubble-text'               => [
				$colorValueGetter('menu-bubble-text'),
				$g->variable('text-color'),
			],
			'menu-bubble-background'         => [
				$colorValueGetter('menu-bubble-background'),
			],
			'menu-bubble-current-text'       => [
				$colorValueGetter('menu-bubble-current-text'),
				$g->variable('text-color'),
			],
			'menu-bubble-current-background' => [
				$colorValueGetter('menu-bubble-current-background'),
				$g->variable('menu-submenu-background'),
			],
		]);
	}

	private function getPartialStyleGenerator($isForAllMenus = true) {
		$g = new StyleGenerator();

		if ( $isForAllMenus ) {
			$li = '#adminmenu > li';
		} else {
			$li = 'li#menu-id-placeholder';
		}

		$g->addRuleSet(
			[$li],
			['background' => $g->variable('base-color')]
		);

		$g->addRuleSet(
			[$li . ' a'],
			['color' => $g->variable('text-color')]
		);

		$g->addRuleSet(
			[$li . ' div.wp-menu-image:before'],
			['color' => $g->variable('icon-color')]
		);

		$g->addRuleSet(
			[
				$li . ' a:hover',
				$li . '.menu-top:hover',
				$li . '.opensub > a.menu-top',
				$li . ' > a.menu-top:focus',
			],
			['color' => $g->variable('menu-highlight-text')]
		);

		$g->addRuleSet(
			[
				$li . '.menu-top:hover',
				$li . '.menu-top > a.menu-top:hover', //Override the "#adminmenu a:hover" rule in admin color schemes.
				$li . '.opensub > a.menu-top',
				$li . ' > a.menu-top:focus',
			],
			['background-color' => $g->variable('menu-highlight-background')]
		);

		$g->addRuleSet(
			[
				$li . '.menu-top:hover div.wp-menu-image:before',
				$li . '.menu-top > a:focus div.wp-menu-image:before',
				$li . '.opensub > a.menu-top div.wp-menu-image:before',
			],
			['color' => $g->variable('menu-highlight-icon')]
		);

		$g->addRuleSet(
			[
				$li . ' .wp-submenu',
				$li . '.wp-has-current-submenu .wp-submenu',
				$li . '.wp-has-current-submenu.opensub .wp-submenu',
				'.folded ' . $li . '.wp-has-current-submenu .wp-submenu a.wp-has-current-submenu:focus + .wp-submenu',
			],
			['background' => $g->variable('menu-submenu-background')]
		);

		$g->addRuleSet(
			[
				$li . '.wp-has-submenu.wp-not-current-submenu.opensub:hover:after',
			],
			['border-right-color' => $g->variable('menu-submenu-background')]
		);

		$g->addRuleSet(
			[$li . ' .wp-submenu .wp-submenu-head'],
			['color' => $g->variable('menu-submenu-text')]
		);

		$g->addRuleSet(
			[
				$li . ' .wp-submenu a',
				$li . '.wp-has-current-submenu .wp-submenu a',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu a',
				'.folded ' . $li . '.wp-has-current-submenu .wp-submenu a',
				$li . '.wp-has-current-submenu.opensub .wp-submenu a',
			],
			['color' => $g->variable('menu-submenu-text')]
		);

		$g->addRuleSet(
			[
				$li . ' .wp-submenu a:focus',
				$li . ' .wp-submenu a:hover',
				$li . '.wp-has-current-submenu .wp-submenu a:focus',
				$li . '.wp-has-current-submenu .wp-submenu a:hover',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu a:focus',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu a:hover',
				'.folded ' . $li . '.wp-has-current-submenu .wp-submenu a',
				$li . '.wp-has-current-submenu.opensub .wp-submenu a:focus',
				$li . '.wp-has-current-submenu.opensub .wp-submenu a:hover',
			],
			[
				'color'            => $g->variable('menu-submenu-focus-text'),
				'background-color' => $g->variable('menu-submenu-focus-background'),
			]
		);

		$g->addRuleSet(
			[
				$li . ' .wp-submenu li.current a',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu li.current a',
				$li . '.wp-has-current-submenu.opensub .wp-submenu li.current a',
			],
			[
				'color'            => $g->variable('menu-submenu-current-text'),
				'background-color' => $g->variable('menu-submenu-current-background'),
			]
		);

		$g->addRuleSet(
			[
				$li . ' .wp-submenu li.current a:hover',
				$li . ' .wp-submenu li.current a:focus',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu li.current a:hover',
				$li . ' a.wp-has-current-submenu:focus + .wp-submenu li.current a:focus',
				$li . '.wp-has-current-submenu.opensub .wp-submenu li.current a:hover',
				$li . '.wp-has-current-submenu.opensub .wp-submenu li.current a:focus',
			],
			[
				'color'            => $g->variable('menu-submenu-focus-text'),
				'background-color' => $g->variable('menu-submenu-focus-background'),
			]
		);

		$g->addRuleSet(
			[
				$li . '.current a.menu-top',
				$li . '.wp-has-current-submenu a.wp-has-current-submenu',
				$li . '.wp-has-current-submenu .wp-submenu .wp-submenu-head',
				'.folded ' . $li . '.current.menu-top',
			],
			[
				'color'      => $g->variable('menu-current-text'),
				'background' => $g->variable('menu-current-background'),
			]
		);

		$currentIconSelectors = [
			$li . '.wp-has-current-submenu div.wp-menu-image:before',
			'#adminmenu a.current:hover div.wp-menu-image:before',
			$li . '.current div.wp-menu-image::before',
			$li . '.wp-has-current-submenu a:focus div.wp-menu-image:before',
			$li . '.wp-has-current-submenu.opensub div.wp-menu-image:before',
			$li . ':hover div.wp-menu-image:before',
			$li . ' a:focus div.wp-menu-image:before',
			$li . '.opensub div.wp-menu-image:before',
			'.ie8 ' . $li . '.opensub div.wp-menu-image:before',
		];
		if ( $isForAllMenus ) {
			$currentIconSelectors[] = '#adminmenu a.current:hover div.wp-menu-image:before';
		}
		$g->addRuleSet(
			$currentIconSelectors,
			['color' => $g->variable('menu-current-icon')]
		);

		$g->addRuleSet(
			[
				$li . ' .awaiting-mod',
				$li . ' .update-plugins',
			],
			[
				'color'      => $g->variable('menu-bubble-text'),
				'background' => $g->variable('menu-bubble-background'),
			]
		);

		$g->addRuleSet(
			[
				$li . ' .current a .awaiting-mod',
				$li . ' a.wp-has-current-submenu .update-plugins',
				$li . ':hover a .awaiting-mod',
				$li . '.menu-top:hover > a .update-plugins',
			],
			[
				'color'      => $g->variable('menu-bubble-current-text'),
				'background' => $g->variable('menu-bubble-current-background'),
			]
		);

		if ( $isForAllMenus ) {
			$g->addRuleSet(
				[
					'#adminmenuback',
					'#adminmenuwrap',
					'#adminmenu',
				],
				['background-color' => $g->variable('base-color')]
			);
		}

		return $g;
	}

	/**
	 * Generate and cache custom menu icon colors.
	 *
	 * Also sets the color CSS modification time to the current time.
	 *
	 * @param array $customMenu Admin menu in the internal format.
	 * @return array Modified menu.
	 */
	public function postProcessMenuColors($customMenu) {
		if ( empty($customMenu) || !is_array($customMenu) ) {
			return $customMenu;
		}

		//Load style settings from the provided menu config, not from the database.
		$currentSettings = new MenuColorSettings(new LazyArrayStorage($customMenu));
		//Generate and save the custom icon colors, if any. WP needs them for SVG icons.
		$globalIconColors = [];
		$globalGenerator = null;
		if (
			isset($customMenu[MenuColorSettings::CONFIG_CHILD_KEY]['[global]'])
			&& !empty($customMenu[MenuColorSettings::CONFIG_CHILD_KEY]['[global]'])
		) {
			$globalGenerator = $this->getGlobalColorGenerator($currentSettings);
			$globalIconColors = [
				'base'    => $globalGenerator->resolveVariable('menu-icon', '#a0a5aa'),
				'focus'   => $globalGenerator->resolveVariable('menu-highlight-icon', '#00a0d2'),
				'current' => $globalGenerator->resolveVariable('menu-current-icon', '#fff'),
			];
		}

		//For backwards compatibility reasons, some details are stored
		//in separate top level keys. That's how older versions did it.
		if ( !empty($globalIconColors) ) {
			$customMenu['icon_color_overrides'] = $globalIconColors;
		} else {
			$customMenu['icon_color_overrides'] = null;
		}

		list($generatedCss, $modifiedMenu) = $this->generateMenuColorStyle(
			$currentSettings,
			$customMenu,
			$globalGenerator
		);
		if ( $modifiedMenu !== null ) {
			$customMenu = $modifiedMenu;
		}

		//Update the modification time if there are any custom icon colors or custom CSS.
		//This is used to refresh the CSS cache. Since individual menu items can have
		//their own custom colors, simply checking for global presets is not enough.
		if ( !empty($globalIconColors) || !empty($generatedCss) ) {
			$customMenu['color_css_modified'] = time();
		} else {
			$customMenu['color_css_modified'] = 0;
		}

		return $customMenu;
	}

	/**
	 * Add the hook that will override icon colors.
	 *
	 * This needs to happen only after the menu configuration has already been
	 * loaded, so it's implemented as a separate hook.
	 */
	public function maybeSetIconColorHook() {
		$customMenu = $this->menuEditor->load_custom_menu();
		if ( isset($customMenu, $customMenu['icon_color_overrides']) ) {
			add_action('admin_head', [$this, 'overrideMenuIconColorScheme'], 9);
		}
	}

	/**
	 * Replace the icon colors in the current admin color scheme with the custom colors
	 * set by the user. This is necessary to make SVG icons display in the right color.
	 */
	public function overrideMenuIconColorScheme() {
		global $_wp_admin_css_colors;

		$customMenu = $this->menuEditor->load_custom_menu();
		if ( !isset($customMenu['icon_color_overrides']) ) {
			return;
		}

		$colorScheme = get_user_option('admin_color');
		if ( empty($_wp_admin_css_colors[$colorScheme]) ) {
			$colorScheme = 'fresh';
		}

		$customColors = array_merge(
			[
				'base'    => '#a0a5aa',
				'focus'   => '#00a0d2',
				'current' => '#fff',
			],
			$customMenu['icon_color_overrides']
		);

		if ( isset($_wp_admin_css_colors[$colorScheme]) ) {
			$_wp_admin_css_colors[$colorScheme]->icon_colors = $customColors;
		}
	}

	/**
	 * Register the dynamically generated stylesheet that applies our custom menu colors.
	 */
	public function registerMenuColorStyle() {
		$helper = MenuScopedStylesheetHelper::getInstance($this->menuEditor);
		$helper->addStylesheet(
			self::COLOR_STYLE_HANDLE,
			function ($menuConfigId) {
				return [
					//Timestamp
					function () use ($menuConfigId) {
						$customMenu = $this->menuEditor->load_custom_menu($menuConfigId);
						return isset($customMenu, $customMenu['color_css_modified'])
							? $customMenu['color_css_modified']
							: 0;
					},
					//CSS
					function () use ($menuConfigId) {
						//To make preview work, we must use the same setting objects
						//that were registered with the customizer.
						if (
							apply_filters('admin_menu_editor-is_preview_frame', false)
							&& $this->settings
						) {
							$settings = $this->settings;
						} else {
							$settings = $this->getSettings($menuConfigId);
						}

						list($css,) = $this->generateMenuColorStyle(
							$settings,
							$this->menuEditor->load_custom_menu($menuConfigId)
						);
						return $css;
					},
				];
			},
			'ame-menu-style-bundle'
		);
	}

	/**
	 * @param MenuColorSettings|null $currentSettings
	 * @param array $customMenu
	 * @param StyleGenerator|null $globalGenerator
	 * @return array{string, array|null} A CSS string and either the modified custom menu or NULL if no changes were made.
	 */
	private function generateMenuColorStyle($currentSettings, $customMenu, $globalGenerator = null) {
		$cssBlocks = [];

		if ( $currentSettings ) {
			if ( !$globalGenerator ) {
				$globalGenerator = $this->getGlobalColorGenerator($currentSettings);
			}
			$globalCss = $globalGenerator->generateCss();
			if ( !empty($globalCss) ) {
				$cssBlocks[] = $globalCss;
			}
		}

		$colorizedMenuCount = 0;
		if ( !empty($customMenu['tree']) ) {
			$usedIds = [];
			$generator = $this->getPartialStyleGenerator(false);

			foreach ($customMenu['tree'] as &$item) {
				if ( empty($item['colors']) ) {
					continue;
				}
				$colorizedMenuCount++;

				//Each item needs to have a unique ID so that we can target it in CSS.
				//Using a class would be cleaner, but the selectors wouldn't have enough
				//specificity to override WP defaults.
				$id = \ameMenuItem::get($item, 'hookname');
				if ( empty($id) || isset($usedIds[$id]) ) {
					$id = (empty($id) ? 'ame-colorized-item' : $id) . '-';
					$id .= $colorizedMenuCount . '-t' . time();
					$item['hookname'] = $id;
				}
				$usedIds[$id] = true;

				$subType = \ameMenuItem::get($item, 'sub_type');
				if ( $subType === 'heading' ) {
					$extraSelectors = ['.ame-menu-heading-item'];
				} else {
					$extraSelectors = [];
				}

				$this->setColorVariablesOn($generator, function ($variableName) use ($item) {
					return \ameMenuItem::get($item['colors'], $variableName, '');
				});

				$itemCss = $generator->generateCss();
				if ( !empty($itemCss) ) {
					//Replace the placeholder item ID with the real ID.
					//Sanitization note: WordPress replaces special characters in the ID
					//with dashes before output. See /wp-admin/menu-header.php, line #110
					//in WP 5.5-alpha.
					$sanitizedId = preg_replace('|[^a-zA-Z0-9_:.]|', '-', $id);
					//Headings need more specific selectors to override heading defaults.
					$replacement = implode('', $extraSelectors) . '#' . $sanitizedId;
					$itemCss = str_replace('#menu-id-placeholder', $replacement, $itemCss);

					$cssBlocks[] = sprintf(
						'/* %1$s (%2$s) */',
						str_replace('*/', ' ', \ameMenuItem::get($item, 'menu_title', 'Untitled menu')),
						str_replace('*/', ' ', \ameMenuItem::get($item, 'file', '(no URL)'))
					);
					$cssBlocks[] = $itemCss;
				}
			}
		}

		$css = implode("\n", $cssBlocks);

		if ( $colorizedMenuCount > 0 ) {
			$modifiedMenu = $customMenu;
		} else {
			$modifiedMenu = null;
		}

		return [$css, $modifiedMenu];
	}
}

class MenuColorSettings extends AbstractSettingsDictionary {
	const SETTING_ID_PREFIX = 'ws_menu_colors--';
	const CONFIG_CHILD_KEY = 'color_presets';

	public function __construct(StorageInterface $store) {
		parent::__construct($store, self::SETTING_ID_PREFIX);
	}

	protected function createDefaults() {
		return []; //No custom colors by default.
	}

	protected function createSettings() {
		$s = new SchemaFactory();
		$presetSlot = $this->store->buildSlot(self::CONFIG_CHILD_KEY);

		$colorPresets = new SingularSetting(
			$s->record(
				$s->string()->stripTags()->trim()->min(0)->max(250, true),
				$s->record(
					$s->enum(MenuColorsModule::MENU_COLOR_VARIABLES),
					new Schemas\Color()
				)
			),
			self::SETTING_ID_PREFIX . 'colorPresets',
			$presetSlot
		);

		$colorFieldSchemas = array_map(function ($label) use ($s) {
			return $s->cssColor($label)->noTransparent()->settingParams([
				'deleteWhenBlank'     => true,
				'supportsPostMessage' => true,
			]);
		}, MenuColorsModule::MENU_COLOR_VARIABLES);

		//The active preset usually refers to the "[global]" preset in the color
		//preset collection. It's registered as a separate setting for convoluted
		//backwards compatibility reasons.
		$activePreset = new StructSetting(
			$s->struct($colorFieldSchemas),
			self::SETTING_ID_PREFIX . 'activePreset',
			$presetSlot->buildSlot('[global]')
		);
		$activePreset->addTags(AbstractSetting::TAG_ADMIN_THEME);

		return [$colorPresets, $activePreset];
	}
}

class ColorPresetDropdown extends Control {
	protected $hasPrimaryInput = true;

	private $isGlobalPresetVisible = true;

	public function __construct($settings = [], $params = []) {
		parent::__construct($settings, $params);

		if ( isset($params['globalPresetVisible']) ) {
			$this->isGlobalPresetVisible = (bool)$params['globalPresetVisible'];
		}
	}

	public function renderContent(Renderer $renderer) {
		$dropdownId = $this->getPrimaryInputId();

		//Container for the dropdown and the "Delete" button.
		echo HtmlHelper::tag(
			'div',
			[
				'class'                      => 'ame-mc-color-preset-control',
				'id'                         => $dropdownId . '-container',
				'data-bind'                  => $this->makeKoDataBind([
					'ameObservableChangeEvents' => '{ observable: '
						. $this->getKoObservableExpression($this->mainSetting)
						. ', sendInitEvent: true }',
				]),
				'data-global-preset-visible' => $this->isGlobalPresetVisible ? '1' : '0',
			]
		);

		echo HtmlHelper::tag(
			'label',
			[
				'for'   => $dropdownId,
				'class' => 'hidden',
			],
			'Presets'
		);

		//The dropdown will mostly be populated by JavaScript.
		$optionTags = [
			HtmlHelper::tag(
				'option',
				[
					'value'    => '',
					'selected' => 'selected',
					'disabled' => 'disabled',
					'class'    => 'ame-meta-option',
				],
				'Select a preset'
			),
		];
		echo HtmlHelper::tag(
			'select',
			[
				'id'    => $dropdownId,
				'class' => 'ame-mc-preset-dropdown',
			],
			implode("\n", $optionTags)
		);

		//Delete button/link.
		echo ' ', HtmlHelper::tag(
			'a',
			[
				'href'  => '#',
				'class' => 'hidden ame-mc-delete-color-preset',
			],
			'Delete preset'
		);

		echo '</div>';

		//Enqueue our script if not already done.
		wp_enqueue_script(MenuColorsModule::mainScriptHandle);
	}
}