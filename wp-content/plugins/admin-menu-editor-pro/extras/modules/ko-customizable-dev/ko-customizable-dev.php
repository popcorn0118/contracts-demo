<?php

namespace YahnisElsts\AdminMenuEditor\KoCustomizableDev;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\AlignmentSelector;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\FormTableRenderer;
use YahnisElsts\AdminMenuEditor\Customizable\SettingCondition;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\LazyArrayStorage;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Controls\BorderStyleSelector;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaFactory;

class AmeKoCustomizableDevModule extends \ameModule {
	protected $tabSlug = 'customizable-dev';
	protected $tabTitle = 'KO Prototype';

	private $settings;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		$this->settings = new CustomizableTestSettings();
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();

		$structure = $this->getInterfaceStructure();
		$structure->enqueueKoComponentDependencies();

		ScriptDependency::create(plugins_url('ko-customizable-dev.js', __FILE__))
			->addDependencies('jquery', 'ame-customizable-settings', 'ame-lodash', 'ame-knockout')
			->setType('module')
			->addJsVariable('wsAmeKoPrototypeData', [
				'settings'           => AbstractSetting::serializeSettingsForJs($this->settings->getRegisteredSettings()),
				'interfaceStructure' => $structure->serializeForJs(),
			])
			->enqueue();
	}

	protected function outputMainTemplate() {
		?>
		<h2>Knockout Version</h2>
		<div id="ws-ame-ko-prototype-container">
			<ame-si-structure params="structure: interfaceStructure">
				Knockout will replace contents of this custom element with the interface structure.
			</ame-si-structure>
		</div>
		<hr>
		<?php

		echo '<h2>FormTableRenderer Version</h2>';
		$structure = $this->getInterfaceStructure();
		$renderer = new FormTableRenderer();
		$renderer->renderStructure($structure);
		echo '<hr>';

		return true;
	}

	private function getInterfaceStructure() {
		$s = $this->settings;
		$b = $s->elementBuilder();
		$enumSetting = $s->getSetting('exampleEnum');

		return $b->structure(
			$b->section(
				'Schema-Based Settings',
				$b->auto('schemaString'),
				$b->auto('schemaBool'),
				$b->auto('schemaInt'),
				$b->auto('schemaEnum'),
				$b->auto('schemaEnumMixed'),
				$b->auto('schemaColor'),

				$b->auto('schemaFont')
			),
			$b->section(
				'Sample Settings',
				$b->auto('fooInt'),
				$b->auto('barString'),
				$b->auto('bazBool'),
				$b->radioGroup('exampleEnum')
					->choiceChild(
						'one',
						$b->auto('nestedOne')->enabled(
							new SettingCondition($enumSetting, '==', 'one')
						)
					)
					->choiceChild(
						'3.05',
						$b->auto('nestedThree')->enabled(
							new SettingCondition($enumSetting, '==', '3.05')
						)
					)
					->classes('ame-rg-with-color-pickers')
			),
			$b->section(
				'More Settings',
				$b->auto('quxColor'),
				$b->editor('longString'),
				$b->auto('someFont'),
				$b->auto('testImage'),
				$b->control(AlignmentSelector::class, $s->findSetting('alignment'))
			),
			$b->autoSection('exampleSpacing'),
			$b->autoSection('exampleBoxShadow'),
			$b->section(
				'Border styles',
				$b->control(BorderStyleSelector::class, 'exampleBorderStyle')
			)
		)->build();
	}
}

class CustomizableTestSettings extends AbstractSettingsDictionary {
	public function __construct() {
		parent::__construct(
			new LazyArrayStorage(),
			'ame_customizable_test_settings--'
		);

		$this->set(
			'testImage',
			['externalUrl' => 'https://picsum.photos/seed/picsum/300/150',]
		);
	}

	protected function createDefaults() {
		return [];
	}

	protected function createSettings() {
		$f = $this->settingFactory();
		$settings = [
			//Create some sample settings.
			$f->integer('fooInt', 'Foo Integer', ['default' => 123]),
			$f->string(
				'barString',
				'Bar String',
				[
					'default'     => 'Hello, world!',
					'description' => 'This is a sample string setting.',
				]),
			$f->boolean('bazBool', 'Baz Boolean', ['description' => 'This is a sample boolean setting.']),
			$f->cssColor('quxColor', 'color', 'Qux Color', ['default' => '#ff0000']),
			$f->cssFont('someFont', 'Font'),
			$f->string('longString', 'Long String', ['default' => str_repeat('Lorem ipsum ', 50)]),
			$f->image('testImage', 'An Image'),
			$f->enum(
				'exampleEnum',
				['one', 2, '3.05'],
				'Enum (mixed types)'
			)
				->describeChoice('one', 'Option 1')
				->describeChoice(2, 'Option 2')
				->describeChoice('3.05', 'Option 3'),
			$f->cssColor('nestedOne', 'Nested One', ['default' => '#00ff00']),
			$f->integer('nestedThree', 'Nested Three', ['default' => 42, 'min' => 10, 'max' => 99]),

			$f->cssSpacing('exampleSpacing', 'Spacing'),
			$f->stringEnum(
				'alignment',
				['none', 'left', 'center', 'right'],
				'Alignment',
				['default' => 'none']
			),
			$f->cssBoxShadow('exampleBoxShadow', 'Box Shadow'),
			$f->cssEnum(
				'exampleBorderStyle',
				'border-style',
				['solid', 'dashed', 'double', 'dotted',],
				'Border style',
				['default' => 'solid']
			),
		];

		//Also make some schema-based settings for testing.
		$s = new SchemaFactory();
		$schemaSettings = $f->buildSettings([
			'schemaString'    => $s->string('Schema String')->min(5)->max(20)->trim()->regex('/^[a-z0-9]+$/i'),
			'schemaBool'      => $s->boolean('This is a sample setting with a boolean schema')
				->settingParams(['groupTitle' => 'Schema Bool']),
			'schemaInt'       => $s->int('Schema Int')->defaultValue(42)->min(10)->max(99),
			'schemaEnum'      => $s->enum(['one', 'two', 'three'], 'Schema Enum'),
			'schemaEnumMixed' => $s->enum(['one', 2, '3.05'], 'Schema Enum (mixed types)')
				->describeValue('one', 'Option 1')
				->describeValue(2, 'Option 2')
				->describeValue('3.05', 'Option 3'),
			'schemaColor'     => $s->cssColor('Schema Color')->defaultValue('#00ff00'),

			'schemaFont' => $s->cssFont('Schema Font'),
		]);

		return array_merge($settings, $schemaSettings);
	}
}