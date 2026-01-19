<?php
/**
 * @var \YahnisElsts\AdminMenuEditor\Customizable\SettingsForm $settingsForm
 */

?>
<div id="ame-qs-settings-page-container" style="display: none" data-bind="visible: true">
	<div id="ame-qs-settings-form-wrapper">
		<?php
		$settingsForm->output();
		?>
	</div>
	<div id="ame-qs-settings-sidebar">
		<div class="metabox-holder">
			<div class="postbox ws_ame_doc_box" data-bind="css: {closed: !isInfoBoxOpen()}">
				<button type="button" class="handlediv button-link" data-bind="click: toggleInfoBox.bind($root)">
					<span class="toggle-indicator"></span>
				</button>
				<h2 class="hndle" data-bind="click: toggleInfoBox.bind($root)">How It Works</h2>
				<div class="inside">
					<ul>
						<li>This feature displays a search box when you press a key combination
							almost anywhere in the admin dashboard.
						</li>
						<li>You can use it to go to an admin page without having to
							find and click it in the admin menu.
						</li>
						<li>If indexing is enabled, you can also search for individual WordPress
							and plugin settings, admin page tabs, and more. Compatibility with
							other plugins varies.
						</li>
						<li>Keyboard navigation:
							<ul>
								<li><kbd>&uparrow;</kbd> <kbd>&downarrow;</kbd> Navigate search results.</li>
								<li><kbd>Enter</kbd> Go to the selected result.</li>
								<li><kbd>Ctrl</kbd>+<kbd>Enter</kbd> Open the selected result in a new tab.</li>
								<li><kbd>Esc</kbd> Close the search box.</li>
							</ul>
						</li>
					</ul>

				</div>
			</div>
		</div>
	</div>
</div>

