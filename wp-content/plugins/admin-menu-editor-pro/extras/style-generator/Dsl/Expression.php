<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

abstract class Expression implements SerializableToJsExpression {
	abstract public function getValue();

	/**
	 * Check if the expression uses settings, and if so, whether any of them
	 * are non-empty.
	 *
	 * This is used by the CSS ruleset class to determine whether it should
	 * generate any declarations. If the ruleset uses settings, but all of them
	 * are empty, then no declarations will be generated even if the ruleset
	 * has some declarations that don't use settings.
	 *
	 * @return array{0: bool, 1: bool} [usesSettings, hasNonEmptySettings]
	 */
	public function checkUsedSettingStatus() {
		return [false, false];
	}

	public static function boxValues($values) {
		return array_map(
			function ($value) {
				if ( $value instanceof SerializableToJsExpression ) {
					return $value; //Already boxed.
				} else if ( $value instanceof AbstractSetting ) {
					return new SettingReference($value);
				} else if ( is_array($value) && !empty($value) ) {
					return new ArrayValue($value);
				} else {
					return new ConstantValue($value);
				}
			},
			$values
		);
	}

	public static function true() {
		return new ConstantValue(true);
	}
}