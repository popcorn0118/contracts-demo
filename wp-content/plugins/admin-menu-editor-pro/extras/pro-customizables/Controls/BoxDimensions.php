<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\AbstractNumericControl;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\ChoiceControlOption;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\PopupSlider;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\BorderRadius;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssBoxDimensions;

class BoxDimensions extends AbstractNumericControl {
	const DEFAULT_DIMENSION_NAMES = ['top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'];

	protected $type = 'boxDimensions';
	protected $koComponentName = 'ame-box-dimensions';

	const INPUT_ID_PREFIX = '_ame-box-dimensions-input-';
	const UNIT_SELECTED_PREFIX = '_ame-box-dimensions-unit-';
	protected static $generatedIdCounter = 0;

	protected $dimensionNames = null;

	/**
	 * @var bool Whether to merge the top/bottom and left/right dimensions into a single dimension.
	 *           This is currently only implemented for the KO component.
	 */
	protected $symmetricModeEnabled = false;

	public function __construct($settings = [], $params = []) {
		parent::__construct($settings, $params);

		if ( isset($params['dimensionNames']) ) {
			$this->dimensionNames = $params['dimensionNames'];
		} else if ( $this->mainSetting instanceof BorderRadius ) {
			$this->dimensionNames = [
				'topLeft'     => 'Top left',
				'topRight'    => 'Top right',
				'bottomRight' => 'Bottom right',
				'bottomLeft'  => 'Bottom left',
			];
		}

		if ( isset($params['symmetricMode']) ) {
			$this->symmetricModeEnabled = $params['symmetricMode'];
		}
	}

	public function renderContent(Renderer $renderer) {
		if ( !($this->mainSetting instanceof CssBoxDimensions) ) {
			throw new \InvalidArgumentException('The main setting for this control must be a CssBoxDimensions.');
		}

		echo HtmlHelper::tag('fieldset', [
			'class'              => array_merge(
				[
					'ame-container-with-popup-slider',
					'ame-box-dimensions-control',
				],
				$this->classes
			),
			'style'              => $this->styles,
			'data-ac-setting-id' => $this->mainSetting->getId(),
			'data-bind'          => $this->makeKoDataBind($this->getKoEnableBinding()),
		]);

		$unitElementId = self::UNIT_SELECTED_PREFIX . (self::$generatedIdCounter++);

		$order = $this->getDimensionNames();
		foreach ($order as $key => $label) {
			$setting = $this->mainSetting->getChild($key);
			if ( !$setting ) {
				continue;
			}

			$inputId = self::INPUT_ID_PREFIX . (self::$generatedIdCounter++);

			echo HtmlHelper::tag('div', [
				'class' => ['ame-single-box-dimension', 'ame-box-dimension-' . $key],
			]);

			echo HtmlHelper::tag(
				'input',
				array_merge(
					$this->getBasicInputAttributes(),
					[
						'name'     => $this->getFieldName($key),
						'value'    => $setting->getValue(),
						'id'       => $inputId,
						'class'    => [
							'ame-small-number-input',
							'ame-input-with-popup-slider',
							'ame-box-dimensions-input',
							'ame-box-dimensions-input-' . $key,
						],
						'disabled' => !$this->isEnabled(),

						'data-unit-element-id'   => $unitElementId,
						'data-ame-box-dimension' => $key,
						'data-bind'              => $this->makeKoDataBind(array_merge([
							'value'                     =>
								$this->getKoObservableExpression($setting->getValue(), $setting),
							'ameObservableChangeEvents' => 'true',
						], $this->getKoEnableBinding())),
					]
				)
			);

			echo HtmlHelper::tag(
				'label',
				[
					'for'   => $inputId,
					'class' => 'ame-box-dimension-label',
				],
				HtmlHelper::tag(
					'span',
					['class' => 'ame-box-dimension-label-text'],
					esc_html($label)
				)
			);
			echo '</div>';
		}

		//Unit selector.
		$unitSetting = $this->mainSetting->getUnitSetting();
		$this->renderUnitDropdown($unitSetting, [
			'name'               => $this->getFieldName('unit'),
			'id'                 => $unitElementId,
			'class'              => 'ame-box-dimensions-unit-selector',
			'data-slider-ranges' => wp_json_encode($this->getSliderRanges()),
			'disabled'           => !$this->isEnabled(),
		]);

		//"Link" button.
		//Enable it by default if all sides are the same. Do not enable if the value is an empty
		//string or null: overwriting all defaults with equal values leads to undesirable results
		//for elements that have different defaults for different sides, like admin menu items.
		$linkButtonEnabled = true;
		$firstSetting = $this->mainSetting->getChild(\ameUtils::getFirstKey($order));
		if ( $firstSetting ) {
			$firstValue = $firstSetting->getValue();
			if ( ($firstValue === '') || ($firstValue === null) ) {
				$linkButtonEnabled = false;
			} else {
				foreach ($order as $side => $label) {
					$setting = $this->mainSetting->getChild($side);
					if ( $setting && ($setting->getValue() !== $firstValue) ) {
						$linkButtonEnabled = false;
						break;
					}
				}
			}
		}

		$buttonClasses = ['button', 'button-secondary', 'ame-box-dimensions-link-button', 'hide-if-no-js'];
		if ( $linkButtonEnabled ) {
			$buttonClasses[] = 'active';
		}
		echo HtmlHelper::tag(
			'button',
			[
				'class'     => $buttonClasses,
				'title'     => 'Link values',
				'disabled'  => !$this->isEnabled(),
				'data-bind' => $this->makeKoDataBind($this->getKoEnableBinding()),
			],
			'<span class="dashicons dashicons-admin-links"></span>'
		);

		$slider = new PopupSlider([
			'positionParentSelector' => '.ame-single-box-dimension',
			'verticalOffset'         => -2,
		]);
		$slider->render();
		echo '</fieldset>';

		static::enqueueDependencies();
	}

	/**
	 * @return array<string,string>
	 */
	protected function getDimensionNames() {
		if ( $this->dimensionNames === null ) {
			return self::DEFAULT_DIMENSION_NAMES;
		}
		return $this->dimensionNames;
	}

	protected function getKoComponentParams() {
		$params = parent::getKoComponentParams();

		if ( ($this->mainSetting instanceof CssBoxDimensions) ) {
			$unitSetting = $this->mainSetting->getUnitSetting();
			$params['unitDropdownOptions'] = ChoiceControlOption::generateKoOptions(
				ChoiceControlOption::tryGenerateFromSetting($unitSetting)
			);
		}

		if ( $this->dimensionNames !== null ) {
			$dimensionNames = $this->getDimensionNames();
			//To preserve order, convert the associative array to a list of [key, value] pairs.
			$pairs = [];
			foreach ($dimensionNames as $key => $label) {
				$pairs[] = [$key, $label];
			}
			$params['dimensionNames'] = $pairs;
		}

		if ( $this->symmetricModeEnabled ) {
			$params['symmetricMode'] = true;
		}

		return $params;
	}

	public function enqueueKoComponentDependencies() {
		parent::enqueueKoComponentDependencies();

		//Enqueue the Popup Slider. Unlike regular rendering, this doesn't happen
		//automatically with KO components.
		PopupSlider::enqueueDependencies();
	}
}