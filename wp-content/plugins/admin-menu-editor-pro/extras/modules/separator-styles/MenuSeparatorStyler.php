<?php

namespace YahnisElsts\AdminMenuEditor\MenuSeparatorStyles;

use ameMenu;
use WPMenuEditor;
use ameMultiDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\SettingFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\AlignmentSelector;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Container;
use YahnisElsts\AdminMenuEditor\Customizable\SettingCondition;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\MenuConfigurationWrapper;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\DynamicStylesheets\MenuScopedStylesheetHelper;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Controls\BorderStyleSelector;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\Margins;
use YahnisElsts\AdminMenuEditor\StyleGenerator\CssRuleSet;
use YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator;

class MenuSeparatorStyler {
	const COLOR_STYLE_HANDLE = 'ame-custom-separator-styles';

	private $menuEditor;

	/**
	 * @var SeparatorStyleSettings|null
	 */
	private $settings = null;

	/**
	 * MenuSeparatorStyler constructor.
	 *
	 * @param WPMenuEditor $menuEditor
	 */
	public function __construct($menuEditor) {
		$this->menuEditor = $menuEditor;
		ameMenu::add_custom_loader([$this, 'loadSeparatorSettings']);

		if ( !is_admin() ) {
			return;
		}

		add_filter('ame_pre_set_custom_menu', [$this, 'addSeparatorModTimeToConfiguration']);
		add_action('init', [$this, 'registerSeparatorStylesheet']);

		add_filter('admin_menu_editor-aux_data_config', [$this, 'addAuxDataConfig']);

		add_action('admin_menu_editor-ms_ui_structure', [$this, 'addSeparatorsToMenuStyler']);
		add_action('admin_menu_editor-ms_ui_setting_defaults', [$this, 'addDefaultsToMenuStyler']);
		add_action('admin_menu_editor-ms_ui_style_generators', [$this, 'addStyleGeneratorToMenuStyler']);
	}

	public function addSeparatorModTimeToConfiguration($customMenu) {
		if ( empty($customMenu) || !is_array($customMenu) ) {
			return $customMenu;
		}

		if ( empty($customMenu[SeparatorStyleSettings::CONFIG_KEY]) ) {
			unset($customMenu['separator_css_modified']);
			return $customMenu;
		}

		//For backwards compatibility reasons, the separator setting modification
		//timestamp is stored in the root of the menu configuration.
		$customMenu['separator_css_modified'] = time();

		return $customMenu;
	}

	public function loadSeparatorSettings($menuConfig, $storedConfig) {
		//Copy separator settings.
		if ( isset($storedConfig['separators']) ) {
			$menuConfig['separators'] = $storedConfig['separators'];

			//Translate margin settings to the new format. Aliases only help
			//when reading settings in PHP, but we also use them in JavaScript.
			$missing = '__missing';
			foreach (SeparatorStyleSettings::getMarginAliases() as $newPath => $oldPath) {
				$fullNewPath = 'separators.' . $newPath;
				$fullOldPath = 'separators.' . $oldPath;

				$oldValue = ameMultiDictionary::get($menuConfig, $fullOldPath, $missing);
				if ( $oldValue !== $missing ) {
					if ( ameMultiDictionary::get($menuConfig, $fullNewPath, $missing) === $missing ) {
						ameMultiDictionary::set($menuConfig, $fullNewPath, $oldValue);
					}
					ameMultiDictionary::delete($menuConfig, $fullOldPath);
				}
			}
		}
		//Copy the modification timestamp.
		if ( isset($storedConfig['separator_css_modified']) ) {
			$menuConfig['separator_css_modified'] = intval($storedConfig['separator_css_modified']);
		}
		return $menuConfig;
	}

	protected function getSettings($menuConfigId = null) {
		if ( $this->settings !== null ) {
			return $this->settings;
		}

		if ( $menuConfigId !== null ) {
			$helper = MenuScopedStylesheetHelper::getInstance($this->menuEditor);
			$menuConfigId = $helper->getConfigIdFromAjaxRequest();
		}

		$this->settings = new SeparatorStyleSettings(
			MenuConfigurationWrapper::getStore($menuConfigId)
				->buildSlot(SeparatorStyleSettings::CONFIG_KEY)
		);
		return $this->settings;
	}

	protected function getInterfaceStructure() {
		$settings = $this->getSettings();
		$b = $settings->elementBuilder();

		$mainSection = $b->section(
			'Separators',
			$b->auto('customSettingsEnabled')->asGroup()->params(['fullWidth' => true])
		)->id('ame-sep-Separators-section');

		$sectionLabels = [
			'topLevelSeparators' => 'Top level separators',
			'submenuSeparators'  => 'Submenu separators',
		];

		$submenuEnabledCondition = new SettingCondition(
			$settings->findSetting('useTopLevelSettingsForSubmenus'),
			SettingCondition::IS_FALSY,
			false
		);

		foreach (SeparatorStyleSettings::SEPARATOR_TYPE_KEYS as $separatorType) {
			$prefix = $separatorType . '.';

			//Put each separator type in its own section.
			$subSection = $b->section(
				isset($sectionLabels[$separatorType]) ? $sectionLabels[$separatorType] : $separatorType
			);
			//Show the "use top level settings" checkbox in the submenu section.
			if ( $separatorType === 'submenuSeparators' ) {
				$subSection->add(
					$b->auto('useTopLevelSettingsForSubmenus')->asGroup()->params(['fullWidth' => true])
				);
			}
			$condition = ($separatorType === 'submenuSeparators') ? $submenuEnabledCondition : true;

			$subSection->add(
				$b->radioGroup($prefix . 'colorType')
					->choiceChild('custom', $b->auto($prefix . 'customColor'))
					->classes('ame-rg-with-color-pickers')
					->enabled($condition),
				$b->control(BorderStyleSelector::class, $prefix . 'borderStyle')->enabled($condition),
				$b->auto($prefix . 'height')->params(['step' => 1])->enabled($condition),
				$b->radioGroup($prefix . 'widthStrategy')
					->enabled($condition)
					->choiceChild(
						'percentage',
						$b->auto($prefix . 'widthInPercent')->params(['step' => 1])
					)
					->choiceChild(
						'fixed',
						$b->auto($prefix . 'widthInPixels')->params(['step' => 1])
					),
				$b->boxDimensions($prefix . 'margin')->enabled($condition),
				$b->control(AlignmentSelector::class, $settings->findSetting($prefix . 'alignment'))
					->enabled($condition)
			);
			$mainSection->add($subSection);
		}

		$structure = $b->structure($mainSection);
		return $structure->build();
	}

	private function getStyleGenerator(SeparatorStyleSettings $settings) {
		$g = new StyleGenerator();
		$g->setStylesheetsToDisableOnPreview(['link#' . self::COLOR_STYLE_HANDLE . '-css']);

		$customSettingsEnabled = $settings->getSetting('customSettingsEnabled');

		$this->addSeparatorRuleSetsFor(
			$g,
			$settings,
			'topLevelSeparators',
			function ($key) use ($settings) {
				return $settings->getSetting('topLevelSeparators.' . $key);
			},
			'#adminmenumain #adminmenu li.wp-menu-separator .separator',
			'#adminmenumain #adminmenu li.wp-menu-separator'
		);

		/* The expected DOM hierarchy for submenu separators is:
		 * li.wp-menu-separator.ws-submenu-separator-wrap > a.wp-menu-separator > hr.ws-submenu-separator
		 *
		 * The usual rule generator only handles the parent (li) and the separator body (hr),
		 * so we'll need to add a special rule for the middle <a> element
		 */
		$g->addCondition(
			$g->ifTruthy($customSettingsEnabled),
			new CssRuleSet(
				['#adminmenumain #adminmenu .wp-submenu a.wp-menu-separator'],
				['padding' => 0, 'margin' => 0]
			)
		);

		//Submenus use either their own setting or top-level settings. To make that
		//possible, let's add variables that will return either the top-level or submenu
		//setting depending on the state of the useTopLevelSettingsForSubmenus setting.
		$typeSettingNames = [
			'colorType',
			'customColor',
			'borderStyle',
			'height',
			'widthStrategy',
			'widthInPercent',
			'widthInPixels',
			'alignment',
			//Margins are handled separately because we need their children.
		];
		$useTopLevelSettings = $settings->getSetting('useTopLevelSettingsForSubmenus');
		$submenuVarPrefix = 'submenu_';
		foreach ($typeSettingNames as $name) {
			$g->setVariable(
				$submenuVarPrefix . $name,
				$g->ifTruthy(
					$useTopLevelSettings,
					$g->cssValue($settings->getSetting('topLevelSeparators.' . $name)),
					$g->cssValue($settings->getSetting('submenuSeparators.' . $name))
				)
			);
		}
		//Margin settings have separate child settings for each side.
		foreach (['top', 'bottom', 'left', 'right'] as $side) {
			$g->setVariable(
				$submenuVarPrefix . 'margin.' . $side,
				$g->ifTruthy(
					$useTopLevelSettings,
					$g->cssValue($settings->getSetting('topLevelSeparators.margin.' . $side)),
					$g->cssValue($settings->getSetting('submenuSeparators.margin.' . $side))
				)
			);
		}

		//Now we can add the submenu separator rules.
		$this->addSeparatorRuleSetsFor(
			$g,
			$settings,
			'submenuSeparators',
			function ($key) use ($g, $submenuVarPrefix) {
				return $g->variable($submenuVarPrefix . $key);
			},
			'#adminmenumain #adminmenu .wp-submenu .ws-submenu-separator',
			'#adminmenumain #adminmenu .wp-submenu .ws-submenu-separator-wrap'
		);

		return $g;
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $g
	 * @param \YahnisElsts\AdminMenuEditor\MenuSeparatorStyles\SeparatorStyleSettings $s
	 * @param string $separatorType
	 * @param callable $valueGetter
	 * @param string $nodeSelector
	 * @param string $parentSelector
	 * @return void
	 */
	private function addSeparatorRuleSetsFor(
		StyleGenerator         $g,
		SeparatorStyleSettings $s,
		                       $separatorType,
		                       $valueGetter,
		                       $nodeSelector,
		                       $parentSelector
	) {
		$nodeSelector = trim($nodeSelector);
		$parentSelector = trim($parentSelector);

		$customSettingsEnabled = $s->getSetting('customSettingsEnabled');

		//If custom settings are enabled, always reset the margins, padding, and dimensions
		//to avoid conflicts.
		$g->addSimpleCondition(
			$customSettingsEnabled,
			'==',
			true,
			new CssRuleSet(
				[$parentSelector],
				[
					'height'  => 'auto',
					'margin'  => '0',
					'padding' => '0',
					'width'   => '100%',
				]
			)
		);

		//Determine the effective separator color. It defaults to "transparent" if that's
		//the color type or if the custom color is empty.
		$g->setVariable(
			$separatorType . 'EffectiveColor',
			$g->ifLooselyEqual(
				$valueGetter('colorType'),
				'transparent',
				'transparent',
				$g->ifLooselyEqual(
					$valueGetter('customColor'),
					'',
					'transparent',
					$valueGetter('customColor')
				)
			)
		);

		//Border style and height.
		$g->addCondition(
			$g->ifAll([
				$customSettingsEnabled,
				$g->ifLooselyEqual($valueGetter('borderStyle'), 'solid'),
			]),
			new CssRuleSet(
				[$nodeSelector],
				[
					'border'           => 'none',
					'background-color' => $g->variable($separatorType . 'EffectiveColor'),
					'height'           => $valueGetter('height'),
				]
			)
		);
		$g->addCondition(
			$g->ifAll([
				$customSettingsEnabled,
				$g->compare($valueGetter('borderStyle'), '!=', 'solid'),
			]),
			new CssRuleSet(
				[$nodeSelector],
				[
					'border-top-style' => $valueGetter('borderStyle'),
					'border-top-width' => $valueGetter('height'),
					'height'           => '0',
					'border-color'     => $g->variable($separatorType . 'EffectiveColor'),
					'background'       => 'transparent',
				]
			)
		);

		//Width.
		$g->addCondition(
			$g->ifAll([
				$customSettingsEnabled,
				$g->ifLooselyEqual($valueGetter('widthStrategy'), 'percentage'),
			]),
			new CssRuleSet(
				[$nodeSelector],
				['width' => $valueGetter('widthInPercent')]
			)
		);
		$g->addCondition(
			$g->ifAll([
				$customSettingsEnabled,
				$g->ifLooselyEqual($valueGetter('widthStrategy'), 'fixed'),
			]),
			new CssRuleSet(
				[$nodeSelector],
				['width' => $valueGetter('widthInPixels')]
			)
		);

		//Margins and alignment.
		//Left and right margins should be "auto" when the separator is centered and not full-width.
		foreach (['left', 'right'] as $side) {
			$g->setVariable(
				$separatorType . '_' . $side . 'Margin',
				$g->ifAll(
					[
						$g->compare($valueGetter('widthStrategy'), '!=', 'full'),
						$g->ifLooselyEqual($valueGetter('alignment'), 'center'),
					],
					'auto',
					$valueGetter('margin.' . $side)
				)
			);
		}

		$g->addCondition(
			$g->ifTruthy($customSettingsEnabled),
			new CssRuleSet(
				[$nodeSelector],
				[
					'margin-top'    => $valueGetter('margin.top'),
					'margin-bottom' => $valueGetter('margin.bottom'),
					'margin-left'   => $g->variable($separatorType . '_leftMargin'),
					'margin-right'  => $g->variable($separatorType . '_rightMargin'),
				]
			)
		);

		//Left and right alignment.
		$g->addCondition(
			$g->ifAll([
				$customSettingsEnabled,
				$g->compare($valueGetter('widthStrategy'), '!=', 'full'),
				$g->ifSome([
					$g->ifLooselyEqual($valueGetter('alignment'), 'left'),
					$g->ifLooselyEqual($valueGetter('alignment'), 'right'),
				]),
			]),
			new CssRuleSet(
				[$nodeSelector],
				['float' => $valueGetter('alignment')]
			),
			//Clear floats.
			new CssRuleSet(
				[$parentSelector . '::after'],
				[
					'content' => '""',
					'display' => 'block',
					'clear'   => 'both',
					'height'  => '0',
				]
			)
		);
	}

	public function addAuxDataConfig($config) {
		$config['keys'][SeparatorStyleSettings::CONFIG_KEY] = SeparatorStyleSettings::SETTING_ID_PREFIX;
		return $config;
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Builders\InterfaceBuilder $structure
	 * @return void
	 */
	public function addSeparatorsToMenuStyler($structure) {
		$myStructure = $this->getInterfaceStructure();
		$separatorSection = $myStructure->findChildById('ame-sep-Separators-section');
		if ( $separatorSection instanceof Container ) {
			$structure->addAfter($separatorSection, 'ame-ms-Submenus-section');
		}
	}

	public function addDefaultsToMenuStyler($defaults) {
		return array_merge($defaults, $this->getSettings()->getRecursiveDefaultsForJs());
	}

	public function addStyleGeneratorToMenuStyler($styleGenerators) {
		$styleGenerators[] = $this->getStyleGenerator($this->getSettings());
		return $styleGenerators;
	}

	public function registerSeparatorStylesheet() {
		$helper = MenuScopedStylesheetHelper::getInstance($this->menuEditor);

		$helper->addStylesheet(
			self::COLOR_STYLE_HANDLE,
			function ($menuConfigId) {
				return [
					function () use ($menuConfigId) {
						$customMenu = $this->menuEditor->load_custom_menu($menuConfigId);
						return isset($customMenu, $customMenu['separator_css_modified'])
							? max(intval($customMenu['separator_css_modified']), 0)
							: 0;
					},
					function () use ($menuConfigId) {
						//In the preview frame, this should use the already-registered
						//settings instead of loading them from the menu configuration.
						if (
							apply_filters('admin_menu_editor-is_preview_frame', false)
							&& isset($this->settings)
						) {
							$settings = $this->settings;
						} else {
							$settings = $this->getSettings($menuConfigId);
						}
						$generator = $this->getStyleGenerator($settings);
						return $generator->generateCss();
					},
				];
			},
			'ame-menu-style-bundle'
		);
	}
}

class SeparatorStyleSettings extends AbstractSettingsDictionary {
	const SETTING_ID_PREFIX = 'ws_separator_styles--';

	const CONFIG_KEY = 'separators';

	const SEPARATOR_TYPE_KEYS = ['topLevelSeparators', 'submenuSeparators'];

	private static $marginAliases = null;

	public function __construct(StorageInterface $store, $lastModifiedTimeEnabled = false) {
		parent::__construct($store, self::SETTING_ID_PREFIX, $lastModifiedTimeEnabled);

		//Margins were previously stored in keys like "marginTop" instead of
		//a single "margins" structure. Let's add aliases for the old keys.
		$this->addReadAliases($this->getMarginAliases());
	}

	/**
	 * @return array
	 */
	public static function getMarginAliases() {
		if ( isset(self::$marginAliases) ) {
			return self::$marginAliases;
		}

		self::$marginAliases = [];
		foreach (self::SEPARATOR_TYPE_KEYS as $separatorType) {
			self::$marginAliases = array_merge(self::$marginAliases, [
				$separatorType . '.margin.top'    => $separatorType . '.marginTop',
				$separatorType . '.margin.bottom' => $separatorType . '.marginBottom',
				$separatorType . '.margin.left'   => $separatorType . '.marginLeft',
				$separatorType . '.margin.right'  => $separatorType . '.marginRight',
			]);
		}
		return self::$marginAliases;
	}

	protected function createDefaults() {
		return [];
	}

	protected function createSettings() {
		$f = $this->settingFactory();
		$f->enablePostMessageSupport();
		$f->setTags(AbstractSetting::TAG_ADMIN_THEME);
		$settings = [
			$f->boolean('customSettingsEnabled', 'Use custom separator styles'),
			$f->boolean('useTopLevelSettingsForSubmenus', 'Use the same settings as top level separators'),
		];

		foreach (self::SEPARATOR_TYPE_KEYS as $separatorType) {
			$settings[] = $f->customStruct(
				$separatorType,
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					return [
						$cf->stringEnum(
							'colorType',
							['transparent', 'custom'],
							'Color',
							['default' => 'transparent']
						),
						$cf->cssColor('customColor', 'border-color', 'Custom color'),
						$cf->cssEnum(
							'borderStyle',
							'border-style',
							['solid', 'dashed', 'double', 'dotted',],
							'Line style',
							['default' => 'solid']
						)
							->describeChoice('solid', 'Solid')
							->describeChoice('dashed', 'Dashed')
							->describeChoice('double', 'Double')
							->describeChoice('dotted', 'Dotted'),
						$cf->cssLength(
							'height',
							'Height',
							'height',
							[
								'defaultUnit' => 'px',
								'default'     => 5,
								'minValue'    => 1,
								'maxValue'    => 100,
							]
						),
						$cf->stringEnum(
							'widthStrategy',
							['full', 'percentage', 'fixed'],
							'Width',
							['default' => 'full']
						)
							->describeChoice('full', 'Full width')
							->describeChoice('percentage', 'Percentage')
							->describeChoice('fixed', 'Fixed width'),
						$cf->cssLength(
							'widthInPercent',
							'Width in percent',
							'width',
							[
								'defaultUnit' => '%',
								'default'     => 100,
								'minValue'    => 1,
								'maxValue'    => 100,
							]
						),
						$cf->cssLength(
							'widthInPixels',
							'Width in pixels',
							'width',
							[
								'defaultUnit' => 'px',
								'default'     => 160,
								'minValue'    => 1,
								'maxValue'    => 300,
							]
						),
						$cf->create(
							Margins::class,
							'margin',
							'Margins',
							[
								'dimensionDefaults' => [
									'top'    => 0,
									'bottom' => 6,
									'left'   => 0,
									'right'  => 0,
								],
							]
						),
						$cf->stringEnum(
							'alignment',
							['none', 'left', 'center', 'right'],
							'Alignment',
							['default' => 'none']
						),
					];
				}
			);
		}

		return $settings;
	}
}