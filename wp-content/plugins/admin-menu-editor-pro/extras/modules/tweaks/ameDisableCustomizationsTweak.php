<?php

class ameDisableCustomizationsTweak extends ameBaseTweak {
	private $component;

	public function __construct($id, $properties) {
		$label = isset($properties['label']) ? $properties['label'] : null;
		parent::__construct($id, $label);

		if ( isset($properties['componentToDisable']) ) {
			$this->component = $properties['componentToDisable'];
		} else {
			throw new LogicException('Component to disable must be provided');
		}
	}

	public function wantsToRunEarly() {
		return true;
	}

	public function apply($settings = null) {
		add_filter(
			'admin_menu_editor-disable_customizations-' . $this->component,
			'__return_true'
		);
	}

	public static function create($id, $properties) {
		return new self($id, $properties);
	}
}