<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\RadioButtonBar;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssSettingCollection;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\JsFunctionCall;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssColorSetting;

class BoxShadow extends CssSettingCollection implements CssPropertyGenerator {
	const DEFAULT_MODE = 'default';
	const CUSTOM_MODE = 'custom';
	const NONE_MODE = 'none';

	/**
	 * @var \YahnisElsts\AdminMenuEditor\Customizable\Settings\StringEnumSetting
	 */
	protected $modeSetting;

	/**
	 * @var array{
	 *          color:    \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssColorSetting,
	 *          offset-x: \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting,
	 *          offset-y: \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting,
	 *          blur:     \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting,
	 *          spread:   \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting
	 *      }
	 */
	protected $settings = array();

	protected static $collectionCounter = 0;

	public function __construct($id, ?StorageInterface $store = null, $params = array()) {
		parent::__construct($id, $store, $params);

		$this->modeSetting = $this->createChild(
			'mode',
			Settings\StringEnumSetting::class,
			array(self::CUSTOM_MODE, self::NONE_MODE, self::DEFAULT_MODE),
			array('default' => self::DEFAULT_MODE, 'label' => 'Enable shadow')
		);
		$this->modeSetting->describeChoice(
			self::DEFAULT_MODE,
			'Default',
			'Leave the box shadow unchanged',
			null,
			'dashicons-image-rotate'
		);
		$this->modeSetting->describeChoice(
			self::CUSTOM_MODE,
			'Custom',
			'Apply a custom box shadow',
			null,
			'dashicons-admin-generic'
		);
		$this->modeSetting->describeChoice(
			self::NONE_MODE,
			'None',
			'Remove the box shadow',
			null,
			'dashicons-no-alt'
		);

		$this->createChild(
			'color',
			CssColorSetting::class,
			null,
			array('default' => '#000000', 'label' => 'Color')
		);

		$this->createChild(
			'colorOpacity',
			Settings\FloatSetting::class,
			array('default' => 0.5, 'minValue' => 0.0, 'maxValue' => 1.0, 'label' => 'Color opacity')
		);

		$this->createChild(
			'offset-x',
			CssLengthSetting::class,
			null,
			array(
				'minValue'    => -100,
				'maxValue'    => 100,
				'default'     => 0,
				'defaultUnit' => 'px',
				'label'       => 'Horizontal position',
			)
		);

		$this->createChild(
			'offset-y',
			CssLengthSetting::class,
			null,
			array(
				'minValue'    => -100,
				'maxValue'    => 100,
				'default'     => 0,
				'defaultUnit' => 'px',
				'label'       => 'Vertical position',
			)
		);

		$this->createChild(
			'blur',
			CssLengthSetting::class,
			null,
			array(
				'minValue'    => 0,
				'maxValue'    => 100,
				'default'     => 10,
				'defaultUnit' => 'px',
				'label'       => 'Blur',
			)
		);

		$this->createChild(
			'spread',
			CssLengthSetting::class,
			null,
			array(
				'minValue'    => -100,
				'maxValue'    => 100,
				'default'     => 0,
				'defaultUnit' => 'px',
				'label'       => 'Spread',
			)
		);

		$this->createChild(
			'inset',
			Settings\BooleanSetting::class,
			array(
				'default'    => false,
				'label'      => 'Inset shadow',
				'groupTitle' => 'Inset',
			)
		);
	}

	public function getCssProperties() {
		$mode = $this->modeSetting->getValue(self::DEFAULT_MODE);
		$color = (string)($this->settings['color']->getValue());

		if ( $mode === self::DEFAULT_MODE ) {
			return array();
		} else if ( ($mode === self::NONE_MODE) || ($color === 'transparent') || ($color === '') ) {
			return array('box-shadow' => 'none');
		}

		$parts = array(
			$this->settings['offset-x']->getCssValue(),
			$this->settings['offset-y']->getCssValue(),
			$this->settings['blur']->getCssValue(),
			$this->settings['spread']->getCssValue(),
		);
		//All of these settings should have valid defaults,
		//but let's be safe and zero out invalid values.
		foreach ($parts as $key => $value) {
			if ( !is_string($value) || ($value === '') ) {
				$parts[$key] = '0';
			}
		}

		if ( $this->settings['inset']->getValue() ) {
			$parts[] = 'inset';
		}

		$colorOpacity = $this->settings['colorOpacity']->getValue();
		if ( $colorOpacity < 1.0 ) {
			$color = \ameUtils::convertHexColorToRgba($color, $colorOpacity);
		}

		//Color is always the last element.
		$parts[] = $color;

		return array('box-shadow' => implode(' ', $parts));
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		$controls = [
			$b->control(RadioButtonBar::class, $this->settings['mode'])
				->classes('ame-box-shadow-mode-control'),
			$b->auto($this->settings['offset-x'])
				->params(['rangeByUnit' => ['px' => ['min' => -50, 'max' => 50, 'step' => 1]]]),
			$b->auto($this->settings['offset-y'])
				->params(['rangeByUnit' => ['px' => ['min' => -50, 'max' => 50, 'step' => 1]]]),
			$b->auto($this->settings['blur'])
				->params(['rangeByUnit' => ['px' => ['min' => 0, 'max' => 50, 'step' => 1]]]),
			$b->auto($this->settings['spread'])
				->params(['rangeByUnit' => ['px' => ['min' => -25, 'max' => 25, 'step' => 1]]]),
			$b->auto($this->settings['color']),
			$b->auto($this->settings['colorOpacity']),
			$b->auto($this->settings['inset']),
		];

		//Add a unique class to all controls so that we can switch the mode to "custom"
		//when the user changes any of the settings.
		self::$collectionCounter++;
		$collectionClass = 'ame-box-shadow-collection-' . self::$collectionCounter;
		foreach ($controls as $control) {
			if ( $control instanceof Builders\BaseElementBuilder ) {
				$control->classes($collectionClass);
			}
		}

		return $controls;
	}

	public function getJsPreviewConfiguration() {
		$inputs = [];
		foreach ($this->settings as $key => $setting) {
			$inputs[$key] = $setting;
		}

		return [new JsFunctionCall('shadow', $inputs)];
	}
}