<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Controls\BoxDimensions;

class BorderRadius extends CssBoxDimensions {
	protected $label = 'Border radius';

	protected $cssPropertyPrefix = 'border-';

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		$this->dimensions = [
			'topLeft'     => 'top-left',
			'topRight'    => 'top-right',
			'bottomRight' => 'bottom-right',
			'bottomLeft'  => 'bottom-left',
		];

		parent::__construct($id, $store, $params);
	}

	protected function combineCssPrefixAndComponent($cssPropertyPrefix, $cssNameComponent) {
		return $cssPropertyPrefix . $cssNameComponent . '-radius';
	}


	public function getCssProperties() {
		$properties = [];

		$allCornersEqual = true;
		$firstCorner = null;

		foreach ($this->dimensions as $corner => $unused) {
			$setting = $this->settings[$corner];
			if ( !($setting instanceof CssLengthSetting) ) {
				continue;
			}

			$value = $setting->getCssValue();
			if ( $value !== null ) {
				$properties[$this->getCssPropertyForDimension($corner)] = $value;
			}

			if ( $firstCorner === null ) {
				$firstCorner = $value;
			} else if ( $firstCorner !== $value ) {
				$allCornersEqual = false;
			}
		}

		//Optimization: If all corners are set and equal, we can use the shorthand property.
		if (
			$allCornersEqual
			&& ($firstCorner !== null)
			&& (count($properties) === count($this->dimensions))
		) {
			$properties = ['border-radius' => $firstCorner];
		}

		return $properties;
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		return [$b->control(BoxDimensions::class, $this)];
	}
}