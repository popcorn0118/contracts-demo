<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

use YahnisElsts\AdminMenuEditor\ProCustomizable\CssValueGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

class SettingReference extends Expression {
	/**
	 * @var AbstractSetting
	 */
	private $setting;

	/**
	 * @param AbstractSetting $setting
	 */
	public function __construct(AbstractSetting $setting) {
		$this->setting = $setting;
	}

	public function getValue() {
		if ( $this->setting instanceof CssValueGenerator ) {
			return $this->setting->getCssValue();
		} else {
			return $this->setting->getValue();
		}
	}

	public function checkUsedSettingStatus() {
		$isNonEmpty = ($this->setting->getValue('') !== '');
		return [true, $isNonEmpty];
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			't'  => 'setting',
			'id' => $this->setting->getId(),
		];
	}
}