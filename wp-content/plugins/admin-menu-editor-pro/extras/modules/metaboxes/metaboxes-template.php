<?php
/**
 * Variables set by ameModule when it outputs a template.
 *
 * @var string $moduleTabUrl
 */

$saveFormAction = add_query_arg(array('noheader' => '1'), $moduleTabUrl);
?>

<div id="ame-meta-box-editor" data-bind="visible: true" style="display: none">
	<div class="ame-sticky-top-bar">
		<div class="ame-sticky-top-bar-flex-content">
			<?php require AME_ROOT_DIR . '/modules/actor-selector/actor-selector-template.php'; ?>
		</div>
	</div>
	<div class="clear"></div>

	<div data-bind="foreach: screens" id="ame-mb-screen-list">
		<h3>
			<span data-bind="text: formattedTitle, attr: {title: 'Screen ID: ' + screenId}"></span>
			<a href="#" data-bind="visible: isContentTypeMissing, click: $parent.deleteScreen.bind($parent)"
			   class="ame-mb-delete-section"
			   title="Delete settings for a post type or taxonomy that no longer exists">
				<span class="dashicons dashicons-trash"></span>
			</a>
		</h3>

		<table class="widefat striped ame-meta-box-list">
			<thead>
			<tr>
				<th class="ame-mb-check-column"></th>
				<th>Title</th>
				<th>ID</th>
				<th>Visible by default</th>
				<th data-bind="visible: $root.canAnyBoxesBeDeleted"></th>
			</tr>
			</thead>
			<tbody data-bind="foreach: boxes">
			<tr>
				<th class="ame-mb-check-column" scope="row">
					<input type="checkbox" title="Uncheck to hide this meta box from the selected role or user"
					       data-bind="checked: isAvailable, attr: {id: uniqueHtmlId + '-isAvailable'}">
				</th>
				<td>
					<label data-bind="text: safeTitle, attr: {for: uniqueHtmlId + '-isAvailable'}"></label>
					<!-- ko if: tooltipText -->
					<a class="ws_tooltip_trigger" title=""
					   data-bind="attr: {title: tooltipText}"
					>
						<div class="dashicons dashicons-info"></div>
					</a>
					<!-- /ko -->
				</td>
				<td data-bind="text: id"></td>
				<td class="ame-mb-default-visibility-column">
					<input type="checkbox"
					       title="Checked: the box is visible by default.&#10;Unchecked: the box starts out hidden, but it can be enabled through Screen Options."
					       data-bind="checked: isVisibleByDefault, enable: canChangeDefaultVisibility">
				</td>
				<td data-bind="visible: $root.canAnyBoxesBeDeleted">
					<a href="#" data-bind="if: canBeDeleted, click: $parent.deleteBox.bind($parent)"
					   title="Delete settings for a meta box that no longer exists">Delete</a>
				</td>
			</tr>
			</tbody>
		</table>
	</div>
	<div id="ame-mb-action-container">

		<div id="ame-mb-main-actions">
			<form method="post" data-bind="submit: saveChanges" class="ame-mb-save-form"
			      action="<?php echo esc_url($saveFormAction); ?>">

				<?php submit_button('Save Changes', 'primary', 'submit', false); ?>

				<input type="hidden" name="action" value="ame_save_meta_boxes">
				<?php wp_nonce_field('ame_save_meta_boxes'); ?>

				<input type="hidden" name="settings" value="" data-bind="value: settingsData">
				<input type="hidden" name="selected_actor" value=""
				       data-bind="value: selectedActor() ? selectedActor().id : ''">
			</form>

			<?php
			submit_button(
				'Refresh...',
				'secondary',
				'ame-refresh-meta-boxes',
				false,
				array(
					'data-bind' => 'click: promptForRefresh',
				)
			);
			?>
		</div>

	</div>


</div>