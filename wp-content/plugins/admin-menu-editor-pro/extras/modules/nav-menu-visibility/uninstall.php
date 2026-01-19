<?php
/*
 * Delete nav menu visibility settings from post meta. This applies to classic nav menus.
 *
 * Note that we don't explicitly delete visibility settings for block-based navigation menus.
 * That would be more complicated, and it's not necessary because WP will automatically delete
 * the custom block attributes the next time the blocks are saved.
 */
if ( defined('ABSPATH') && defined('WP_UNINSTALL_PLUGIN') ) {
	delete_metadata('post', 0, '_ame_nav_menu_visibility', null, true);
}