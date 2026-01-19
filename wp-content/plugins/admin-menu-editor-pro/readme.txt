=== Admin Menu Editor Pro ===
Contributors: whiteshadow
Tags: admin, dashboard, menu, security, wpmu
Requires at least: 5.4
Tested up to: 6.8.2
Stable tag: 2.30

Lets you directly edit the WordPress admin menu. You can re-order, hide or rename existing menus, add custom menus and more.

== Description ==
Pro version of the Admin Menu Editor plugin. Lets you manually edit the Dashboard menu. You can reorder the menus, show/hide specific items, change access rights, and more. 

[Get the latest version here.](http://adminmenueditor.com/updates/)

**Pro Version Features**

- Import/export custom menus.
- Set menu items to open in a new window or IFrame.
- Improved, role-based menu permissions interface.
- Use shortcodes in menu fields.
- Create menus accessible only to a specific user (by setting extra capability to "user:username").

**Other Features**

- Change menu title, access rights, URL and more. 
- Create custom menus.
- Sort items using a simple drag & drop interface.
- Move items to a different submenus.
- Hide any menu or submenu item.
- Supports WordPress MultiSite/WPMU.

**Requirements**

- WordPress 5.4 or later

_For maximum compatibility and security, using a modern web browser such as Firefox, Opera, Chrome or Safari is recommended. Certain advanced features (e.g. menu import) may not work reliably or at all in Internet Explorer and other outdated browsers._

**Credits**

This plugin uses some icons from the ["Silk" icon set](http://fortawesome.github.io/Font-Awesome/) by Mark James. These icons are licensed under the [Creative Commons Attribution 3.0 License](http://creativecommons.org/licenses/by/3.0/).

== Installation ==

_If you already have the free version of Admin Menu Editor installed, deactivate it before installing the Pro version._

**Normal installation**

1. Download the admin-menu-editor-pro.zip file to your computer.
1. Unzip the file.
1. Upload the `admin-menu-editor-pro` directory to your `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

That's it. You can access the the menu editor by going to _Settings -> Menu Editor Pro_. The plugin will automatically load your current menu configuration the first time you run it.

**WP MultiSite installation**

If you have WordPress set up in multisite ("Network") mode, you can also install Admin Menu Editor as a global plugin. This will enable you to edit the Dashboard menu for all sites and users at once.

1. Download the admin-menu-editor-pro.zip file to your computer.
1. Unzip the file.
1. Create a new directory named `mu-plugins` in your site's `wp-content` directory (unless it already exists).
1. Upload the `admin-menu-editor-pro` directory to `/wp-content/mu-plugins/`.
1. Move `admin-menu-editor-mu.php` from `admin-menu-editor-pro/includes` to `/wp-content/mu-plugins/`.

Plugins installed in the `mu-plugins` directory are treated as "always on", so you don't need to explicitly activate the menu editor. Just go to _Settings -> Menu Editor_ and start customizing your admin menu :)

_Notes_

- Instead of installing Admin Menu Editor in `mu-plugins`, you can also install it normally and then activate it globally via "Network Activate". However, this will make the plugin visible to normal users when it is inactive (e.g. during upgrades).
- When Admin Menu Editor is installed in `mu-plugins` or activated via "Network Activate", only the "super admin" user can access the menu editor page. Other users will see the customized Dashboard menu, but be unable to edit it.
- It is currently not possible to install Admin Menu Editor as both a normal and global plugin on the same site.

== Notes ==
Here are some usage tips and other things that can be good to know when using the menu editor : 

- WordPress uses the concept of [roles and capabilities](http://codex.wordpress.org/Roles_and_Capabilities "An overview of roles and capabilities") to manage access rights. Each Dashboard menu item has an associated capability setting, and only the users that possess that capability can see and use that menu item.

- "Hidden" menus are invisible to everyone - including the administrator - but they can still be accessed via the direct page URL. To make a menu inaccessible to certain users, edit its permissions.

- If you delete any of the default menus they will reappear after saving. This is by design. To get rid of a menu for good, either hide it or set it's access rights to a higher level.

== Changelog ==

[Get the latest version here.](http://adminmenueditor.com/updates/)

= 2.30 (2025-09-08) =
##### Breaking Changes
* Increased the minimum required PHP version from 5.6 to 7.1.

##### Added
* New Quick Search features:
  * You can now search posts (by title) and users (by login, email, or display name). You can configure enabled post types in the "Quick Search" settings tab, under "Search scopes".
  * You can press Ctrl + Enter to open the selected search result in a new window or tab.
  * Quick Search remembers recently used items for each user. Previously, it would preload the same recent items for all users.
* Added a tweak that disables the Gutenberg Welcome Guide.

##### Fixed
* Fixed import error messages not being shown in the widget import popup.
* Fixed PHP 8.4 deprecation notices like "Implicitly marking parameter $foo as nullable is deprecated, the explicit nullable type must be used instead".
* Fixed repeated re-crawling of menu items where the URL contains a "#fragment".
* Fixed a bug where the plugin would unexpectedly hide the top-level "Profile" menu from non-admin users in some configurations.
* Fixed long menu titles wrapping to the next line and inconsistent field label spacing in the menu editor.
* Fixed a conflict with "FunnelKit Funnel Builder" where "WooFunnels" would permanently stay highlighted as a new menu because its "Upgrade to Pro" submenu item could not be correctly marked as seen.
* Fixed a conflict with Forminator Forms where the "Forminator" menu would always be highlighted as a new menu.
* Fixed a PHP warning "fgetcsv(): escape must be character" in PHP versions older than 7.4.
* Fixed a dropdown potentially extending outside its parent dialog/popup when one of the items is very long.
* Fixed the "Copy Permissions" button state not being updated when dropdown values were changed using the keyboard instead of the mouse.
* Fixed improper sanitization of the "placeholder" attribute for the "ame-user-info" shortcode.
* Fixed the "admin_menu_editor-menu_url_blacklist" filter being called too early, before most other plugins have been loaded. Now other plugins should be able to actually use this filter to modify the menu blacklist.
* Fixed a conflict with "Advanced Responsive Video Embedder" (ARVE) that prevented the contents of the ARVE settings tabs from displaying.
* Improved profile field detection by ignoring hidden fields and nested tables. This slightly improves compatibility with plugins like "Store Toolkit for Woocommerce" and "Dokan".
* Improved detection and handling of non-hardcoded meta capabilities in the menu editor. The plugin should now display their status more accurately, and enabling menu items that use meta capabilities should work more reliably. However, meta capability support remains incomplete since plugins can implement them in arbitrarily complex ways.

= 2.29.1 (2025-05-22) =
* Hotfix for a bug in version 2.29 that could cause it to crash with a fatal error upon activation. If you've encountered that bug on your site, you will likely need to upload this update manually because the regular update mechanism won't work while the plugin is deactivated.

= 2.29 (2025-05-22) =
##### Added
* Added a new settings tab: "Columns". It lets you hide and reorder table columns used in various admin tables, like on "Posts -> All Posts". Plugin-created tables are also supported as long as they use the same API.
* Added two new Gutenberg tweaks: disable block locking and disable access to the Code Editor.
  * "Disable block locking and unlocking": the user can't lock or unlock blocks. This doesn't affect previously locked blocks; they stay locked.
  * "Disable access to the Code Editor and "Edit as HTML"": the user can't switch to the "Code editor" mode that normally lets you view and edit the underlying block markup for a post or page. This also removes the "Edit as HTML" option from the block context menu.
* Added two new tweaks for customizing the "Plugins" page: one moves active plugins to the top of the plugin list, the other selects the "Active" filter by default.
* The plugin now automatically detects user profile fields so they can be hidden in the "Tweaks" tab. Previously, the "Hide Profile Fields" section only showed a limited set of predefined options.

##### Fixed
* Fixed "That option is currently not allowed" errors when trying to change font weight in the Admin Customizer.
* Fixed PHP 8.4 deprecation notice "fgetcsv(): the $escape parameter must be provided as its default value will change".
* Fixed a conflict with the "Bricks" theme/site builder that could cause the fatal error "Uncaught TypeError: Illegal offset type in isset or empty".
* Fixed a minor visual issue where some box backgrounds could overflow box borders when using rounded corners and custom background colors.

##### Changed
* In most AME settings tabs, the role/user list will now stick to the top when scrolling the page. This currently doesn't apply to the "Admin Menu" tab.
* Added an "About" tab to the "Content Permissions (AME)" meta box. Some users reported they found it difficult to figure out which plugin added this box.

= 2.28 (2025-04-01) =
##### Added
* Added a "Reset Roles" feature to the role editor. It can reset the default roles to their default capabilities. Unlike some similar plugins, you can choose which roles to reset, and you don't lose custom roles. However, the downside is that it uses predefined defaults, which may eventually become outdated. Before resetting roles, check the WordPress version number shown in the "Reset roles to defaults" screen.

##### Fixed
* Fixed a conflict with Toolset Types and Toolset Blocks 1.6.18 that prevented the content template editor and several other Toolset pages from working.
* Fixed Quick Search treating headings inside admin notices as sections.
* Added a workaround for plugins and themes that could crash in the "Content Permissions (AME)" meta box due to how AME detects post type capabilities. On new installations, the workaround will be applied automatically if the meta box runs into a fatal error the first time AME tries to display it. In other cases, you can apply it manually by going to the "Settings" tab and unchecking this option: "Detect post type capabilities by checking them with a non-existent user".

##### Changed
* The role list is now sorted. The default roles like "Administrator", "Editor", etc, are shown in a predefined order, while the rest are sorted alphabetically. Previously, roles were shown in the order they were created.

= 2.27.4 (2025-03-13) =
##### Fixed
* Fixed the "Disable Customizations" -> "Admin menu content" tweak not working on sites without plugins. It had a bug that was masked by having specific other plugins installed (e.g. "Classic Editor"), so it would work only on sites that were also using those plugins.
* Fixed a potential crash in the Admin Customizer.

= 2.27.3 (2025-03-13) =
##### Fixed
* Fixed the inability to disable the new "Search" item that's shown in the Toolbar / Admin Bar.
* Fixed certain menu style settings such as separator colors, menu logo, menu width, etc no longer being saved on some sites.
* Fixed a conflict with the Visualizer plugin (version 3.11.9) that stopped the edit chart feature from working due to a fatal PHP error in an AJAX handler. 
* Fixed a minor conflict with the Post SMTP plugin that caused the hidden menu items "Dashboard -> Welcome" and "Dashboard -> Credits" to become visible.

= 2.27.2 (2025-03-07) =
* Improved reliability of the 'Hide the "Patterns" tab' and the 'Hide the "Media" tab' tweaks. It appears that the block inserter tabs can have different IDs on different sites (even on sites running the same WP version) so it's difficult to hide them consistently.

= 2.27.1 (2025-03-06) =
* Fixed an issue with HTTP request method validation that could cause some of the new "Quick Search" features to fail in some server configurations (e.g. old PHP versions).

= 2.27 (2025-03-06) =
##### Added
* Added a way to control access to specific posts and pages. The new "Content Permissions" box in the post editor lets you choose which roles will be able to see a post. It also has an "Advanced" tab where you can enable or disable individual permissions like read/edit/delete for each role. Finally, you can control what happens when someone who doesn't have permission tries to view a post: you can replace the post content with something else, show a custom error, simulate a "404 Not Found" error, or redirect the user to a custom URL.
* Added "Quick Search": a pop-up search box that searches admin menus. Optionally, it can also search admin pages for specific settings, tabs, filter links, and a few more things. You can open the search box by pressing the Shift key twice or clicking the search icon in the Toolbar/Admin Bar. You can configure this feature in the new "Quick Search" tab or turn it off in the "Settings" tab (under "Modules").
* Added margin settings for menu headings.
* Added "Disable Customizations" tweaks that you can use to disable the custom admin menu, custom metabox visibility, and a few other things for specific roles or users.
* Added a filter that can be used to turn off admin menu customizations. Basic example: `add_filter('admin_menu_editor-disable_customizations-admin_menu_structure', '__return_true');`
* Added an option to highlight unsaved changes (i.e. enabled/disabled capabilities) in the "Roles" tab.
* Added an "Optimize menu configuration size" option to the "Settings" tab. It makes the plugin store the admin menu configuration in a more space-efficient format, which should reduce the size of the "ws_menu_editor_pro" database entry. This option is enabled by default. Previously, this was controlled by the option "Compress menu configuration data that's stored in the database", but now the "Compress..." option only applies to actual compression.

##### Fixed
* Fixed default redirects not being saved due to a bug in the "delete settings associated with missing roles" feature.
* Fixed the "Highlight background" color setting in admin menu colors not completely overriding the "Menu highlight background" setting in the general admin color scheme.
* Fixed a potential crash when the Zlib extension is enabled but the gzuncompress() function is disabled.
* Fixed a PHP warning related to WP Adminify that was triggered by trying to parse an invalid hook callback.
* Fixed the "Hide the Gutenberg options menu" tweak not working in WP 6.7.x.
* Fixed the tweaks that hide the "Media" and "Patterns" tabs in the Gutenberg block inserter not working in WP 6.7.x.
* Fixed a potential CSS issue where menu heading styles failed to override general menu item styles.
* Fixed a minor conflict with Elementor that caused the hidden menu items "Elementor -> Connect" and "Elementor -> Note Proxy" to become visible.
* Fixed the left side of the focus outline being cut off for capability checkboxes in the "Roles" tab.
* Fixed a potential caching issue with generated CSS stylesheets.

##### Changed
* Adjusted the size and layout of the top settings tabs on the "Menu Editor Pro" page in situations where the tabs don't fit on a single row.
* Removed the "Screen Options" panel from the menu editor. The only option that was in that panel - "Hide advanced options" - is still available in the "Settings" tab (in the "Interface" section).
* Tested with WP 6.7.2; partially tested with WP 6.8-alpha.
* Increased minimum required WP version to 5.4.

= 2.26.1 (2024-07-17) =
##### Added
* Added the "CSS classes" field to submenu items. You can use it to add custom CSS classes to menu items. Previously, only top level menus had this field.
* Updated the capability database. The plugin uses this to categorize the role capabilities shown in the "Roles" tab.

##### Fixed
* Fixed a bug introduced in version 2.26 that prevented certain blocks from rendering in the default block editor. This only affected blocks that call the "block-renderer" REST API.

= 2.26 (2024-07-16) =
##### Added
* Added a "Nav Menus" tab that lets you hide navigation menu items from roles and users. It supports both classic navigation menus and block-based navigation menus used by FSE themes. In addition to per-role settings, you can also hide menu items from all logged-in users or all logged-out users.

##### Fixed
* Fixed a bug introduced in version 2.25 that could cause the "Redirects" tab to be blank in some configurations. The bug also triggered this JS error: "settings.redirects.map is not a function".
* Fixed a WooCommerce conflict where two "Subscriptions" menu items would appear when AME was active.

##### Changed
* Tested with WP 6.6-RC3 and WP 6.7-alpha.

= 2.25 (2024-07-02) =
##### Added
* Added background color settings for submenu item hover states, both for the admin menu and for the Toolbar.
* Added a background color setting for the currently active admin menu item.
* Added an option to open the admin menu logo link in a new tab.
* Added an option to automatically delete settings associated with missing roles and users. This only applies to certain settings, such as menu permissions and login redirects. "Missing" means that the role or user doesn't exist on the current site, which usually happens when it has been deleted. In Multisite, it can also happen if different subsites have different roles. By default, this option is enabled on regular sites and disabled in Multisite.

##### Fixed
* Fixed a WooCommerce conflict where two "Orders" menu items would appear when AME was active.
* Fixed a rare PHP warning "Undefined array key "parent" in ... menu-editor-core.php".
* Fixed a potential crash if the global `$menu` variable is not a native array but is still array-like.
* Improved compatibility with old versions of UiPress.

##### Changed
* Improved menu drag & drop from the top level to the submenu. You can now drop items anywhere in the active submenu, not just at the bottom.
* Tested with WP 6.5.5.

= 2.24.3 (2024-05-15) =
##### Fixed
* Fixed the WooCommerce admin page header staying in its old place when changing the admin menu width, the Toolbar height, or when hiding the Toolbar completely. Now the page header will be automatically moved or resized so that it's still fully visible.
* Fixed custom menu items that use the "Embed WP page" feature sometimes having different item URLs for different users or having the URL change unnecessarily. With this update, the URL will change the next time you save the admin menu, but after that it should generally stay static unless you select a different embedded page.
* Fixed synced patterns (a.k.a. reusable blocks) not working when AME Pro is active and *any* Gutenberg blocks are hidden.
* Fixed a potential PHP warning about array indexing in `hide-others-posts.php` when at least one of the "Hide Other Users' Posts" options was enabled in the "Tweaks" tab.
* Updated the MailPoet compatibility fix for MailPoet 4.49.1. Now custom admin menu colors should once again work on MailPoet's admin pages.

= 2.24.2 (2024-04-29) =
* Fixed a fatal error "Cannot use a scalar value as an array" when trying to save changes on the "Easy Hide" page.

= 2.24.1 (2024-04-22) =
* Fixed a conflict with UIPress that was introduced in version 2.24. The conflict prevented most admin menu settings from being applied. For example, hidden menu items would become visible, menu order would reset, renamed items would display the old title, and so on. Custom menu permissions were still effective, so unchecked items would be visible but inaccessible.

= 2.24 (2024-04-15) =
##### Added
* Added a way to prevent a role from editing or deleting media files that were uploaded by other users. You can find the new settings in the "Tweaks" tab, in a section named "Media Library Restrictions".
* Added the ability to hide and/or remove block patterns.
* Added an option to hide the "Media" tab in the Gutenberg block inserter.

##### Fixed
* Fixed inability to save various menu styles and menu separator styles without creating an admin menu configuration first. This issue could cause errors when saving changes in the Admin Customizer.
* Fixed the Gutenberg block editor and the block-based widget editor having unnecessary empty space at the top when the Toolbar (Admin Bar) is hidden.
* Fixed the Gutenberg editor overlapping the admin menu when increasing the width of the admin menu.
* Fixed hidden special blocks like "Pattern placeholder" and "Legacy Widget" showing up in the "Hide Gutenberg Blocks" section even though they are not actually visible in the block inserter.
* Fixed a conflict between unchecking the "Show the Toolbar" option and enabling "Full height" admin menu mode in the Admin Customizer. This could cause the first item of the admin menu to go off the screen, making it invisible.
* Various other layout fixes.
* Fixed an Admin Ccustomizer bug where changing admin menu settings in a certain order and then saving the changes could result in some of those changes being lost. For example, this could happen if you changed the menu bar width, then changed the menu separator margins, then saved the changes - the menu bar width would be reset.
* Made the "Download as admin theme" feature available even when the current session doesn't have any unsaved changes.
* Fixed "Warning: Attempt to read property "cap_key" on null" if a metadata update happens for a non-existent user or the user can't be retrieved.
* Fixed the "Media" menu always being highlighted as "new" when the "Enable Media Replace" plugin is active.

##### Changed
* The "Hide Profile Fields" tweaks now also apply to the "Edit User" screen.
* Tested up to WordPress 6.5.2.

= 2.23.3 (2024-01-21) =
* Hiding the "Author" meta box now also hides the corresponding section of the "Summary" panel in the Gutenberg block editor.
* Updated the "Meta Boxes" tab to automatically reselect the previously selected role after saving changes.
* Fixed dashboard widget visibility not being saved. 

= 2.23.2 (2024-01-20) =
* Fixed meta box visibility not being saved. This problem was likely related to the recent Lodash 4 migration.

= 2.23.1 (2024-01-19) =
* Hotfix: Fixed users that have the Administrator role and one or more additional roles unexpectedly losing the ability to edit or assign certain roles. This was caused by a preexisting but previously unknown bug that was exposed by the 2.23 update changing the default "Editable roles" setting for the Administrator role to "Leave unchanged". Users who have manually set it to "Automatic" or "Custom" are likely unaffected.

= 2.23 (2024-01-17) =
##### Added
* Added way to hide posts created by other users. It's a group of new settings in the "Tweaks" tab. It works with posts, pages, media, and custom post types that use the default admin UI.
	* Limitations: This feature only affects post tables like the one shown under "Posts -> All Posts". Hiding other users' posts from someone doesn't prevent them from opening those posts by following a direct link, and they can still edit the posts if they have the necessary capabilities (e.g. `edit_others_posts`).
* Made the Gutenberg panel "Revisions" hideable by hiding the corresponding meta box.

##### Changed
* Made menu editor toolbars "sticky". They will now stay below the admin bar when scrolling down, which can be useful when editing very long menus.
* Changed the default "editable roles" setting for the Administrator role from "Automatic" to "Leave unchanged". This means that users with the Administrator role will now be able to edit any user, even if the user being edited has more capabilities than the Administrator role (which can only happen with custom/modified roles). Of course, you can still configure this by clicking "Editable roles" in the "Roles" tab.
* Restored the "custom permissions" and "custom item" indicators for the "Modern" editor color scheme. Previously, they were not visible in the menu editor when using that color scheme.
* Migrated to Lodash 4.

= 2.22.1 (2023-11-09) =
* Fixed a conflict with Query Monitor that had the potential to cause a fatal error in some rare situations (no errors actually reported by users so far).
* Tested with WP 6.4.1.

= 2.22 (2023-10-17) =
##### Added
* Added a search box for menu icons. For the moment, Dashicons and Font Awesome icons have separate search boxes.
* Added more Dashicons. Now the icon dropdown should show all currently existing Dashicons.

= 2.21.2 (2023-10-09) =
##### Fixed
* Fixed a conflict that prevented users from changing the menu icon of the Wordfence plugin (and possibly some other plugins that use similar CSS). Note that the option "Attempt to override menu icon CSS..." in the "Settings" tab may need to be enabled for this fix to be effective.
* Fixed a crash if the plugin encounters a supposed "menu item" that has the wrong data type, like a boolean. This was likely caused by a bug in an unidentified plugin or theme that modified the menu list incorrectly.
* Fixed plugin visibility restrictions not being applied when editing plugin files via AJAX.
* Fixed separator and admin color settings not being included when generating an admin theme in the Admin Customizer (the generated CSS was correct, but the settings could not be reimported later).
* Added a compatibility workaround to the menu export feature for dealing with buggy plugins that add superfluous whitespace to every WordPress response.

##### Changed
* Prevented the Admin Customizer interface from being displayed in any kind of a frame. Let's not do recursion.
* Tested with WP 6.3.1 and WP 6.4-alpha.

= 2.21.1 (2023-07-21) =
##### Fixed
* Fixed a persistent fatal error that could be triggered by changing certain menu style settings in the Admin Customizer *without* editing the admin menu first. 
* Fixed a PHP 8 deprecation notice about creating dynamic properties on the `ameRexCapability` class. 

= 2.21 (2023-07-14) =
##### Added
* Added "Admin Customizer": a new visual customization interface that lets you change various admin dashboard styles with live preview. It was inspired by the Theme Customizer. Admin Customizer includes the existing admin menu style settings and adds a number of new style settings for various admin UI elements: 
 * Buttons
 * Boxes (dashboard widgets and meta boxes)
 * Page headings
 * Tables
 * The Toolbar (Admin Bar)
* Admin Customizer can generate an admin theme from your custom style settings. The generated admin theme includes only a subset of visual settings (e.g. no menu permissions or custom widgets), and it is entirely stand-alone: you can use it without Admin Menu Editor Pro.
* Note: Since Admin Customizer is a complex and brand new feature, it may have currently more bugs than the rest of the plugin.
* Added more options to the "Tweaks" tab that can be used to hide more profile fields.

##### Changed
* Added a dash before secondary menu items like "Easy Hide" and "Admin Customizer" when they are shown below the main "Menu Editor Pro" menu item.
* Renamed some buttons that don't immediately save settings to the database from "Save Changes" to "OK". For example, this affects the confirmation button in the "Style" dialog. The behaviour is still the same, only the labels have changed. You still need to click the "Save Changes" button in the menu editor to actually save the menu configuration.
* Added a green indicator to the "Save Changes" button on the menu editor page when there are certain types of pending changes.

##### Fixed
* Fixed a minor conflict with Essential Grid 3.0.17 that caused tooltips in AME dialogs to appear underneath the dialogs.
* Fixed the JS error "Uncaught TypeError: colorPresets is null" when trying to edit the colors of an individual menu item when there is no global menu color preset.
* Fixed an inefficiency where some menu logo styles were added even when no logo was selected.
* Fixed a rare but severe admin dashboard performance issue that was triggered by dynamically generated stylesheets using the `Content-Length` HTTP header.
* Fixed menu item color settings not being applied to custom menu items that don't have a custom element ID. This used to work before, but was accidentally broken around version 2.19.

= 2.20 (2023-05-30) =
##### Added
* Added the ability to move dashboard widgets and override the number of dashboard columns. The custom dashboard layout can be enabled or disabled per role.
* Added an "open links in a new tab" setting to custom RSS widgets.

##### Fixed
* Fixed a minor plugin conflict with "WPFunnels" and "Email Marketing Automation - Mail Mint" that caused hidden menu items created by those plugins to become visible when AME was activated.
* Fixed a conflict with the "Fortress" plugin that could reportedly cause an infinite loop.
* Fixed a conflict with "Da Reactions" 4.0.3 that triggered PHP warnings like "Warning: Array to string conversion in .../includes/menu-item.php on line 54".
* Switched TypeScript to strict mode.

= 2.19.3 (2023-04-24) =
* Hotfix: Fixed another bug in the "full height layout" feature that pushed the right side of the Toolbar off the screen, potentially hiding the "Howdy, username" dropdown.

= 2.19.2 (2023-04-24) =
##### Fixed
* Fixed a fatal error in the role editor and on "Settings -> Easy Hide" that could be triggered when a single admin menu item had multiple notification badges. In this context, "badge" refers to the colorful circles that WordPress uses to show things like the number of available updates and pending comments.
* Fixed menu logo not showing up in certain configurations.
* Fixed menu logo not being visible when the admin menu is collapsed and the logo has large horizontal margins/padding. The horizontal margins and padding will now be set to zero in this case.
* Fixed the "Full height menu" option not working outside the preview unless menu width was also changed to a custom value.
* Fixed a bug where changing the color settings of an individual menu item did not change the actual appearance of the item unless custom colors were also set for the admin menu as a whole.
* Fixed a few PHP 8.1 deprecation notices related to implicit conversions from float to int.
* Fixed color settings generating invalid CSS rules when the setting value is an empty string.
* When the popup slider is shown above an input because there is not enough space below, the triangular tip should now appear below the popup (i.e. pointing towards the input), not above it.

##### Changed
* Updated the popup slider ranges for more reasonable box shadow settings.

= 2.19.1 (2023-03-28) =
##### Fixed
* Fixed the error "Undefined index: menu_styles in ...menu-styler.php". This could show up either as a PHP warning or as a notice depending on the PHP version.

= 2.19 (2023-03-27) =
##### Added
* Added many new admin menu customization settings. This includes: menu width, menu bar shadow, item font size and style, item margins and padding, full-height menu option, custom logo above the admin menu, custom "collapse" button text, and so on. To access these settings, click the "Style" button in the menu editor sidebar. 

##### Fixed
* Fixed a minor conflict with the WPForms plugin where the hidden menu item "Dashboard -> Welcome to WPForms" became visible when Admin Menu Editor was installed.
* Fixed a conflict with Toolset Types 3.4.7 that prevented redirect settings from being saved.
* Fixed a PHP warning triggered when a menu item didn't have a URL or a required capability.
* Fixed a plugin visibility bug where, if none of the user's roles had custom access settings for a specific plugin or in general, AME would immediately deny access instead of also checking user capabilities. This could theoretically happen if all the user's roles were new or if the user didn't have any roles (they might still have access due to directly granted capabilities).
* Fixed a fatal error ("array_merge(): Argument #1 must be of type array") and multiple warnings that were caused by third-party roles that had an invalid capability list. The list should be an `array`, but for some custom roles it is apparently `null` or `false`.
* Prevented a potential fatal error if JSON-encoded module settings stored in the database have been corrupted and can't be decoded.
* Fixed a bug where changing the title of a dashboard widget did not change the title shown in the "Screen Options" panel.
* Fixed PHP notice about enqueueing the "wp-editor" script incorrectly. The notice only appeared on the "Appearance -> Widgets" page.
* Fixed the "Hide the Gutenberg options menu" setting not working in recent WP versions.
* Added some missing `.map` files that could cause 404 errors for users looking at the developer console.

##### Changed
* The "Separators" and "Colors" buttons were removed from the menu editor and the corresponding settings were moved to the new "Style" screen.
* Lots of internal reorganization.
* Tested up to WP 6.2.

= 2.18.1 (2022-11-01) =
##### Fixed
* Fixed multiple nested submenu layout issues that showed up when the admin menu was in the folded/collapsed state.

##### Changed
* Tested up to WP 6.1.

= 2.18 (2022-08-30) =
##### Requirements
* Increased the minimum required PHP version to 5.6. Technically, some AME features may still work on older PHP versions, but those versions are now officially unsupported.

##### Added
* Added a way to delete meta box settings associated with post types and taxonomies that no longer exist.

##### Fixed
* Fixed a number of deprecation warnings related to PHP 8.
* Fixed a conflict with "Anti-Spam by CleanTalk" that could potentially cause a fatal error.
* Fixed a conflict with "Admin Theme - Musik" where the menu order settings in the other plugin would override the menu order configured in AME.
* Fixed a subtle conflict with plugins that use the boolean value TRUE as a capability name. For example, "Admin Columns Pro - WooCommerce" version 3.7.3 caused the "manage_woocommerce" capability to disappear.
* Fixed missing padding in the "edit plugin details" panel (in the "Plugins" tab). Also updated the button layout to match the new button layout of the "Quick Edit" panel introduced in WP 6.0.
* Fixed a visual issue where some form fields might briefly show up and then disappear if the settings page stylesheet took a while to load.

##### Changed
* Removed dependency on the "icon16" CSS class. The class is likely to be removed in WP 6.1.

= 2.17 (2022-05-24) =
##### Added
* Added a new "Settings -> Easy Hide" admin page. This page collects most Admin Menu Editor Pro settings that are related to hiding things and provides a centralized interface for turning those settings on/off for different roles. The intent is to help people who primarily use this plugin to hide parts of the admin dashboard or clean up the interface for their users. If you don't need this feature, you can turn it off in the "Settings" tab: find the "Modules" section and uncheck the "Easy Hide" option.

##### Fixed
* Added additional validation and escaping in multiple places.
* Fixed a number of issues related to the WordPress coding standard and the WordPress-VIP-Go coding standard.
* Fixed visual misalignment of the "Extra capability" field and its drowpdown dropdown button in the "Permissions" dialog.
* Fixed inconsistent spacing aroud some radio buttons on the settings page.
* Fixed a fatal error during dashboard widget import. This bug affected only the "Import" button in the "Dashboard Widgets" tab; it did not affect the "Import" tab.

##### Changed
* Introduced a limit to how many unique menu URLs can be remembered by the "highlight new menu items" feature. Previously, when this feature was enabled, the plugin would record each "seen" menu item, which could cause the associated database entry to grow endlessly. Now the plugin will remember up to 700 items per user.
* Tested with WordPress 6.0 (release candidate) and 6.1-alpha.

= 2.16.2 (2022-03-31) =
##### Added
* Optionally, you can make the plugin clear the option cache when updating all subsites in Multisite. This is usually not necessary, but may help with compatibility issues when using an external object cache. Use the new custom filter  "admin_menu_editor-clear_role_cache" to enable this feature. Example: `add_filter('admin_menu_editor-clear_role_cache', '__return_true');`

##### Fixed
* Fixed a bug where collapsing a menu heading would hide menu items but not separators.
* Fixed a conflict that could trigger a fatal error in PHP 8. The conflicting plugin or theme has not been identified, but the conflict was most likely caused by somebody returning NULL from one of the "mce_buttons" filters.
* When a user is hidden, the per-role user counts on the "Users -> All Users" page will now be adjusted accordingly.
* Fixed some minor issues with the size and layout of indeterminate checkboxes on small screens.
* Fixed the `[ame-user-info]` shortcode not working in login redirects. It would always output "(No user)" instead of the actual user data.
* Fixed a warning caused by a conflict with plugins and themes that call the "login_redirect" filter with only 1 parameter instead of the expected 3.
* Fixed a subtle bug where AME did not correctly prefer submenu items when trying to determine the current menu item based on the current URL.
* Probably fixed a bug where menu items that use fully qualified URLs would lose their custom settings when the site URL changed (such as when migrating the site to a different domain).
* Fixed an edge case where the plugin would incorrectly show an "is this option enabled for everyone" checkbox in an indeterminate state when it was actually enabled for all roles but was not explicitly enabled (or disabled) for individual users.
* Fixed a rare bug where a top level menu and a nested menu could share the same submenu items due to a key collision.
* Fixed a minor conflict with "Google Analytics for WordPress by MonsterInsights" where the "Dashboard -> Welcome to MonsterInsights" menu item that is usually hidden would become visible when AME was activated.

= 2.16.1 (2021-12-09) =
##### Fixed
* Fixed a conflict with "Amazon Simple Affiliate (ASA2)" that caused the "Add ASA2 Product" page to crash with an exception. This change should also fix conflicts with other plugins that create meta boxes with invalid IDs.
* Fixed a minor issue with "Happy Elementor Addons" where the "HappyAddons News & Updates" dashboard widget didn't show up in the "Dashboard Widgets" tab.
* For submenu separators and menu headings, removed the colorful left border that would appear when you hover, click, or focus on the item. Headings will still have the colored border if the "collapsible headings" option is enabled.

##### Changed
* The option "Hide the Admin Menu Editor Pro entry on the Plugins page from other users" now also hides any installed add-ons. The option has been renamed to "Hide Admin Menu Editor Pro and its add-ons from the Plugins page for other users".
* The plugin will now show an admin notice instead of crashing with an exception if meta box settings are corrupted by a site migration plugin or a similar tool.
* Switched the JSON serialization implementation from the jQuery JSON plugin to `JSON.stringify`. While this should not affect most users in any way, those that use Internet Explorer may or may not notice some changes in the handling of Unicode characters (e.g. in menu titles).

= 2.16 (2021-10-21) =
##### Added
* Added a "Redirects" feature. You can create login redirects, logout redirects, and registration redirects. You can configure redirects for specific roles and users. You can also set up a default redirect that will apply to everyone who doesn't have a specific setting.
* Added a few utility shortcodes: `[ame-wp-admin]`, `[ame-home-url]`, `[ame-user-info field="user_login"]`. These are mainly intended to be used to create dynamic redirects, but they will also work in posts and pages.

##### Fixed
* Fixed a minor conflict where several hidden menu items created by "WP Grid Builder" would unexpectedly show up when AME is active.
* Fixed a conflict with "LoftLoader Pro", "WS Form", and probably a few other plugins that create new admin menu items that link to the theme customizer. Previously, it was impossible to hide or edit those menu items.
* Partially fixed an obscure bug where numeric role capabilities like "1" or "234" would be displayed as "0" instead.

##### Changed
* Improved the appearance of settings page tabs on small screens and in narrow browser windows.

= 2.15.1 (2021-08-25) =
##### Fixed
* Fixed the "sort" button in the submenu toolbar sorting all submenu items in all menus, not just in the currently selected submenu.
* Fixed a conflict with "PPOM for WooCommerce by N-MEDIA" version 23.0 that could cause a fatal error when both plugins were active.
* Fixed a conflict with the "Oxygen" (page builder) where the "Role" dropdown was missing the "Administrator" role when both plugins were active.
* Fixed a warning about using the deprecated filter "allowed_block_types".
* Fixed a rare conflict where clicking on a role wouldn't do anything if another plugin or theme had rewritten all links on the page.

= 2.15 (2021-07-28) =
##### Added
* Added experimental support for three level menus. This lets you create deeply nested menus such as "Menu -> Submenu -> Nested Submenu". Due to risk of conlicts and bugs, this feature is disabled by default. To enable it, go to the "Settings" tab and set "Three level menus" to "Enabled".

##### Fixed
* Fixed a conflict with bbPress where, if you used the "Custom" setting in the "Editable roles" screen, it would be impossible to edit users who had any bbPress roles even when those roles were enabled.
* Fixed a few jQuery deprecation warnings.
* Fixed a warning about using the deprecated filter "allowed_block_types".
* Fixed an "Undefined array key" warning that could appear if another plugin created a user role that did not have a "capabilities" key.
* Fixed a minor BuddyBoss Platform compatibility issue where the menu editor would show a "BuddyBoss -> BuddyBoss" menu item that was not present in the actual admin menu. The item is created by BuddyBoss Platform, but it is apparently intended to be hidden.

= 2.14.2 (2021-05-07) =
##### Fixed
* Fixed a bug where the plugin could incorrectly identify a separator as the current menu item. In practice, separators don't link to anything and they cannot be clicked, so they should not even be considered when trying to figure out which menu item matches the current page URL.
* Fixed a couple of icon and separator rendering bugs where the hover marker that was introduced in WP 5.7 could either show up in the wrong place or show up when it's not supposed to.
* Fixed the submenu box not expanding to align with the selected parent item.
* Fixed a PHP 5 compatibility issue where the "Prevent bbPress from resetting role capabilities" setting would trigger notices and not work correctly. This bug did not affect more recent PHP versions such as PHP 7.x.

= 2.14.1 (2021-03-15) =
##### Fixed
* Fixed a bug introduced in version 2.14 where the "Embed WP page" feature would no longer display the field that let you choose the page.
* Fixed a jQuery Migrate warning about isFunction() being deprecated.

= 2.14 (2021-03-08) = 
##### Added
* Added the ability to create menu headings. Headings are unclickable and can be styled separately from regular menu items. By default, their color and background don't change on hover. You can configure headings to act as collapsible sections. When you click a collapsible heading, it will show/hide all of the menu items between that heading and the next one.
* Added environment-dependent colors. In the "Tweaks" tab, you can configure the plugin to change the background color of the Toolbar (a.k.a Admin Bar) and the admin menu based on the current environment: production, development, etc. You can also display the environment type in the Toolbar. The plugin uses the environment type reported by WP core, which in turn uses the "WP_ENVIRONMENT_TYPE" constant.

##### Fixed
* Fixed a conflict with the "PRO Theme" plugin where "PRO Theme" would expand the wrong top level admin menu if the current submenu item had been moved from one parent menu to another.
* Fixed PHP notice "Undefined offset: 0 in /wp-includes/capabilities.php on line 70" (various line numbers).
* Fixed a conflict with "Stripe For WooCommerce" 3.2.12 where the "Stripe Gateway" menu had a wrong URL because a hidden menu item was not removed.
* Fixed a browser warning about the "ws_nmh_pending_seen_urls" cookie not using the SameSite attribute.
* Fixed a conflict with WooFunnels where changing the WooFunnels menu icon would result in both of the icons - the original one and the new one - showing up at the same time. The new icon was also misaligned.
* Fixed an unconfirmed conflict where AME could trigger a fatal error because the cached meta box settings were seemingly cleared just before they were used.
* Fixed misleading option name in the "Export" tab that made it look like it was possible to export roles. Actually, at the moment the plugin only exports the "editable roles" setting, not the roles themselves.
* Fixed admin menu not scrolling when the Toolbar/Admin Bar was hidden.
* Fixed a bug where the "Admin CSS" code editor would be rendered incorrectly - broken layout, partially invisible text, etc - if the "Admin CSS" section was closed when the user opened the "Tweaks" tab.
* Fixed an encoding related issue where the "Roles" tab would be empty or just show a "Loading..." message forever.
* Fixed an occasional bug where AME did not detect custom Gutenberg blocks created by Advanced Custom Fields Pro. 

##### Changed
* Minor visual changes.
* Dashboard settings are now compressed and base64-encoded to prevent data corruption caused by database migration tools that attempt to replace file paths in the database without correctly escaping (back-)slashes.
* Tested with WordPress 5.7 and 5.8-alpha.

= 2.13 (2020-12-15) = 
##### Added
* Added a "bbPress override" option that prevents bbPress from resetting all changes that are made to dynamic bbPress roles. Enabling this option allows you to edit bbPress roles with this or any other role editing plugin.

##### Fixed
* Fixed a bug where registering a custom post type in a mu-plugin could cause Admin Menu Editor Pro to trigger either a fatal error or a warning and multiple notices.
* Fixed the role editor going into infinite recursion if there was a meta capability that mapped to itself. 
* Fixed a conflict that caused some hidden Simple Calendars menu items to show up when Admin Menu Editor was activated.
* Fixed a bug where menu items that had special characters like "&" and "/" in the slug could stop working if they were moved to a different submenu or to the top level.
* Fixed a bug where changing the menu icon to an external image (like a URL pointing to a PNG file) could result in the old and the new icon being displayed at once, either side by side or one below the other. This only affected menu items that had an icon set in CSS by using  a `::before` pseudo-element. 
* Fixed many jQuery deprecation warnings.
* Fixed a bug where some menu settings would not loaded from the database when another plugin triggered a filter that caused the menu configuration to be loaded before AME loaded its modules.
* Fixed bug that could cause an obscure conflict with plugins that change the admin URL, like "WP Hide & Security Enhancer". When a user tried to open "Dashboard -> Home", the plugin could incorrectly apply the permisssions of a another menu item to the "Home" item. If the other menu item was configured to be inaccessible, the user would get an error message when logging in (they were still successfully logged in).

##### Changed
* Improved error reporting in situations where the plugin can't parse menu data.

= 2.12.4 (2020-10-02) =
* Hotfix: Fixed a new plugin conflict introduced in version 2.12.3 that could prevent some add-on features like the "Branding" tab from being loaded on some sites.

= 2.12.3 (2020-10-02) = 
* Fixed a bug where separator settings could be lost if another plugin or theme indirectly caused this plugin to load the menu configuration earlier than expected.
* Made custom menu icon colors apply to SVG icons. Previously, you could only change the color of those menu icons that are implemented using icon fonts like Dashicons.
* Improved error reporting in situations where the plugin can't parse menu data.

= 2.12.2 (2020-08-25) =
* Fixed a conflict with Elementor 3.0 that caused the "Theme Builder" menu item to have the wrong URL. 
* Minor performance improvements.

= 2.12.1 (2020-08-13) =
* Fixed a few bugs and plugin conflicts introduced in version 2.12 that could prevent certain roles from editing some or all users. This issue was related to the new "editable roles" feature. The default settings were too restrictive for some sites that use custom capabilities and the feature was not compatible with bbPress roles.

= 2.12 (2020-08-10) =
##### Added
* Added "editable roles" settings. You can use these settings to control which roles a user can assign to other users. Additionally, this feature will prevent the user or role from editing users that have any non-editable roles. By default, a role can only assign/edit those roles that have the same or fewer capabilities.
* Added a new section to the "Roles" tab that lets you change user roles, including the ability to assign multiple roles to one user.
* Added the ability to change menu separator color, size, and margins.
* Added an option to disable WPML support.
* Added an option to hide the WordPress logo when Gutenberg is in fullscreen mode.

##### Fixed
* Added a workaround to deal with plugins and themes that create invalid, non-string capabilities. Previously, if another plugin used a capability like `false` (the boolean value, not the word) when registering a custom post type or taxonomy, the "Roles" tab would be empty and unusable.
* Fixed menu colors not being applied to menus that have an ID that contains special characters like "&", "/" and so on.
* Fixed the browser freezing for a long time when pasting a very large block of code into the "Add custom admin CSS" text box.
* Fixed custom menu colors not being applied to the icon of the currently selected menu item.
* Fixed a minor WP 5.5 compatibility issue where some of the boxes shown on the plugin settings pages were displayed incorrectly.
* Fixed a bug where hidden plugins were still visible under "Dashboard -> Updates" and were included in the number of updates shown in the admin menu, Toolbar and other places.
* Fixed a conflict with WP Job Manager where activating Admin Menu Editor made the hidden "Dashboard -> Setup" menu visible.
* Fixed a browser warning about cookies using "SameSite: None".
* Fixed a conflict with plugins that use a different, incompatible version of the jquery-cookie library. For example: Participants Database Field Group Tabs.
* Fixed a bug where it was impossible to assign any capabilities to a role that didn't already have at least one capability.

##### Changed 
* The "hide all admin notices" option now also hides notices that use the core update notification styles. For example, it will hide this notification: "WordPress X.Y is available! Please update now."

= 2.11 (2020-02-22) =
##### Added
* Added a role editor. You can assign/remove role capabilities, create custom roles, delete roles, and more. This is a new and complex feature. If you notice any bugs or other issues, please report them.
* Added the ability to add custom CSS to the WordPress admin. To enter your CSS code, go to the "Tweaks" tab and find the new "Admin CSS" section. You can add multiple CSS snippets and enable/disable each snippet for specific roles or users.
* Added a "reset permissions" toolbar button. It resets all menu permissions and visibility settings for the selected role or user to the defaults. This doesn't affect custom permissions applied by other plugins. To display the new button, click the "V" button on the right side of the toolbar.
* Added "Title" and "Content Editor" options to the "Meta Boxes" tab. They let you hide the post title field and the main post editor, respectively. 
* Added a the ability to hide specific Gutenberg blocks from roles or users. This feature is in the "Tweaks" tab.
* Added an option to hide the Gutenberg options menu. This is the "more tools & options" menu that you can open by clicking the three vertical dots in the top right corner of the editor.
* Hiding the "Format" and "Publish" meta boxes now also hides the corresponding rows in Gutenberg's "Status & Visibility" panel: "Post Format" and "Publish".

##### Fixed
* Fixed admin notices being briefly visible even when the "hide all admin notices" option was enabled.
* Added a compatibility workaround for MailPoet 3. Custom menu colors and FontAwesome icons will now show up properly on MailPoet's admin pages.
* The hidden "getting started" menu item created by the plugin Extended Widget Options will no longer become visible when you activate Admin Menu Editor.
* Slightly reduced the chance of encountering a race condition when saving meta box settings.

##### Changed
* Reduced the amount of space used by plugin visibility settings. This change will take effect the next time you save those settings.
* Extended the "compress menu configuration data" feature to use ZLIB compression in addition to menu data restructuring. This greatly decreases the amount of data stored in the database, but increases decompression overhead. Configuration compression is disabled by default. You can enable it in the "Settings" tab.

= 2.10.2 (2019-12-16) =
##### Fixed
* Fixed a warning about get_magic_quotes_gpc() being deprecated in PHP 7.4.
* Fixed a conflict with plugins that use the "all_plugins" filter incorrectly.
* Most likely fixed the fatal error "call to a member function isFirstRefreshDone() on null".
* Fixed a conflict with the WikiPress theme from HeroThemes and similar themes and plugins that load the Gutenberg editor in unusal ways (e.g. outside the admin panel).

= 2.10.1 (2019-11-13) =
##### Fixed
* Fixed a bug where the "highlight new menus" feature could keep certain top level menus permanently highlighted when some of their submenus were hidden via CSS/JS.

##### Changed
* Updated the appearance of plugin settings pages to match the admin CSS changes introduced in WordPress 5.3.

= 2.10 (2019-10-22) =
##### Added
* Added a "Tweaks" tab to the settings page. This tab contains miscellaneous admin customization features. It includes new options like:
	- Hide the "Sreen Options" button.
	- Hide all admin notices.
	- Hide profile fields.
	- Hide sidebars and sidebar widgets.
	- Hide TinyMCE buttons (TinMCE is the classic post editor).
	
##### Fixed
* Fixed inconsistent dialog title bar colours that could happen when another plugin loaded the default WP dialog styles.
* Fixed an issue where the meta box list in the "Meta Boxes" tab could be missing some boxes if you edited a post/page after installing the plugin but before opening that tab.
* Changed how the plugin displays errors that happen while loading the menu configuration (e.g. due to an incompatible format or corrupted data). Instead of triggering a fatal error, the plugin will display an admin notice.

= 2.9.1 (2019-08-23) =
##### Fixed
* Fixed a minor conflict with Toolset Types.
* Fixed a conflict with the MailPoet plugin where it was not possible to change the plugin's menu icon. 
* Fixed a bug where the plugin could misidentify certain core menus that have different URLs for different roles.
* Fixed a bug where the plugin could generate incorrect URLs for submenu items where the parent menu URL contained HTML entities like "&amp;".
* Fixed an issue where certain vulnerability scanners showed a warning about one of the plugin files because it used the eval() function. This particular instance of eval() was not a security flaw, but it has been now been removed to prevent further false positives.
* Fixed a bug where the plugin could show an incorrect error message when a menu item was hidden due to there being another hidden menu item with the same URL.
* Fixed a minor issue with field alignment in menu properties.
* The "Site Health" menu will no longer be highlighted as a new menu.

##### Changed
* Improved compatibility with plugins that use non-standard hooks to add their meta boxes (example: WPML).

= 2.9 (2019-05-09) =
##### Added
* Added a "Hide the frame scollbar" option to menu items that open in an iframe.

##### Changed
* Improved the automatic frame height calculation algorithm. When the framed page is on the same site (that is, same origin), the frame height will match the content height. This means you no longer need to manually set the frame height for pages like that.
* Tested with WP 5.2.

= 2.8.2 (2019-04-05) =
##### Added
* Added an option to automatically hide new plugins from the "Plugins" page. It was already possible to hide newly installed plugins, but previously this feature was tied to the "show all plugins" checkbox in the "Plugins" tab. Now there's a separate setting just for new plugins, which lets you hide new plugins without also hiding existing plugins.

##### Fixed
* Fixed a bug that stopped the icon dropdown from working in certain menu items and made it impossible to change the icon of the Jetpack menu.
* Fixed a PHP warning: "non-string needles will be interpreted as strings". This was originally patched in 2.8, but the patch was incomplete and did not fully resolve the issue. This version should fix the warning in all cases.
* Fixed missing dependencies that prevented users from hiding meta boxes in Gutenberg. Partial Gutenberg support was added in 2.8, but most users couldn't take advantage of it due to missing files.

= 2.8.1 (2019-02-15) =
##### Fixed
* Fixed a bug where clicking the "Add User" button in the "Users" tab opened an empty "Select Users" dialog, which made it impossible to select any users. Now the dialog should actually display existing users.

= 2.8 (2019-02-07) =

##### Added
* Added partial Gutenberg support. When you hide a meta box, the corresponding Gutenberg document panel will also be removed. This works for most boxes/panels.
* Added three new shortcodes: `[wp-user-first-name]`, `[wp-user-last-name]`, `[wp-user-login]`. They display the current user's first name, last name, and username/login, respectively.
* Added a new WP-CLI command: "reset-plugin-access". It changes the "who can access this plugin" setting to the default value. The default is either "Super Admin" (in Multisite) on "Anyone with the "manage_options" capability" (on regular sites). This is useful in situations where someone accidentally locks themselves out by deleting the only user who could access the plugin.

##### Fixed
* Fixed a PHP warning being thrown when the WPMU_PLUGIN_DIR constant is not a valid path or the full path cannot be determined.
* Fixed a potential PHP warning about an undefined constant when uninstalling the plugin.
* Fixed a rare PHP warning "invalid argument supplied for foreach() in menu-editor-core.php on line 4258" that was most likely caused by an unidentified plugin conflict.
* Fixed a few minor HTML validation errors.
* Fixed an inefficiency in the "select an icon from the media library" process that could make the browser use more memory than necessary.

##### Changed
* When a dashboard widget is only enabled for one or a few roles, it will no longer appear enabled in the "All" section. Instead, it will be shown in an "indeterminate" state (like menus that have some visible and some hidden items).
* The "Load default menu" and "Undo changes" buttons will now appear disabled when you select a role or a user. They will be enabled only when you select the "All" option. This is to indicate that these buttons affect all roles and users, not just the current selection.

= 2.7 (2018-10-01) =
##### Added
* Added new "Import" and "Export" tabs. Now you can import and export most of the plugin settings, not just the admin menu and widgets.

= 2.6.6 (2018-08-14) =
##### Fixed
* Fixed a bug where very long submenus wouldn't be scrollable if the current item was one that was moved to the current submenu from a different top level menu.
* Fixed an obscure bug where clicking on an item in the current submenu could cause the entire submenu to "jump" up or down.
* Fixed AME not highlighting the correct menu item when there was a space in any of the query parameter values.
* Fixed another bug where the plugin didn't highlight the correct item if it was the first item in a submenu and also a custom item.

= 2.6.5 (2018-05-25) =
##### Added
* Added a "Documentation" link below the plugin description. For people concerned about the recent GDPR legislation, the documentation now includes a page explaining [how the plugin processes personal data](https://adminmenueditor.com/documentation/data-processing-notes/).

##### Fixed
* Fixed a security issue where non-privileged users who were given access to a menu item that requires the "manage_options" capability would then also be able to access the special "All Settings" page (options.php).

= 2.6.4 (2018-05-03) =
##### Added
* Added an option to automatically hide new menu items from users who can't access the menu editor. You can find it in the "Settings" tab.
* Added an RSS feed widget. You can create new widgets in the "Dashboard Widgets" tab. 

##### Fixed
* Fixed a bug that made the settings UI treat certain users as if they had no roles.
* Fixed a potential crash that was caused by a bug in the "WP Editor" plugin version 1.2.6.3.
* Fixed some obsolete callback syntax that was still using "&$this".

##### Changed
* Made the "Attachment" section automatically show up in the "Meta Boxes" tab.
* Changed the order of some menu settings and added separators between groups of settings.
* Removed the "Screen Options" panel from AME tabs that didn't need it like "Plugins".

= 2.6.3 (2018-02-23) =
##### Fixed
* Fixed a bug where the "Dashboard Widgets" tab always used site-wide settings regardless of which scope the user had selected.

##### Changed
* Various technical improvements that are related to supporting add-ons like Toolbar Editor or the upcoming Branding add-on.

= 2.6.2 (2018-02-12) =
##### Added
* Added an `[ame-count-bubble]` shortcode. When used in a menu title, it takes the number bubble from the default menu title and displays it in the custom title. A "bubble" is the small number in a colored circle that appears on some menu items, like "Dashboard -> Updates (3)", "Comments (12)", or "WooCommerce -> Orders (5)".

##### Fixed
* Fixed a PHP 7.2 compatibility issue that caused this warning to show up on the menu editor page: "count(): Parameter must be an array or an object that implements Countable in menu-editor-core.php".
* Fixed a JavaScript error "cannot read property 'replace' of null" in the "Meta Boxes" tab that would show up if another plugin added a meta box without a valid title. 
* Fixed a JavaScript error "actor is null" when trying to save dashboard widget settings that contain user-specific permissions for nonexistent users. This typically happened when importing settings from another site.
* Fixed a conflict with Ultra WordPress Admin 7.4 that made it impossible to hide plugins.

##### Changed
* Tested with WP 4.9.4 and 5.0-alpha.

= 2.6.1 (2018-01-07) =
##### Added
* Added a list of basic tutorials to the menu editor screen.

##### Fixed
* Fixed a bug where menu items that use meta capabilities would have the "hidden from everyone" icon even if there were not actually hidden.
* Fixed incorrect version numbers in the "can't import settings from a newer version" error message.
* Fixed a bug that could cause some network admin menus to be highlighted as if they were new.
* Fixed a conflict with WP Courseware 4.1.2 where activating AME would cause a lot of extra menu items to show up unexpectedly.

##### Changed
* The plugin will display a warning if you try to hide the "Slug" metabox. Due to a known bug in WordPress, hiding this box can prevent you from changing the post permalink.
* Replaced the green puzzle-piece icon that was used to mark new items with an icon that just says "new".
* Tested with 4.9.1.

= 2.6 (2017-11-09) =
##### Fixed
* Added a workaround for a buggy "defer_parsing_of_js" code snippet that some users have added to their functions.php. This snippet produces invalid HTML code, which used to break the menu editor.
* Fixed a bug that caused WP-CLI to behave as if there were no installed plugins and no available updates.
* Fixed the "Tags" menu showing up as inaccessible.
* Fixed missing color labels in WordPress 4.9.
* Fixed a PHP warning that appeared when using this plugin together with WooCommerce or YITH WooCommerce Gift Cards and running PHP 7.1.
* Fixed a bug that prevented some capability changes from being applied.
* Fixed a bug where submenu items would not inherit FontAwesome icons from their parent menu.

##### Added
* You can now edit the network admin menu. The settings page is in the network admin, under "Settings -> Menu Editor Pro". Note that you still need to go to a site dashboard to edit the regular admin menu.
* The "Embed WP page" feature now works with private pages and posts, not just public pages.
* Added the ability to hide the special "Welcome" widget.

##### Changed
* Minor performance improvements.
* Tested with WP 4.8.3 and 4.9-RC2.

= 2.5.1 (2017-06-09) =
##### Fixed
* Fixed a WP-CLI compatibility issue where some CLI commands would fail with a fatal error: "Call to a member function check_current_user_access() on null".
* Fixed a couple of UI layout issues that affected RTL sites.
* Fixed a conflict with WP Nag Hide that prevented the "Open in": "New window" setting from working.
* Fixed an oversight that could cause a fatal error if a theme or plugin was using magic methods (i.e. __call and __callStatic) for hook callbacks.
* Fixed a CSS issue where, when changing the menu icon, certain Dashicons wouldn't properly replace the original icon.

= 2.5 (2017-06-05) =
##### Added
* You can edit plugin names and descriptions through the "Plugins" tab. This only changes how plugins are displayed on the "Plugins" page. It doesn't affect plugin files on disk.
* Added "Meta Boxes" tab. It lets you hide meta boxes and custom fields from roles and users. 
* Added an option to highlight new menu items. This feature is off by default. You can enable it in the "Settings" tab.
* Added an option to compress menu data that the plugin stores in the database. This significantly reduces the size of the relevant database entries, but it also adds some decompression overhead to every admin page load. You can enable this feature in the "Settings" tab.

##### Fixed
* Added a compatibility workaround for the Divi Training plugin. The hidden menu items that it adds to the "Dashboard" menu should no longer show up when you activate AME.
* Added a workaround that improves compatibility with plugins that set their menu icons using CSS. Previously, there were situations where users were not able to change menu icons because the plugin that created the menu item used CSS to override the normal icon. AME now adds an extra stylesheet that overrides icons with !important.
* Fixed an old bug where sorting menu items would put all separators at the top. Now they'll stay near their preceding menu item.
* Fixed incorrect shadows on custom screen options links.
* Fixed a couple of UI layout issues that were caused by bugs in other plugins.
* Fixed a rare issue where hiding the admin bar would leave behind empty space.

##### Changed
* Minor performance optimizations.
* When you use the "A-Z" button to sort top level menus, it also sorts submenu items. To avoid compatibility issues, the first item of each submenu stays in its original position.
* Automatically reset plugin access if the only allowed user no longer exists. This should cut down on the number of users who accidentally lock themselves out by setting "Who can access the plugin" to "Only the current user" and then later deleting that user account.

= 2.4.3 (2017-01-05) =
* Added a workaround for WooCommerce 2.6.8 to display the number of new orders in the "Orders" menu title.
* Tested with WP 4.7 and 4.8-alpha.

= 2.4.2 (2016-10-21) =
##### Added
* Added a way to completely hide the admin menu and the Toolbar (a.k.a Admin Bar) from specific roles or users.
* You can now copy menu permissions from one user to another with the "Copy permissions" toolbar button. Note that this button is hidden by default. To reveal it, click the rightmost button that looks like a double down arrow.

##### Fixed
* Fixed a bug where opening and closing the properties of a new menu item would set "extra capability" to "read".
* The "Welcome to All in One SEO Pack" menu item no longer shows up after activating the menu editor.
* Fixed an inconsequential "403 Forbidden" error in Chrome dev toools that was caused by Chrome trying and failing to load source maps for certain CSS & JS files.

= 2.4.1 (2016-08-29) =
* The plugin now remembers the last selected menu item and re-selects it after you save changes.
* Fixed a layout issue where menus with very long titles would appear incorrectly in the menu editor.
* When you change the menu title, the window title will also be changed to match it. You can still edit the window title separately if necessary.
* Moved the "Icon URL" field up and moved "Window title" down.

= 2.4 (2016-08-15) =
* Added Font Awesome icons.
* Added a "Users" tab that lets you hide individual users. Hidden users don't show up on the "All Users" page and they can't be edited or deleted by normal users. However, they can still show up in other parts of the WP admin (e.g. as post authors), and their content is not specially protected.
* Added more detailed permission error messages. You can turn them off in the "Settings" tab by changing "Error verbosity level" to "Low".
* Fixed the "Cancel" button in the "Select Visible Users" dialog not working when the "Plugins" tab is open.
* Tested up to WP 4.6.

= 2.3.1 (2016-06-16) =
* Allow the activation of license keys that no longer have access to updates. Previously activation could fail, which was confusing and made some users think that their key was invalid. Now "expired" keys can be properly activated (though they still don't get plugin updates).

= 2.3 (2016-06-11) =
* Added the "Plugins" tab. It lets you hide specific plugins from other users. Note that this only affects the list on the "Plugins" page and tasks like editing plugin files, but it doesn't affect the admin menu. If you hide a plugin, its menu items will still show up (assuming the user has the required permissions).
* Fixed the role/user list in the "Permissions" popup. Now it should show all selected users, not just the current user.
* Fixed wp-cli integration.

= 2.2.3 (2016-05-22) =
* Fixed a bug where selecting a role and then creating a new menu item could render most of the admin menu inaccessible to all other roles. This bug was introduced in version 2.2.2.

= 2.2.2 (2016-05-11) =
##### Fixed
* Fixed a bug that made menu items "jump" slightly to the left when you start to drag them.
* Fixed a bug that caused menu items with the required capability "administrator" to appear unchecked (= hidden) for the Administrator role. This also applies to other roles.
* Fixed a Multisite-specific bug where temporarily switching to another site using the switch_to_blog() function could result in the user having the wrong permissions.

##### Changed
* When saving settings, the plugin will compress the menu data before sending it to the server. This reduces the chances of exceeding request size limits that are imposed by some hosting companies.
* Tested up to WordPress 4.5.2.

= 2.2.1 (2016-04-16) =
##### Fixed
* Fixed a bug where typing in the search box in the "Choose Visible Users" dialog didn't do anything. Now it will actually search the list of registered users. This bug was introduced in version 2.2.
* Fixed a bug that prevented CPT permissions from working. This bug was also introduced in version 2.2.
* Fixed a backwards-compatibility bug related to the Ninja Forms plugin.
* Fixed a bug in the "Modern" editor theme that caused the "embed WordPress page" panel to appear underneath the selected menu item, making it near-impossible to see the panel.

##### Changed
* You can dismiss the "Settings saved" notification by clicking the "x" button.

= 2.2 =
##### Added
* Added a "Colors" button that lets you set the default color scheme for all admin menus at once. 
* Added a few more menu icons.
* Added basic support for the special "customize" and "delete_site" meta capabilities.

##### Fixed
* Fixed a "deprecated constructor" warning on sites running PHP 7.
* Fixed a bug that prevented menu items with an empty slug (i.e. no URL) from showing up.
* Fixed a bug where collapsing submenu properties would flag the "Icon URL" field as having a custom value even if you hadn't actually changed it.
* Fixed a rare WPML conflict that sometimes caused the admin menu to use a mix of different languages.
* Improved compatibility with buggy plugins and themes that throw JavaScript errors in their DOM-ready handlers.
* Renamed jquery.cookie.js to jquery.biscuit.js as a workaround for servers with overly aggressive ModSecurity configuration. Apparently, some servers block access to any URL that contains the text ".cookie".
* Added a compatibility workaround for the DW Question & Answer plugin. The hidden "Welcome", "Changelog" and "Credits" menu items should no longer show up when you activate AME.
* Added locking to reduce the risk of triggering a race condition when saving menu settings.

##### Changed
* Added tabs to the settings page: "Admin Menu" and "Settings". These tabs replace the heading buttons that were previously used to switch between the menu editor and general plugin settings.
* Tested up to WordPress 4.5-RC1.

= 2.1 =
##### Added
* Added a new editor colour scheme that makes the menu editor look more like other WordPress admin pages (e.g. Appearance -> Menus). You can enable it through the plugin settings page.
* New menu items that are added by other plugins will now show up in the same relative position as they would be in the default admin menu. Alternatively, they can be displayed at the bottom of the menu. You can configure this in plugin settings.

##### Fixed
* Fixed JavaScript error "_.empty is not a function".
* Improved compatibility with IP Geo Block.
* Fixed a layout issue where starting to drag one menu item would cause some other items to move around or change size very slightly. 
* Fixed a misleading tooltip. 

##### Changed
* Tested up to WordPress 4.4-RC1.
* Show an error message if an update fails due to license problems (expired key, key not activated, etc). Previously updates done through the "Dashboard -> Updates" pages would fail with a vague download error, and inline updates would hang at "Updating..." indefinitely.

= 2.0 =
##### Added
* Added "Choose users..." link that lets you select which users will show up in the menu editor. This makes it easier to change menu permissions for specific users.
* Added "Hide all submenu items when this item is hidden" setting. Normally, a top level menu stays visible as long as it has at least one accessible submenu item (that's just how WordPress works). You can use this setting to override that, forcing all submenu items to stay hidden if the user doesn't have access to the parent menu. Note that this can break plugins that rely on the default WordPress behaviour, so use with care.
* Added "Frame height" field. You can manually set the height of `<iframe>` elements that the plugin generates for menu items configured to open in a frame. Leave it empty to calculate the height automatically (the default).
* Improved support for custom post types. You can now change individual CPT permissions like "edit [content]", "edit other user's [content]", "delete private [content]", and so on without having to install additional plugins. To access these settings, select a menu item that points to a CPT and click the "Edit..." button next to the "Permissions" field.
* Added "Hide and prevent access" toolbar button. When the "All" option is selected in the list of roles, this button will hide the selected menu from everyone except the currently logged-in user (and Super Admin on Multisite). Basically, it's a shortcut for clicking on each role and unchecking the menu, then enabling the menu for your own account. When a specific role is selected, the button will just hide the selected menu from that role - the equivalent of toggling the menu checkbox.
* Added basic WP-CLI support. Run `wp help admin-menu-editor` for a list of available commands.
* Added "Apply to All" button to the color scheme screen. 
* You can save color presets.
* Added "Keep this menu open" checkbox. As the name implies, this setting keeps a top level menu expanded even if it is not the current menu.
* Added a way to embed a page or post in the WordPess admin. Set "Target page" to "Embed WP page", then pick a page from the "Embedded page ID" dropdown. You can also use the "Custom" tab to select a CPT item or to pick a page from another site of a Multisite network.
* You can define an `AME_LICENSE_KEY` constant in `wp-config.php` to set your license key. The plugin will attempt to automatically activate that key the next time an administrator visits the Dashboard. It will only try that *once* - if the key is incorerect or something else goes wrong, you'll have to activate your key manually.
* Added sort buttons to the top level menu toolbar. Click the down chevron button to display them.
* Added an arrow that points from the current submenu to the currently selected parent menu. This might help new users understand that the left column shows top level menus and the right column shows the corresponding submenu(s).

##### Fixed
* Role names no longer move around when you click them.
* No longer reserve space for submenu icons when the items with icons are not actually visible. 
* Fixed a rare bug where the menu editor would crash if one of the menu items had a `null` menu title. Technically, it's not valid to set the title to `null`, but it turns out that some plugins do that anyway.
* Fixed another rare bug where certain valid colors would be treated as "no color selected".
* Top level menus that have an empty title ("", an empty string) are no longer treated as separators.
* The "page heading" setting now works properly on pages that use `<h1>` headings.
* Made all text fields and dropdowns the same height. 
* All fields have consistent vertical margins.
* Fixed a number of layout bugs that could cause field labels to show up in the wrong place or get wrapped/broken in half when another plugin changed the default font or input size.
* Fixed a minor layout bug that caused the "expand menu properties" arrow to move down slightly when holding down the mouse button.
* Fixed a minor bug that could cause toolbar buttons to change size or position if another plugin happens to override the default link and image CSS.
* Added a workaround for plugins that create "Welcome", "What's New" or "Getting Started" menu items and then hide those items in a non-standard way. Now (some of) these items will no longer show up unnecessarily.
* Fixed a bug that mostly affected mobile devices and made it impossible to expand a top level menu when its "target page" was set to "None".
* Improved compatibility with buggy plugins that unintentionally corrupt the list of users' roles by misusing `array_shift`.
* Fixed a URL parsing bug that caused AME to mix up the "Customize", "Header" and "Background" menu items in some configurations.

##### Changed
* Increased minimum required WordPress version to 4.1.
* In the "Permissions" dialog, moved the "enabled" checkbox to the left side of the list of roles. 
* Un-deprecated the "Show/Hide" button, renamed it to "Hide without preventing access" and improved its compatibility with other features. Now it supports per-role settings. Changed the icon from a grey puzzle piece to a rectangle with a dashed border.
* Made the plugin more resilient to JavaScript crashes caused by other plugins.
* Use `<h1>` headings for admin pages in WordPress 4.2 and above.
* The plugin will remember the last few site URLs it has been activated on and automatically load the appropriate license details if the URL changes. This only works when all of the sites share the same WordPress database. 
* The default submenu icon now matches the parent icon.
* Changed the wording of a bunch of tooltips.
* Made the "delete" button appear disabled when the selected menu item can't be deleted.
* Moved the "new separator" button so that it's next to the "new menu" button.  
* Ran out of space in the toolbar, moved a few buttons to the second row. Click the new last button in the toolbar ("down chevron") to show or hide the second row.
* Changed the close icon of plugin dialogs to a plain white "X".
* Increased tooltip text size.
* Optimization: Removed emoji scripts from the menu editor page. WordPress emoji implementation can significantly slow down JavaScript-heavy plugins because it tracks all DOM changes via MutationObserver.
* Tested up to WordPress 4.4-beta3.

= 1.99 =
* Fixed a `TypeError: invalid 'in' operand a` error that caused compatibility issues with WordPress 4.3.
* Fixed a bug where the current menu item wouldn't get highlighted if its URL included %-encoded query parameters.
* Fixed a bug in menu URL generation that could cause problems when moving a plugin menu from "Posts", "Pages" or a CPT to another menu. The URL of the menu item got changed in a way that could break some plugins.
* Fixed a .htaccess compatiblility issue with with Apache 2.3+.
* Fixed a layout issue that caused the "reset to default" button for the "Color scheme" field to show up in the wrong place.
* Fixed an incorrect directory name in an error message.
* The "Links" menu will no longer show up in the editor unless explicitly enabled. As of WP 3.5, the "Links" menu still exists in WordPress core but is inaccessible because the Links Manager is disabled by default.
* Slightly improved menu item drag-and-drop. You can now drop top level menus anywhere in the submenu box. Previously you had to drop them in a specific, fixed spot. Also, when dragging a submenu item to the top level, a placeholder box will show up to indicate where you can drop the item.
* Tested with WordPress 4.3.

= 1.98 =
* Tested up to WordPress 4.2.
* Fixed a tab layout issue in the license information screen.

= 1.97 =
* Reduced export file size by about 50%. Slightly reduced database space usage.
* Trying to delete a non-custom menu item will now trigger a warning dialog that offers to hide the item instead. In general, it's impossible to permanently delete menus created by WordPress itself or other plugins (without editing their source code, that is).
* Improved the algorithm used to calculate iframe height. Now it's less likely that pages set to "Open in: Frame" will have an unnecessary scrollbar.
* Added a workaround for a bug in W3 Total Cache 0.9.4.1 that could cause menu permissions to stop working properly when the CDN or New Relic modules were activated.
* Fixed a plugin conflict where certain menu items didn't show up in the editor because the plugin that created them used a very low priority.
* Significantly improved sanitization of menu properties. 
* Renamed the "Choose Icon" button to "Media Library".
* Minor compatibility improvements.
* Tested up to WordPress 4.2-alpha.

= 1.96 =
* Added a "Copy permissions" button. It lets you copy all menu permissions from one role to another.
* Fixed a bug that allowed Administrators to bypass custom permissions for the "Appearance -> Customize" menu item.
* Fixed a regression in the menu highlighting algorithm.
* Fixed an "array to string conversion" notice caused by passing array data in the query string. 
* Fixed menu scrolling occasionally not working when the user moved an item from one menu to another, much larger menu (e.g. having 20+ submenu items).
* Fixed a bug where moving a submenu item from a plugin menu that doesn't have a hook callback (i.e. an unusable menu serving as a placeholder) to a different menu would corrupt the menu item URL.
* Added more detailed error reporting for situations when uploading a menu fails due to a server-side error.
* Switching between tabs in the "License" pop-up no longer requires JavaScript. In rare cases, JS-based tab switching could stop working due to bugs in other plugins.
* Fixed a few minor bugs in the "License" pop-up.

= 1.95 =
* The plugin now automatically removes consecutive submenu separators on display. This fixes an issue with multiple separators "bunching up" when the menus that they would usually separate are invisible due to role permissions.
* Fixed "Appearance -> Customize" always showing up as "new" and ignoring custom settings.
* Fixed a WooCommerce 2.2.1+ compatibility issue that caused a superfluous "WooCommerce -> WooCommerce" submenu item to show up. Normally this item is invisible.
* Fixed a bug where the plugin would fail to determine the current menu if the user tries to add a new item of a custom post type that doesn't have an "Add New" menu. Now it highlights the CPT parent menu instead.
* Fixed a very obscure bug where certain old versions of PHP would crash if another plugin created a menu item using an absolute file name as the slug while AME was active. The crash was due to a known bug in PHP and only affected Windows systems with open_basedir enabled.
* Added more debugging information for situations where the plugin can't save menu settings due to server configuration problems.
* Other minor fixes and optimizations.

= 1.94 =
* Added experimental support for submenu icons. By default, submenu items start out with no icons. You can manually add an icon to each item, configure the plugin to show default icons for all items, or turn off this feature entirely.
* Added two new shortcodes: [wp-logout-url] and [wp-user-display-name].
* Added a "Logout" option to the "Target page" dropdown.
* Added a special target page option: "< None >". It makes the selected menu item unclickable.
* Added a "permissions modified" status icon. It will appear on menu items that have custom permissions for the selected role or user.
* Added a new menu editor colour scheme that's similar to the default WordPress admin colour scheme. Click the "Settings" button next to the menu editor page title to switch colour schemes.
* Added a warning that explains that non-custom menus can't be deleted and should be hidden instead. Previously the plugin would let the user delete a menu, then automatically restore it on the next page reload.
* Fixed strange boxes showing up in the icon selector in Internet Explorer.
* Fixed duplicate top level menus mysteriously disappearing. Now the plugin will properly warn the user that all top level menus must have unique URLs.
* Fixed an obscure bug where changing the "Target page" from the default setting to "Custom" and back would occasionally make some menu properties suddenly show up as modified for no apparent reason.
* Fixed submenu separators being invisible in WP 4.0-beta.
* Fixed incorrect submenu item height and margins in WP 4.0-beta.
* Fixed a minor layout bug where items with no title would be smaller than other items.
* Fixed combo-box dropdown button height for WP 3.9.x.
* Fixed the selected_actor query parameter not being URL-encoded. 
* Added a workaround for a bug in WordPress Mu Domain Mapping 0.5.4.3.
* Added a workaround for the very unusual situation where the "user_has_cap" filter is called without a capability.
* Fixed duplicates of bbPress menu items showing up.
* Increased import file size limit from 512 KB to 5 MB.
* Added better error reporting for failed imports.
* Changed the default custom menu icon to the generic "cogwheel" icon from Dashicons.
* Other small UI changes.
* Raised minimum requirements to WordPress 3.8 or later. This is mainly due to the increased reliance on Dashicons as menu icons.

= 1.93 =
* Added a large number of menu icons based on the Dashicons icon font.
* Added a way to change the color scheme of individual top level menus.
* Added a "page heading" menu setting. 
* Fixed default menu icons not showing up in WP 3.9.
* Fixed a rare "$link.attr(...) is undefined" JS error.
* Fixed a bug where a hidden submenu page with a URL like "options-general.php?page=something" would still be accessible via "admin.php?page=something".
* Update notifications now work even when the plugin installed in `mu-plugins`. However, updates still need to be uploaded manually because WordPress does not support automatic updates for MU plugins.
* Tested with WordPress 3.9-beta3.
* Increased minimum requirements to WordPress 3.5 or later.

= 1.92 =
* Tested with WordPress 3.8.
* Fixed several minor UI/layout issues related to the new 3.8 admin style.
* Fixed a bug where moving an item to a plugin menu and then deactivating that plugin would cause the moved item to disappear.
* Fixed deleted submenus not being restored if their original parent menu is no longer available.
* Fixed a rare glitch where submenu separators added by certain other plugins would sometimes disappear.
* Fixed a conflict with Shopp 1.2.9.
* Fixed: Opening the "Permissions" window for a menu item that does not have an extra capability and then clicking "Save Changes" in the window and the main editor screen (without making any changes) no longer adds a new extra capability equal to the required capability. 
* Made the plugin treat "users.php" and "profile.php" as the same parent menu. This fixes situations where it would be impossible to hide a "Users" submenu item from roles that don't have access to the "Users" menu and instead get a "Profile" menu.
* Added extra logging for situations where a menu item is hidden because a higher-priority item with the same URL is also hidden. 
* Further performance improvements.

= 1.91 =
* Added a number of small performance optimizations.
* Added an admin notice that explains how to get to the menu editor page.
* The plugin will now display an "are you sure?" dialog if you try to hide "Dashboard -> Home". Hiding this menu item can prevent people from logging in unless you also change what page they are redirected to upon login.
* Deprecated the "Show/Hide" toolbar button. Use role permissions or custom capabilities instead. You can still enable the button through the settings page if you need it.
* Fixed: Opening a modal dialog like "Permissions" or "Import menu" will now properly grey out the rest of the page.
* Tested with WordPress 3.7.1 and 3.8-alpha.

= 1.90 =
* Added a new settings page that lets you choose whether admin menu settings are per-site or network-wide, as well as specify who can access the plugin. To access this page, go to "Settings -> Menu Editor Pro" and click the small "Settings" link next to the page title.
* Added an option to hide the plugin from the list of installed plugins.
* Added a way to show/hide advanced menu options through the settings page in addition to the "Screen Options" panel.
* Added a "Show menu access checks" option to make debugging menu permissions easier.
* Added a "toggle all menus for the selected role" button.
* Added partial WPML support. Now you can translate custom menu titles with WPML.
* Replaced a number of toolbar icons with GPL-compatible alternatives so that they're consistent with the free version.
* The plugin will now display an error if you try to activate it when another version of it is already active.
* Fixed a bug where the capability drop-down list would briefly show up and then immediately disappear.

= 1.83 =
* Fixed an "invalid argument" warning caused by the plugin sometimes failing to retrieve a user's roles.
* Fixed a rare issue where the user would be unable to make a sub-menu item visible if a top-level menu with the same URL was hidden (e.g. hide "Posts", show "Posts -> All Posts" in another menu). Creating duplicate menus is still not recommended.
* Fixed a conflict with some CSS/JS auto-versioning schemes that could prevent the user from saving the menu.

= 1.82 =
* Added an "Upgrade or renew license" link to the license information screen.
* Added license expiration date (if applicable) to the license information screen.
* Fixed plugin updates not showing up in ManageWP.
* Fixed a rare bug where the icon selector would appear at the bottom of the page instead of next to the icon button.
* Fixed incorrect icon alignment and checkboxes showing up in the wrong place when using the MP6 admin UI.
* Removed "site token" from the license information screen.

= 1.81 =
* Added a workaround for a bug in BackupBuddy that would cause Admin Menu Editor Pro to check for updates much more often than it's supposed to.

= 1.80 =
* Added an icon drop-down that lets you pick one of the default WordPress menu icons or upload your own through the media library.
* The editor will now automatically re-select the last selected role after saving a menu.
* You can hold Ctrl while moving a menu item from a submenu to a top-level menu (or vice versa) to copy instead of moving.
* Changed the way permission checkboxes are displayed for menus that have some visible and some hidden items.
* Switched to a different, more subtle submenu separator style.
* Moved the "Icon URL" field below the "CSS classes" field.
* Made the "broken editor permissions" error message more helpful.
* Fixed menus with custom icons sometimes displaying two overlapping icons.
* Fixed the "Window title" setting not working for anything except custom menus.
* Fixed a hard-to-identify bug where one user would get "$(...).ajaxForm is not a function" errors all the time.
* Fixed compatibility issues with Ultimate TinyMCE.

= 1.70 =
* Added the ability to create menu separators in submenus.
* Fixed a bug where the height of the IFrame element generated for menus set to display in a frame would be limited to 300px (WP 3.5).
* Fixed a rare "call to a member function function get_virtual_caps() on a non-object" error.
* Fixed a couple of layout glitches that would make the editor sidebar display incorrectly in WP 3.5.
* Fixed: Ensure that the correct menu item gets highlighted when adding a new item of a custom post type, even if there's no "Add New {Thing}" entry in the admin menu. This fixes the bug where clicking the "Add New" button in WooCommerce -> Coupons would highlight the Posts -> Add New menu.
* Other minor fixes.
* Tested on WordPress 3.5 (RC6).

= 1.60 =
* Added a number of small optimizations. On some systems menu output time is reduced by almost 30%.
* Fixed a couple of PHP warnings caused by a bug in the custom update checker.
* Fixed a conflict with plugins that use an old version of the custom update checker.
* Changed how the plugin treats situations where multiple menu items have the same URL. Now either all of them will show up in the final menu or none will. However, it's still best to keep your menu free from duplicate items to avoid ambiguity.

= 1.50 =
* Added support for fully automatic plugin upgrades. You will need to enter a license key to enable this feature.
* Existing users will get a license key when they download this version. New users will get one in their purchase confirmation email.
* Added a "Check for updates" link to the plugin's entry in the "Plugins" page.

= 1.40 =
* Added a new way to view and set per-role permissions. There's now a role selector at the top of the menu editor page, and selecting a role grays out any menu items that role can't access. You can also change menu permissions for the currently selected role via checkboxes in menu titles.
* Added a warning to menus that are only accessible by a specific role due to hardcoded restrictions in the plugin(s) responsible for those menus.
* Added a warning if saving the menu would make it impossible for the current user to access the menu editor. This doesn't catch 100% of configuration problems, but should help.
* Re-added the ability to reset permissions to default. 
* Fixed incompatibility with Ozh's Admin Drop Down Menu.
* Fixed: Going to http://example.com/wp-admin/ now expands and highlights Dashboard -> Home as the current menu. Previously it didn't highlight any menus for that URL.
* Fixed: Newly created items sometimes didn't reflect the actual menu settings. This was a problem when loading the default menu or undoing changes.
* Fixed: When displaying a menu, remove all duplicate separators, not just every other one.
* Fixed: Only consider Super Admins when in multisite mode. On single-install sites, they're just identical to normal administrators.
* Fixed: If the currently open menu page has been moved to a different parent, clicking it's original parent menu would briefly flash the submenu as if it was currently active.
* Make the capability that's required to use the plugin user-configurable (via the "admin_menu_editor_capability" filter).
* When adding a new menu, sub-menu or separator, insert them after the currently selected item instead of at the end of the list.
* Only display the survey notice on the editor page, and let the user disable it by adding a `never-display-surveys.txt` file to the plugin folder.

= 1.30 =
* Added a new user survey. The link only shows up after you've used the plugin for a few days.
* Fixed a bug where menus that by default require a specific role (e.g. "administrator") and not a normal capability would become inaccessible immediately after activating the plugin.
* Fixed the wrong menu to being expanded/highlighted sometimes.
* Fixed a deprecated function notice.
* Fixed compatibility issues with sites using SSL.
* Fixed a number of complex bugs related to moved menu items.

= 1.20 =
* Revamped the permissions interface. Now you can directly select the roles that should or shouldn't be able to access a menu instead of trying to find a capability that matches your requirements. You can still set the capability if you want.
* Added a "Target page" field that lets you select what page a menu item should link to. This is less error-prone than entering the URL manually. Of course, if you *do* want to use custom URL, you can select "Custom" and type in your own URL.
* Fixed a bug that would cause certain menu items to not be highlighted properly if they've been moved to a different top-level menu.
* Made it possible to move a sub-menu item to the top level and vice versa. Drag the item in question to the very end of the (sub-)menu and drop it on the yellow rectangle that will appear.
* You can now cut & paste menu items between the sub-menus and the top level menu.
* You can now use "not:capability", "capability1,capability2", "capability1+capability2" and other advanced syntax in the capability field.
* Added a new menu export format. Old exported menus should still work.
* Items that point to missing menu pages (e.g. a page belonging to a plugin that has been deactivated) are automatically removed. Previously, they would still show up in the editor until you removed them manually.
* All custom menus now use the same set of defaults.
* Custom menus (i.e. menus with custom URLs) can no longer use defaults associated with default menus.

= 1.16 =
* Removed the "Feedback" button. You can still provide feedback via email or the contact form, of course.

= 1.15 =
* Fixed a PHP warning when no custom menu exists (yet).

= 1.14 =
* Fixed menu export not working when WP_DEBUG is set to True.
* Fixed the updater's cron hook not being removed when the plugin is deactivated.
* Fixed updates not showing up in some situations.
* Fixed the "Feedback" button not responding to mouse clicks in some browsers.
* Enforce the custom menu order by using the 'menu_order' filter. Fixes Jetpack menu not staying put.
* Fixed "Feedback" button style to be consistent with other WP screen meta buttons.
* You can now copy/paste as many menu separators as you like without worrying about some of them mysteriously disappearing on save.
* Fixed a long-standing copying related bug where copied menus would all still refer to the same JS object instance.
* Added ALT attributes to the toolbar icon images.
* Removed the "Custom" checkbox. In retrospect, all it did was confuse people.
* Made it impossible to edit separator properties.
* Removed the deprecated "level_X" capabilities from the "Required capability" dropdown. You can still type them in manually if you want.

= 1.13 =
* Tested for WordPress 3.2 compatibility.

= 1.12 =
* Fixed a "failed to decode input" error that would show up when saving the menu.

= 1.11 = 
* WordPress 3.1.3 compatibility. Should also be compatible with the upcoming 3.2.
* Fixed spurious slashes sometimes showing up in menus.
* Fixed a fatal error concerning "Services_JSON".

= 1.1 = 
* WordPress 3.1 compatibility.
* Added the ability to drag & drop a menu item to a different menu.
* You can now set the "Required capability" to a username. Syntax: "user:john_smith".
* Added a drop-down list of Dashboard pages to the "File" box.
* When the menu editor is opened, the first top-level menu is now automatically selected and it's submenu displayed. Hopefully, this will make the UI slightly easier to understand for first-time users.
* All corners rounded on the "expand" link when not expanded.
* By popular request, the "Menu Editor" menu entry can be hidden again.

== Upgrade Notice ==

= 1.94 =
Added support for submenu icons, two new menu URL options ("Logout" and "None"), better error reporting, and a new menu editor colour scheme. Fixed several UI layout bugs, plugin conflicts and issues with importing large JSON files.