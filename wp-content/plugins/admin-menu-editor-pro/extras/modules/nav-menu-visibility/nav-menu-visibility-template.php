<?php

use YahnisElsts\AdminMenuEditor\NavMenuVisibility\NavMenuModule;

/**
 * @var string $moduleTabUrl Full URL of the settings tab. Provided by the base module class.
 */

?>
<?php require AME_ROOT_DIR . '/modules/actor-selector/actor-selector-template.php'; ?>
	<div class="clear"></div>

	<div id="ame-nav-menu-visibility-editor" style="display: none" data-bind="visible: true">
		<div id="ame-nv-no-menus-notices" data-bind="if: (navigationMenus.length < 1)">
			<p>No non-empty navigation menus found.</p>
		</div>

		<div id="ame-nv-navigation-menu-list" data-bind="foreach: navigationMenus">
			<div class="ws-ame-postbox ame-nv-navigation-menu">
				<div class="ws-ame-postbox-header">
					<h3 class="ame-nv-navigation-menu-title" data-bind="text: label"></h3>
				</div>
				<div class="ws-ame-postbox-content"
				     data-bind="template: { name: 'ame-nv-nav-item-template', foreach: items }"></div>
			</div>
		</div>

		<form data-bind="submit: saveChanges, if: (navigationMenus.length > 0)" method="post" action="<?php
		echo esc_url(add_query_arg(['noheader' => '1'], $moduleTabUrl));
		?>">
			<input type="hidden" name="settings" value="" data-bind="value: settingsToSave">
			<input type="hidden" name="selectedActor" value="" data-bind="value: selectedActorId">

			<?php
			printf(
				'<input type="hidden" name="action" value="%s">',
				esc_attr(NavMenuModule::SAVE_SETTINGS_ACTION)
			);
			wp_nonce_field(NavMenuModule::SAVE_SETTINGS_ACTION);

			submit_button(
				'Save Changes',
				'primary',
				'ame-nv-save-settings',
				true,
				[
					'data-bind' => 'disable: isSaving()',
					'disabled'  => 'disabled',
				]
			);
			?>
		</form>
	</div>

	<template id="ame-nv-nav-item-template">
		<div class="ame-nv-nav-item" data-bind="css: {'ame-nv-hidden': isHidden, 'ame-nv-locked': isLocked}">
			<div class="ame-nv-nav-item-title">
				<label data-bind="attr: {title: isLocked() ? 'Locked because it is disabled for all logged-in users' : ''}">
					<input type="checkbox" data-bind="checked: isChecked, disable: isLocked,
						css: {'ame-nv-locked-checkbox': isLocked()}"/>
					<span class="ame-nv-nav-item-title-text" data-bind="text: label"></span>
				</label>
			</div>
			<div class="ame-nv-nav-item-children"
			     data-bind="template: { name: 'ame-nv-nav-item-template', foreach: children }"></div>
		</div>
	</template>
<?php