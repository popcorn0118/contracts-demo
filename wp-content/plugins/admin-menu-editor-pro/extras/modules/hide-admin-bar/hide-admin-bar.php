<?php

/**
 * Hides the Admin Bar / Toolbar.
 *
 * phpcs:disable WordPressVIPMinimum.UserExperience.AdminBarRemoval
 * ^- That's the whole point of this feature, hiding is disabled by default,
 *    and the admin can choose the roles that will be affected.
 */
class ameAdminBarHider {
	const HIDEABLE_ITEM_ID = 'hide_admin_bar';

	/**
	 * @var WPMenuEditor
	 */
	private $menuEditor;
	/**
	 * @var wsMenuEditorExtras
	 */
	private $extras;

	public function __construct($menuEditor) {
		$this->menuEditor = $menuEditor;

		add_action('init', array($this, 'maybe_hide_admin_bar'));
		add_filter('admin_menu_editor-show_general_box', '__return_true');
		add_action('admin_menu_editor-general_box', array($this, 'output_option'), 20);

		add_filter('admin_menu_editor-hideable_vis_components', array($this, 'add_hideable_component'));
	}

	public function maybe_hide_admin_bar() {
		$this->extras = $GLOBALS['wsMenuEditorExtras'];

		if ( $this->should_hide_admin_bar() ) {
			$this->hide_admin_bar();
		}
	}

	/**
	 * Should we hide the admin bar from the current user?
	 *
	 * @return bool
	 */
	private function should_hide_admin_bar() {
		$config = $this->menuEditor->load_custom_menu();
		if ( !isset($config, $config['component_visibility'], $config['component_visibility']['toolbar']) ) {
			return false;
		}

		$grant_access = $config['component_visibility']['toolbar'];
		return !$this->extras->check_current_user_access($grant_access, null, null, true, AME_RC_USE_DEFAULT_ACCESS);
	}

	/**
	 * Hide the Toolbar/Admin Bar both on the front-end and the dashboard.
	 */
	private function hide_admin_bar() {
		add_filter('show_admin_bar', '__return_false');
		add_action('in_admin_header', array($this, 'remove_admin_bar_css_classes'));
		add_filter('wp_admin_bar_class', array($this, 'filter_admin_bar_class'));
		add_action('admin_print_scripts-profile.php', array($this, 'hide_toolbar_settings'));
		add_action('admin_bar_init', array($this, 'remove_bump_css'));
		add_action('admin_bar_init', array($this, 'override_admin_bar_height_css'));
		add_action('enqueue_block_editor_assets', array($this, 'add_gutenberg_styles'));
	}

	/**
	 * Remove Admin Bar related classes from the <html> and <body> tags. Usually
	 * these classes are not filterable, so we have to remove them with JS.
	 */
	public function remove_admin_bar_css_classes() {
		?>
		<script type="text/javascript">
			var body = document.body,
				html = document.documentElement;
			body.className = body.className.replace(/\badmin-bar\b/, '');
			html.className = html.className.replace(/\bwp-toolbar\b/, '');
		</script>
		<?php
	}

	/**
	 * Replace the WP_Admin_Bar class with a dummy implementation that doesn't render anything.
	 *
	 * @param string $className
	 * @return string
	 */
	public function filter_admin_bar_class($className) {
		require_once dirname(__FILE__) . '/ameDummyAdminBar.php';

		if ( class_exists('ameDummyAdminBar') ) {
			return 'ameDummyAdminBar';
		} else {
			//Just in case something changes in WP core and the WP_Admin_Bar class becomes unavailable.
			return $className;
		}
	}

	/**
	 * Hide the "Show Toolbar when viewing site" option on the "Profile" page.
	 */
	public function hide_toolbar_settings() {
		?>
		<!--suppress CssUnusedSymbol -->
		<style> .show-admin-bar { display: none; } </style>
		<?php
	}

	/**
	 * Remove the callback that adds an "!important" top margin to <html> and <body>.
	 *
	 * Normally this isn't necessary. It's a compatibility workaround.
	 */
	public function remove_bump_css() {
		remove_action('wp_head', '_admin_bar_bump_cb');
	}

	/**
	 * Change the CSS variable that holds the Admin Bar height to 0. Also, add special-case
	 * styles for popular plugins that assume a fixed Admin Bar height.
	 *
	 * Overriding the variable does not affect the actual height of the Admin Bar, but some
	 * themes and plugins use this variable to calculate the position of elements on the page.
	 */
	public function override_admin_bar_height_css() {
		if ( function_exists('wp_add_inline_style') ) {
			$overrideCss = 'html { --wp-admin--admin-bar--height: 0px !important; }';

			//WooCommerce uses a fixed header for its admin pages. It has a hardcoded "top: 32px"
			//style (not using the CSS variable), so we need to override that to move the header.
			if ( class_exists('WooCommerce', false) ) {
				$overrideCss .= '.woocommerce-layout .woocommerce-layout__header { top: 0 !important; }';
			}

			wp_add_inline_style('admin-bar', $overrideCss);
		}
	}

	public function add_gutenberg_styles() {
		if ( did_action('admin_print_styles') ) {
			$this->print_gutenberg_styles();
		} else {
			add_action('admin_print_styles', array($this, 'print_gutenberg_styles'));
		}
	}

	/**
	 * Output extra CSS for the Gutenberg editor interface.
	 */
	public function print_gutenberg_styles() {
		//By default, the Gutenberg editor interface is offset from the top of the screen by
		//32px or 46px (depending on media queries) to make space for the Admin Bar. Let's remove
		//this offset when the Admin Bar is hidden.
		?>
		<!--suppress CssUnusedSymbol -->
		<style>
			.block-editor #editor .interface-interface-skeleton,
			.block-editor-page .blocks-widgets-container .interface-interface-skeleton {
				top: 0;
			}
		</style>
		<?php
	}

	/**
	 * Add a checkbox to the menu editor page.
	 */
	public function output_option() {
		?>
		<label>
			<input type="checkbox" id="ws_ame_show_toolbar">
			Show the Toolbar

			<a class="ws_tooltip_trigger"
			   title="Uncheck to hide the Toolbar (a.k.a Admin Bar) both on the front-end and in the dashboard."
			><div class="dashicons dashicons-info"></div></a>
		</label>
		<?php
	}

	public function add_hideable_component($definitions) {
		$definitions[self::HIDEABLE_ITEM_ID] = array(
			'label' => 'Toolbar (Admin Bar)',
			'component' => 'toolbar'
		);
		return $definitions;
	}
}
//phpcs:enable