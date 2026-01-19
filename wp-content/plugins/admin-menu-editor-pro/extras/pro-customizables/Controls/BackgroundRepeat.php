<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\RadioGroup;

class BackgroundRepeat extends RadioGroup {
	public function __construct($settings = array(), $params = array()) {
		$params = array_merge(
			array(
				'choices' => array(
					'repeat'    => 'Repeat all',
					'repeat-x'  => 'Repeat horizontally',
					'repeat-y'  => 'Repeat vertically',
					'no-repeat' => 'Do not repeat',
				),
				'label'   => 'Repeat image',
			),
			$params
		);

		parent::__construct($settings, $params);
	}
}