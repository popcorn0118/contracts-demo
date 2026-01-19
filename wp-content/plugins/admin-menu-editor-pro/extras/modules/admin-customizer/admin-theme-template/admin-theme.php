<?php
/**
 * Plugin Name: {pluginName}
 * Description: {shortDescription}
 * Version: {pluginVersion}
 * Requires PHP: 5.6.20
{optionalPluginHeaders}
 */

//Exit if accessed directly.
if ( !defined('ABSPATH') ) {
	exit;
}

class acIdentPrefixAdminTheme {
	const COLOR_SCHEME_ID = 'acIdentPrefix-color-scheme';
	const MAIN_STYLESHEET_HANDLE = 'acIdentPrefix-admin-theme';

	/**
	 * @var null|array Loaded color scheme data. NULL when not loaded yet,
	 *                 can be an empty array if no color scheme is defined.
	 */
	private $colorSchemeData = null;

	public function __construct() {
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminTheme']);

		add_action('admin_init', [$this, 'applyAdminColorScheme']);
		add_action('wp_enqueue_scripts', [$this, 'enqueueAdminBarStyle']);
	}

	/**
	 * Enqueue the admin theme stylesheet on all admin pages.
	 */
	public function enqueueAdminTheme() {
		wp_enqueue_style(
			self::MAIN_STYLESHEET_HANDLE,
			plugins_url('custom-admin-theme.css', __FILE__),
			[],
			'{randomHash}'
		);
	}

	/**
	 * Apply the custom admin color scheme.
	 */
	function applyAdminColorScheme() {
		if ( !file_exists(__DIR__ . '/color-scheme.css') ) {
			return;
		}

		$config = $this->getColorSchemeData();

		$demoColors = array_merge(
			[
				'base'         => '#23282d',
				'icon'         => '#2c3338',
				'notification' => '#d54e21',
				'highlight'    => '#0073aa',
			],
			isset($config['colors']['demo']) ? $config['colors']['demo'] : []
		);
		$demoColors = array_slice(array_values($demoColors), 0, 4);

		$iconColors = array_merge(
			[
				'base'    => '#23282d',
				'focus'   => '#fff',
				'current' => '#fff',
			],
			isset($config['colors']['icons']) ? $config['colors']['icons'] : []
		);

		$name = trim('{pluginName}');
		if ( empty($name) ) {
			$name = 'Custom Admin Color Scheme';
		}

		//Register the custom admin color scheme.
		wp_admin_css_color(
			self::COLOR_SCHEME_ID,
			$name,
			add_query_arg(
				['version' => '{randomHash}'],
				plugins_url('color-scheme.css', __FILE__)
			),
			$demoColors,
			$iconColors
		);

		if ( $this->isColorOverrideEnabled() ) {
			//Remove the "Admin Color Scheme" setting from the "Profile" page.
			remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
			//Force everyone to use the custom color scheme.
			add_filter('get_user_option_admin_color', [$this, 'overrideUserColorScheme'], 10, 0);
		}
	}

	private function getColorSchemeData() {
		//Use the cached data if available.
		if ( $this->colorSchemeData !== null ) {
			return $this->colorSchemeData;
		}

		//Load from JSON.
		$filePath = __DIR__ . '/color-scheme.json';
		if ( !file_exists($filePath) ) {
			$this->colorSchemeData = [];
		} else {
			//This should always be a local file.
			//phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			$this->colorSchemeData = json_decode(file_get_contents($filePath), true);
			if ( !is_array($this->colorSchemeData) ) {
				$this->colorSchemeData = [];
			}
		}
		return $this->colorSchemeData;
	}

	private function isColorOverrideEnabled() {
		$data = $this->getColorSchemeData();
		return !empty($data['isColorOverrideEnabled']);
	}

	/**
	 * Override the admin color scheme.
	 *
	 * @return string The custom admin color scheme.
	 */
	function overrideUserColorScheme() {
		return self::COLOR_SCHEME_ID;
	}

	/**
	 * Apply the custom color scheme to the admin bar / Toolbar
	 * in the front-end.
	 */
	public function enqueueAdminBarStyle() {
		//Only logged-in users can see the admin bar.
		if ( !is_user_logged_in() ) {
			return;
		}

		//Does the stylesheet exist?
		if ( !file_exists((__DIR__) . '/admin-bar-colors.css') ) {
			return;
		}

		//Should we use the custom color scheme for this user?
		if (
			$this->isColorOverrideEnabled()
			|| (get_user_option('admin_color') === self::COLOR_SCHEME_ID)
		) {
			return;
		}

		wp_enqueue_style(
			self::COLOR_SCHEME_ID . '-admin-bar',
			plugins_url('admin-bar-colors.css', __FILE__),
			[],
			'{randomHash}'
		);
	}
}

new acIdentPrefixAdminTheme();