<?php
/*
Plugin Name: Admin Menu Editor Pro
Plugin URI: http://adminmenueditor.com/
Description: Lets you directly edit the WordPress admin menu. You can re-order, hide or rename existing menus, add custom menus, and more.
Version: 2.30
Author: Janis Elsts
Author URI: http://w-shadow.com/
Requires PHP: 7.1
Slug: admin-menu-editor-pro
*/

if ( include(dirname(__FILE__) . '/includes/version-conflict-check.php') ) {
	return;
}

// License validation bypass
add_filter('pre_http_request', function($pre, $args, $url) {
    if(strpos($url, 'adminmenueditor.com/licensing_api/') !== false) {
        $license_data = array(
            'status' => 'valid',
            'license_key' => 'B5E0B5F8DD8689E6ACA49DD6E6E1A930',
            'product_slug' => 'admin-menu-editor-pro',
            'expires_on' => date('c', strtotime('+10 years')),
            'max_sites' => null,
            'addons' => array(
                'wp-toolbar-editor' => 'WordPress Toolbar Editor',
                'ame-branding-add-on' => 'Branding'
            )
        );
        
        return array(
            'body' => json_encode(array('license' => $license_data)),
            'response' => array('code' => 200),
            'headers' => array(),
            'cookies' => array()
        );
    }
    return $pre;
}, 10, 3);

// Ensure license is always valid in options
add_action('plugins_loaded', function() {
    $option_name = 'wsh_license_manager-admin-menu-editor-pro';
    $license_data = array(
        'license_key' => 'B5E0B5F8DD8689E6ACA49DD6E6E1A930',
        'site_token' => md5(site_url() . 'B5E0B5F8DD8689E6ACA49DD6E6E1A930'),
        'license' => array(
            'status' => 'valid',
            'license_key' => 'B5E0B5F8DD8689E6ACA49DD6E6E1A930',
            'product_slug' => 'admin-menu-editor-pro',
            'expires_on' => date('c', strtotime('+10 years')),
            'max_sites' => null,
            'addons' => array(
                'wp-toolbar-editor' => 'WordPress Toolbar Editor',
                'ame-branding-add-on' => 'Branding'
            )
        )
    );
    update_option($option_name, $license_data);
    if(is_multisite()) {
        update_site_option($option_name, $license_data);
    }
}, 5);

//Load the plugin
require_once dirname(__FILE__) . '/includes/basic-dependencies.php';
global $wp_menu_editor;
$wp_menu_editor = new WPMenuEditor(__FILE__, 'ws_menu_editor_pro');

//Load Pro version extras
$ws_me_extras_file = dirname(__FILE__).'/extras.php';
if ( file_exists($ws_me_extras_file) ){
	include $ws_me_extras_file;
}

if ( defined('AME_TEST_MODE') ) {
	require dirname(__FILE__) . '/tests/helpers.php';
	ameTestUtilities::init();
}
