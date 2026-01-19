<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class Borders extends IndividualBorder implements CssPropertyGenerator {
	protected $cssPropertyPrefix = 'border-';

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		$this->side = null;
		unset($params['side']);

		parent::__construct($id, $store, $params);
	}
}