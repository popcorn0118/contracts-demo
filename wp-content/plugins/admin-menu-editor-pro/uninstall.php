<?php

/**
 * @author W-Shadow
 * @copyright 2025
 *
 * The uninstallation script.
 */

if ( defined('ABSPATH') && defined('WP_UNINSTALL_PLUGIN') ) {

	//Remove the plugin's settings
	delete_option('ws_menu_editor_pro');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_menu_editor_pro');

		//Remove the "automatic license activation failed" flag. Most sites won't have this.
		delete_site_option('wslm_auto_activation_failed-admin-menu-editor-pro');
	}
	//Remove update metadata
	delete_option('ame_pro_external_updates');

	//Remove hint visibility flags
	if ( function_exists('delete_metadata') ) {
		delete_metadata('user', 0, 'ame_show_hints', '', true);
	}

	//Remove meta box settings.
	delete_option('ws_ame_meta_boxes');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_ame_meta_boxes');
	}

	//Remove dashboard widget settings.
	delete_option('ws_ame_dashboard_widgets');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_ame_dashboard_widgets');
	}
	if ( function_exists('delete_metadata') ) {
		delete_metadata('user', 0, 'ws_ame_dashboard_preview_cols', '', true);
	}

	//Remove table column settings.
	delete_option('ws_ame_table_columns');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_ame_table_columns');
	}

	//Remove tweak settings.
	delete_option('ws_ame_tweak_settings');
	delete_option('ws_ame_detected_tmce_buttons');
	delete_option('ws_ame_detected_gtb_blocks');
	delete_option('ws_ame_detected_profile_fields');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_ame_tweak_settings');
		delete_site_option('ws_ame_detected_tmce_buttons');
		delete_site_option('ws_ame_detected_gtb_blocks');
		delete_site_option('ws_ame_detected_profile_fields');
	}

	//Remove redirect settings.
	delete_option('ws_ame_redirects');
	if ( function_exists('delete_site_option') ) {
		delete_site_option('ws_ame_redirects');
		delete_site_option('ws_ame_rui_first_change');
	}
	if ( function_exists('delete_metadata') ) {
		delete_metadata('user', 0, 'ame_rui_first_login_done', '', true);
	}

	//Run module uninstallers.
	$ameModuleUninstallers = [
		__DIR__ . '/extras/modules/easy-hide/eh-preferences-uninstall.php',
		__DIR__ . '/modules/highlight-new-menus/uninstall.php',
		__DIR__ . '/extras/modules/role-editor/uninstall.php',
		__DIR__ . '/extras/modules/nav-menu-visibility/uninstall.php',
		__DIR__ . '/extras/modules/quick-search/uninstall.php',
		__DIR__ . '/modules/content-permissions/uninstall.php',
	];
	foreach ($ameModuleUninstallers as $ameUninstallerFile) {
		if ( file_exists($ameUninstallerFile) ) {
			include($ameUninstallerFile);
		}
	}

	//Clear the stylesheet cache.
	function ws_ame_delete_transients_with_prefix($prefix) {
		//phpcs:disable WordPress.DB.DirectDatabaseQuery
		global $wpdb;

		//Delete normal transients.
		/** @noinspection SqlResolve */
		$transientOptions = $wpdb->get_col($wpdb->prepare(
			"SELECT `option_name` FROM `$wpdb->options` WHERE `option_name` LIKE %s LIMIT 500",
			$wpdb->esc_like('_transient_' . $prefix) . '%'
		));
		foreach ($transientOptions as $optionName) {
			$transientName = substr($optionName, strlen('_transient_'));
			delete_transient($transientName);
		}

		//Delete site transients.
		if ( is_multisite() ) {
			$tableName = $wpdb->sitemeta;
		} else {
			$tableName = $wpdb->options;
		}

		//PHPCS incorrectly complains about the table name not being prepared.
		//As of WP 6.1, WPDB doesn't actually provide a way to escape the table name.
		//phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		/** @noinspection SqlResolve */
		$siteTransientOptions = $wpdb->get_col($wpdb->prepare(
			"SELECT `option_name` FROM `$tableName` WHERE `option_name` LIKE %s LIMIT 500",
			$wpdb->esc_like('_site_transient_' . $prefix) . '%'
		));
		//phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ($siteTransientOptions as $siteOptionName) {
			$siteTransientName = substr($siteOptionName, strlen('_site_transient_'));
			delete_site_transient($siteTransientName);
		}
		//phpcs:enable
	}

	ws_ame_delete_transients_with_prefix('wsdst25-');

	//Remove license data (if any).
	if ( file_exists(dirname(__FILE__) . '/extras.php') ) {
		require_once dirname(__FILE__) . '/includes/basic-dependencies.php';

		require_once dirname(__FILE__) . '/extras.php';
		if ( isset($ameProLicenseManager) ) {
			$ameProLicenseManager->unlicenseThisSite();
		}
	}
}