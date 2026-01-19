<?php
require_once __DIR__ . '/../includes/AmeAutoloader.php';
require_once AME_ROOT_DIR . '/extras/exportable-module.php';
require_once AME_ROOT_DIR . '/extras/persistent-pro-module.php';
require_once AME_ROOT_DIR . '/extras/import-export/import-export.php';

use YahnisElsts\AdminMenuEditor\AmeAutoloader;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

$wsAmeProAutoloader = new AmeAutoloader([
	'YahnisElsts\\AdminMenuEditor\\ProCustomizable\\'    => __DIR__ . '/pro-customizables',
	'YahnisElsts\\AdminMenuEditor\\StyleGenerator\\'     => __DIR__ . '/style-generator/',
	'YahnisElsts\\AdminMenuEditor\\DynamicStylesheets\\' => __DIR__ . '/dynamic-stylesheets/',
	'YahnisElsts\\AdminMenuEditor\\DashboardStyler\\'    => __DIR__ . '/modules/dashboard-styler',
	'YahnisElsts\\AdminMenuEditor\\WebpackRegistry\\'    => __DIR__ . '/webpack-registry',
]);

$wsAmeProAutoloader->register();

//Additionally, "autoload" JS scripts by registering them before they're used.
//Other modules can then enqueue them or add them as dependencies.
//
//This file only registers scripts that are not part of a specific module. Specific
//modules can register their own scripts in their own hooks.
if ( function_exists('add_action') ) {
	/**
	 * Register JS assets used on AME pages.
	 *
	 * @param \WPMenuEditor $menuEditor
	 * @return void
	 */
	function ws_ame_register_customizable_js_lib($menuEditor) {
		static $isDone = false;
		if ( $isDone ) {
			return;
		}
		$isDone = true;

		//Register client-side setting classes and view models.
		$customizableDependencies = [
			'ame-mini-functional-lib',
			'ame-knockout',
			'ame-lodash',
		];
		$customizableBase = ScriptDependency::create(
			plugins_url('pro-customizables/assets/customizable.js', __FILE__),
			'ame-customizable-settings'
		)
			->addDependencies(...$customizableDependencies)
			->setTypeToModule()
			->register();

		//Webpack bundle of the above. Technically not an ES6 module.
		$useBundles = defined('WS_AME_USE_BUNDLES') && WS_AME_USE_BUNDLES
			&& file_exists(AME_ROOT_DIR . '/dist/customizable.bundle.js');

		if ( $useBundles && $menuEditor ) {
			$registry = $menuEditor->get_webpack_registry();

			$customizableBundle = $registry->getWebpackScriptChunk('customizable');
			$customizableBundle->addDependencies(...$customizableDependencies);
			$customizableBundle->register();
		}

		//Register style generator stuff.
		ScriptDependency::create(
			plugins_url('style-generator/style-generator.js', __FILE__),
			'ame-style-generator'
		)
			->addDependencies(
				$customizableBase,
				'ame-knockout',
				'ame-lodash',
				'ame-mini-functional-lib',
				'jquery-color'
			)
			->setTypeToModule()
			->register();
	}

	add_action('admin_menu_editor-register_scripts', 'ws_ame_register_customizable_js_lib', 9);
}