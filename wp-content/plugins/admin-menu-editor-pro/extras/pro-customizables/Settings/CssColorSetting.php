<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Color;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class CssColorSetting extends WithSchema\CssPropertySetting implements CssPropertyGenerator {
	protected $dataType = 'color';
	protected $label = 'Color';

	public function __construct($id, StorageInterface $store, $cssProperty, $params = array()) {
		$schema = (new Color())->orTransparent();
		if ( array_key_exists('default', $params) ) {
			$schema->defaultValue($params['default']);
		}

		$params['cssProperty'] = $cssProperty;

		parent::__construct($schema, $id, $store, $params);
	}
}