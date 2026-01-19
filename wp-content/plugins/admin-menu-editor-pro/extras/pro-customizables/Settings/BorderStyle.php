<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class BorderStyle extends CssEnumSetting {
	const SUPPORTED_STYLES = ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'];

	protected $label = 'Border style';

	public function __construct($id, StorageInterface $store, $cssProperty = 'border-style', $params = []) {
		$enumValues = self::SUPPORTED_STYLES;

		if (
			!empty($params['nullAllowed'])
			|| (array_key_exists('default', $params) && ($params['default'] === null))
		) {
			//If null is allowed, it's the first option.
			array_unshift($enumValues, null);
		}

		parent::__construct($id, $store, $cssProperty, $enumValues, $params);
	}
}