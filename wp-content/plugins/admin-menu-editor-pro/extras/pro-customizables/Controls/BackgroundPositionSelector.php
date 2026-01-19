<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\ClassicControl;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;

class BackgroundPositionSelector extends ClassicControl {

	public function renderContent(Renderer $renderer) {
		$selectedPosition = $this->getMainSettingValue('left top');
		$fieldName = $this->getFieldName();

		echo '<div class="ame-background-position-control">';
		printf(
			'<p class="ame-background-position-output"><code>%s</code></p>',
			esc_html($selectedPosition)
		);

		echo '<fieldset>';
		foreach (array('top', 'center', 'bottom') as $verticalPos) {
			echo '<div class="ame-background-position-group">';
			foreach (array('left', 'center', 'right') as $horizontalPos) {
				$position = $horizontalPos . ' ' . $verticalPos;
				if ( ($horizontalPos === 'center') && ($horizontalPos === $verticalPos) ) {
					$position = 'center';
				}

				if ( $horizontalPos !== 'center' ) {
					$icon = 'dashicons-arrow-' . $horizontalPos . '-alt';
				} else if ( $verticalPos === 'top' ) {
					$icon = 'dashicons-arrow-up-alt';
				} else if ( $verticalPos === 'bottom' ) {
					$icon = 'dashicons-arrow-down-alt';
				} else {
					$icon = null;
				}

				$isSelected = ($position === $selectedPosition);

				echo '<label title="' . esc_attr($position) . '">';
				echo HtmlHelper::tag('input', [
					'type'    => 'radio',
					'name'    => $fieldName,
					'value'   => $position,
					'checked' => $isSelected,
				]);
				echo '<span class="screen-reader-text">' . esc_html(ucwords($position)) . '</span>';

				if ( $icon ) {
					$buttonTitle = HtmlHelper::tag('span', ['class' => ['dashicons', $icon]]);
				} else {
					$buttonTitle = HtmlHelper::tag('span', ['class' => 'ame-bps-center'], ' ');
				}
				echo HtmlHelper::tag('span', ['class' => 'button'], $buttonTitle);
				echo '</label>';
			}
			echo '</div>';
		}
		echo '</fieldset>';
		$this->outputSiblingDescription();
		echo '</div>';

		self::enqueueDependencies();
	}
}