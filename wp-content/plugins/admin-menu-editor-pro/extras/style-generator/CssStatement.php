<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator;

interface CssStatement {
	/**
	 * @return string
	 */
	public function getCssText($indentLevel = 0);

	/**
	 * @return array|object
	 */
	public function serializeForJs();
}