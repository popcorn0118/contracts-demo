<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

class ConstantValue extends Expression {
	private $value;

	/**
	 * @param mixed $value
	 */
	public function __construct($value) {
		$this->value = $value;
	}

	public function getValue() {
		return $this->value;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			't'     => 'constant',
			'value' => $this->value,
		];
	}
}