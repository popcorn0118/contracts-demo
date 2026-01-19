<?php
/**
 * @var string $settingsPageUrl URL of the module settings page.
 */

//Quick Search module: Templates for Knockout.
$spinnerImageUrl = plugins_url('images/spinner.gif', AME_ROOT_DIR . '/stub');

?>
<div id="ame-quick-search-root" data-bind="ameQuickSearchVisible: {visible: isVisible, store: geometryStore}"
     class="ame-quick-search-no-scan" style="display: none">
	<div id="ame-quick-search" data-bind="event: {keydown: handleAppKeyDown}">
		<div class="ame-qs-header">
			<ul data-bind="foreach: $root.tabs.items" class="ame-qs-tab-nav">
				<li data-bind="css: {'current': isActive}, visible: isVisible">
					<a href="#" data-bind="click: $root.tabs.switchToTab.bind($root.tabs, $data)">
						<span data-bind="text: title"></span>
					</a>
				</li>
			</ul>
			<div id="ame-qs-page-stats" data-bind="if: pageStatsVisible">
				<span class="ame-qs-stats-property" data-bind="attr: {title: pageStats.memoryUsageTitle}">
					<span data-bind="text: pageStats.formattedPeakMemoryUsage"></span><span
							class="ame-qs-stats-unit">MiB</span>
				</span>
				<span class="ame-qs-stats-property" title="Page generation time">
					<span data-bind="text: pageStats.formattedPageGenerationTime"></span><span
							class="ame-qs-stats-unit">s</span>
				</span>
			</div>
			<div id="ame-qs-settings-link-container">
				<a href="<?php echo esc_url($settingsPageUrl); ?>" title="Go to Quick Search settings">
					<span class="dashicons dashicons-admin-generic"></span>
				</a>
			</div>
		</div>

		<div class="ame-qs-tab-wrapper">
			<div id="ame-qs-search-tab" class="ame-qs-tab" data-bind="visible: tabs.isTabActive('search')">
				<div class="ame-qs-search-box-container">
					<label>
						<span class="screen-reader-text">Search:</span>
						<input type="text" class="large-text ame-qs-search-box"
						       placeholder="&#x1F50D;&#xFE0E; Search menus, settings, etc..."
						       data-bind="value: searchInput, valueUpdate: 'input',
						        event: { keydown: handleSearchBoxKeyDown }, ameQuickSearchInputFocused: isSearchBoxFocused"/>
					</label>
				</div>
				<div class="ame-qs-search-results"
				     data-bind="foreach: searchResults, ameAutoScrollToChild: $root.selectedResult">
					<div class="ame-qs-search-result"
					     data-bind="css: {'ame-qs-selected-result': ($root.selectedResult() === $data)}, class: itemClass">
							<span data-bind="if: iconClass" class="ame-qs-result-icon"><span
										data-bind="class: iconClass">
							</span></span>
						<span data-bind="text: item.label, attr: {title: item.label}"
						      class="ame-qs-result-label"></span>
						<span data-bind="text: score, visible: false" class="ame-qs-result-score"></span>
						<span data-bind="text: metaLabel" class="ame-qs-result-meta"></span>
					</div>
				</div>
			</div>
			<div id="am-qs-crawler-tab" class="ame-qs-tab" data-bind="visible: tabs.isTabActive('crawler')">
				<p class="ame-qs-crawler-status-message" data-bind="text: crawlerStatusMessage"></p>
				<p class="ame-qs-crawler-actions">
					<button data-bind="click: $root.stopCrawler.bind($root), enable: isCrawlerRunning"
					        class="button button-secondary">
						Stop Crawler
					</button>
					<button data-bind="click: $root.runIncrementalCrawl.bind($root), enable: canStartIndexUpdate"
					        class="button button-secondary">
						Incremental Crawl
					</button>
					<button data-bind="click: $root.crawlCurrentPage.bind($root), enable: canStartIndexUpdate"
					        class="button button-secondary">
						Crawl This Page
					</button>
				</p>

				<div data-bind='component: {
					    name: "ame-subsubsub-tabs",
					    params: { tabs: crawlerTabs }
					}'></div>

				<div class="ame-qs-crawler-status-tab" data-bind="visible: crawlerTabs.isTabActive('menus')">
					<div class="ame-qs-object-list-container">
						<div data-bind="foreach: menuCrawlerMeta" class="ame-qs-object-list">
							<div class="ame-qs-object">
								<details class="ame-qs-object-details">
									<summary>
											<span class="ame-qs-object-summary">
											<span data-bind="text: menuItem.label"
											      class="ame-qs-object-label"></span>
											<span class="ame-qs-object-status">
												<span data-bind="text: request ? (request.isTreeInProgress() ? 'In progress' : (request.isTreeCompletedOrError() ? 'Done' : 'Stopped')) : 'Skipped'"
												      class="ame-qs-object-status-text"></span>
												<!-- ko if: request -->
													<span data-bind="visible: request.totalTreeItems() > 0"
													      class="ame-qs-object-count ame-qs-request-total-items">
														(<span data-bind="text: request.totalTreeItems"></span>)
													</span>
												<!-- /ko -->
											</span>
											</span>
									</summary>
									<table>
										<tr>
											<th scope="row">Decision</th>
											<td>
												<span data-bind="text: (request !== null) ? 'Crawl now' : 'Skip'"></span>:
												<span data-bind="text: reason"></span>
											</td>
										</tr>
										<tr>
											<th scope="row">Menu URL</th>
											<td data-bind="text: menuItem.getUrl()"></td>
										</tr>
										<tr>
											<th scope="row">Previous attempt</th>
											<td data-bind="text: previousCrawlAttempt ? previousCrawlAttempt : 'N/A'"></td>
										</tr>
										<!-- ko if: request -->
										<tr data-bind="if: request.errorMessage">
											<th>Request error</th>
											<td data-bind="text: request.errorMessage"></td>
										</tr>
										<tr>
											<th scope="row">Depth</th>
											<td data-bind="text: request.depth"></td>
										</tr>
										<tr>
											<th scope="row">Child requests</th>
											<td data-bind="text: request.childRequests.size"></td>
										</tr>
										<tr data-bind="if: (request.childRequests.size > 0)">
											<th scope="row">Subtree</th>
											<td data-bind="text: request.isTreeInProgress() ? 'In progress' : (request.isTreeCompletedOrError() ? 'Done' : 'Stopped')"></td>
										</tr>
										<tr>
											<th scope="row">Found items</th>
											<td>
												<span data-bind="text: request.totalTreeItems()"></span>
												<!-- ko if: (totalUniqueItems() !== null) -->
												<span data-bind="text: ' (' + totalUniqueItems() + ' unique)'"></span>
												<!-- /ko -->
											</td>
										</tr>
										<!-- /ko -->
									</table>
								</details>
							</div>
						</div>
					</div>
				</div>

				<div class="ame-qs-crawler-status"
				     data-bind="template: {name: 'ame-qs-crawler-status-template', if: crawler, data: crawler}">
				</div>
			</div>
		</div>

		<!-- ko if: crawlerOfferVisible -->
		<div id="ame-qs-crawler-offer">
			<div id="ame-qs-crawler-offer-main">
				<strong>Scan admin pages for settings?</strong>
				<div id="ame-qs-crawler-offer-actions">
					<a href="#" data-bind="click: enableIncrementalCrawl.bind($data)">
						Yes
					</a>
					<a href="#" data-bind="click: disableIncrementalCrawl.bind($data)">
						No
					</a>
					<a href="#" data-bind="click: toggleCrawlerOfferDetails.bind($data)">What's this?</a>
				</div>
			</div>
			<div id="ame-qs-crawler-short-description" data-bind="visible: crawlerOfferDetailsVisible">
				<p>Admin Menu Editor can regularly scan your admin pages to detect available settings,
					tabs, filter links, and so on. Then you can quickly find them using the search
					box. However, only some types of settings are supported, and scanning may slow down
					your admin pages a bit.
				</p>
				<p>
					You can always enable or disable this feature in
					<a href="<?php echo esc_url($settingsPageUrl); ?>">Quick Search settings</a>.
				</p>
			</div>
		</div>
		<!-- /ko -->

		<div id="ame-qs-status-bar">
			<div id="ame-qs-current-item-url" data-bind="visible: tabs.isTabActive('search')">
				<span data-bind="text: selectedResultDisplayUrl"></span>
			</div>
			<div id="ame-qs-status-bar-placeholder">|</div>
			<div id="ame-qs-search-progress-indicator" data-bind="visible: searchProgressIndicatorVisible">
				<img src="<?php echo esc_url($spinnerImageUrl); ?>" alt="Searching..."/>
			</div>
			<div id="ame-qs-crawler-status-panel"
			     data-bind="
			        visible: showCrawlerStatusInStatusBar,
			        css: {'ame-qs-align-right': !$root.tabs.isTabActive('search')}">
				<span data-bind="text: crawlerStatusMessage"></span>
			</div>
			<div id="ame-qs-search-result-count"
			     data-bind="visible: tabs.isTabActive('search') && !showCrawlerStatusInStatusBar()">
				<span data-bind="text: searchResults().length"></span>
				results
			</div>
		</div>
	</div>
</div>
<template id="ame-qs-crawler-status-template" style="display: none">
	<div class="ame-qs-crawler-status-tab" data-bind="visible: $root.crawlerTabs.isTabActive('active')">
		<div class="ame-qs-object-list-container ame-qs-crawl-request-list-container">
			<div class="ame-qs-object-list ame-qs-crawl-request-list"
			     data-bind="template: {name: 'ame-qs-crawl-request-template', foreach: activeRequests}">
			</div>
		</div>
	</div>

	<div class="ame-qs-crawler-status-tab" data-bind="visible: $root.crawlerTabs.isTabActive('queue')">
		<div class="ame-qs-object-list-container ame-qs-crawl-request-list-container">
			<div class="ame-qs-object-list ame-qs-crawl-request-list"
			     data-bind="template: {name: 'ame-qs-crawl-request-template', foreach: crawlRequestQueue.items()}">
			</div>
		</div>
	</div>
	<div class="ame-qs-crawler-status-tab" data-bind="visible: $root.crawlerTabs.isTabActive('finished')">
		<div class="ame-qs-object-list-container ame-qs-crawl-request-list-container">
			<div class="ame-qs-object-list ame-qs-crawl-request-list" id="ame-qs-finished-requests"
			     data-bind="template: {name: 'ame-qs-crawl-request-template', foreach: finishedRequests.items()}">
			</div>
		</div>
	</div>
</template>

<template id="ame-qs-crawl-request-template" style="display: none">
	<div class="ame-qs-object ame-qs-crawl-request" data-bind="class: itemCssClass">
		<details class="ame-qs-object-details ame-qs-request-details">
			<summary>
					<span class="ame-qs-object-summary">
					<span data-bind="text: relativePageUrl"
					      class="ame-qs-object-label ame-qs-request-page-url"></span>

					<span class="ame-qs-object-status ame-qs-request-status">
						<span data-bind="text: $data.status()"
						      class="ame-qs-object-status-text ame-qs-request-status-text"></span>
								<span data-bind="visible: totalItems() > 0"
								      class="ame-qs-object-count ame-qs-request-total-items">
							(<span data-bind="text: totalItems, visible: totalItems() > 0"></span>)
						</span>
					</span>
					</span>
			</summary>
			<table>
				<tr data-bind="if: errorMessage">
					<th>Error</th>
					<td data-bind="text: errorMessage"></td>
				</tr>
				<tr>
					<th scope="row">Menu URL</th>
					<td data-bind="text: menuUrl"></td>
				</tr>
				<tr data-bind="if: reason">
					<th scope="row">Reason</th>
					<td data-bind="text: reason"></td>
				</tr>
				<tr>
					<th scope="row">Item location</th>
					<td data-bind="text: JSON.stringify($data.location)"></td>
				</tr>
				<tr>
					<th scope="row">Depth</th>
					<td data-bind="text: depth"></td>
				</tr>
				<tr>
					<th scope="row">Children</th>
					<td data-bind="text: childRequests.size"></td>
				</tr>
				<tr data-bind="if: (childRequests.size > 0)">
					<th scope="row">Subtree</th>
					<td data-bind="text: isTreeInProgress() ? 'In progress' : (isTreeCompletedOrError() ? 'Done' : 'Stopped')"></td>
				</tr>
			</table>
		</details>
	</div>
</template>