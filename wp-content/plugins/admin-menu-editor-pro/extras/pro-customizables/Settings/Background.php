<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\ImageSetting;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class Background extends CssSettingCollection implements CssPropertyGenerator {
	protected $label = 'Background';
	/**
	 * @var ImageSetting
	 */
	protected $backgroundImage;

	public function __construct($id, ?StorageInterface $store = null, $params = array()) {
		parent::__construct($id, $store, $params);

		$this->createChild(
			'color',
			CssColorSetting::class,
			'background-color',
			array('default' => null)
		);

		$this->backgroundImage = $this->createChild(
			'image',
			BackgroundImageSetting::class,
			array('default' => null)
		);

		$this->createChild(
			'repeat',
			CssEnumSetting::class,
			'background-repeat',
			array('repeat', 'repeat-x', 'repeat-y', 'no-repeat'),
			array('default' => 'repeat', 'label' => 'Repeat image')
		);

		//Enumerate background positions.
		//Note: More options are available in the CSS specification, such as % offsets.
		// https://developer.mozilla.org/en-US/docs/Web/CSS/background-position
		$horizontal = array('left', 'center', 'right');
		$vertical = array('top', 'center', 'bottom');
		$validPositions = array();
		foreach ($horizontal as $h) {
			foreach ($vertical as $v) {
				if ( $h === $v ) {
					$validPositions[] = $h;
				} else {
					$validPositions[] = $h . ' ' . $v;
				}
			}
		}

		$this->createChild(
			'position',
			CssEnumSetting::class,
			'background-position',
			$validPositions,
			array('default' => 'left top', 'label' => 'Image position')
		);

		$this->createChild(
			'size',
			BackgroundSizeSetting::class,
			array('default' => 'auto')
		);

		$this->createChild(
			'attachment',
			CssEnumSetting::class,
			'background-attachment',
			array('scroll', 'fixed', 'local'),
			array(
				'default' => 'scroll',
				//Not sure about adding/omitting articles here.
				'label'   => 'Scroll background image with page',
			)
		);
	}

	public function getCssProperties() {
		$properties = array();

		$backgroundUrl = $this->backgroundImage->getImageUrl();
		if ( $backgroundUrl === null ) {
			//If there is no background image then most background properties are
			//irrelevant, but we still need the background color.
			$included = array_intersect_key($this->settings, array('color' => true));
		} else {
			$included = $this->settings;
		}

		foreach ($included as $suffix => $setting) {
			if ( $setting instanceof CssPropertyGenerator ) {
				$properties = array_merge($properties, $setting->getCssProperties());
			} else if ( $setting instanceof AbstractSetting ) {
				$value = $setting->getValue();
				if ( $value !== null ) {
					$properties['background-' . $suffix] = $value;
				}
			}
		}

		return $properties;
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		return [
			$b->auto($this->settings['color'])->asGroup('Background color'),
			$b->auto($this->settings['image'])->asGroup('Background image'),
			$b->backgroundRepeat($this->settings['repeat'])->asGroup(),
			$b->backgroundPosition($this->settings['position'])->asGroup(),
			$b->backgroundSize($this->settings['size'])->asGroup(),
			$b->toggleCheckBox($this->settings['attachment'])
				->onValue('scroll')
				->offValue('fixed')
				->asGroup('Scrolling'),
		];
	}
}