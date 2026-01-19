<?php

namespace YahnisElsts\AdminMenuEditor\StyleGenerator;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SingularSetting;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssValueGenerator;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\Expression;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\JsFunctionCall;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\SerializableToJsExpression;

class CssRuleSet implements CssStatement {
	private $selectors;
	private $declarations;
	private $nestedStatements;

	/**
	 * @param string[] $selectors
	 * @param array<string|int|float|CssPropertyGenerator> $declarations
	 * @param CssStatement[] $nestedStatements
	 */
	public function __construct($selectors, $declarations, $nestedStatements = []) {
		$this->selectors = $selectors;
		$this->declarations = $declarations;
		$this->nestedStatements = $nestedStatements;
	}

	public function serializeForJs() {
		$result = [
			'selectors'  => $this->selectors,
			'generators' => iterator_to_array($this->makeGeneratorConfigs(), false),
		];
		if ( !empty($this->nestedStatements) ) {
			$result['nestedStatements'] = array_map(
				function (CssStatement $statement) {
					return $statement->serializeForJs();
				},
				$this->nestedStatements
			);
		}
		return $result;
	}

	private function makeGeneratorConfigs() {
		foreach ($this->declarations as $key => $value) {
			if ( is_string($key) ) {
				if ( $value instanceof CssPropertyGenerator ) {
					foreach ($value->getJsPreviewConfiguration() as $c) {
						$c->setCssProperty($key);
						yield $c;
					}
				} else {
					yield JsFunctionCall::prop($key, $value);
				}
			} else if ( $value instanceof CssPropertyGenerator ) {
				yield from $value->getJsPreviewConfiguration();
			} else if ( $value instanceof SerializableToJsExpression ) {
				//It's up to you to ensure that the serialized expression actually
				//produces a valid array of CSS declarations.
				yield $value;
			} else {
				throw new \LogicException(sprintf(
					"Error generating JS config: Unsupported declaration type '%s' for key '%s'",
					gettype($value),
					$key
				));
			}
		}
	}

	public function getCssText($indentLevel = 0, $parentSelectors = []) {
		$hasSettings = false;
		$hasNonEmptySettings = false;

		$declarations = [];
		foreach ($this->declarations as $key => $value) {
			//Remember if this rule set uses settings.
			if ( !$hasNonEmptySettings ) {
				if ( $value instanceof AbstractSetting ) {
					$hasSettings = true;
					if ( $value->getValue('') !== '' ) {
						$hasNonEmptySettings = true;
					}
				} else if ($value instanceof Expression) {
					list($exprUsesSettings, $exprHasNonEmptySettings) = $value->checkUsedSettingStatus();
					$hasSettings = $hasSettings || $exprUsesSettings;
					$hasNonEmptySettings = $hasNonEmptySettings || $exprHasNonEmptySettings;
				}
			}

			if ( is_string($key) ) {
				$cssValue = null;

				if ( $value instanceof CssValueGenerator ) {
					$cssValue = $value->getCssValue();
				} else if ( $value instanceof Expression ) {
					$cssValue = $value->getValue();
				} else if ( $value instanceof Setting ) {
					$cssValue = $value->getValue();
				} else if ($value instanceof SingularSetting) {
					$cssValue = $value->getValue();
				} else if ( is_scalar($value) ) {
					$cssValue = $value;
				} else {
					throw new \LogicException(sprintf(
						"Unsupported declaration type '%s' for key '%s'",
						gettype($value),
						$key
					));
				}

				if ( !StyleGenerator::isEmptyCssValue($cssValue) ) {
					$declarations[$key] = $cssValue;
				}

			} else if ( $value instanceof CssPropertyGenerator ) {
				$declarations = array_merge($declarations, $value->getCssProperties());
			} else {
				throw new \LogicException(sprintf(
					"Unsupported declaration type '%s'",
					gettype($value)
				));
			}
		}

		//Generate CSS for nested statements.
		$nestedStatementCss = [];
		foreach ($this->nestedStatements as $nestedStatement) {
			if ( $nestedStatement instanceof CssRuleSet ) {
				$nestedStatementCss[] = $nestedStatement->getCssText($indentLevel + 1, $this->selectors);
			} else {
				$nestedStatementCss[] = $nestedStatement->getCssText($indentLevel + 1);
			}
		}

		//If this ruleset is based on dynamic settings but all settings are empty,
		//don't generate any CSS declarations. However, nested statements can still
		//generate CSS in this case.
		if ( $hasSettings && !$hasNonEmptySettings ) {
			$declarations = [];
		}
		if ( empty($declarations) && empty($nestedStatementCss) ) {
			return '';
		}

		$css = '';

		if ( !empty($declarations) ) {
			//Combine our selectors with the parent selectors like SCSS does.
			$selectors = $this->selectors;
			if ( !empty($parentSelectors) ) {
				$selectors = self::combineSelectors($selectors, $parentSelectors);
			}

			$selectorIndent = str_repeat("\t", $indentLevel);
			$declarationIndent = str_repeat("\t", $indentLevel + 1);

			$css .= $selectorIndent . implode(', ', $selectors) . " {\n";
			foreach ($declarations as $key => $value) {
				$css .= $declarationIndent . $key . ': ' . $value . ";\n";
			}
			$css .= $selectorIndent . "}\n";
		}

		if ( !empty($nestedStatementCss) ) {
			$css .= implode("\n", $nestedStatementCss);
			//Note: Each statement should already have a trailing newline.
		}

		return $css;
	}

	private static function combineSelectors($selectors, $parentSelectors) {
		$combinedSelectors = [];
		foreach ($selectors as $selector) {
			if ( $selector === '' ) {
				continue;
			}
			if ( strpos($selector, '&') !== false ) {
				//Insert the parent selectors into the current selector at the position of the "&".
				foreach ($parentSelectors as $parentSelector) {
					$combinedSelectors[] = str_replace('&', rtrim($parentSelector), $selector);
				}
			} else {
				//Just append the current selector to the parent selectors.
				foreach ($parentSelectors as $parentSelector) {
					$combinedSelectors[] = $parentSelector . ' ' . $selector;
				}
			}
		}
		return $combinedSelectors;
	}
}