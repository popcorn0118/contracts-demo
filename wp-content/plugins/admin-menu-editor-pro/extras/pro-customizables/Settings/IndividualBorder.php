<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

/**
 * This setting represents an individual CSS border (e.g. the top border).
 *
 * It can also be used to specify all borders if they have the same style, width and
 * color. In this case, the "side" parameter should be set to `null`.
 */
class IndividualBorder extends CssSettingCollection {
	protected $cssPropertyPrefix = 'border-';

	protected $side = null;
	protected $includesColor = true;

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		parent::__construct($id, $store, $params);

		if ( isset($params['side']) ) {
			$this->side = strval($params['side']);
			$this->cssPropertyPrefix = 'border-' . $this->side . '-';
		}
		if ( array_key_exists('includesColor', $params) ) {
			$this->includesColor = (bool)$params['includesColor'];
		}

		if ( $this->includesColor ) {
			$this->createChild(
				'color',
				CssColorSetting::class,
				$this->cssPropertyPrefix . 'color',
				['default' => null, 'label' => 'Color']
			);
		}

		$this->createChild(
			'width',
			CssLengthSetting::class,
			$this->cssPropertyPrefix . 'width',
			['minValue' => 0, 'maxValue' => 200, 'default' => null, 'label' => 'Width']
		);

		$this->createChild(
			'style',
			BorderStyle::class,
			$this->cssPropertyPrefix . 'style',
			['default' => null, 'label' => 'Style', 'nullAllowed' => true]
		);

		if ( $this->side === null ) {
			$this->createChild(
				'radius',
				BorderRadius::class,
				['label' => 'Border radius']
			);
		}
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		$controls = [
			$b->select($this->settings['style']),
		];
		if ( $this->includesColor ) {
			$controls[] = $b->colorPicker($this->settings['color']);
		}
		$controls[] = $b->number($this->settings['width']);

		if ( $this->side === null ) {
			$controls[] = $b->boxDimensions($this->settings['radius']);
		}

		/*
		 * Style (including "Default"; maybe as a dropdown)
		 * Color (if included)
		 * Width
		 * Radius (only for the "all sides" version)
		 */
		return $controls;
	}
}