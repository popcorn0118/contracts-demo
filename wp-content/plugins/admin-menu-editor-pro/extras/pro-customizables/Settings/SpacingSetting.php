<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssBoxDimensions;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting;

abstract class SpacingSetting extends CssBoxDimensions implements CssPropertyGenerator {
	public function getCssProperties() {
		$properties = [];
		if ( empty($this->cssPropertyPrefix) ) {
			return $properties;
		}

		foreach ($this->dimensions as $side => $unused) {
			$setting = $this->settings[$side];
			if ( !($setting instanceof CssLengthSetting) ) {
				continue;
			}

			$value = $setting->getCssValue();
			if ( $value !== null ) {
				$properties[$this->getCssPropertyForDimension($side)] = $value;
			}
		}
		return $properties;
	}

	public function getJsPreviewConfiguration() {
		$sideConfigs = [];
		foreach ($this->dimensions as $side => $unused) {
			$setting = $this->settings[$side];
			if ( !($setting instanceof CssLengthSetting) ) {
				continue;
			}
			$sideConfigs = array_merge($sideConfigs, $setting->getJsPreviewConfiguration());
		}
		return $sideConfigs;
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		return [$b->boxDimensions($this)];
	}
}