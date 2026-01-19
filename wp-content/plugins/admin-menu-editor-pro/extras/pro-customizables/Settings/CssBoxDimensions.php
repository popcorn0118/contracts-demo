<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

abstract class CssBoxDimensions extends CssSettingCollection {
	/**
	 * @var \YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting[]
	 */
	protected $settings = [];

	/**
	 * @var \YahnisElsts\AdminMenuEditor\Customizable\Settings\StringEnumSetting
	 */
	protected $unitSetting;

	protected $dimensions = [
		'left'   => 'left',
		'top'    => 'top',
		'right'  => 'right',
		'bottom' => 'bottom',
	];

	protected $cssPropertyPrefix = '';

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		parent::__construct($id, $store, $params);

		$this->unitSetting = $this->createChild(
			'unit',
			Settings\StringEnumSetting::class,
			['px', 'em', '%'],
			['default' => 'px']
		);

		$this->unitSetting->describeChoice('px', 'px');
		$this->unitSetting->describeChoice('em', 'em');
		$this->unitSetting->describeChoice('%', '%');

		foreach ($this->dimensions as $dimension => $cssName) {
			$this->createChild(
				$dimension,
				CssLengthSetting::class,
				$this->getCssPropertyForDimension($dimension),
				[
					'minValue'    => -1000,
					'maxValue'    => 1000,
					'unitSetting' => $this->unitSetting,
					'default'     => \ameUtils::get($params, ['dimensionDefaults', $dimension]),
				]
			);
		}
	}

	protected function getCssPropertyForDimension($dimensionKey) {
		if ( empty($this->cssPropertyPrefix) ) {
			return '';
		}
		if ( isset($this->dimensions[$dimensionKey]) ) {
			$cssNameComponent = $this->dimensions[$dimensionKey];
		} else {
			$cssNameComponent = $dimensionKey;
		}
		return $this->combineCssPrefixAndComponent($this->cssPropertyPrefix, $cssNameComponent);
	}

	/**
	 * @param string $cssPropertyPrefix CSS property prefix, e.g. "padding-" or "border-".
	 * @param string $cssNameComponent CSS name for an individual side or corner, e.g. "top" or "bottom-right".
	 * @return string
	 */
	protected function combineCssPrefixAndComponent($cssPropertyPrefix, $cssNameComponent) {
		return $cssPropertyPrefix . $cssNameComponent;
	}

	/**
	 * @return \YahnisElsts\AdminMenuEditor\Customizable\Settings\StringEnumSetting
	 */
	public function getUnitSetting() {
		return $this->unitSetting;
	}

	abstract public function createControls(Builders\ElementBuilderFactory $b);
}