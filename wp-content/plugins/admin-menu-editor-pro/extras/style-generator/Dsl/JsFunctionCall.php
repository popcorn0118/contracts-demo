<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl;

use YahnisElsts\AdminMenuEditor\Customizable\Settings;

/**
 * Special alternative of the FunctionCall expression that only describes JS function
 * calls and is not actually callable from PHP.
 */
class JsFunctionCall implements SerializableToJsExpression {
	/**
	 * @var string
	 */
	private $functionName;
	/**
	 * @var array<string,mixed>
	 */
	private $inputs;

	public function __construct($functionName, $inputs = []) {
		$this->functionName = $functionName;
		$this->inputs = Expression::boxValues($inputs);
	}

	/**
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			't'    => 'funcCall',
			'name' => $this->functionName,
			'args' => $this->inputs,
		];
	}

	public static function withMainSetting(
		Settings\AbstractSetting $mainSetting,
		$generator = 'simpleProperty',
		$extraProperties = []
	) {
		return new self(
			$generator,
			array_merge(['value' => $mainSetting], $extraProperties)
		);
	}

	/**
	 * @param string $cssPropertyName
	 * @return $this
	 * @throws \RuntimeException
	 */
	public function setCssProperty($cssPropertyName) {
		if ( $this->functionName === 'simpleProperty' ) {
			$this->inputs['name'] = new ConstantValue($cssPropertyName);
		} else {
			throw new \RuntimeException('This generator does not support setting CSS property name.');
		}
		return $this;
	}

	/**
	 * Utility method that generates an instance of this class that describes a call
	 * to the "simpleProperty" generator in JS.
	 *
	 * @param string $cssPropertyName
	 * @param mixed $propertyValue
	 * @return SerializableToJsExpression
	 */
	public static function prop($cssPropertyName, $propertyValue) {
		return new self('simpleProperty', [
			'name'  => $cssPropertyName,
			'value' => $propertyValue,
		]);
	}
}