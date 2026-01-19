<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Number;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssValueGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class CssLengthSetting extends WithSchema\CssLengthSetting implements CssPropertyGenerator, CssValueGenerator {
	public function __construct(
		$id,
		StorageInterface $store,
		$cssProperty,
		$params = array()
	) {
		if ( $cssProperty && !isset($params['cssProperty']) ) {
			$params['cssProperty'] = $cssProperty;
		}

		$schema = new Number();
		//Copy min, max, and default value from params to the schema.
		if ( isset($params['minValue']) ) {
			$schema->min($params['minValue']);
		}
		if ( isset($params['maxValue']) ) {
			$schema->max($params['maxValue']);
		}
		if ( array_key_exists('default', $params) ) { //Not isset() because default can be null.
			$schema->defaultValue($params['default']);
		}

		parent::__construct($schema, $id, $store, $params);
	}
}