<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Control;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;

class HorizontalSeparator extends Control {
	protected $koComponentName = 'ame-horizontal-separator';

	public function __construct($params = []) {
		parent::__construct([], $params);
	}

	public function renderContent(Renderer $renderer) {
		echo HtmlHelper::tag(
			'div',
			['class' => 'ame-horizontal-separator'],
			''
		);
	}
}