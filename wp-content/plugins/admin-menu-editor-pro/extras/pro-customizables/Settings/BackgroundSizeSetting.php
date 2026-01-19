<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssEnumSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class BackgroundSizeSetting extends CssEnumSetting {
	public function __construct($id, ?StorageInterface $store = null, $params = array()) {
		$enumValues = array('auto', 'cover', 'contain', '100% 100%');

		parent::__construct($id, $store, 'background-size', $enumValues, $params);

		$this->describeChoice(
			'auto',
			'Original size'
		);
		$this->describeChoice(
			'contain',
			'Fit',
			'Scales the image to fit the area, preserving the aspect ratio. '
			. 'Can result in empty spaces around the image if image repeat is disabled.'
		);
		$this->describeChoice(
			'cover',
			'Cover',
			'Scales the image to completely cover the area, clipping any parts that don\'t fit. '
			. 'Preserves the aspect ratio.'
		);
		$this->describeChoice(
			'100% 100%',
			'Stretch to fill',
			'Scales the image to exactly match the size of the area. Does NOT preserve the aspect ratio.'
		);
	}
}