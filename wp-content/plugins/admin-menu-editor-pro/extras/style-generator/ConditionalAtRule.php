<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator;

class ConditionalAtRule implements CssStatement {
	/**
	 * @var string
	 */
	protected $identifier;
	/**
	 * @var string
	 */
	protected $conditionString;
	/**
	 * @var CssStatement[]
	 */
	private $nestedStatements;

	public function __construct($identifier, $conditionString, $nestedStatements = []) {
		$this->identifier = $identifier;
		$this->conditionString = $conditionString;
		$this->nestedStatements = $nestedStatements;
	}

	public function getCssText($indentLevel = 0) {
		$indent = str_repeat("\t", $indentLevel);

		$nestedStatementCss = '';
		foreach ($this->nestedStatements as $statement) {
			$statementCss = $statement->getCssText($indentLevel + 1);
			if ( $statementCss !== '' ) {
				$nestedStatementCss .= $indent . $statementCss . "\n";
			}
		}

		if ( $nestedStatementCss === '' ) {
			return '';
		}

		$output = "@$this->identifier $this->conditionString {\n";
		$output .= $nestedStatementCss;
		$output .= "}\n";
		return $output;
	}

	public function serializeForJs() {
		return [
			't'                => 'conditionalAtRule',
			'identifier'       => $this->identifier,
			'condition'        => $this->conditionString,
			'nestedStatements' => array_map(
				function (CssStatement $statement) {
					return $statement->serializeForJs();
				},
				$this->nestedStatements
			),
		];
	}
}