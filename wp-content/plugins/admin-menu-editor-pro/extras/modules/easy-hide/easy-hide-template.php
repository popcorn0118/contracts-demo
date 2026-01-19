<?php
/**
 * @var string $settingsPageUrl Should be provided by the method that outputs the template
 * @var string $moduleTitle
 * @var string $moduleSettingsUrl
 * @var bool $isExplanationVisible
 */

use YahnisElsts\AdminMenuEditor\EasyHide\Core;

$isProVersion = apply_filters('admin_menu_editor_is_pro', false);
$pluginName = $isProVersion ? 'Admin Menu Editor Pro' : 'Admin Menu Editor';

echo '<div class="wrap">';

if ( isset($_GET['message']) && (intval($_GET['message']) === 1) ) {
	add_settings_error('ame-easy-hide-page', 'settings_updated', __('Settings saved.'), 'updated');
}
settings_errors('ame-easy-hide-page');

//WP 4.3+ uses H1 headings for admin pages.
$headingTag = 'h1';

printf(
	'<%1$s id="ws_ame_editor_heading">%2$s - Easy Hide</%1$s>',
	$headingTag,
	apply_filters('admin_menu_editor-self_page_title', 'Menu Editor')
);
?>

	<div id="ame-easy-hide-container">
		<?php if ( $isExplanationVisible ): ?>
			<div id="ame-easy-hide-explanation" class="notice notice-info is-dismissible">
				<p>
					<strong>Tip:</strong> This page puts all the <?php echo esc_html($pluginName) ?>
					settings that are related to hiding things in one place. It's an alternative way
					to quickly find and edit those settings.
				</p>
				<p>
					If you don't need this page, you can disable the "<?php echo esc_html($moduleTitle); ?>"
					module in <a href="<?php echo esc_attr($moduleSettingsUrl); ?>">Settings</a>.
				</p>
			</div>
		<?php endif; ?>

		<div id="ame-easy-hide-loader" data-bind="visible: false">
			Loading...
		</div>
		<div id="ame-easy-hide" style="display: none;" data-bind="visible: true">

			<div class="ame-sticky-top-bar">
				<div class="ame-sticky-top-bar-flex-content">
					<?php require AME_ROOT_DIR . '/modules/actor-selector/actor-selector-template.php'; ?>
					<div id="ame-eh-top-save-button">
						<div data-bind="template: 'ame-eh-inner-save-button'"></div>
					</div>
				</div>
			</div>

			<div id="ame-easy-hide-ui">
				<div id="ame-eh-category-container">
					<div data-bind="template: {
						name: 'ame-eh-category-list-template',
						data: [rootCategory]}"></div>
					<div id="ame-eh-side-save-button">
						<div data-bind="template: 'ame-eh-inner-save-button'"></div>
					</div>
				</div>
				<div id="ame-eh-content-area">
					<div id="ame-eh-view-toolbar">
						<div id="ame-eh-search-container"
						     data-bind="event: { 'keydown': filterState.processEscKey.bind(filterState)}">
							<input type="search" title="Search items" placeholder="Search" id="ame-eh-search-query"
							       data-bind="textInput: filterState.internalSearchQuery">
							<button class="ame-eh-clear-search-box" title="Clear search box (Esc)"
							        data-bind="click: filterState.clearSearchBox.bind(filterState),
							            visible: (filterState.searchQuery() !== '')">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
							<label for="ame-eh-search-query" class="screen-reader-text">Search items</label>
						</div>

						<div id="ame-eh-column-selector">
							Columns:
							<div data-bind="foreach: [1, 2, 3]" class="ame-eh-column-option-list">
								<button
									class="button ame-eh-column-option"
									data-bind="
									        text: $data,
									        click: $parent.preferences.numberOfColumns.bind($parent, $data),
									        css: {
									            'ame-eh-selected-column-option':
									            ($parent.preferences.numberOfColumns() === $data)
									        }">
								</button>
							</div>
						</div>
					</div>
					<div id="ame-eh-item-container" data-bind="class: itemContainerClasses">
						<div data-bind="template: {
						name: 'ame-eh-category-content',
						data: rootCategory}">
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>

	<div style="display: none">
		<template id="ame-eh-category-list-template">
			<ul data-bind="foreach: $data" class="ame-eh-category-list">
				<li data-bind="visible: isNavVisible">
					<div class="ame-cat-nav-item ame-eh-cat-nav-item"
					     data-bind="
					        click: $root.selectedCategory,
					        class: navCssClasses">
						<span class="ame-cat-nav-toggle"
						      data-bind="
						        visible: (parent !== null),
						        click: toggle.bind($data),
						        clickBubble: false"></span><span class="ame-eh-cat-label"
						                                         data-bind="text: label"></span>
					</div>
					<!-- ko template: {
						name: 'ame-eh-category-list-template',
						data: sortedSubcategories,
						if: (sortedSubcategories().length > 0)
					} -->
					<!-- /ko -->
				</li>
			</ul>
		</template>

		<template id="ame-eh-category-content">
			<div data-bind="
				visible: isVisible,
				css: {
					'ame-eh-columns-allowed': (items().length >= 5),
					'ame-eh-is-root-category': (parent === null),
					'ame-eh-selected-cat': isSelected()
				}"
			     class="ame-eh-category">
				<!-- ko if: (shouldRenderContent() && isStandardRenderingEnabled()) -->

				<div data-bind="visible: isShowingItems" class="ame-eh-category-item-wrap">
					<h3 class="ame-eh-category-heading">
						<label>
							<input type="checkbox" class="ame-eh-negative-box"
							       data-bind="checked: isChecked,
							       indeterminate: isIndeterminate">
							<span data-bind="text: label"></span>
							<!-- ko if: tooltip -->
							<span class="dashicons dashicons-editor-help ame-eh-category-tooltip"
							      data-bind="attr: {'title': tooltip}"></span>
							<!-- /ko -->
						</label>
					</h3>

					<!-- ko if: (tableViewEnabled && tableView) -->
					<div data-bind="template: {
						name: 'ame-eh-table-layout-template',
						data: tableView
					}"></div>
					<!-- /ko -->

					<!-- ko ifnot: (tableViewEnabled && tableView) -->
					<div data-bind="template: {
						name: 'ame-eh-item-list-template',
						data: directItems
					}" class="ame-eh-category-items"></div>
					<!-- /ko -->
				</div>

				<!-- ko if: (sortedSubcategories().length > 0) -->
				<div data-bind="template: {
					name: 'ame-eh-category-content',
					foreach: sortedSubcategories
				}">
				</div>
				<!-- /ko -->

				<!-- /ko -->
				<!-- ko ifnot: shouldRenderContent -->
				<div class="ame-eh-lazy-category">
					<h3 class="ame-eh-category-heading" data-bind="text: label"></h3>
					Loading...
				</div>
				<!-- /ko -->
			</div>
		</template>

		<template id="ame-eh-item-list-template">
			<ul data-bind="foreach: $data" class="ame-eh-item-list">
				<li class="ame-eh-item" data-bind="visible: isVisible">
					<label class="ame-eh-item-self" data-bind="attr: {'title': tooltip}">
						<input type="checkbox" class="ame-eh-negative-box"
						       data-bind="checked: isChecked, indeterminate: isIndeterminate,
						       readonly: !isEditableForSelectedActor">
						<span data-bind="html: htmlLabel"></span>
					</label>
					<!-- ko template: {
						name: 'ame-eh-item-list-template',
						data: children,
						if: (children.length > 0)
					} -->
					<!-- /ko -->
				</li>
			</ul>
		</template>

		<template id="ame-eh-table-layout-template">
			<table class="widefat striped ame-eh-category-table-view">
				<thead data-bind="event: {mouseover: onTableHover}">
				<tr>
					<th scope="col" class="ame-eh-table-corner-cell"><!-- Row label --></th>
					<!-- ko foreach: columns -->
					<th scope="col">
						<label data-bind="attr: {'for': safeElementId+'-column-cb'}">
							<span data-bind="text: label"></span>
						</label>
					</th>
					<!-- /ko -->
				</tr>
				<tr>
					<th scope="row"></th>
					<!-- ko foreach: columns -->
					<td>
						<!--suppress HtmlFormInputWithoutLabel -->
						<input type="checkbox" class="ame-eh-negative-box"
						       data-bind="
							       checked: isChecked,
							       indeterminate: isIndeterminate,
							       attr: {'id': safeElementId+'-column-cb'}">
					</td>
					<!-- /ko -->
				</tr>
				</thead>
				<tbody data-bind="foreach: rows, event: {mouseover: onTableHover}">
				<tr data-bind="visible: isVisible">
					<th scope="row">
						<label>
							<input type="checkbox" class="ame-eh-negative-box"
							       data-bind="checked: isChecked, indeterminate: isIndeterminate">
							<span data-bind="html: highlightedLabel"></span>
							<!-- ko if: subtitle -->
							<span data-bind="text: subtitle" class="ame-eh-category-subtitle"></span>
							<!-- /ko -->
						</label>
					</th>
					<!-- ko foreach: $parent.columns -->
					<td data-bind="foreach: $parents[1].getCellItems($parent, $data)">
						<label
							data-bind="attr: {title: ('&quot;' + $data.label + '&quot; on &quot;' + $parent.label + '&quot;')}">
							<input type="checkbox" class="ame-eh-negative-box"
							       data-bind="checked: isChecked, indeterminate: isIndeterminate,
							       readonly: !isEditableForSelectedActor">
							<span class="screen-reader-text" data-bind="text: label"></span>
						</label>
					</td>
					<!-- /ko -->
				</tr>
				</tbody>
			</table>
		</template>

		<template id="ame-eh-inner-save-button">
			<form action="<?php echo esc_attr(add_query_arg(['noheader' => 1], $settingsPageUrl)) ?>"
			      data-bind="submit: saveChanges"
			      method="post"
			      class="ame-eh-save-form">
				<?php
				submit_button(
					'Save Changes',
					'primary',
					'submit',
					false,
					['data-bind' => 'enable: isSaveButtonEnabled',]
				); ?>

				<input type="hidden" name="action" value="<?php echo esc_attr(Core::SAVE_ACTION); ?>">
				<?php wp_nonce_field(Core::SAVE_ACTION); ?>

				<input type="hidden" name="settings" value="" data-bind="value: settingsData">
				<input type="hidden" name="selectedActor" value="" data-bind="value: selectedActorId">
				<input type="hidden" name="selectedCategory" value="" data-bind="value: selectedCategoryId">
			</form>
		</template>
	</div>

<?php
echo '</div>'; //Close the "wrap" container.