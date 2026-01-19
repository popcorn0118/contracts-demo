<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator;

class CssMediaQuery extends ConditionalAtRule {

	public function __construct($conditionString, $nestedStatements = []) {
		parent::__construct('media', $conditionString, $nestedStatements);
	}
}