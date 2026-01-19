<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

class FunctionCall extends Expression {
	private $name;
	/**
	 * @var Expression[]
	 */
	private $args;
	/**
	 * @var callable
	 */
	private $callback;

	/**
	 * @param string $name
	 * @param array $args
	 * @param callable $callback
	 */
	public function __construct($name, $args, $callback) {
		$this->name = $name;
		$this->args = self::boxValues($args);
		$this->callback = $callback;
	}

	public function getValue() {
		$actualArgs = array_map(
			function (Expression $arg) {
				return $arg->getValue();
			},
			$this->args
		);
		return call_user_func($this->callback, $actualArgs);
	}

	public function checkUsedSettingStatus() {
		$usesSettings = false;

		foreach($this->args as $expr) {
			list($argUsesSettings, $argHasNonEmptySettings) = $expr->checkUsedSettingStatus();
			if ( $argHasNonEmptySettings ) {
				return [true, true];
			}
			if ( $argUsesSettings ) {
				$usesSettings = true;
			}
		}

		return [$usesSettings, false];
	}


	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			't'    => 'funcCall',
			'name' => $this->name,
			'args' => $this->args,
		];
	}
}