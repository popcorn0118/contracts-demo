<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

use YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator;

class VariableReference extends Expression {
	private $name;
	/**
	 * @var \YahnisElsts\AdminMenuEditor\StyleGenerator\StyleGenerator
	 */
	private $generator;

	/**
	 * @param string $name
	 */
	public function __construct($name, StyleGenerator $generator) {
		$this->name = $name;
		$this->generator = $generator;
	}

	public function getName() {
		return $this->name;
	}

	public function getValue() {
		return $this->generator->resolveVariable($this->name);
	}

	public function checkUsedSettingStatus() {
		return $this->generator->checkSettingsUsedByVariable($this->name);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			't'    => 'var',
			'name' => $this->getName(),
		];
	}
}