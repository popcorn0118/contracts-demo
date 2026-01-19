<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable;

interface CssValueGenerator {
	/**
	 * @return string|null
	 */
	public function getCssValue();

	/**
	 * @return \YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\SerializableToJsExpression
	 */
	public function getCssValueExpression();
}