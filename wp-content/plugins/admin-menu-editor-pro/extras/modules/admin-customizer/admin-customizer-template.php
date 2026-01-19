<?php
/**
 * These variables should be provided by the module.
 *
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting[] $settings
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure $structure
 * @var AcChangeset $currentChangeset
 * @var string $returnUrl
 */

use YahnisElsts\AdminMenuEditor\AdminCustomizer\AcChangeset;

//Hide the Toolbar (a.k.a  Admin Bar). Using show_admin_bar(false) would not work
//because WordPress ignores it when is_admin() is true and shows the bar anyway.
if ( !defined('IFRAME_REQUEST') ) {
	define('IFRAME_REQUEST', true);
}

header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));

//This template uses some internal WP core functions to generate a bare-bones
//admin page without most of the regular WP admin UI. It won't work if the functions
//are not available.
$requiredFunctions = [
	'_wp_admin_html_begin',
	'print_head_scripts',
	'print_admin_styles',
	'_wp_footer_scripts',
];
foreach ($requiredFunctions as $functionName) {
	if ( !function_exists($functionName) ) {
		wp_die(sprintf(
			'Error: This feature is not compatible with your WordPress version.' .
			' The required function <code>%s</code> is not defined.',
			esc_html($functionName)
		));
	}
}

wp_user_settings();
_wp_admin_html_begin();

$bodyClasses = ['wp-core-ui'];
if ( is_rtl() ) {
	$bodyClasses[] = ' rtl';
}
$bodyClasses[] = ' locale-' . sanitize_html_class(strtolower(str_replace('_', '-', get_user_locale())));

$pageTitle = apply_filters('admin_menu_editor-ac_page_title', 'Admin Customizer');
?>
	<title><?php echo esc_html($pageTitle); ?></title>
<?php

print_head_scripts();
print_admin_styles();

do_action('admin_menu_editor-ac_head');

echo '</head>';

if ( empty($currentChangeset) ) {
	wp_die('Changeset not defined');
}

$defaultButtonsEnabled = apply_filters('admin_menu_editor-ac_default_buttons_enabled', true);

?>
	<body class="<?php echo esc_attr(implode(' ', $bodyClasses)); ?>">

	<div id="ame-ac-admin-customizer">
		<div id="ame-ac-sidebar">
			<div id="ame-ac-primary-actions">
				<?php do_action('admin_menu_editor-ac_action_bar_start'); ?>
				<?php if ( $defaultButtonsEnabled ): ?>
					<a id="ame-ac-exit-admin-customizer"
					   href="<?php echo esc_url($returnUrl); ?>"
					   data-bind="click: confirmExit"><span class="screen-reader-text">Close</span></a>
				<?php endif; ?>

				<span class="spinner"></span>

				<?php do_action('admin_menu_editor-ac_primary_actions'); ?>
				<?php if ( $defaultButtonsEnabled ): ?>
					<div id="ame-ac-save-button-wrapper">
						<?php
						submit_button(
							'Save Changes',
							'primary',
							'apply-changes',
							false,
							[
								'id'                  => 'ame-ac-apply-changes',
								'data-default-text'   => 'Save Changes',
								'data-published-text' => 'Saved',
								'disabled'            => 'disabled',
								//Disabled by default, enabled when changes are detected.
							]
						);

						echo '<button id="ame-ac-extra-actions-trigger"
					        class="button button-primary dashicons dashicons-admin-generic"
					        disabled
					        data-bind="click: toggleExtraActionMenu.bind($root), enable: true"></button>'
						?>
					</div>

					<ul id="ame-ac-extra-actions-menu" class="ame-ac-menu" style="display: none">
						<li class="ame-ac-menu-item ame-ac-download-theme-action"
						    data-bind="click: actionOpenDownloadDialog.bind($root)">
							<div>Download as admin theme...</div>
						</li>
						<li class="ame-ac-menu-item ame-ac-import-theme-action"
						    data-bind="click: actionOpenImportDialog.bind($root)">
							<div>
								<label for="ame-ac-import-admin-theme-file">Import settings from admin theme...</label>
							</div>
						</li>
						<li class="ame-ac-menu-item ame-ac-menu-separator">
							<div></div>
						</li>
						<li class="ame-ac-menu-item ame-ac-menu-item-delete ame-ac-discard-changes-action"
						    data-bind="click: actionDiscardChanges.bind($root)">
							<div>
								<span class="dashicons dashicons-trash"></span>
								Discard changes
							</div>
						</li>
					</ul>
					<input type="file" id="ame-ac-import-admin-theme-file" class="ame-ac-visually-hidden"
					       data-bind="event: {'change': handleImportFileSelection.bind($root)}">
				<?php endif; ?>
			</div>
			<div id="ame-ac-sidebar-info">
				<div id="ame-ac-global-notification-area"></div>
				<!-- ko if: isImportReportVisible() && lastImportReport() -->
				<div id="ame-ac-latest-import-report" class="notice is-dismissible" style="display: none"
				     data-bind="visible: true, css: {
				        'notice-success': (lastImportReport().importedSettings > 0),
				        'notice-warning':  (lastImportReport().importedSettings < 1)
				     }">
					<p>
						Imported settings from <span data-bind="text: lastImportReport().fileName"></span>
						(<em data-bind="text: lastImportReport().pluginName"></em>):
					</p>
					<table>
						<tbody>
						<tr>
							<th>Valid</th>
							<td data-bind="text: lastImportReport().importedSettings"></td>
						</tr>
						<tr>
							<th>Changed</th>
							<td data-bind="text: lastImportReport().differentImportedSettings"></td>
						</tr>
						<tr>
							<th>Invalid</th>
							<td data-bind="text: lastImportReport().invalidSettings"></td>
						</tr>
						<tr>
							<th>Skipped</th>
							<td data-bind="text: lastImportReport().skippedSettings"></td>
						</tr>
						</tbody>
					</table>
					<button type="button" class="notice-dismiss" data-bind="click: dismissImportReport.bind($root)">
						<span class="screen-reader-text">Dismiss this notice</span>
					</button>
				</div>
				<!-- /ko -->
			</div>
			<div id="ame-ac-sidebar-content">
				<div id="ame-ac-container-collection"
				     data-bind="component: {
				        name: 'ame-ac-structure',
				        params: {structure: interfaceStructure, breadcrumbs: sectionNavigation.breadcrumbs}}">
				</div>
				<div id="ame-ac-sidebar-blocker-overlay"></div>
			</div>
			<?php do_action('admin_menu_editor-ac_sidebar_footer'); ?>
		</div>
		<div id="ame-ac-preview-container">
			<iframe id="ame-ac-preview" name="ame-ac-preview-frame" src="about:blank">
				Preview
			</iframe>
			<div id="ame-ac-preview-refresh-indicator" title="Refreshing the preview...">
				<div id="ame-ac-refresh-spinner"></div>
			</div>
		</div>
		<div id="ame-ac-general-screen-overlay" class="ui-widget-overlay ui-front"
		     data-bind="visible: isGeneralOverlayVisible">
			<div class="ame-ac-spinner-container">
				<div class="ame-ac-general-progress-spinner"></div>
			</div>
		</div>
		<div style="display: none">
			<div id="ame-ac-download-theme-dialog"
			     data-bind="ameDialog: downloadThemeDialog, ameEnableDialogButton: downloadThemeDialog.isConfirmButtonEnabled"
			     title="Generate admin theme"
			     style="display: none;" class="ame-ac-dialog">

				<div class="ame-ac-dialog-help" data-bind="visible: downloadThemeDialog.helpContainerVisible">
					<div data-bind="visible: downloadThemeDialog.helpVisible">
						This feature generates an
						<abbr title="An admin theme is a plugin that changes the appearance of the admin dashboard">admin
							theme</abbr> from the current settings.
						<span data-bind="text: downloadThemeDialog.adminThemeTexts.standalonePluginNote"></span>
						The plugin:
						<ul>
							<li>Includes visual customizations: colors, fonts, borders, etc.</li>
							<li>Doesn't include role settings, menu properties, custom widgets, etc.</li>
							<li>Is not configurable. Once generated, the admin CSS is fixed.</li>
						</ul>
					</div>

					<a href="#" class="ame-ac-more-toggle"
					   data-bind="click: downloadThemeDialog.toggleHelp.bind(downloadThemeDialog),
								  text: downloadThemeDialog.helpToggleLabel,
								  css: {
								    'ame-ac-more-toggle-active': downloadThemeDialog.helpVisible,
								    'ame-ac-more-toggle-inactive': !(downloadThemeDialog.helpVisible())
								  }">
						About this feature
					</a>
				</div>

				<form data-bind="submit: downloadThemeDialog.onSubmit.bind(downloadThemeDialog)">
					<fieldset data-bind="enable: downloadThemeDialog.areFieldsEditable">
						<div class="ame-ac-dialog-row">
							<label>
								<span class="ame-ac-dialog-label">Name</span>
								<input type="text" data-bind="value: downloadThemeDialog.meta().pluginName,
								ameObservableValidity: downloadThemeDialog.meta().pluginName">
							</label>
						</div>
						<div class="ame-ac-dialog-row">
							<label>
								<span class="ame-ac-dialog-label">Description</span>
								<textarea data-bind="value: downloadThemeDialog.meta().shortDescription,
								ameObservableValidity: downloadThemeDialog.meta().shortDescription"></textarea>
							</label>
						</div>

						<div class="ame-ac-advanced-theme-options"
						     data-bind="visible: downloadThemeDialog.advancedOptionsVisible">
							<div class="ame-ac-dialog-row">
								<label>
									<span class="ame-ac-dialog-label">Plugin URL</span>
									<input type="text" data-bind="value: downloadThemeDialog.meta().pluginUrl,
									ameObservableValidity: downloadThemeDialog.meta().pluginUrl">
								</label>
							</div>
							<div class="ame-ac-dialog-row">
								<label>
									<span class="ame-ac-dialog-label">Author</span>
									<input type="text" data-bind="value: downloadThemeDialog.meta().authorName,
									ameObservableValidity: downloadThemeDialog.meta().authorName">
								</label>
							</div>
							<div class="ame-ac-dialog-row">
								<label>
									<span class="ame-ac-dialog-label">Slug</span>
									<input type="text" data-bind="value: downloadThemeDialog.meta().pluginSlug,
									ameObservableValidity: downloadThemeDialog.meta().pluginSlug">
								</label>
							</div>
							<div class="ame-ac-dialog-row">
								<label>
									<span class="ame-ac-dialog-label">Version</span>
									<input type="text" data-bind="value: downloadThemeDialog.meta().pluginVersion,
									ameObservableValidity: downloadThemeDialog.meta().pluginVersion">
								</label>
							</div>
						</div>

						<a href="#" class="ame-ac-more-toggle"
						   data-bind="click: downloadThemeDialog.toggleAdvancedOptions.bind(downloadThemeDialog),
								  text: downloadThemeDialog.advancedOptionsToggleLabel,
								  css: {'ame-ac-more-toggle-active': downloadThemeDialog.advancedOptionsVisible}">
							More options
						</a>

						<!-- A hidden submit button is needed for the Enter key to work -->
						<input type="submit" style="display: none">
					</fieldset>
				</form>

				<div style="display: none">
					<!-- This hidden form is used to initiate a download. -->
					<form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
					      id="ame-ac-theme-download-request-form" target="ame-ac-theme-download-frame">
						<input type="hidden" name="action" value="ws_ame_ac_create_admin_theme">
						<input type="hidden" name="_wpnonce" value="<?php
						echo esc_attr(wp_create_nonce('ws_ame_ac_create_admin_theme'));
						?>">
						<input type="hidden" name="changeset" data-bind="value: downloadThemeDialog.changesetName">
						<input type="hidden" name="metadata" data-bind="value: downloadThemeDialog.metadataJson">
						<input type="hidden" name="downloadCookieName"
						       data-bind="value: downloadThemeDialog.downloadCookieName">
					</form>
					<iframe src="about:blank" name="ame-ac-theme-download-frame"
					        id="ame-ac-theme-download-frame"></iframe>
				</div>

				<div id="ame-ac-download-progress-overlay"
				     data-bind="visible: downloadThemeDialog.isOperationInProgress">
					<div class="ame-ac-spinner-container">
						<div class="ame-ac-general-progress-spinner"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php
	do_action('admin_menu_editor-admin_customizer_footer');

	//Some WordPress components like the visual editor want to load their scripts
	//and styles in the admin footer. Special-casing each dependency is too complex
	//and prone to bugs, so we'll just trigger the footer hooks instead.

	//In case some plugin developer tries to add content to all admin pages,
	//we'll also wrap all output in a hidden element.
	echo '<div id="ame-ac-hidden-footer-content" style="display:none;">';

	do_action('admin_footer', '');

	if ( !empty($GLOBALS['hook_suffix']) ) {
		do_action('admin_print_footer_scripts-' . $GLOBALS['hook_suffix']);
	}
	do_action('admin_print_footer_scripts');

	echo '</div>';
	?>

	<div id="ame-ac-templates" style="display:none">
		<div id="ame-ac-validation-error-list-template">
			<ul class="ame-ac-validation-errors" data-bind="foreach: $root">
				<li class="notice notice-error ame-ac-validation-error">
					<span data-bind="text: message, attr: {title: code}"></span>
				</li>
			</ul>
		</div>
	</div>
	</body>
<?php
echo '</html>';