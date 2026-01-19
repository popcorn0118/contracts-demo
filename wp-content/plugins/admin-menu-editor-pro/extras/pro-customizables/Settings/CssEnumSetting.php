<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\JsFunctionCall;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class CssEnumSetting extends Settings\StringEnumSetting implements CssPropertyGenerator {
	protected $cssProperty = '';

	public function __construct($id, StorageInterface $store, $cssProperty, $enumValues, $params = array()) {
		parent::__construct($id, $store, $enumValues, $params);
		$this->cssProperty = $cssProperty;
	}

	public function getCssProperties() {
		$value = $this->getValue();
		if ( $value === null ) {
			return array();
		}

		return array($this->cssProperty => $value,);
	}

	public function getJsPreviewConfiguration() {
		return [JsFunctionCall::prop($this->cssProperty, $this)];
	}
}