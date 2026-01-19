<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;

class BackgroundImageSetting extends Settings\ImageSetting implements CssPropertyGenerator {
	protected $cssProperty = 'background-image';

	public function getCssProperties() {
		$value = $this->getImageUrl();
		if ( $value === null ) {
			return array();
		}
		return array($this->cssProperty => 'url("' . esc_url_raw($value) . '")');
	}

	public function getJsPreviewConfiguration() {
		return []; //TODO: Background image preview.
	}
}