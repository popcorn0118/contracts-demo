<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Schema;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\DslFunctions;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\FunctionCall;
use YahnisElsts\AdminMenuEditor\StyleGenerator\Dsl\SettingReference;

class CssLengthSetting extends CssPropertySetting {
	protected $defaultUnit = 'px';
	/**
	 * @var AbstractSetting|null
	 */
	protected $unitSetting = null;

	public function __construct(Schema $schema, $id = '', ?StorageInterface $store = null, $params = []) {
		parent::__construct($schema, $id, $store, $params);

		$this->defaultUnit = isset($params['defaultUnit']) ? $params['defaultUnit'] : $this->defaultUnit;

		if ( isset($params['unitSetting']) ) {
			if ( !($params['unitSetting'] instanceof AbstractSetting) ) {
				throw new \InvalidArgumentException('"unitSetting" must be a setting');
			}
			$this->unitSetting = $params['unitSetting'];
		}
	}

	public function getUnit() {
		if ( $this->unitSetting === null ) {
			return $this->defaultUnit;
		}
		return $this->unitSetting->getValue($this->defaultUnit);
	}

	/**
	 * @return string|null
	 */
	public function getCssValue() {
		return DslFunctions::runFormatLength([
			'value' => $this->getValue(),
			'unit'  => $this->getUnit(),
		]);
	}

	public function getCssValueExpression() {
		$inputs = ['value' => new SettingReference($this)];
		if ( $this->unitSetting ) {
			$inputs['unit'] = $this->unitSetting;
		} else {
			$inputs['unit'] = $this->defaultUnit;
		}
		return new FunctionCall('formatLength', $inputs, [DslFunctions::class, 'runFormatLength']);
	}

	/**
	 * @return AbstractSetting|null
	 */
	public function getUnitSetting() {
		return $this->unitSetting;
	}
}