<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\ClassicControl;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting;

class FontStylePicker extends ClassicControl {
	protected $koComponentName = 'ame-font-style-picker';

	public function renderContent(Renderer $renderer) {
		$options = [
			'font-style'      => [
				['value' => null, 'text' => 'Default font style', 'label' => '&mdash;'],
				[
					'value' => 'italic',
					'text'  => 'Italic',
					'label' => '<span class="dashicons dashicons-editor-italic"></span>',
				],
			],
			'text-transform'  => [
				['value' => null, 'text' => 'Default letter case', 'label' => '&mdash;'],
				[
					'value' => 'uppercase',
					'text'  => 'Uppercase',
					'label' => self::fontSample(['text-transform' => 'uppercase']),
				],
				[
					'value' => 'lowercase',
					'text'  => 'Lowercase',
					'label' => self::fontSample(['text-transform' => 'lowercase']),
				],
				[
					'value' => 'capitalize',
					'text'  => 'Capitalize each word',
					'label' => self::fontSample(['text-transform' => 'capitalize']),
				],
			],
			'font-variant'    => [
				['value' => null, 'text' => 'Default font variant', 'label' => '&mdash;'],
				[
					'value' => 'small-caps',
					'text'  => 'Small caps',
					'label' => self::fontSample(['font-variant' => 'small-caps']),
				],
			],
			'text-decoration' => [
				['value' => null, 'text' => 'Default text decoration', 'label' => '&mdash;'],
				[
					'value' => 'underline',
					'text'  => 'Underline',
					'label' => '<span class="dashicons dashicons-editor-underline"></span>',
				],
				[
					'value' => 'line-through',
					'text'  => 'Strikethrough',
					'label' => '<span class="dashicons dashicons-editor-strikethrough"></span>',
				],
			],
		];

		echo HtmlHelper::tag('fieldset', [
			'class' => array_merge(['ame-font-style-control'], $this->classes),
			'style' => $this->styles,
		]);

		foreach ($options as $property => $choices) {
			if ( !isset($this->settings[$property]) ) {
				continue;
			}

			/** @var Setting $setting */
			$setting = $this->settings[$property];

			foreach ($choices as $choice) {
				$text = !empty($choice['text']) ? $choice['text'] : '';

				$dataBindings = [];

				$classes = ['ame-font-style-control-choice'];
				$inputClasses = [];

				if ( $choice['value'] === null ) {
					$classes[] = 'ame-font-style-null-choice';
					$inputClasses[] = 'ame-font-style-null-input';
					$dataBindings = [
						'ameObservableChangeEvents' => $this->getKoObservableExpression(null, $setting),
					];
				}

				echo HtmlHelper::tag('label', [
					'class' => $classes,
					'title' => $text,
				]);

				//Use a checkbox instead of a radio button because we want a second
				//click on the same option to deselect it.
				echo HtmlHelper::tag(
					'input',
					[
						'type'                   => 'checkbox',
						'name'                   => $this->getFieldName(null, $setting),
						'value'                  => (string)$choice['value'],
						'checked'                => ($choice['value'] === $setting->getValue()),
						'class'                  => $inputClasses,
						'data-ac-setting-id'     => $setting->getId(),
						'data-ame-on-value'      => wp_json_encode($choice['value']),
						'data-ame-off-value'     => wp_json_encode(null),
						'data-ame-font-property' => $property,
						'data-bind'              => $this->makeKoDataBind($dataBindings),
					]
				);

				if ( !empty($text) ) {
					echo HtmlHelper::tag('span', ['class' => 'screen-reader-text'], esc_html($text));
				}

				echo HtmlHelper::tag(
					'span',
					[
						'class' => ['button', 'button-secondary', 'ame-font-style-control-choice-label'],
					],
					$choice['label']
				);

				echo '</label>';
			}
		}

		echo '</fieldset>';

		static::enqueueDependencies();
	}

	protected static function fontSample($styles) {
		return HtmlHelper::tag(
			'span',
			['class' => 'ame-font-sample', 'style' => $styles],
			'ab' //Match Gutenberg's letter case icons in WP 6.0.
		);
	}
}