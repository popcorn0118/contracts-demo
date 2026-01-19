<?php

namespace YahnisElsts\AdminMenuEditor\DashboardStyler;

use YahnisElsts\AdminMenuEditor\AdminCustomizer\AmeAdminCustomizer;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\SettingFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;
use YahnisElsts\AdminMenuEditor\DynamicStylesheets\Stylesheet;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\Padding;
use YahnisElsts\AdminMenuEditor\StyleGenerator\CssRuleSet;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\Expression;
use YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

/**
 * General visual customization features for the admin dashboard.
 *
 * Many features of Admin Menu Editor Pro could fall under the umbrella of
 * an "admin theme". This module focuses on cosmetic settings that are not
 * related to the admin menu; preferably ones that can be implemented using
 * only CSS. At some point, it may also have the ability to build an actual
 * admin theme as a downloadable plugin.
 *
 * Dashboard styler? Admin theme may be too general.
 */
class DashboardStyler extends \amePersistentProModule {
	const STYLESHEET_HANDLE = 'ame-custom-dashboard-styles';
	const CUSTOM_CSS_STYLE_ID = 'ame-ds-user-custom-css';

	const BUTTON_TYPES = [
		'primary'   => 'Primary buttons',
		'secondary' => 'Secondary buttons',
		'addNew'    => '"Add New" buttons',
	];
	const BUTTON_COLOR_FIELDS = [
		'Background'      => ['Background', 'background-color'],
		'Text'            => ['Text', 'color'],
		'BackgroundHover' => ['Background (hover)', 'background-color'],
		'TextHover'       => ['Text (hover)', 'color'],
	];

	protected $optionName = 'ws_ame_dashboard_styler';

	/**
	 * @var null|StyleGenerator
	 */
	private $styleGenerator = null;

	public function __construct($menuEditor) {
		$this->settingsWrapperEnabled = true;
		$this->lastModifiedSettingEnabled = true;

		parent::__construct($menuEditor);

		$stylesheet = new Stylesheet(
			self::STYLESHEET_HANDLE,
			function () {
				$g = $this->getStyleGenerator($this->loadSettings());
				return $g->generateCss();
			},
			function () {
				$settings = $this->loadSettings();
				return $settings->getLastModifiedTimestamp();
			}
		);
		$stylesheet->addHooks();

		add_action('admin_menu_editor-register_ac_items', [$this, 'registerAdminCustomizerItems']);
		add_action('admin_menu_editor-register_ac_preview_deps', [$this, 'registerAdminCustomizerPreview']);

		add_action('admin_print_styles', [$this, 'outputUserCustomCss'], 100);

		add_action('admin_menu_editor-ac_admin_theme_css', [$this, 'addAdminThemeCss']);
		add_action('admin_menu_editor-ac_admin_theme_css', [$this, 'addCustomCssToAdminTheme'], 100);
	}

	public function createSettingInstances(ModuleSettings $settings) {
		$f = $settings->settingFactory();
		return [
			$f->customStruct(
				'boxes',
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					return [
						$cf->cssColor('containerBackgroundColor', 'background-color', 'Background color'),
						$cf->cssBoxShadow('containerShadow', 'Shadow'),
						$cf->cssBorders('containerBorder', 'Border'),

						$cf->cssFont('headerFont', 'Font'),
						$cf->cssColor('headerTextColor', 'color', 'Text color'),
						$cf->cssColor('headerBackgroundColor', 'background-color', 'Background color'),
						$cf->create(
							Padding::class,
							'headerPadding',
							'Text padding'
						),
						$cf->cssIndividualBorder('headerBorder', 'Bottom border', ['side' => 'bottom']),
					];
				}
			),
			$f->customStruct(
				'buttons',
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					return [
						$cf->cssFont('font', 'Font'),

						$cf->customStruct(
							'colors',
							function (SettingFactory $if) {
								$if->enablePostMessageSupport();
								$colorSettings = [];
								foreach (array_keys(self::BUTTON_TYPES) as $type) {
									foreach (self::BUTTON_COLOR_FIELDS as $field => [$label, $cssProperty]) {
										$colorSettings[] = $if->cssColor(
											$type . ucfirst($field),
											$cssProperty,
											$label
										);
									}
								}
								return $colorSettings;
							}
						),

						$cf->cssSpacing('spacing'),
						$cf->cssBorders('border', 'Border', ['includesColor' => false]),
						$cf->cssBoxShadow('boxShadow', 'Shadow'),
					];
				}
			),
			$f->customStruct(
				'headings',
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					$levels = ['h1', 'h2'];
					$settings = [];
					foreach ($levels as $level) {
						$settings[] = $cf->customStruct(
							$level,
							function (SettingFactory $if) use ($level) {
								$if->enablePostMessageSupport();
								return [
									$if->cssFont('font', 'Font'),
									$if->cssColor('textColor', 'color', 'Text color'),
									$if->cssSpacing('spacing'),
								];
							}
						);
					}

					//Note: Not implemented.
					$settings[] = $cf->stringEnum(
						'addNewButtonPosition',
						['after', 'opposite'],
						'Position of the "Add New" button'
					)
						->describeChoice('after', 'After the heading')
						->describeChoice('opposite', 'Opposite side of the page');

					return $settings;
				}
			),
			$f->customStruct(
				'tables',
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					$settings = [
						$cf->cssColor('backgroundColor', 'background-color', 'Background color'),
						$cf->cssBorders('border', 'Border'),
						$cf->cssBoxShadow('boxShadow', 'Shadow'),
						$cf->cssColor(
							'alternateRowBackgroundColor',
							'background-color', '
							Alternate row background color'
						),

						$cf->cssFont('baseFont', 'Font'),
						$cf->create(
							Padding::class,
							'cellPadding',
							'Cell padding'
						),
						$cf->cssColor('cellBorderColor', 'border-color', 'Color'),
						$cf->cssIndividualBorder(
							'horizontalCellBorder',
							'Horizontal border',
							['side' => 'bottom', 'includesColor' => false]
						),
						$cf->cssIndividualBorder(
							'verticalCellBorder',
							'Vertical border',
							['side' => 'right', 'includesColor' => false]
						),
					];

					foreach (['header', 'footer'] as $part) {
						$settings[] = $cf->customStruct(
							$part,
							function (SettingFactory $if) use ($part) {
								$if->enablePostMessageSupport();
								$if->setTags(AbstractSetting::TAG_ADMIN_THEME);
								return [
									$if->cssColor('backgroundColor', 'background-color', 'Background color'),
									$if->cssFont('font', 'Font'),
									$if->cssIndividualBorder(
										'border',
										($part === 'footer') ? 'Top border' : 'Bottom border',
										['side' => ($part === 'footer') ? 'top' : 'bottom']
									),
									$if->cssBorderStyle(
										'verticalBorderStyle',
										'border-right-style',
										'Vertical border style',
										['nullAllowed' => true]
									),
									$if->create(
										Padding::class,
										'padding',
										'Cell padding'
									),
								];
							}
						);
					}

					return $settings;
				}
			),
			$f->customStruct(
				'toolbar',
				function (SettingFactory $cf) {
					$cf->enablePostMessageSupport();
					$cf->setTags(AbstractSetting::TAG_ADMIN_THEME);
					return [
						$cf->cssLength(
							'height',
							'Toolbar height',
							'height',
							[
								'minValue' => 1,
								'maxValue' => 100,
								'default'  => null,
							]
						),
						$cf->cssBoxShadow('boxShadow', 'Shadow'),
						$cf->cssFont(
							'font',
							'Font',
							//Line height is determined by the height of the toolbar.
							['includesLineHeight' => false]
						),

						$cf->cssColor('backgroundColor', 'background-color', 'Background color'),
						$cf->cssColor('textColor', 'color', 'Text color'),
						$cf->cssColor('textHoverColor', 'color', 'Text hover color'),
						$cf->cssColor('itemHoverBackgroundColor', 'background-color', 'Item hover background color'),
						$cf->cssColor('submenuTextColor', 'color', 'Submenu text color'),
						$cf->cssColor('submenuTextHoverColor', 'color', 'Submenu text hover color'),
						$cf->cssColor('submenuHoverBackgroundColor', 'color', 'Submenu item hover background color'),

						$cf->cssLength(
							'submenuItemHeight',
							'Submenu item height',
							'height',
							[
								'defaultUnit' => 'px',
								'minValue'    => 1,
								'maxValue'    => 100,
								'default'     => null,
							]
						),
						$cf->cssFont('submenuFont', 'Submenu font', ['includesLineHeight' => false]),
						$cf->cssBoxShadow('submenuBoxShadow', 'Submenu shadow'),
					];
				}
			),
			$f->userText(
				'customCss',
				'Custom CSS',
				['maxLength' => 10 * 1024]
			)
				->enablePostMessageSupport()
				->addTags(
					...(apply_filters('admin_menu_editor-ac_custom_css_enabled', true)
					? [AbstractSetting::TAG_ADMIN_THEME]
					: [])
				),
		];
	}

	public function getInterfaceStructure() {
		$settings = $this->loadSettings();
		$b = $settings->elementBuilder();

		$buttonColorGroups = [];
		foreach (self::BUTTON_TYPES as $buttonType => $typeLabel) {
			$buttonColorControls = [];
			foreach (self::BUTTON_COLOR_FIELDS as $field => [$label,]) {
				$buttonColorControls[] = $b->auto('buttons.colors.' . $buttonType . $field);
			}
			$buttonColorGroups[] = $b->contentSection($typeLabel, ...$buttonColorControls);
		}

		return $b->structure(
			$b->section(
				'Boxes',
				$b->section(
					'Container',
					$b->auto('boxes.containerBackgroundColor'),
					$b->auto('boxes.containerBorder'),
					$b->auto('boxes.containerShadow')
				),
				$b->section(
					'Header',
					$b->auto('boxes.headerBackgroundColor'),
					$b->auto('boxes.headerTextColor'),
					$b->auto('boxes.headerFont'),
					$b->contentSection(
						'Padding',
						$b->boxDimensions('boxes.headerPadding')->params(['symmetricMode' => true])
					),
					$b->auto('boxes.headerBorder')
				)
			),
			$b->section(
				'Buttons',
				$b->section(
					'Colors',
					//TODO: This could be a section description that is revealed by clicking a button, like in the Customizer.
					$b->html('<span>Button colors are usually set by changing the "Color Scheme" settings, but you can also override them here.</span>'),
					...$buttonColorGroups
				),
				$b->auto('buttons.font'),
				$b->auto('buttons.spacing'),
				$b->auto('buttons.border'),
				$b->auto('buttons.boxShadow')
			),
			$b->section(
				'Headings',
				$b->section(
					'Primary (H1)',
					$b->auto('headings.h1.textColor'),
					$b->auto('headings.h1.font'),
					$b->auto('headings.h1.spacing')
				)->id('ame-ds-primary-headings'),
				$b->section(
					'Secondary (H2)',
					$b->auto('headings.h2.textColor'),
					$b->auto('headings.h2.font'),
					$b->auto('headings.h2.spacing')
				)->id('ame-ds-secondary-headings')
			)->id('ame-ds-headings'),
			$b->section(
				'Tables',
				$b->section(
					'Whole Table',
					$b->auto('tables.backgroundColor'),
					$b->auto('tables.alternateRowBackgroundColor'),
					$b->auto('tables.border'),
					$b->auto('tables.boxShadow')
				),
				$b->section(
					'Content',
					$b->auto('tables.baseFont'),
					$b->contentSection(
						'Cell padding',
						$b->boxDimensions('tables.cellPadding')
							->params(['symmetricMode' => true])
							->label('') //The section title is enough, we don't need a duplicate label.
					),
					$b->contentSection(
						'Cell borders',
						$b->auto('tables.cellBorderColor'),
						$b->auto('tables.horizontalCellBorder'),
						$b->auto('tables.verticalCellBorder')
					)
				),
				$b->section(
					'Header',
					$b->auto('tables.header.backgroundColor'),
					$b->auto('tables.header.font'),
					$b->auto('tables.header.border'),
					$b->select('tables.header.verticalBorderStyle'),
					$b->contentSection(
						'Cell padding',
						$b->boxDimensions('tables.header.padding')
							->params(['symmetricMode' => true])
					)
				),
				$b->section(
					'Footer',
					$b->auto('tables.footer.backgroundColor'),
					$b->auto('tables.footer.font'),
					$b->auto('tables.footer.border'),
					$b->select('tables.footer.verticalBorderStyle'),
					$b->contentSection(
						'Cell padding',
						$b->boxDimensions('tables.footer.padding')
							->params(['symmetricMode' => true])
					)
				)
			),
			$b->section(
				'Toolbar',
				$b->section(
					'General',
					$b->number('toolbar.height')->step(1),
					$b->auto('toolbar.font'),
					$b->auto('toolbar.boxShadow')
				),
				$b->section(
					'Colors',
					$b->auto('toolbar.backgroundColor'),
					$b->auto('toolbar.textColor'),
					$b->auto('toolbar.textHoverColor'),
					$b->auto('toolbar.itemHoverBackgroundColor'),
					$b->auto('toolbar.submenuTextColor'),
					$b->auto('toolbar.submenuTextHoverColor'),
					$b->auto('toolbar.submenuHoverBackgroundColor')
				),
				$b->section(
					'Submenus',
					$b->number('toolbar.submenuItemHeight')->step(1),
					$b->auto('toolbar.submenuFont'),
					$b->auto('toolbar.submenuBoxShadow')
				)
			)->id('ame-ds-toolbar'),
			$b->section(
				'Custom CSS',
				$b->codeEditor('customCss')
					->cssMode()
					->description(
						'The custom CSS will be added to all admin pages. '
						. 'It will be inserted using a <code>&lt;style&gt;</code> element in the page header.'
					)
			)->id('ame-ds-custom-css')
		)->build();
	}

	public function registerAdminCustomizerItems(AmeAdminCustomizer $customizer) {
		//Register settings.
		$customizer->addSettings($this->loadSettings()->getRegisteredSettings());

		//Register all sections from our interface structure.
		//TODO: Order these better later.
		$structure = $this->getInterfaceStructure();
		foreach ($structure->getAsSections() as $section) {
			$customizer->addSection($section);
		}

		add_action('admin_menu_editor-enqueue_ac_preview', [$this, 'enqueueAdminCustomizerPreview']);
	}

	private function getStyleGenerator(AbstractSettingsDictionary $s) {
		if ( !empty($this->styleGenerator) ) {
			return $this->styleGenerator;
		}

		$g = $this->styleGenerator = new StyleGenerator();
		$g->setStylesheetsToDisableOnPreview(['link#' . self::STYLESHEET_HANDLE . '-css']);

		$this->addBoxStyles($g, $s);
		$this->addButtonStyles($g, $s);
		$this->addHeadingStyles($g, $s);
		$this->addTableStyles($g, $s);
		$this->addToolbarStyles($g, $s);

		return $g;
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $g
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary $s
	 * @return void
	 */
	private function addButtonStyles(StyleGenerator $g, AbstractSettingsDictionary $s) {
		//General buttons.
		$g->addRuleSet(
			['.wp-core-ui .button'],
			[
				$s->getSetting('buttons.font'),
				$s->getSetting('buttons.spacing'),
				$s->getSetting('buttons.border'),
				$s->getSetting('buttons.boxShadow'),
			]
		);

		//"Add New" buttons. These have different styles by default, so we only apply
		//some of the settings. For example, their line height is different, so we
		//don't change it to match other buttons.
		$g->addRuleSet(
			[
				'.wrap .page-title-action',
				'.wrap .page-title-action:active',
			],
			[
				$s->getSetting('buttons.font.size'),
				$s->getSetting('buttons.font.weight'),
				$s->getSetting('buttons.font.style'),
				$s->getSetting('buttons.font.variant'),
				$s->getSetting('buttons.font.text-transform'),
				$s->getSetting('buttons.font.text-decoration'),

				$s->getSetting('buttons.border'),
				$s->getSetting('buttons.boxShadow'),
			]
		);

		//Button colors.
		//Modeled after /wp-admin/css/colors/_admin.scss in WP 6.2.
		//Note: If all this mess with secondary buttons gets too confusing, we could
		//just throw out consistency with WordPress's default button styles and use
		//the same color calculations for all buttons.

		$primaryButtonBackground = $s->getSetting('buttons.colors.primaryBackground');
		//Default buttons and secondary buttons.
		$g->setVariables([
			'secondaryButtonTextColor'   => [
				$s->getSetting('buttons.colors.secondaryText'),
				$primaryButtonBackground,
			],
			'secondaryButtonBorderColor' => [
				$primaryButtonBackground,
			],
		]);
		$secondaryButtonTextColor = $g->variable('secondaryButtonTextColor');
		$secondaryButtonBorderColor = $g->variable('secondaryButtonBorderColor');
		$customSecondaryButtonHoverBackground = $s->getSetting('buttons.colors.secondaryBackgroundHover');

		$g->addCondition(
			$g->ifSome([$secondaryButtonTextColor, $secondaryButtonBorderColor]),
			//Default state.
			new CssRuleSet(
				[
					'.wp-core-ui .button:not(.button-primary)',
					'.wp-core-ui .button-secondary',
				],
				[
					'border-color' => $secondaryButtonBorderColor,
					'color'        => $secondaryButtonTextColor,
				]
			),
			//Hover state.
			//Notably, admin themes generated from _admin.scss don't include a custom background
			//color for the hover and focus states of default/secondary buttons. It's always #f0f0f1
			//for hover and #f6f7f7 for focus, as set in /wp-includes/css/buttons.css. We'll only
			//change this if the user has explicitly specified a custom hover color.
			new CssRuleSet(
				[
					'.wp-core-ui .button.hover:not(.button-primary)',
					'.wp-core-ui .button:hover:not(.button-primary)',
					'.wp-core-ui .button-secondary:hover',
				],
				[
					'border-color' => $g->darken($secondaryButtonBorderColor, 10),
					'color'        => $g->firstNonEmpty([
						$s->getSetting('buttons.colors.secondaryTextHover'),
						$g->darken($secondaryButtonTextColor, 10),
					]),
					'background'   => $customSecondaryButtonHoverBackground,
				]
			),
			//Focus state.
			new CssRuleSet(
				[
					'.wp-core-ui .button.focus:not(.button-primary)',
					'.wp-core-ui .button:focus:not(.button-primary)',
					'.wp-core-ui .button-secondary:focus',
				],
				[
					'border-color' => $g->lighten($secondaryButtonBorderColor, 10),
					'color'        => $g->darken($secondaryButtonTextColor, 20),
					//By default, the focus state has a light-grey background (#f6f7f7).
					//This looks jarring if the user has specified a more saturated hover color,
					//as the button will suddenly change to grey when clicked/focused.
					//To avoid this, let's use the same background color as the hover state,
					//but lighten it a bit.
					'background'   => $g->lighten($customSecondaryButtonHoverBackground, 2.2),
				]
			)
		);

		//Box shadow for the focus state.
		$g->addCondition(
			$g->ifTruthy($secondaryButtonBorderColor),
			new CssRuleSet(
				[
					'.wp-core-ui .button.focus:not(.button-primary)',
					'.wp-core-ui .button:focus:not(.button-primary)',
					'.wp-core-ui .button-secondary:focus',
				],
				[
					'--ame-ds-light-bb-color' => $g->lighten($secondaryButtonBorderColor, 10),
					'box-shadow'              => '0 0 0 1px var(--ame-ds-light-bb-color)',
				]
			)
		);

		//Special .active states.
		$g->addCondition(
			$g->ifTruthy($primaryButtonBackground),
			new CssRuleSet(
				[
					'.wp-core-ui .button.active:not(.button-primary)',
					'.wp-core-ui .button.active:focus:not(.button-primary)',
					'.wp-core-ui .button.active:hover:not(.button-primary)',
				],
				[
					'--ame-ds-button-color' => $primaryButtonBackground,
					'border-color'          => 'var(--ame-ds-button-color)',
					'box-shadow'            => 'inset 0 2px 5px -3px var(--ame-ds-button-color)',
				]
			)
		);

		//Strangely, WP has a different, fixed shadow just for .active:focus buttons.
		$g->addCondition(
			$g->ifSome([$primaryButtonBackground, $secondaryButtonBorderColor, $customSecondaryButtonHoverBackground]),
			new CssRuleSet(
				['.wp-core-ui .button.active:focus'],
				['box-shadow' => '0 0 0 1px #32373c']
			)
		);

		//Primary buttons.
		$g->addRuleSet(
			['.wp-core-ui .button-primary'],
			[
				'border-color' => $primaryButtonBackground,
			]
		);
		$g->addRuleSet(
			[
				'.wp-core-ui .button-primary:hover',
				'.wp-core-ui .button-primary:focus',
			],
			[
				//This is not how it works in the default color scheme (there the background color
				//gets darker on hover), but it is how custom admin color schemes work.
				'background'   => $g->lighten($primaryButtonBackground, 3),
				'border-color' => $g->darken($primaryButtonBackground, 3),
				'color'        => $s->getSetting('buttons.colors.primaryText'),
			]
		);
		$g->addCondition(
			$g->ifTruthy($primaryButtonBackground),
			new CssRuleSet(
				['.wp-core-ui .button-primary:focus'],
				[
					'--ame-ds-pb-shadow-color' => $primaryButtonBackground,
					'box-shadow'               =>
						'0 0 0 1px #fff, 0 0 0 3px var(--ame-ds-pb-shadow-color)',
				]
			)
		);
		$g->addRuleSet(
			['.wp-core-ui .button-primary:active'],
			[
				'background'   => $g->darken($primaryButtonBackground, 5),
				'border-color' => $g->darken($primaryButtonBackground, 5),
				'color'        => $s->getSetting('buttons.colors.primaryText'),
			]
		);
		$extraPrimaryButtonActiveSelectors = [
			'.wp-core-ui .button-primary.active',
			'.wp-core-ui .button-primary.active:focus',
			'.wp-core-ui .button-primary.active:hover',
		];
		$g->addRuleSet(
			$extraPrimaryButtonActiveSelectors,
			[
				'background'   => $primaryButtonBackground,
				'color'        => $s->getSetting('buttons.colors.primaryText'),
				'border-color' => $g->darken($primaryButtonBackground, 5),
			]
		);
		$g->addCondition(
			$g->ifTruthy($primaryButtonBackground),
			new CssRuleSet(
				$extraPrimaryButtonActiveSelectors,
				[
					'--ame-ds-pb-active-shadow-color' => $primaryButtonBackground,
					'box-shadow'                      => 'inset 0 2px 5px -3px var(--ame-ds-pb-active-shadow-color)',
				]
			)
		);

		//"Add New" button.
		$g->setVariables([
			'addNewButtonTextColor' => [
				$s->getSetting('buttons.colors.addNewText'),
				$secondaryButtonTextColor,
			],
		]);
		$addNewButtonTextColor = $g->variable('addNewButtonTextColor');
		$addNewButtonBorderColor = $secondaryButtonBorderColor;

		$g->addRuleSet(
			[
				'.wrap .page-title-action',
				'.wrap .page-title-action:active',
			],
			[
				'color'        => $addNewButtonTextColor,
				'border-color' => $addNewButtonBorderColor,
			]
		);
		$g->addRuleSet(
			['.wrap .page-title-action:hover'],
			[
				'color'        => $g->darken($addNewButtonTextColor, 10),
				'border-color' => $g->darken($addNewButtonBorderColor, 10),
			]
		);
		$g->addRuleSet(
			['.wrap .page-title-action:focus'],
			[
				'color'        => $g->darken($addNewButtonTextColor, 20),
				'border-color' => $g->lighten($addNewButtonBorderColor, 10),
			]
		);
		$g->addCondition(
			$g->ifTruthy($addNewButtonBorderColor),
			new CssRuleSet(
				['.wrap .page-title-action:focus'],
				[
					'--ame-ds-anb-focus-shadow-color' => $g->lighten($addNewButtonBorderColor, 10),
					'box-shadow'                      => '0 0 0 1px var(--ame-ds-anb-focus-shadow-color)',
				]
			)
		);

		//Color picker buttons.
		//When the border radius changes, the "Select Color" element must have its right border
		//radius adjusted to avoid either creating a gap or an overlap between its background
		//and the button's border.
		$g->addCondition(
			$g->ifSome([
				$g->compare($s->getSetting('buttons.border.radius.topRight'), '>=', 0),
				$g->compare($s->getSetting('buttons.border.radius.bottomRight'), '>=', 0),
			]),
			new CssRuleSet(
				['.wp-core-ui .button.wp-color-result .wp-color-result-text'],
				[
					'--ame-ds-btn-top-right-radius'    => $s->getSetting('buttons.border.radius.topRight'),
					'--ame-ds-btn-bottom-right-radius' => $s->getSetting('buttons.border.radius.bottomRight'),
					'--ame-ds-btn-border-width'        => $s->getSetting('buttons.border.width'),
					'border-top-right-radius'          => 'calc(var(--ame-ds-btn-top-right-radius, 3px) - var(--ame-ds-btn-border-width, 1px))',
					'border-bottom-right-radius'       => 'calc(var(--ame-ds-btn-bottom-right-radius, 3px) - var(--ame-ds-btn-border-width, 1px))',
				]
			)
		);

		//General color overrides.
		//These could be worked into the above rules, but it's clearer to keep them separate.
		$selectorsByButtonType = [
			'primary'   => ['.wp-core-ui .button-primary'],
			'secondary' => ['.wp-core-ui .button:not(.button-primary)', '.wp-core-ui .button-secondary'],
			'addNew'    => ['.wrap .page-title-action'],
		];
		foreach ($selectorsByButtonType as $buttonType => $selectors) {
			$g->addRuleSet(
				$selectors,
				[
					$s->getSetting('buttons.colors.' . $buttonType . 'Background'),
					$s->getSetting('buttons.colors.' . $buttonType . 'Text'),
				]
			);

			$hoverSelectors = array_map(function ($selector) {
				return $selector . ':hover';
			}, $selectors);
			$g->addRuleSet(
				$hoverSelectors,
				[
					$s->getSetting('buttons.colors.' . $buttonType . 'BackgroundHover'),
					$s->getSetting('buttons.colors.' . $buttonType . 'TextHover'),
				]
			);
		}

		//Let the menu editor know the custom border radius so that it can properly
		//display the "unsaved changes" indicator.
		$g->addCondition(
			$g->ifTruthy($s->getSetting('buttons.border.radius.topRight')),
			new CssRuleSet(
				['.wp-core-ui .button'],
				[
					'--ame-ds-btn-radius-tr' => $s->getSetting('buttons.border.radius.topRight'),
				]
			)
		);
	}

	private function addBoxStyles(StyleGenerator $g, AbstractSettingsDictionary $s) {
		$g->addRuleSet(
			['.postbox', '.ws-ame-postbox.ws-ame-postbox'],
			[
				$s->getSetting('boxes.containerBackgroundColor'),
				$s->getSetting('boxes.containerBorder'),
				$s->getSetting('boxes.containerShadow'),
			]
		);


		//Adjust background colors derived from the container background color.
		//For computed colors, the adjustment values were chosen by taking the difference
		//between the default background color (#fff) and the relevant WP grey color.
		//See https://make.wordpress.org/core/2021/02/23/standardization-of-wp-admin-colors-in-wordpress-5-7/
		$boxBackgroundColor = $g->cssValue($s->getSetting('boxes.containerBackgroundColor'));
		$boxGrey0 = $g->adjustHexAsHsl($boxBackgroundColor, null, 0.01, -0.03);
		$boxGrey5 = $g->adjustHexAsHsl($boxBackgroundColor, null, 0.01, -0.13);
		$g->addCondition(
			$g->ifTruthy($s->getSetting('boxes.containerBackgroundColor')),
			//By default, some content inside widgets and meta boxes has a background color
			//that's slightly darker than the container. When using a custom background color,
			//adjust the background color of that content to keep it consistent.
			new CssRuleSet(
				[
					//Comment list in the activity widget.
					'#activity-widget #the-comment-list .comment-item',
					//Bottom panel of the "Publish" meta box.
					'#major-publishing-actions',
				],
				[
					'background-color' => $boxGrey0,
				]
			),
			//In the "Categories" meta box, and possibly other boxes that use tabs,
			//the background color of the active tab's content matches the container's
			//background color by default.
			new CssRuleSet(
				[
					'.wp-tab-panel',
					'.categorydiv div.tabs-panel',
					'.customlinkdiv div.tabs-panel',
					'.posttypediv div.tabs-panel',
					'.taxonomydiv div.tabs-pane',
				],
				[
					$s->getSetting('boxes.containerBackgroundColor'),
					'border-color' => $boxGrey5,
				]
			),
			//The same applies to the tab title.
			new CssRuleSet(
				[
					'ul.category-tabs li.tabs',
					'ul.add-menu-item-tabs li.tabs',
					'.wp-tab-active',
				],
				[
					$s->getSetting('boxes.containerBackgroundColor'),
					'border-color'        => $boxGrey5,
					'border-bottom-color' => $s->getSetting('boxes.containerBackgroundColor'),
				]
			),
			//Some boxes have bordered content inside them, so we need to adjust that
			//border color, too. Since WP itself uses a grey palette, it's hard to say
			//if this color should be derived from the border color or the container
			//background color. For now, we'll use the background color.
			new CssRuleSet(
				['#major-publishing-actions'],
				['border-top-color' => $boxGrey5]
			)
		);

		$g->addRuleSet(
			[
				'.postbox-header',
				'.ws_ame_custom_postbox h2.hndle',
				'.ws-ame-postbox.ws-ame-postbox .ws-ame-postbox-header',
			],
			[
				$s->getSetting('boxes.headerBackgroundColor'),

				'border-bottom-color' => $g->firstNonEmpty([
					$s->getSetting('boxes.headerBorder.color'),
					$s->getSetting('boxes.containerBorder.color'),
				]),
				'border-bottom-style' => $g->firstNonEmpty([
					$s->getSetting('boxes.headerBorder.style'),
					$s->getSetting('boxes.containerBorder.style'),
				]),
				'border-bottom-width' => $g->firstNonEmpty([
					$g->cssValue($s->getSetting('boxes.headerBorder.width')),
					$g->cssValue($s->getSetting('boxes.containerBorder.width')),
				]),
			]
		);

		//Round the top corners of the header if the container has rounded corners. This is
		//necessary because otherwise the header's corners would overlap the container's border.
		$g->addCondition(
			$g->ifSome([
				$g->compare($s->getSetting('boxes.containerBorder.radius.topLeft'), '>', 0),
				$g->compare($s->getSetting('boxes.containerBorder.radius.topRight'), '>', 0),
			]),
			new CssRuleSet(
				[
					'.postbox-header',
					'.ws_ame_custom_postbox h2.hndle',
					'.ws-ame-postbox .ws-ame-postbox-header',
				],
				[
					//Header's border radius = container's border radius - container's border width.
					//Using the same radius for both would result in a small gap between the header's
					//background and the container's border.
					'--ame-ds-box-tl-radius'    => $s->getSetting('boxes.containerBorder.radius.topLeft'),
					'--ame-ds-box-tr-radius'    => $s->getSetting('boxes.containerBorder.radius.topRight'),
					'--ame-ds-box-border-width' => $s->getSetting('boxes.containerBorder.width'),
					'border-top-left-radius'    => 'calc(var(--ame-ds-box-tl-radius, 0px) - var(--ame-ds-box-border-width, 0px))',
					'border-top-right-radius'   => 'calc(var(--ame-ds-box-tr-radius, 0px) - var(--ame-ds-box-border-width, 0px))',
				]
			)
		);

		//Box header text.
		$g->addRuleSet(
			[
				'.postbox-header h2',
				'.metabox-holder .postbox h2.hndle',
				'#poststuff .postbox h2',
			],
			[
				$s->getSetting('boxes.headerTextColor'),
				$s->getSetting('boxes.headerFont'),
				$s->getSetting('boxes.headerPadding'),
			]
		);

		//When changing the header padding, button/handle height could be calculated
		//as `font size * line-height + padding` (36px by default). This doesn't appear
		//to be necessary for layout, but it could be good for usability.
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $g
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary $s
	 * @return void
	 */
	private function addHeadingStyles(StyleGenerator $g, AbstractSettingsDictionary $s) {
		$g->addRuleSet(
			['.wrap h1'],
			[
				$s->getSetting('headings.h1.textColor'),
				$s->getSetting('headings.h1.font'),
				$s->getSetting('headings.h1.spacing'),
			]
		);
		//Careful: This also applies to widget headings.
		$g->addRuleSet(
			['h2'],
			[
				$s->getSetting('headings.h2.textColor'),
				$s->getSetting('headings.h2.font'),
				$s->getSetting('headings.h2.spacing'),
			]
		);
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $g
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary $s
	 * @return void
	 */
	private function addTableStyles(StyleGenerator $g, AbstractSettingsDictionary $s) {
		$g->addRuleSet(
			['table.widefat'],
			[
				$s->getSetting('tables.backgroundColor'),
				$s->getSetting('tables.border'),
				$s->getSetting('tables.boxShadow'),
			]
		);

		$g->addRuleSet(
			[
				'table.striped > tbody > :nth-child(2n+1)',
				'table .alternate',
			],
			[$s->getSetting('tables.alternateRowBackgroundColor')]
		);

		//Fonts.
		$g->addRuleSet(
			[
				'table.widefat td',
				'table.widefat td p',
				'table.widefat td ol',
				'table.widefat td ul',
			],
			[$s->getSetting('tables.baseFont')]
		);

		//Row actions have the same font size as the rest of the table, but WP specifies
		//it with a separate CSS rule, so we need to override it.
		$g->addRuleSet(
			['table.widefat .row-actions'],
			[$s->getSetting('tables.baseFont.size')]
		);

		//Row titles and column headings are 1px larger than regular table text by default.
		//While calculating the new size proportionally would be nice, it's too complicated.
		//Let's just increase the base font size by 1px.
		$g->addCondition(
			$g->ifTruthy($s->getSetting('tables.baseFont.size')),
			new CssRuleSet(
				['table.widefat'],
				[
					'--ame-ds-table-font-size'       => $s->getSetting('tables.baseFont.size'),
					'--ame-ds-table-base-heading-fs' => 'calc(var(--ame-ds-table-font-size, 13px) + 1px)',
				]
			),
			new CssRuleSet(
				['table.widefat .row-title'],
				[
					//WP adds "!important" to the .row-title font size, so we have to do the same.
					'font-size' => 'var(--ame-ds-table-base-heading-fs, 14px) !important',
				]
			),
			new CssRuleSet(
				[
					'table.widefat th',
					'.widefat thead td',
					'.widefat tfoot td',
				],
				['font-size' => 'var(--ame-ds-table-base-heading-fs, 14px)']
			)
		);

		//Padding.
		$g->addRuleSet(
			['table.widefat td', '.table.widefat th'],
			[$s->getSetting('tables.cellPadding')]
		);

		//Cell borders. WordPress doesn't have these by default.
		$g->addCondition(
			$g->ifTruthy($s->getSetting('tables.cellBorderColor')),
			//Vertical borders.
			new CssRuleSet(
				[
					'table.widefat td',
					'table.widefat th',
				],
				[
					'border-right-color' => $s->getSetting('tables.cellBorderColor'),
					$s->getSetting('tables.verticalCellBorder'),
				]
			),
			//No border on the last cell in a row.
			new CssRuleSet(
				[
					'table.widefat td:last-child',
					'table.widefat th:last-child',
				],
				[
					'border-right-style' => 'none',
					'border-right-width' => '0',
				]
			),
			//Horizontal borders.
			new CssRuleSet(
				[
					'table.widefat tbody > tr td',
					'table.widefat tbody > tr th',
				],
				[
					'border-bottom-color' => $s->getSetting('tables.cellBorderColor'),
					$s->getSetting('tables.horizontalCellBorder'),
				]
			),
			//No border on the last row.
			new CssRuleSet(
				[
					'table.widefat tbody > tr:last-child td',
					'table.widefat tbody > tr:last-child th',
				],
				[
					'border-bottom-style' => 'none',
					'border-bottom-width' => '0',
				]
			)
		);

		//Set the border radius of the header/footer corners to the table's border radius
		//minus its border width to prevent the header/footer background color from overflowing
		//the table borders.
		$g->addRuleSet(
			['table.widefat'],
			['--ame-ds-table-border-width' => $s->getSetting('tables.border.width')]
		);
		foreach (['top', 'bottom'] as $vertical) {
			foreach (['left', 'right'] as $horizontal) {
				$settingName = 'tables.border.radius.' . $vertical . ucfirst($horizontal);
				$varName = '--ame-ds-table-border-radius-' . substr($vertical, 0, 1) . substr($horizontal, 0, 1);
				$property = 'border-' . $vertical . '-' . $horizontal . '-radius';

				$elementSelector = ($vertical === 'top') ? 'thead' : 'tfoot';
				$childSelector = (($horizontal === 'left') ? 'first' : 'last') . '-child';

				$g->addCondition(
					$g->ifTruthy($s->getSetting($settingName)),
					new CssRuleSet(
						[
							'table.widefat ' . $elementSelector . ' th:' . $childSelector,
							'table.widefat ' . $elementSelector . ' td:' . $childSelector,
						],
						[
							$varName  => $s->getSetting($settingName),
							$property =>
								'calc(var(' . $varName . ', 0) - var(--ame-ds-table-border-width, 1px))',
						]
					)
				);
			}
		}

		//Table header. By default, the footer uses the same styles. Optionally, the user
		//can override footer styles (see below).
		$g->addRuleSet(
			[
				'table.widefat thead th',
				'table.widefat thead td',
				'table.widefat tfoot th',
				'table.widefat tfoot td',
			],
			[
				$s->getSetting('tables.header.font'),
				$s->getSetting('tables.header.backgroundColor'),
				$s->getSetting('tables.header.padding'),
			]
		);
		//Header border.
		$g->addRuleSet(
			[
				'table.widefat thead th',
				'table.widefat thead td',
			],
			[$s->getSetting('tables.header.border')]
		);
		//Apply the same border to the footer. It can be overridden later.
		//This is a bit complicated because for the footer it's a top border.
		$g->addRuleSet(
			[
				'table.widefat tfoot th',
				'table.widefat tfoot td',
			],
			[
				'border-top-color' => $s->getSetting('tables.header.border.color'),
				'border-top-style' => $s->getSetting('tables.header.border.style'),
				'border-top-width' => $s->getSetting('tables.header.border.width'),
			]
		);
		//Vertical borders between header cells. Also applies to the footer by default.
		$g->addRuleSet(
			[
				'table.widefat thead th',
				'table.widefat thead td',
			],
			[$s->getSetting('tables.header.verticalBorderStyle')]
		);
		//Vertical border color and width. These need to be set to something in case
		//cell borders are not enabled for table content, or we'll get browser defaults
		//(e.g. black borders) when the user changes the border style.
		$g->addCondition(
			$g->ifAll([
				$g->ifTruthy($s->getSetting('tables.header.verticalBorderStyle')),
				$g->compare($s->getSetting('tables.header.verticalBorderStyle'), '!=', 'none'),
			]),
			new CssRuleSet(
				[
					'table.widefat thead th',
					'table.widefat thead td',
				],
				[
					//Vertical border color is either the bottom border color or the cell border color.
					'border-right-color' => $g->firstNonEmpty([
						$s->getSetting('tables.header.border.color'),
						$s->getSetting('tables.cellBorderColor'),
					]),
					//Border width defaults to the cell border width or 1 px. Don't use browser
					//defaults because they might not match the rest of the table.
					'border-right-width' => $g->firstNonEmpty([
						$g->cssValue($s->getSetting('tables.verticalCellBorder.width')),
						'1px',
					]),
				]
			),
			//Still no border on the rightmost header cell.
			new CssRuleSet(
				[
					'table.widefat thead th:last-child',
					'table.widefat thead td:last-child',
				],
				['border-right-style' => 'none']
			)
		);

		//For sortable and sorted column headings, the padding applies to the link
		//instead of the cell. The cell itself has zero padding.
		$g->addRuleSet(
			[
				'table.widefat thead th.sortable a',
				'table.widefat thead th.sorted   a',
				'table.widefat tfoot th.sortable a',
				'table.widefat tfoot th.sorted   a',
			],
			[$s->getSetting('tables.header.padding')]
		);

		//Table footer.
		$g->addRuleSet(
			[
				'table.widefat tfoot th',
				'table.widefat tfoot td',
			],
			[
				$s->getSetting('tables.footer.font'),
				$s->getSetting('tables.footer.backgroundColor'),
				$s->getSetting('tables.footer.padding'),
			]
		);
		//Footer border. This will override the CSS generated using the header border settings.
		$g->addRuleSet(
			[
				'table.widefat tfoot th',
				'table.widefat tfoot td',
			],
			[$s->getSetting('tables.footer.border')]
		);
		//Vertical borders between footer cells.
		//Defaults to the same style as the vertical border between header cells.
		$g->setVariable(
			'footerVerticalBorderStyle',
			$s->getSetting('tables.footer.verticalBorderStyle'),
			$s->getSetting('tables.header.verticalBorderStyle')
		);
		$footerVerticalBorderStyle = $g->variable('footerVerticalBorderStyle');
		$g->addRuleSet(
			[
				'table.widefat tfoot th',
				'table.widefat tfoot td',
			],
			['border-right-style' => $footerVerticalBorderStyle]
		);
		//Vertical border color and width. These only apply to the footer.
		$g->addCondition(
			$g->ifAll([
				$g->ifTruthy($footerVerticalBorderStyle),
				$g->compare($footerVerticalBorderStyle, '!=', 'none'),
			]),
			new CssRuleSet(
				[
					'table.widefat tfoot th',
					'table.widefat tfoot td',
				],
				[
					'border-right-color' => $g->firstNonEmpty([
						$s->getSetting('tables.footer.border.color'),
						$s->getSetting('tables.header.border.color'),
						$s->getSetting('tables.cellBorderColor'),
					]),
					'border-right-width' => $g->firstNonEmpty([
						$g->cssValue($s->getSetting('tables.verticalCellBorder.width')),
						'1px',
					]),
				]
			),
			new CssRuleSet(
				[
					'table.widefat tfoot th:last-child',
					'table.widefat tfoot td:last-child',
				],
				['border-right-style' => 'none']
			)
		);

		//Sortable footer headings.
		$g->addRuleSet(
			[
				'table.widefat tfoot th.sortable a',
				'table.widefat tfoot th.sorted   a',
			],
			[$s->getSetting('tables.footer.padding')]
		);

		//Move the sorting indicator up/down to compensate for font size changes.
		//In WP 6.2, its "::before" pseudo-element has "top: -4px" by default.
		$g->addCondition(
			$g->ifSome([
				$s->getSetting('tables.baseFont.size'),
				$s->getSetting('tables.header.font.size'),
				$s->getSetting('tables.footer.font.size'),
			]),
			//Put header/footer font size in variables.
			new CssRuleSet(
				['table.widefat'],
				[
					//Custom font sizes.
					'--ame-ds-table-h-cfs'   => $s->getSetting('tables.header.font.size'),
					'--ame-ds-table-f-cfs'   => $s->getSetting('tables.footer.font.size'),
					//Effective font sizes. This will be the custom size if it's set, otherwise the base size.
					'--ame-ds-table-head-fs' => 'var(--ame-ds-table-h-cfs, var(--ame-ds-table-base-heading-fs, 14px))',
					'--ame-ds-table-foot-fs' => 'var(--ame-ds-table-f-cfs, var(--ame-ds-table-base-heading-fs, 14px))',
				]
			),
			//The multiplier was chosen by trying 5 different font sizes and
			//eyeballing the best indicator position.
			new CssRuleSet(
				['table.widefat thead .sorting-indicator::before'],
				['top' => 'calc(-4px + (var(--ame-ds-table-head-fs, 14px) - 14px) * 0.75)']
			),
			new CssRuleSet(
				['table.widefat tfoot .sorting-indicator::before'],
				['top' => 'calc(-4px + (var(--ame-ds-table-foot-fs, 14px) - 14px) * 0.75)']
			)
		);
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator $g
	 * @param \YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary $s
	 * @return void
	 */
	private function addToolbarStyles(StyleGenerator $g, AbstractSettingsDictionary $s) {
		//Toolbar height.
		$g->addMediaQuery(
			$g->ifTruthy($s->getSetting('toolbar.height')),
			//Don't change the height in mobile view (width <= 782px). It has a different layout
			//and height. Because media queries don't affect specificity, WordPress core styles
			//would not override our custom styles automatically.
			'screen and (min-width: 783px)', //i.e. larger than 782px, not including 782px.
			//Let WP and other components know that the toolbar height has changed.
			new CssRuleSet(
				['html'],
				['--wp-admin--admin-bar--height' => $g->cssValue($s->getSetting('toolbar.height'))]
			),
			//Change the height.
			new CssRuleSet(
				['#wpadminbar'],
				['height' => $g->cssValue($s->getSetting('toolbar.height'))]
			),
			//Move the rest of the page down.
			new CssRuleSet(
				['html.wp-toolbar'],
				['padding-top' => 'var(--wp-admin--admin-bar--height, 32px)']
			),
			//By default, the line-height of everything in the toolbar is set to a unitless
			//value that evaluates to the toolbar height(32px) given the default font size.
			//We'll override that to match the custom height.
			new CssRuleSet(
				['#wpadminbar *'],
				['line-height' => 'var(--wp-admin--admin-bar--height, 32px)']
			),
			//Top level item height matches the bar height.
			new CssRuleSet(
				[
					'#wpadminbar .quicklinks a',
					'#wpadminbar .quicklinks .ab-empty-item',
					'#wpadminbar .shortlink-input',
				],
				[
					'height' => 'var(--wp-admin--admin-bar--height, 32px)',
					//'line-height' => 'var(--wp-admin--admin-bar--height, 32px)',
				]
			),
			//WP sets the label height separately.
			new CssRuleSet(
				['#wpadminbar .ab-label'],
				['height' => 'var(--wp-admin--admin-bar--height, 32px)']
			),
			//Icons have a 4px top padding by default. Adjust it to keep the icon centered.
			new CssRuleSet(
				[
					'#wpadminbar > #wp-toolbar > #wp-admin-bar-root-default .ab-icon',
					'#wpadminbar .ab-icon',
					'#wpadminbar .ab-item:before',
					'.wp-admin-bar-arrow',
				],
				['padding-top' => 'calc(4px + (var(--wp-admin--admin-bar--height, 32px) - 32px) / 2)']
			),
			//Move Gutenberg editor's header and top toolbar down.
			new CssRuleSet(
				[
					'.interface-interface-skeleton',
					'.edit-post-visual-editor .block-editor-block-contextual-toolbar.is-fixed',
				],
				['top' => 'var(--wp-admin--admin-bar--height, 32px)']
			),
			//Move the WooCommerce header.
			new CssRuleSet(
				['.woocommerce-layout .woocommerce-layout__header'],
				['top' => 'var(--wp-admin--admin-bar--height, 32px)']
			)
		);

		//Toolbar fonts.
		$submenuItemSelectors = [
			'#wpadminbar .quicklinks .menupop ul li .ab-item',
			'#wpadminbar .quicklinks .menupop ul li a strong',
			'#wpadminbar .quicklinks .menupop.hover ul li .ab-item',
			'#wpadminbar.nojs .quicklinks .menupop:hover ul li .ab-item',
			//Special case for the "Debug Bar" plugin.
			'#wpadminbar .quicklinks .menupop#wp-admin-bar-debug-bar ul li .ab-item > span',
		];

		$g->addMediaQuery(
			Expression::true(),
			'screen and (min-width: 783px)',
			//Base font.
			new CssRuleSet(
				['#wpadminbar *'],
				[$s->getSetting('toolbar.font')]
			),
			//Submenu font.
			new CssRuleSet(
				$submenuItemSelectors,
				[$s->getSetting('toolbar.submenuFont')]
			)
		);

		//Submenu item height and line height.
		//By default, submenu font size is the same as the base font size. We'll need
		//this calculate the item height if the user hasn't specified a custom value.
		$g->setVariable(
			'customSubmenuFontSize',
			$g->cssValue($s->getSetting('toolbar.submenuFont.size')),
			$g->cssValue($s->getSetting('toolbar.font.size'))
		);
		$g->addMediaQuery(
			$g->ifSome([
				$g->variable('customSubmenuFontSize'),
				$s->getSetting('toolbar.submenuItemHeight'),
			]),
			'screen and (min-width: 783px)',
			new CssRuleSet(
				['#wpadminbar'],
				[
					//Custom font size.
					'--ame-ds-tb-sm-fs'               => $g->variable('customSubmenuFontSize'),
					//WordPress sets the submenu item height explicitly, and it's usually 2x the font size.
					//If we have a custom item height, use that. Otherwise, calculate the height based on
					//the font size.
					'--ame-ds-tb-cust-sm-item-height' => $g->cssValue($s->getSetting('toolbar.submenuItemHeight')),
					'--ame-ds-tb-sm-item-height'      =>
						'var(--ame-ds-tb-cust-sm-item-height, calc(var(--ame-ds-tb-sm-fs) * 2))',
				]
			),
			new CssRuleSet(
				$submenuItemSelectors,
				[
					//Explicitly set the font size to the calculated custom value
					//in case the way Font class does it changes in the future.
					'font-size'   => 'var(--ame-ds-tb-sm-fs)',
					//Height and line height are equal.
					'height'      => 'var(--ame-ds-tb-sm-item-height)',
					'line-height' => 'var(--ame-ds-tb-sm-item-height)',
				]
			)
		);
		//The user's display name is a special case - it won't match the submenu item selectors.
		//So we'll need to set its font size explicitly, or it will default to the base font size.
		$g->addRuleSet(
			['#wpadminbar #wp-admin-bar-user-info .display-name'],
			['font-size' => $g->variable('customSubmenuFontSize')]
		);

		//Toolbar colors.
		$g->addRuleSet(
			['#wpadminbar'],
			[
				'background-color' => $s->getSetting('toolbar.backgroundColor'),
				'color'            => $s->getSetting('toolbar.textColor'),
			]
		);

		$g->addRuleSet(
			[
				'#wpadminbar .ab-item',
				'#wpadminbar a.ab-item',
				'#wpadminbar > #wp-toolbar span.ab-label',
				'#wpadminbar > #wp-toolbar span.noticon',
			],
			['color' => $s->getSetting('toolbar.textColor')]
		);

		//Note: May need to add an "icon color" setting in the future to properly support
		//dark-text-on-light-background themes.
		$g->setVariable(
			'toolbarIconColor',
			$g->editHexAsHsl(
				$g->cssValue($s->getSetting('toolbar.backgroundColor')),
				null,
				0.07,
				0.95
			)
		);
		$g->setVariable(
			'itemBackgroundHoverColor',
			$g->firstNonEmpty([
				$g->cssValue($s->getSetting('toolbar.itemHoverBackgroundColor')),
				$g->darken($s->getSetting('toolbar.backgroundColor'), 7),
			])
		);
		$itemBackgroundHoverColor = $g->variable('itemBackgroundHoverColor');

		//Toolbar icons: top level.
		$g->addRuleSet(
			[
				'#wpadminbar .ab-icon',
				'#wpadminbar .ab-icon:before',
				'#wpadminbar .ab-item:before',
				'#wpadminbar .ab-item:after',
			],
			['color' => $g->variable('toolbarIconColor')]
		);
		//WordPress has a lot of different selectors for Toolbar/Admin Bar colors
		//and item hover/focus states. We'll try to override all of them.
		$g->addRuleSet(
			[
				'#wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item',
				'#wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus',
				'#wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus',
				'#wpadminbar.nojs .ab-top-menu > li.menupop:hover > .ab-item',
				'#wpadminbar .ab-top-menu > li.menupop.hover > .ab-item',
			],
			[
				'background-color' => $itemBackgroundHoverColor,
				$s->getSetting('toolbar.textHoverColor'),
			]
		);
		$g->addRuleSet(
			[
				'#wpadminbar:not(.mobile) > #wp-toolbar li:hover span.ab-label',
				'#wpadminbar:not(.mobile) > #wp-toolbar li.hover span.ab-label',
				'#wpadminbar:not(.mobile) > #wp-toolbar a:focus span.ab-label',
				'#wpadminbar:not(.mobile) li:hover .ab-icon:before',
				'#wpadminbar:not(.mobile) li:hover .ab-item:before',
				'#wpadminbar:not(.mobile) li:hover .ab-item:after',
				'#wpadminbar:not(.mobile) li:hover #adminbarsearch:before',
			],
			[$s->getSetting('toolbar.textHoverColor')]
		);
		//The submenu background color matches the background color of a hovered item.
		$g->addRuleSet(
			['#wpadminbar .menupop .ab-sub-wrapper'],
			['background-color' => $itemBackgroundHoverColor]
		);
		//WP has an "alt" submenu background color that's used for the site list
		//in the "My Sites" menu. This calculation is not the same one that WP uses to
		//generate the color because that depends on color scheme settings, but this
		//should be close enough.
		$g->addRuleSet(
			[
				'#wpadminbar .quicklinks .menupop ul.ab-sub-secondary',
				'#wpadminbar .quicklinks .menupop ul.ab-sub-secondary .ab-submenu',
			],
			['background-color' => $g->adjustHexAsHsl($itemBackgroundHoverColor, 5, -0.02, 0.05)]
		);

		//Submenu text.
		$g->addRuleSet(
			[
				'#wpadminbar .ab-submenu .ab-item',
				'#wpadminbar .quicklinks .menupop ul li a',
				'#wpadminbar .quicklinks .menupop.hover ul li a',
				'#wpadminbar.nojs .quicklinks .menupop:hover ul li a',
			],
			[$s->getSetting('toolbar.submenuTextColor')]
		);

		//Submenu icons.
		$g->addRuleSet(
			[
				'#wpadminbar .quicklinks li .blavatar',
				'#wpadminbar .menupop .menupop > .ab-item:before',
			],
			['color' => $g->variable('toolbarIconColor')]
		);

		//Submenu text hover color. Oh boy, there are a lot of these selectors.
		$g->setVariable(
			'submenuTextHoverColor',
			$s->getSetting('toolbar.submenuTextHoverColor'),
			$s->getSetting('toolbar.textHoverColor')
		);
		$g->addRuleSet(
			[
				'#wpadminbar .quicklinks .menupop ul li a:hover',
				'#wpadminbar .quicklinks .menupop ul li a:focus',
				'#wpadminbar .quicklinks .menupop ul li a:hover strong',
				'#wpadminbar .quicklinks .menupop ul li a:focus strong',
				'#wpadminbar .quicklinks .ab-sub-wrapper .menupop.hover > a',
				'#wpadminbar .quicklinks .menupop.hover ul li a:hover',
				'#wpadminbar .quicklinks .menupop.hover ul li a:focus',
				'#wpadminbar.nojs .quicklinks .menupop:hover ul li a:hover',
				'#wpadminbar.nojs .quicklinks .menupop:hover ul li a:focus',
				'#wpadminbar li:hover .ab-icon:before',
				'#wpadminbar li:hover .ab-item:before',

				//Strangely, WP does seem to set the icon focus color to the submenu text hover color.
				'#wpadminbar li a:focus .ab-icon:before',
				'#wpadminbar li .ab-item:focus:before',
				'#wpadminbar li .ab-item:focus .ab-icon:before',
				'#wpadminbar li.hover .ab-icon:before',
				'#wpadminbar li.hover .ab-item:before',

				'#wpadminbar li:hover #adminbarsearch:before',
				'#wpadminbar li #adminbarsearch.adminbar-focused:before',
				'#wpadminbar .quicklinks li a:hover .blavatar',
				'#wpadminbar .quicklinks li a:focus .blavatar',
				'#wpadminbar .quicklinks .ab-sub-wrapper .menupop.hover > a .blavatar',
				'#wpadminbar .menupop .menupop > .ab-item:hover:before',
				'#wpadminbar.mobile .quicklinks .ab-icon:before',
				'#wpadminbar.mobile .quicklinks .ab-item:before',
			],
			['color' => $g->variable('submenuTextHoverColor')]
		);

		//Submenu item hover background color.
		$g->addRuleSet(
			[
				'#wpadminbar .quicklinks .menupop ul li a:hover',
				'#wpadminbar .quicklinks .menupop ul li a:focus',
				'#wpadminbar .quicklinks .menupop ul li > .ab-item:hover',
			],
			['background-color' => $s->getSetting('toolbar.submenuHoverBackgroundColor')]
		);

		//"My Account" has its own text color rules.
		//The display name uses the base text color, not the submenu text color.
		$g->addRuleSet(
			['#wpadminbar #wp-admin-bar-user-info .display-name'],
			[$s->getSetting('toolbar.textColor')]
		);
		//...and the submenu text hover color.
		$g->addRuleSet(
			['#wpadminbar #wp-admin-bar-user-info a:hover .display-name'],
			['color' => $g->variable('submenuTextHoverColor')]
		);
		//The username uses the submenu text color, though.
		$g->addRuleSet(
			['#wpadminbar #wp-admin-bar-user-info .username'],
			[$s->getSetting('toolbar.submenuTextColor')]
		);

		//Miscellaneous submenu icons.
		$g->addRuleSet(
			[
				'#wpadminbar.mobile .quicklinks .hover .ab-icon:before',
				'#wpadminbar.mobile .quicklinks .hover .ab-item:before',
				'#wpadminbar #adminbarsearch:before',
			],
			['color' => $g->variable('toolbarIconColor')]
		);

		//Toolbar shadow
		$g->addRuleSet(
			['#wpadminbar'],
			[$s->getSetting('toolbar.boxShadow')]
		);
		//Submenu shadow.
		$g->addRuleSet(
			[
				'#wpadminbar .menupop .ab-sub-wrapper',
				'#wpadminbar .shortlink-input',
			],
			[$s->getSetting('toolbar.submenuBoxShadow')]
		);
	}

	public function registerAdminCustomizerPreview(AmeAdminCustomizer $customizer) {
		$customizer->addPreviewStyleGenerator($this->getStyleGenerator($this->loadSettings()));
	}

	public function outputUserCustomCss() {
		$settings = $this->loadSettings();
		$customCss = $settings['customCss'];
		if ( !empty($customCss) && apply_filters('admin_menu_editor-ac_custom_css_enabled', true) ) {
			//The custom CSS should already be sanitized when it's saved (unless
			//the user has the "unfiltered_html" capability).
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<style id="' . esc_attr(self::CUSTOM_CSS_STYLE_ID) . '">' . $customCss . '</style>';
		}
	}

	public function enqueueAdminCustomizerPreview() {
		if ( !apply_filters('admin_menu_editor-ac_custom_css_enabled', true) ) {
			return;
		}

		$settings = $this->loadSettings();

		ScriptDependency::create(plugins_url('custom-css-preview.js', __FILE__))
			->addDependencies('jquery')
			->addJsVariable(
				'wsAmeDsCustomCssPreviewData',
				[
					'settingId'            => $settings->getSetting('customCss')->getId(),
					'normalStyleElementId' => self::CUSTOM_CSS_STYLE_ID,
				]
			)
			->enqueue();
	}

	/**
	 * @param callable $addCss
	 * @return void
	 */
	public function addAdminThemeCss($addCss) {
		$s = $this->loadSettings();
		$g = $this->getStyleGenerator($s);
		call_user_func($addCss, $g->generateCss());
	}

	/**
	 * @param callable $addCss
	 * @return void
	 * @internal
	 */
	public function addCustomCssToAdminTheme($addCss) {
		if ( !apply_filters('admin_menu_editor-ac_custom_css_enabled', true) ) {
			return;
		}

		//The custom CSS is usually output separately, but for an admin theme,
		//we'll just include it in the main stylesheet.
		$s = $this->loadSettings();
		$customCss = $s['customCss'];
		if ( !empty($customCss) ) {
			call_user_func($addCss, $customCss);
		}
	}

	public function getExportOptionLabel() {
		return 'Dashboard styles';
	}
}