<?php
/**
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer $renderer
 * @var \YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure $structure
 */

?>
<div id="ws-ame-mc-menu-color-settings" title="Default menu colors" style="display: none;">
	<?php $renderer->renderStructure($structure); ?>

	<div class="ws_dialog_buttons">
		<?php
		submit_button('OK', 'primary', 'ws-ame-mc-save-menu-colors', false, [
			'data-bind' => 'click: onConfirmDialog.bind($data)',
		]);
		?>
		<?php
		submit_button('Apply to All', 'secondary', 'ws-ame-mc-apply-colors-to-all', false, [
			'data-bind' => 'click: onApplyToAll.bind($data)',
		]);
		?>
		<input type="button" class="button ws_close_dialog" value="Cancel" autofocus="autofocus"
		       data-bind="click: onCancelDialog.bind($data)">
	</div>
</div>