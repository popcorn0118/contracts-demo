<div id="ame-dashboard-widget-editor" style="display: none" data-bind="visible: true">

	<?php require AME_ROOT_DIR . '/modules/actor-selector/actor-selector-template.php'; ?>

	<div id="ame-dashboard-widget-main-area">
		<div id="ame-dashboard-widgets"
		     data-bind="class: ('ame-widget-preview-columns-' + desiredPreviewColumnCount()), foreach: columnLayout">
			<div class="ame-widget-preview-column"
			     data-bind="foreach: $data, attr: {id: ('ame-widget-preview-column-' + $index())}">
				<div class="ame-widget-area"
				     data-bind="attr: {id: 'ame-widget-area-' + ($data)}">
					<div class="ame-widget-area-header"
					     data-bind="text: ('Column #' + $data),
				        visible: (($data > 1) || ($root.desiredPreviewColumnCount() > 1))"></div>
					<div class="ame-dashboard-widget-collection"
					     data-bind="sortable: {
	                            data: $root.getColumnContents($data),
	                            template: 'ame-widget-container-template',
	                            connectClass: 'ame-dashboard-widget-collection',
	                            beforeMove: $root.onBeforeMoveWidget.bind($root),
	                            afterMove: $root.onAfterMoveWidget.bind($root),
	                            options: {
	                                placeholder: 'ame-widget-move-placeholder',
	                                forcePlaceholderSize: true,
	                                handle: '.ame-widget-title h3',
	                                items: '> .ame-dashboard-widget.ame-movable-dashboard-widget'
	                            }
						     },
							 css: {
							 	'ame-empty-dashboard-widget-collection': $root.isColumnEmpty($data)
							 }">
					</div>
				</div>
			</div>
		</div>

		<div id="ame-widget-editor-sidebar">
			<div id="ame-major-widget-actions">
				<form method="post" data-bind="submit: saveChanges" action="<?php
				echo esc_url(add_query_arg(
					[
						'page'        => 'menu_editor',
						'noheader'    => '1',
						'sub_section' => 'dashboard-widgets',
					],
					admin_url('options-general.php')
				));
				?>">
					<?php
					submit_button('Save Changes', 'primary', 'submit', false);
					wp_nonce_field('save_widgets');
					?>

					<input type="hidden" name="action" value="save_widgets">
					<input type="hidden" name="data" value="" data-bind="value: widgetData">
					<input type="hidden" name="data_length" value="" data-bind="value: widgetDataLength">
					<input type="hidden" name="selected_actor" value="" data-bind="value: selectedActor">
					<input type="hidden" name="preview_columns" value="" data-bind="value: desiredPreviewColumnCount">
				</form>

				<?php
				submit_button(
					'Add HTML Widget',
					'secondary',
					'ame-add-html-widget',
					false,
					[
						'data-bind' => 'click: addHtmlWidget',
					]
				);

				submit_button(
					'Add RSS Widget',
					'secondary',
					'ame-add-rss-widget',
					false,
					[
						'data-bind' => 'click: addRssWidget',
					]
				);
				?>

				<!-- Export form -->
				<?php
				$formActionUrl = admin_url('admin-ajax.php');
				?>

				<form
						action="<?php echo esc_url($formActionUrl); ?>"
						method="post"
						target="ame-widget-export-frame"
						data-bind="submit: exportWidgets"
				>
					<?php wp_nonce_field('ws-ame-export-widgets'); ?>
					<input type="hidden" name="action" value="ws-ame-export-widgets">
					<input type="hidden" name="widgetData" value="" data-bind="value: widgetData">

					<?php submit_button(
						'Export',
						'secondary',
						'ame-export-widgets',
						false,
						['data-bind' => 'enable: isExportButtonEnabled']
					); ?>
				</form>
				<!--suppress HtmlUnknownTarget -->
				<iframe name="ame-widget-export-frame" src="about:blank" style="display:none;"></iframe>

				<!-- Import button -->
				<?php
				submit_button(
					'Import',
					'secondary',
					'ame-import-widgets',
					false,
					['data-bind' => 'click: openImportDialog']
				);
				?>
			</div>

			<div class="metabox-holder">
				<div class="postbox">
					<div class="postbox-header">
						<h2 class="hndle">Widget Order</h2>
					</div>
					<div class="inside">
						<ul>
							<li>
								<label>
									<input type="checkbox"
									       id="ame-dwe-default-order-override"
									       data-bind="checked: isDefaultOrderOverrideEnabled">
									Override default order

									<a class="ws_tooltip_trigger"
									   title="Check this box if you want to manually change the order
							             and position of widgets. Leave it unchecked to let WordPress
							            organize widgets based on their properties.">
										<div class="dashicons dashicons-info"></div>
									</a>
								</label>
							</li>

							<li>
								<label>
									<input type="checkbox"
									       id="ame-dwe-actor-order-override"
									       data-bind="
									        checked: actorOrderOverride.isEnabled,
									        indeterminate: actorOrderOverride.isIndeterminate,
											enable: isDefaultOrderOverrideEnabled">
									Override user order

									<a class="ws_tooltip_trigger"
									   title="Usually, users can move widgets around, and WordPress will
							            automatically remember their custom widget order. Enable this option
							            to override saved user preferences and use your widget order instead.">
										<div class="dashicons dashicons-info"></div>
									</a>
								</label>
							</li>
						</ul>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header">
						<h2 class="hndle">Columns</h2>
					</div>
					<div class="inside">
						<p class="ame-widget-editor-box-subheading">
							<strong>Preview columns</strong>
						</p>
						<fieldset class="ame-widget-preview-column-choices"
						          data-bind="foreach: previewColumnOptions">
							<label>
								<input type="radio"
								       data-bind="checkedValue: $data, checked: $root.desiredPreviewColumnCount"
								       name="ame-desired-preview-columns">
								<span data-bind="text: $data"></span>
							</label>
						</fieldset>

						<fieldset>
							<p class="ame-widget-editor-box-subheading">
								<label for="ame-forced-dashboard-column-count">
									<strong>Force number of columns</strong>
								</label>
								<a class="ws_tooltip_trigger"
								   title="By default, WordPress automatically sets the number of columns
							     based on the size of the screen or the browser window. Use this setting
							     to override the number of widget columns in the dashboard.">
									<span class="dashicons dashicons-info"></span>
								</a>
							</p>

							<ul>
								<li>
									<select id="ame-forced-dashboard-column-count"
									        data-bind="value: forcedColumnCount,
											    options: forcedColumnSelectorOptions,
												optionsText: 'label',
												optionsValue: 'value'">
									</select>
								</li>

								<li>
									<label for="ame-forced-column-strategy-selector" class="screen-reader-text">
										Minimum screen size where to apply the specified
										number of columns
									</label>
									<select id="ame-forced-column-strategy-selector"
									        data-bind="value: forcedColumnStrategy,
									            options: forcedColumnStrategyOptions,
												optionsText: 'label',
												optionsValue: 'value',
												enable: (forcedColumnCount() !== null)">
										<option value="always">Always</option>
										<option value="breakpoint">Above X pixels</option>
									</select>
								</li>

								<li>
									<label>
										<input type="checkbox"
										       id="ame-dwe-actor-forced-columns"
										       data-bind="checked: actorForcedColumns.isEnabled,
													indeterminate: actorForcedColumns.isIndeterminate">
										<span data-bind="text: toggleLabelForSelectedActor">Enable for selected role</span>
									</label>
								</li>
							</ul>
						</fieldset>
					</div>
				</div>
			</div>
		</div>

	</div>

	<?php require dirname(__FILE__) . '/import-dialog-template.php'; ?>
</div>

<div style="display: none;">
	<template id="ame-widget-container-template">
		<div class="ame-dashboard-widget"
		     data-bind="css: {
		        'ame-open-dashboard-widget' : isOpen,
		        'ame-movable-dashboard-widget' : $data.canBeMoved
		     }">

			<div class="ame-widget-top">
				<a class="ame-widget-title-action" data-bind="click: toggle, if: false"></a>
				<div class="ame-widget-flags">
					<div class="ame-widget-flag ame-missing-widget-flag"
					     data-bind="visible: !isPresent, attr: {title: missingWidgetTooltip}"></div>
				</div>
				<div class="ame-widget-title">
					<input type="checkbox" class="ame-widget-access-checkbox"
					       data-bind="checked: isEnabled, indeterminate: isIndeterminate" title="Visibility">
					<h3><span data-bind="text: safeTitle"></span>&nbsp; </h3>
				</div>
			</div>

			<div class="ame-widget-properties">
				<ame-widget-property params="widget: $data, label: 'Title'">
					<input data-bind="value: title, enable: canChangeTitle" type="text"
					       class="ame-widget-property-value" title="Title">
				</ame-widget-property>

				<!-- ko template: { if: propertyTemplate, name: propertyTemplate, data: $data } --><!-- /ko -->

				<div data-bind="visible: areAdvancedPropertiesVisible">
					<ame-widget-property params="widget: $data, label: 'ID'">
						<input data-bind="value: id" type="text"
						       class="ame-widget-property-value ame-widget-id-property-value" readonly title="ID">
					</ame-widget-property>

					<ame-widget-property params="widget: $data, label: 'Location'">
						<input data-bind="value: location" type="text" class="ame-widget-property-value" readonly
						       title="Location">
					</ame-widget-property>

					<ame-widget-property params="widget: $data, label: 'Priority'">
						<select data-bind="value: priority, enable: canChangePriority"
						        class="ame-widget-property-value" title="Priority">
							<option value="high">high</option>
							<option value="sorted">sorted</option>
							<option value="core">core</option>
							<option value="default">default</option>
							<option value="low">low</option>
						</select>
					</ame-widget-property>
				</div>

				<div class="ame-widget-control-actions">
					<a href="#" class="ame-close-widget" data-bind="click: toggle">Close</a>
					<span data-bind="if: canBeDeleted">
						|
						<a href="#" class="ame-delete-widget"
						   data-bind="click: $root.removeWidget.bind($root)">Delete</a>
					</span>
				</div>
			</div>
		</div>
	</template>

	<template id="ame-widget-property-template">
		<label>
			<!-- ko if: label -->
			<span class="ame-widget-property-name" data-bind="text: label"></span><br>
			<!-- /ko -->
			<!-- ko template: { nodes: $componentTemplateNodes, data: widget } --><!-- /ko -->
		</label>
	</template>

	<template id="ame-custom-html-widget-template">
		<ame-widget-property params="widget: $data, label: 'Content'">
			<textarea data-bind="value: content"
			          class="ame-widget-property-value"
			          title="Content"
			          rows="10">
			</textarea>
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: ''">
			<input type="checkbox"
			       data-bind="checked: filtersEnabled"
			       class="ame-widget-property-value"
			       title="Enable filters like automatic paragraphs, smart quotes and automatic tag balancing">
			Apply content filters
		</ame-widget-property>
	</template>

	<template id="ame-custom-rss-widget-template">
		<ame-widget-property params="widget: $data, label: 'Feed URL'">
			<input type="url"
			       data-bind="value: feedUrl"
			       class="ame-widget-property-value"
			       title="The URL of the RSS feed">
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: 'Max. items to show'">
			<input type="number"
			       data-bind="value: maxItems"
			       min="1"
			       max="20"
			       class="ame-widget-property-value"
			       title="Max items">
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: ''">
			<input type="checkbox"
			       data-bind="checked: showAuthor"
			       class="ame-widget-property-value"
			       title="Show author">
			Show author
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: ''">
			<input type="checkbox"
			       data-bind="checked: showDate"
			       class="ame-widget-property-value"
			       title="Show date">
			Show date
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: ''">
			<input type="checkbox"
			       data-bind="checked: showSummary"
			       class="ame-widget-property-value"
			       title="Show summary">
			Show summary
		</ame-widget-property>

		<ame-widget-property params="widget: $data, label: ''">
			<input type="checkbox"
			       data-bind="checked: openInNewTab"
			       class="ame-widget-property-value"
			       title="Open links in a new tab or window">
			Open links in a new tab
		</ame-widget-property>
	</template>

	<template id="ame-welcome-widget-template">
		<p class="howto">
			This is a special widget. It can't be renamed or moved. Only users who have
			the <code>edit_theme_options</code> capability can see it.
		</p>
	</template>
</div>