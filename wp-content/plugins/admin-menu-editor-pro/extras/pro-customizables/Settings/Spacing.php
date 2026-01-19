<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class Spacing extends CssSettingCollection implements CssPropertyGenerator {
	protected $label = 'Spacing';

	/**
	 * @var \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\Padding
	 */
	private $padding;
	/**
	 * @var \YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\Margins
	 */
	private $margin;

	public function __construct($id, ?StorageInterface $store = null, $params = array()) {
		parent::__construct($id, $store, $params);

		$this->padding = $this->createChild('padding', Padding::class, ['label' => 'Padding']);
		$this->margin = $this->createChild('margin', Margins::class, ['label' => 'Margin']);
	}

	public function getCssProperties() {
		$properties = array();
		foreach ($this->settings as $setting) {
			if ( $setting instanceof CssPropertyGenerator ) {
				$properties = array_merge($properties, $setting->getCssProperties());
			}
		}
		return $properties;
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		$ranges = [
			'px' => ['min' => 0, 'max' => 100, 'step' => 1],
			'em' => ['min' => 0, 'max' => 10, 'step' => 0.1],
			'%'  => ['min' => 0, 'max' => 100, 'step' => 1],
		];

		return [
			$b->boxDimensions($this->settings['margin'])
				->params(['rangeByUnit' => $ranges,]),
			$b->boxDimensions($this->settings['padding'])
				->params(['rangeByUnit' => $ranges,]),
		];
	}

	public function getJsPreviewConfiguration() {
		//Preview should be handled by the child settings.
		return array_merge(
			$this->padding->getJsPreviewConfiguration(),
			$this->margin->getJsPreviewConfiguration()
		);
	}
}