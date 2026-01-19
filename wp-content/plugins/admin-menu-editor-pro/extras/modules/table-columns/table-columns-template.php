<?php
$dragIconUrl = plugins_url('modules/redirector/drag-indicator.svg', AME_ROOT_DIR . '/placeholder');
?>
<div id="ame-tc-settings-page-container" style="display: none;" data-bind="visible: true">
	<div class="ame-sticky-top-bar">
		<div class="ame-tc-top-bar-content">
			<?php require AME_ROOT_DIR . '/modules/actor-selector/actor-selector-template.php'; ?>
		</div>
	</div>
	<div class="clear"></div>

	<div id="ame-tc-settings-form-wrapper">
		<div data-bind="foreach: screens" class="ame-txt-screen-list">
			<div class="ws-ame-postbox ame-tc-screen" data-bind="css: { 'ws-ame-closed-postbox': !isOpen() }">
				<div class="ws-ame-postbox-header">
					<h3>
						<span data-bind="text: title"></span>
					</h3>
					<a href="#" class="ame-tc-delete-item"
					   title="It seems this admin screen no longer exists. You can delete these settings."
					   data-bind="visible: canDelete, click: $parent.deleteScreen.bind($parent)"
					><span class="dashicons dashicons-trash"></span></a>
					<button class="ws-ame-postbox-toggle" data-bind="click: toggle"></button>
				</div>
				<div class="ws-ame-postbox-content">
					<div data-bind="sortable: {
						data: $data.columns,
						allowDrop: false,
						options: {
							handle: '.ame-tc-drag-handle'
						}}" class="ame-tc-column-list">
						<div class="ame-tc-column">
							<div class="ame-tc-drag-handle">
								<img src="<?php echo esc_url($dragIconUrl); ?>" alt="Drag indicator" width="24">
							</div>
							<label>
								<input type="checkbox"
								       data-bind="checked: visibility.isEnabled, indeterminate: visibility.isIndeterminate"/>
								<span class="ame-tc-column-title" data-bind="text: title"></span>
							</label>
							<a href="#" class="ame-tc-delete-item"
							   data-bind="
							   visible: canDelete,
							   click: $parent.deleteColumn.bind($parent),
							   attr: { title: $data.deleteTooltip }">
								<span class="dashicons dashicons-trash"></span>
							</a>
						</div>
					</div>

					<div class="ame-tc-other-screen-options">
						<div class="ame-tc-order-settings">
							<label>
								<input type="checkbox"
								       data-bind="checked: customColumnOrder.isEnabled, indeterminate: customColumnOrder.isIndeterminate"/>
								<span>Enable custom column order</span>
							</label>
							<a href="#" class="ame-tc-reset-order" data-bind="
							   click: resetOrder.bind($data),
							   css: { 'ame-tc-disabled': isDefaultOrder },
							   visible: !isDefaultOrder()">
								Reset order
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ko if: screens().length === 0 -->
	<div class="ame-tc-no-screens-message">
		<p>No table columns detected. Try going to an admin page that contains a table and then back to this page.</p>
	</div>
	<!-- /ko -->

	<div id="ame-tc-action-container">
		<div id="ame-tc-main-actions">
			<!--suppress HtmlUnknownTag -->
			<ame-save-settings-form params="form: saveSettingsForm"></ame-save-settings-form>
		</div>
	</div>

</div>


