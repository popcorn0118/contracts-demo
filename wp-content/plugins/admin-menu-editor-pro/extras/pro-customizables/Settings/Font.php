<?php

namespace YahnisElsts\AdminMenuEditor\ProCustomizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Builders;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\ChoiceControlOption;
use YahnisElsts\AdminMenuEditor\ProCustomizable\CssPropertyGenerator;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\StringEnumSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class Font extends CssSettingCollection implements CssPropertyGenerator {
	protected $includesLineHeight = true;

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		parent::__construct($id, $store, $params);

		if ( array_key_exists('includesLineHeight', $params) ) {
			$this->includesLineHeight = (bool)$params['includesLineHeight'];
		}

		$this->createChild(
			'family',
			Settings\StringSetting::class,
			['default' => null]
		);

		/** @var StringEnumSetting $fontSizeUnit */
		$fontSizeUnit = $this->createChild(
			'sizeUnit',
			StringEnumSetting::class,
			['px', 'em', 'rem', 'vw'],
			['default' => 'px']
		);
		//Override the default label generation algorithm and just use the unit name.
		$fontSizeUnit->describeChoice('px', 'px');
		$fontSizeUnit->describeChoice('em', 'em');
		$fontSizeUnit->describeChoice('rem', 'rem');
		$fontSizeUnit->describeChoice('vw', 'vw');

		$this->createChild(
			'size',
			CssLengthSetting::class,
			'font-size',
			[
				'minValue'    => 0,
				'maxValue'    => 200,
				'default'     => null,
				'unitSetting' => $fontSizeUnit,
				'label'       => 'Font size',
			]
		);

		$this->createChild(
			'weight',
			CssEnumSetting::class,
			'font-weight',
			[
				null,
				'normal',
				'bold',
				'bolder',
				'lighter',
				'100',
				'200',
				'300',
				'400',
				'500',
				'600',
				'700',
				'800',
				'900',
			],
			['default' => null, 'label' => 'Font weight']
		);

		if ( $this->includesLineHeight ) {
			/** @var StringEnumSetting $lineHeightUnit */
			$lineHeightUnit = $this->createChild(
				'lineHeightUnit',
				StringEnumSetting::class,
				['', 'px', 'em'],
				['default' => '']
			);
			$lineHeightUnit->describeChoice('', '—'); //Unit-less.
			$lineHeightUnit->describeChoice('px', 'px');
			$lineHeightUnit->describeChoice('em', 'em');

			$this->createChild(
				'line-height',
				CssLengthSetting::class,
				'line-height',
				[
					'minValue'    => 0,
					'maxValue'    => 200,
					'default'     => null,
					'unitSetting' => $lineHeightUnit,
					'defaultUnit' => '',
					'label'       => 'Line height',
				]
			);
		}

		$this->createChild(
			'style',
			CssEnumSetting::class,
			'font-style',
			[null, 'normal', 'italic', 'oblique'],
			['default' => null]
		);

		$this->createChild(
			'variant',
			CssEnumSetting::class,
			'font-variant',
			[null, 'normal', 'small-caps'],
			['default' => null]
		);

		$this->createChild(
			'text-transform',
			CssEnumSetting::class,
			'text-transform',
			[null, 'none', 'uppercase', 'lowercase', 'capitalize', 'full-width'],
			['default' => null]
		);

		$this->createChild(
			'text-decoration',
			CssEnumSetting::class,
			'text-decoration',
			[null, 'none', 'underline', 'overline', 'line-through'],
			['default' => null]
		);
	}

	public function createControls(Builders\ElementBuilderFactory $b) {
		/** @var StringEnumSetting $sizeUnit */
		$sizeUnit = $this->settings['sizeUnit'];

		$controls = [
			$b->number($this->settings['size'])
				->unitSetting($sizeUnit)
				->inputClasses('ame-font-size-input')
				->params([
					'rangeByUnit' => [
						'px'  => ['min' => 1, 'max' => 72, 'step' => 1],
						'em'  => ['min' => 0.2, 'max' => 10, 'step' => 0.1],
						'rem' => ['min' => 0.2, 'max' => 10, 'step' => 0.1],
						'vw'  => ['min' => 0.1, 'max' => 10, 'step' => 0.1],
					],
				])
				->asGroup(),
			$b->select($this->settings['weight'])
				->params([
					'choices' => [
						//The default value is NULL = no change.
						new ChoiceControlOption(null, 'Default'),
						new ChoiceControlOption('100', 'Thin'),
						new ChoiceControlOption('200', 'Extra Light'),
						new ChoiceControlOption('300', 'Light'),
						new ChoiceControlOption('400', 'Normal'),
						new ChoiceControlOption('500', 'Medium'),
						new ChoiceControlOption('600', 'Semi Bold'),
						new ChoiceControlOption('700', 'Bold'),
						new ChoiceControlOption('800', 'Extra Bold'),
						new ChoiceControlOption('900', 'Heavy'),
					],
				]),
		];

		if ( $this->includesLineHeight && isset($this->settings['line-height']) ) {
			$controls[] = $b->number($this->settings['line-height'])
				->inputClasses('ame-small-number-input', 'ame-line-height-input')
				->params([
					'rangeByUnit' => [
						''   => ['min' => 0.1, 'max' => 5, 'step' => 0.1],
						'px' => ['min' => 1, 'max' => 100, 'step' => 1],
						'em' => ['min' => 0.2, 'max' => 10, 'step' => 0.1],
					],
				]);
		}

		$controls[] = $b->fontStyle([
			'font-style'      => $this->settings['style'],
			'font-variant'    => $this->settings['variant'],
			'text-transform'  => $this->settings['text-transform'],
			'text-decoration' => $this->settings['text-decoration'],
		])->label('Style');

		return $controls;
	}
}