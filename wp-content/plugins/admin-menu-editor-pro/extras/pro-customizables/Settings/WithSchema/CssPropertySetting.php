<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Schema;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SingularSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssValueGenerator;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\JsFunctionCall;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\SettingReference;

class CssPropertySetting extends SingularSetting implements CssPropertyGenerator, CssValueGenerator {
	protected $cssProperty = '';

	public function __construct(Schema $schema, $id = '', ?StorageInterface $store = null, $params = []) {
		parent::__construct($schema, $id, $store, $params);

		if ( array_key_exists('cssProperty', $params) ) {
			$this->cssProperty = $params['cssProperty'];
		}
	}

	public function getCssProperties() {
		if ( empty($this->cssProperty) ) {
			return [];
		}

		$value = $this->getCssValue();
		if ( ($value === null) || ($value === '') ) {
			return [];
		}

		return [$this->cssProperty => $value];
	}

	public function getJsPreviewConfiguration() {
		return [JsFunctionCall::prop($this->cssProperty, $this->getCssValueExpression())];
	}

	public function getCssValue() {
		return $this->getValue();
	}

	public function getCssValueExpression() {
		return new SettingReference($this);
	}
}