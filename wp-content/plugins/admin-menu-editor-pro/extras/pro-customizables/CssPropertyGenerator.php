<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable;

use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\JsFunctionCall;

interface CssPropertyGenerator {
	/**
	 * Generate CSS properties for this setting.
	 *
	 * @return array<string,string> [property => value, ...]
	 */
	public function getCssProperties();

	/**
	 * @return JsFunctionCall[]
	 */
	public function getJsPreviewConfiguration();
}