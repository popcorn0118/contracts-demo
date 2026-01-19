<?php
/**
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer $renderer
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure $structure
 */
?>
<div style="display: none;">
	<div id="ws-ame-menu-style-settings" title="Menu Style">
		<div id="ws-ame-ms-dialog-wrapper">
			<div id="ws-ame-ms-dialog-content">
				<?php
				$renderer->renderStructure($structure);
				?>
			</div>
			<div class="ws_dialog_buttons">
				<?php
				submit_button(
					'OK',
					'primary',
					'ws-ame-save-menu-styler-settings',
					false,
					[
						'data-bind' => 'click: onConfirmDialog.bind($data)',
					]
				);
				?>

				<div id="ws-ame-ms-preview-box-container">
					<label>
						<input type="checkbox" data-bind="checked: isPreviewEnabled, enable: isPreviewPossible">
						Live preview
					</label>
				</div>

				<input type="button" class="button ws_close_dialog" value="Cancel"
				       data-bind="click: onCancelDialog.bind($data)">
			</div>
		</div>
	</div>
</div>