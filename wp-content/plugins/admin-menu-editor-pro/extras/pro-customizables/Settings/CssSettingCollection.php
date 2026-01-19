<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;

abstract class CssSettingCollection extends Settings\AbstractStructSetting implements CssPropertyGenerator, Settings\PredefinedSet {
	/**
	 * @var string|null
	 */
	protected $cssPropertyPrefix = null;

	public function getCssProperties() {
		$result = array();
		foreach ($this->settings as $key => $setting) {
			if ( $setting instanceof CssPropertyGenerator ) {
				$result = array_merge($result, $setting->getCssProperties());
			} else if ( ($setting instanceof Settings\Setting) && !empty($key) ) {
				$value = $setting->getValue();
				if ( ($value !== null) && ($value !== '') && ($this->cssPropertyPrefix !== null) ) {
					$result[$this->cssPropertyPrefix . $key] = $value;
				}
			}
		}
		return $result;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator($this->settings);
	}

	public function getJsPreviewConfiguration() {
		$configs = [];
		foreach ($this->settings as $setting) {
			if ( $setting instanceof CssPropertyGenerator ) {
				$childConfig = $setting->getJsPreviewConfiguration();
				if ( !empty($childConfig) ) {
					$configs = array_merge($configs, $childConfig);
				}
			}
		}
		return $configs;
	}
}