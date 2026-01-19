<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\RadioGroup;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\StaticHtml;

class BorderStyleSelector extends RadioGroup {
	const SUPPORTED_STYLES = array(
		'hidden' => true,
		'dotted' => true,
		'dashed' => true,
		'solid'  => true,
		'double' => true,
		'groove' => true,
		'ridge'  => true,
		'inset'  => true,
		'outset' => true,
		//"none", "inherit", and "initial" are not included because they
		//cannot be represented visually.
	);

	public function __construct($settings = array(), $params = array()) {
		parent::__construct($settings, $params);

		//Add samples for recognized values.
		foreach ($this->options as $option) {
			if (
				!empty(self::SUPPORTED_STYLES[$option->value])
				//Don't overwrite existing children.
				&& empty($this->choiceChildren[$option->value])
			) {
				$this->choiceChildren[$option->value] = new StaticHtml(sprintf(
					'<label class="ame-border-sample-container" for="%s">'
					. '<span class="ame-border-sample" style="border-top-style: %s"></span>'
					. '</label>',
					$this->getRadioInputId($option),
					esc_attr($option->value)
				));
			}
		}
	}
}