'use strict';
import * as _ from 'lodash-es';
import log from 'loglevel';
import { builtinScanners, PageScanner, parseAdminMenuItems, userIdQueryPlaceholder } from './scanner';
import { escapeRegExp, getRelativeAdminPageUrl, isAdminPageUrl, lazy, LRUCache, normalizeUrl, queryAdvancedSelector } from './utils';
import { AdminMenuItem, DashboardItem, EntityItem, LoadingPlaceholderItem, unserializeItem } from './items';
import { Crawler, CrawlerUrlBlacklist, CrawlRequest } from './crawler';
import { PanelGeometryStore } from './app-geometry';
import { DashboardCrawlerDb, ItemUsageTracker } from './db';
/*
This annoying js-cookie library doesn't seem to work with any import syntax that doesn't
require allowSyntheticDefaultImports to be enabled in tsconfig.json.

For example, "import * as Cookies from 'js-cookie';" compiles, but the types are wrong.
The real export is at Cookies.default, while TypeScript thinks Cookies itself is the default
export. Importing as below works, for some reason.
 */
import Cookies from 'js-cookie';
var AmeQuickSearchTool;
(function (AmeQuickSearchTool) {
    var Either = AmeMiniFunc.Either;
    var Left = AmeMiniFunc.Left;
    var Right = AmeMiniFunc.Right;
    const logger = log.getLogger('AmeQuickSearchTool');
    class SearchResult {
        constructor(item, score, findMenuTitle = () => null, findEntityIconClass = () => null) {
            this.findMenuTitle = findMenuTitle;
            this.iconClass = 'ame-fa ame-fa-puzzle-piece';
            this.itemClass = '';
            this.item = item;
            this.score = score;
            if (item instanceof DashboardItem) {
                switch (item.getTargetType()) {
                    case 'menu':
                        this.iconClass = 'ame-fa ame-fa-bars';
                        break;
                    case 'tab':
                        this.iconClass = 'ame-fa ame-fa-folder-o';
                        break;
                    case 'section':
                        this.iconClass = 'ame-fa ame-fa-sliders';
                        break;
                    case 'filter':
                        this.iconClass = 'ame-fa ame-fa-filter';
                        break;
                    case 'group':
                        this.iconClass = 'ame-fa ame-fa-list-ul';
                        break;
                    case 'control':
                        this.iconClass = 'ame-fa ame-fa-pencil-square-o';
                        break;
                }
            }
            else if (item instanceof AdminMenuItem) {
                this.iconClass = 'ame-fa ame-fa-bars';
            }
            else if (item instanceof EntityItem) {
                const entityIconClass = findEntityIconClass(item);
                if (entityIconClass) {
                    this.iconClass = entityIconClass;
                }
                else {
                    switch (item.kind) {
                        case 'postType':
                            this.iconClass = 'dashicons dashicons-edit-page';
                            break;
                        case 'taxonomy':
                            this.iconClass = 'dashicons dashicons-tag';
                            break;
                        case 'user':
                            this.iconClass = 'dashicons dashicons-admin-users';
                            break;
                    }
                }
            }
            if (item instanceof LoadingPlaceholderItem) {
                this.itemClass = 'ame-qs-loading-placeholder';
            }
        }
        get metaLabel() {
            let label = this.item.getMetaLabel();
            if (label !== '') {
                return label;
            }
            let location = this.item.getLocation();
            if (this.item instanceof DashboardItem) {
                const menuUrl = this.item.getMenuUrl();
                if (menuUrl && this.findMenuTitle) {
                    const title = this.findMenuTitle(menuUrl);
                    if (title) {
                        location = title.concat(location);
                    }
                }
            }
            return location.join(' → ');
        }
    }
    AmeQuickSearchTool.SearchResult = SearchResult;
    class ResultPool {
        constructor(findMenuTitle, findEntityIconClass) {
            this.findMenuTitle = findMenuTitle;
            this.findEntityIconClass = findEntityIconClass;
            this.resultCache = new LRUCache(200);
        }
        makeResultForItem(item, score = 1) {
            const itemId = item.getUniqueId();
            const cachedResult = this.resultCache.get(itemId);
            if (cachedResult) {
                cachedResult.score = score;
                return cachedResult;
            }
            const result = new SearchResult(item, score, this.findMenuTitle, this.findEntityIconClass);
            this.resultCache.put(itemId, result);
            return result;
        }
    }
    class SearchEngine {
        constructor(initialItems, initialResultsCallback, fullResultsCallback, findMenuTitle, findEntityIconClass, relativeMenuUrls, searchAction, cacheCapacity = 2000) {
            this.initialResultsCallback = initialResultsCallback;
            this.fullResultsCallback = fullResultsCallback;
            this.relativeMenuUrls = relativeMenuUrls;
            this.searchAction = searchAction;
            this.maxLengthLimit = 5000;
            this.currentQuery = null;
            this.currentPendingServerQuery = null;
            this.serverQueryQueue = new Map();
            this.queryResponseCache = new LRUCache(20);
            this.activeServerQueries = ko.observable(0);
            this.throttledServerQuery = _.throttle(this.sendPendingServerQuery, 1000, { leading: true, trailing: true });
            this.itemCache = new LRUCache(cacheCapacity);
            this.resultPool = new ResultPool(findMenuTitle, findEntityIconClass);
            for (const item of initialItems) {
                const itemId = item.getUniqueId();
                if (!this.itemCache.has(itemId)) {
                    this.itemCache.put(itemId, item);
                }
            }
        }
        search(query, maxResults = Number.POSITIVE_INFINITY) {
            const searchStart = performance.now();
            const queryWords = query.toLowerCase().trim().split(/\s+/);
            const augmentedQuery = {
                originalQuery: query,
                normalizedQuery: queryWords.join(' '),
                words: queryWords
            };
            this.currentQuery = augmentedQuery;
            const results = [];
            const mainLoopStart = performance.now();
            this.itemCache.forEach((item) => {
                const score = this.calculateScore(item, augmentedQuery);
                if (score > 0) {
                    const result = this.resultPool.makeResultForItem(item, score);
                    results.push(result);
                }
            });
            const mainLoopTime = performance.now() - mainLoopStart;
            const sortStart = performance.now();
            results.sort((a, b) => b.score - a.score);
            const sortTime = performance.now() - sortStart;
            if (results.length > maxResults) {
                results.length = maxResults;
            }
            const resultUpdateStart = performance.now();
            this.initialResultsCallback(results);
            const resultUpdateTime = performance.now() - resultUpdateStart;
            const searchEnd = performance.now();
            logger.log('Search time: ' + (searchEnd - searchStart), 'ms. ('
                + 'main loop: ' + mainLoopTime + 'ms, '
                + 'sort: ' + sortTime + 'ms, '
                + 'update results: ' + resultUpdateTime + 'ms'
                + ')');
            //Exception: If the query is empty, we don't need to ask the server for recent items.
            //Those should already be preloaded together with other script data.
            if (augmentedQuery.normalizedQuery === '') {
                this.fullResultsCallback(results);
                return results;
            }
            //Query the server for more results.
            this.queryServer(augmentedQuery).then((response) => {
                logger.log('Server response received after ' + (performance.now() - searchEnd) + 'ms '
                    + 'for query:', augmentedQuery);
                if (this.currentQuery !== augmentedQuery) {
                    //The query has changed since the server request was sent so we don't
                    //need to update the results.
                    return;
                }
                const alreadyIncludedItems = new Set(results.map((result) => result.item.getUniqueId()));
                let foundNewItems = false;
                for (const item of response.items) {
                    //Skip items that are already in the results. This can happen because items
                    //are cached in memory.
                    if (alreadyIncludedItems.has(item.getUniqueId())) {
                        continue;
                    }
                    const score = this.calculateScore(item, augmentedQuery);
                    if (score > 0) {
                        foundNewItems = true;
                        results.push(this.resultPool.makeResultForItem(item, score));
                    }
                }
                if (foundNewItems) {
                    results.sort((a, b) => b.score - a.score);
                    if (results.length > maxResults) {
                        results.length = maxResults;
                    }
                    this.fullResultsCallback(results);
                }
            }).catch((error) => {
                if (error instanceof QueryCancelledError) {
                    //The query was cancelled, likely because the user typed a new query.
                    //This is fine and doesn't need to be reported.
                    return;
                }
                //Errors just mean the results won't be updated.
                logger.error('Server query failed:', error, augmentedQuery);
            });
            return results;
        }
        calculateScore(item, query) {
            let score = this.calculateMatchScore(item, query);
            if (score > 0) {
                //Bonus for recently used or visited items.
                const recencyMultiplier = Math.max(this.calculateRecencyBonus(item.lastVisitedAt, Date.now()), 2 * this.calculateRecencyBonus(item.lastUsedAt, Date.now()));
                score = score * (1 + recencyMultiplier);
                if (recencyMultiplier > 0) {
                    //logger.log('Recency bonus for item:', item, 'is', recencyMultiplier);
                }
            }
            return score;
        }
        calculateMatchScore(item, query) {
            //Empty query matches everything.
            if (query.originalQuery === '') {
                return 1;
            }
            const searchableText = item.getSearchableText().toLowerCase();
            const simpleScore = this.scoreFullMatch(searchableText, query);
            if (simpleScore > 0) {
                return simpleScore;
            }
            return this.scoreWordMatch(searchableText, query);
        }
        scoreFullMatch(text, query) {
            const position = text.indexOf(query.normalizedQuery);
            if (position < 0) {
                return 0;
            }
            const distanceFromStartScore = 1 - this.mapTextLengthToScoreRange(position);
            const coverageScore = query.normalizedQuery.length / text.length;
            return (distanceFromStartScore + coverageScore) / 2;
        }
        scoreWordMatch(text, query) {
            let firstMatchStart = -1;
            let lastMatchEnd = -1;
            let totalCoverage = 0;
            let positionSum = 0;
            for (const word of query.words) {
                const position = text.indexOf(word, lastMatchEnd + 1);
                if (position < 0) {
                    return 0;
                }
                positionSum += position;
                if (firstMatchStart < 0) {
                    firstMatchStart = position;
                }
                lastMatchEnd = position + word.length;
                totalCoverage += word.length;
            }
            const distanceFromStartScore = 1 - this.mapTextLengthToScoreRange(firstMatchStart);
            const coverageScore = totalCoverage / text.length;
            const averagePosition = positionSum / query.words.length;
            const averagePositionScore = 1 - this.mapTextLengthToScoreRange(averagePosition);
            const startPositionWeight = 0.5;
            const avgPositionWeight = 0.3;
            const coverageWeight = 0.2;
            const weightSum = startPositionWeight + avgPositionWeight + coverageWeight;
            return (startPositionWeight * distanceFromStartScore
                + coverageWeight * coverageScore
                + avgPositionWeight * averagePositionScore) / weightSum;
        }
        mapTextLengthToScoreRange(value) {
            const maxValue = this.maxLengthLimit;
            if (value <= 0) {
                return 0.0;
            }
            if (value >= maxValue) {
                return 1.0;
            }
            //Linear part: f(100) = 0.9
            const linearThreshold = 100;
            const linearMaxOutput = 0.9;
            if (value <= linearThreshold) {
                return (value / linearThreshold) * linearMaxOutput;
            }
            //Asymptotic part.
            const remainingValue = value - linearThreshold;
            const remainingRange = maxValue - linearThreshold;
            const asymptoteBase = 0.5; //Adjust this to control the curve. Higher values make it steeper.
            return linearMaxOutput + (1 - linearMaxOutput) * (1 - Math.exp(-asymptoteBase * remainingValue / remainingRange));
        }
        calculateRecencyBonus(itemTimestampMs, nowMs) {
            if (itemTimestampMs <= 0) {
                return 0;
            }
            const elapsedDays = (nowMs - itemTimestampMs) / (24 * 60 * 60 * 1000);
            if (elapsedDays >= 365) {
                return 0;
            }
            const halfLifeInDays = 30;
            const decayRate = Math.log(2) / halfLifeInDays;
            return Math.exp(-decayRate * elapsedDays);
        }
        addItem(item) {
            this.itemCache.put(item.getUniqueId(), item);
        }
        /**
         * Add an item if there is room in the cache.
         * @param item
         */
        offerItem(item) {
            if (!this.itemCache.isFull()) {
                this.itemCache.put(item.getUniqueId(), item);
            }
        }
        async queryServer(query) {
            const cachedResponse = this.queryResponseCache.get(query.normalizedQuery);
            if (cachedResponse) {
                return cachedResponse;
            }
            return new Promise((resolve, reject) => {
                const queryKey = query.normalizedQuery;
                const queue = this.serverQueryQueue.get(queryKey);
                if (queue) {
                    queue.push({ resolve, reject });
                }
                else {
                    this.serverQueryQueue.set(queryKey, [{ resolve, reject }]);
                }
                this.currentPendingServerQuery = query;
                this.throttledServerQuery();
            });
        }
        sendPendingServerQuery() {
            const query = this.currentPendingServerQuery;
            if (query === null) {
                return;
            }
            const responseHandlers = this.serverQueryQueue.get(query.normalizedQuery) || [];
            this.serverQueryQueue.delete(query.normalizedQuery);
            //Cancel and fail all other requests that are still in the queue.
            //We will only send the most recent query to the server.
            const cancelError = new QueryCancelledError();
            for (const queueItems of this.serverQueryQueue.values()) {
                for (const item of queueItems) {
                    item.reject(cancelError);
                }
            }
            //UI: When this is non-zero, a loading indicator will be shown.
            this.activeServerQueries(this.activeServerQueries() + 1);
            this.searchAction.ajax({
                data: {
                    query: query.originalQuery,
                    presentMenuUrls: JSON.stringify(Array.from(this.relativeMenuUrls))
                },
                dataType: 'json'
            }).done((response) => {
                logger.log('Server query response:', response);
                const items = [];
                for (const itemData of response.data.items) {
                    const item = unserializeItem(itemData);
                    if (item) {
                        //Exclude items associated with missing menu items.
                        if (item instanceof DashboardItem) {
                            const menuUrl = item.getMenuUrl();
                            if (menuUrl && !this.relativeMenuUrls.has(menuUrl)) {
                                continue;
                            }
                        }
                        //Cache items, and reuse the existing instance if we get the same item again.
                        const existingItem = this.itemCache.get(item.getUniqueId());
                        if (existingItem) {
                            items.push(existingItem);
                        }
                        else {
                            items.push(item);
                            this.itemCache.put(item.getUniqueId(), item);
                        }
                    }
                }
                const serverResponse = {
                    items: items,
                    hasMore: !!response.data.hasMore
                };
                this.queryResponseCache.put(query.normalizedQuery, serverResponse);
                for (const item of responseHandlers) {
                    item.resolve(serverResponse);
                }
            }).fail((_, textStatus, error) => {
                logger.error('AJAX error:', textStatus, error);
                let errorInstance;
                if (error instanceof Error) {
                    errorInstance = error;
                }
                else {
                    errorInstance = new Error((error ? '' : 'AJAX error: ') + error);
                }
                for (const item of responseHandlers) {
                    item.reject(errorInstance);
                }
            }).always(() => {
                const activeQueries = this.activeServerQueries();
                this.activeServerQueries(Math.max(0, activeQueries - 1));
            });
        }
    }
    class QueryCancelledError extends Error {
        constructor() {
            super('Query cancelled');
        }
    }
    /**
     * Query parameter used to pass the target selector to the next page.
     * Must match the value in our PHP module.
     * @private
     */
    AmeQuickSearchTool.NavigationSelectorParam = 'ame-qs-target-selector';
    function completeNavigation(targetSelector) {
        if (!targetSelector) {
            return;
        }
        //Unmark the previous target, if any.
        const highlightClass = 'ame-qs-navigation-target';
        jQuery('.' + highlightClass).removeClass(highlightClass);
        //Find the new target.
        let $target = queryAdvancedSelector(targetSelector);
        if ($target.length < 1) {
            return;
        }
        //If it's a form control inside a label, use the label instead.
        //Or, if it has a separate label, highlight both.
        const $ancestorLabel = $target.closest('label');
        if ($ancestorLabel.length > 0) {
            $target = $ancestorLabel;
        }
        else {
            const id = $target.prop('id');
            if (id) {
                const $label = jQuery('label[for="' + id + '"]');
                if ($label.length > 0) {
                    $target = $target.add($label);
                }
            }
        }
        //Highlight the target and scroll it into view.
        $target.addClass(highlightClass);
        $target.get(0).scrollIntoView({ block: 'center' });
    }
    AmeQuickSearchTool.completeNavigation = completeNavigation;
    class MenuItemCrawlerInfo {
        constructor(menuItem, reason, request = null, previousCrawlAttempt = null) {
            this.menuItem = menuItem;
            this.reason = reason;
            this.request = request;
            this.previousCrawlAttempt = previousCrawlAttempt;
            this.totalUniqueItems = ko.observable(null);
        }
    }
    class PageStats {
        constructor() {
            this.phpPeakMemoryUsage = ko.observable(-1);
            this.phpMemoryLimit = ko.observable('');
            this.pageGenerationTime = ko.observable(-1);
            this.formattedPeakMemoryUsage = ko.pureComputed(() => {
                const usage = this.phpPeakMemoryUsage();
                if (usage <= 0) {
                    return '';
                }
                const usageInMb = usage / (1024 * 1024);
                return usageInMb.toFixed(1);
            });
            this.formattedPageGenerationTime = ko.pureComputed(() => {
                const time = this.pageGenerationTime();
                if (time <= 0) {
                    return '';
                }
                return time.toFixed(3);
            });
            this.memoryUsageTitle = ko.pureComputed(() => {
                const basicTitle = 'Peak PHP memory usage';
                const usage = this.phpPeakMemoryUsage();
                const memoryLimit = this.phpMemoryLimit();
                if ((usage <= 0) || (memoryLimit === '') || (memoryLimit === '-1')) {
                    return basicTitle;
                }
                //Convert the memory limit to bytes.
                const limitMatch = memoryLimit.match(/^(\d+)([KMG]?)$/);
                if (!limitMatch) {
                    return basicTitle;
                }
                let limitInBytes = parseInt(limitMatch[1]);
                switch (limitMatch[2]) {
                    case 'K':
                        limitInBytes *= 1024;
                        break;
                    case 'M':
                        limitInBytes *= 1024 * 1024;
                        break;
                    case 'G':
                        limitInBytes *= 1024 * 1024 * 1024;
                        break;
                }
                const usageInMb = usage / (1024 * 1024);
                const limitInMb = limitInBytes / (1024 * 1024);
                const percentageUsed = (usageInMb / limitInMb) * 100;
                const formattedLimit = (Math.floor(limitInMb) === limitInMb) ? limitInMb.toFixed(0) : limitInMb.toFixed(1);
                return basicTitle + '\n' + percentageUsed.toFixed(1) + '% of ' + formattedLimit + ' MiB';
            });
        }
        push(data) {
            switch (data[0]) {
                case 'phpPeakMemoryUsage':
                    this.phpPeakMemoryUsage(parseInt(data[1]));
                    break;
                case 'phpMemoryLimit':
                    this.phpMemoryLimit('' + data[1]);
                    break;
                case 'pageGenerationTime':
                    this.pageGenerationTime(parseFloat(data[1]));
                    break;
            }
        }
    }
    AmeQuickSearchTool.PageStats = PageStats;
    class QuickSearchApp {
        constructor(deps) {
            this.deps = deps;
            this.searchInput = ko.observable('');
            this.searchResults = ko.observableArray([]);
            this.maxResults = 100;
            this.recentItemsLimit = 30;
            this.isVisible = ko.observable(false);
            this.isSearchBoxFocused = ko.observable(false);
            this.selectedResult = ko.observable(null);
            this.selectedResultIndex = -1;
            this.currentAdminMenuItem = null;
            /**
             * Admin menu items that are present on the current page.
             * @private
             */
            this.presentAdminMenuItems = [];
            this.tabs = new AmeKnockoutTabCollection([
                {
                    title: 'Search',
                    slug: 'search',
                },
                {
                    title: 'Crawler',
                    slug: 'crawler',
                },
            ]);
            this.crawler = ko.observable(null);
            this.menuCrawlerMeta = ko.observableArray([]);
            this.isCrawlerRunning = ko.pureComputed(() => {
                const crawler = this.crawler();
                if (crawler) {
                    return crawler.isRunning();
                }
                return false;
            });
            this.crawlerTabs = new AmeKnockoutTabCollection([
                {
                    title: 'Menus',
                    slug: 'menus',
                    count: ko.pureComputed(() => this.menuCrawlerMeta().length),
                },
                {
                    title: 'Active',
                    slug: 'active',
                    count: ko.pureComputed(() => this.crawler()?.activeRequests().length || 0),
                },
                {
                    title: 'Queue',
                    slug: 'queue',
                    count: ko.pureComputed(() => this.crawler()?.crawlRequestQueue.size || 0),
                },
                {
                    title: 'Finished',
                    slug: 'finished',
                    count: ko.pureComputed(() => this.crawler()?.finishedRequests.size || 0),
                }
            ]);
            this.pageStatsEnabled = ko.observable(true);
            this.pageStatsVisible = ko.pureComputed(() => {
                return this.pageStatsEnabled() && (this.pageStats.phpPeakMemoryUsage() >= 0);
            });
            this.updateInitialResults = (results) => {
                this.searchResults(results);
            };
            this.updateFullResults = (results) => {
                this.searchResults(results);
                // logger.log('Full results:', results);
            };
            this.itemUrlFormatter = (url) => {
                url = this.replaceUserPlaceholderInUrl(url);
                if (url.startsWith('http')) {
                    //Try to make the URL relative to the admin dashboard URL.
                    const adminBase = this.adminUrl.origin + this.adminUrl.pathname;
                    if (url.startsWith(adminBase)) {
                        url = url.substring(adminBase.length);
                    }
                }
                return url;
            };
            this.selectedResultDisplayUrl = ko.pureComputed(() => {
                const selection = this.selectedResult();
                if (!selection) {
                    return '';
                }
                return selection.item.getStatusBarText(this.itemUrlFormatter);
            });
            this.searchProgressIndicatorVisible = ko.pureComputed(() => {
                return this.searchEngine.activeServerQueries() > 0;
            });
            this.isIndexUpdateRunning = ko.observable(false);
            this.canStartIndexUpdate = ko.pureComputed(() => {
                if (this.isIndexUpdateRunning()) {
                    return false;
                }
                const crawler = this.crawler();
                return !(crawler && crawler.isRunning());
            });
            this.lastIndexUpdateError = ko.observable('');
            this.lastIndexUpdateResult = ko.observable(null);
            this.crawlerStatusMessage = ko.pureComputed(() => {
                const crawler = this.crawler();
                let state = null;
                if (crawler) {
                    state = crawler.getState();
                    if (state === 'running') {
                        return ('Indexing admin pages (' + crawler.getFinishedRequestCount()
                            + ' of ' + crawler.getTotalRequestCount() + ')...');
                    }
                }
                if (this.isIndexUpdateRunning()) {
                    if ((state === 'idle') || !state) {
                        return 'Index update starting...';
                    }
                    else {
                        return 'Index update finishing...';
                    }
                }
                if (this.lastIndexUpdateError()) {
                    return 'Error: ' + this.lastIndexUpdateError();
                }
                const lastResult = this.lastIndexUpdateResult();
                if (lastResult !== null) {
                    return 'Index update completed. ' + lastResult + ' pages crawled.';
                }
                if (crawler) {
                    return 'Crawler status: ' + crawler.getState();
                }
                else {
                    return 'Crawler not started yet.';
                }
            });
            this.showCrawlerStatusInStatusBar = ko.pureComputed(() => {
                return this.isIndexUpdateRunning();
            });
            this.crawlerUrlBlacklist = lazy(() => new CrawlerUrlBlacklist([
                /^(post-new|media-new|link-add|user-new)\.php/,
                /^(update-core|plugin-install|plugin-editor|theme-editor|customize|site-editor|widgets)\.php/,
                /^(import|export|site-health)\.php$/,
                /^(erase-personal-data|export-personal-data)\.php/,
                //Some table view links, like the "Mine" link on the "Posts" page, contain a user ID.
                //Let's exclude all links like that. Otherwise, we would get a lot of duplicate pages.
                /[?&](author|user_id)=\d+/,
                //Some scanners will replace the current user ID with a placeholder. Skip those as well.
                new RegExp(escapeRegExp(userIdQueryPlaceholder)),
                //The Admin Customizer is not a normal admin page.
                /page=ame-admin-customizer/,
            ]));
            this.pendingDashboardIndexUpdates = new Map();
            this.dashboardIndexUpdateTimeout = null;
            this.crawlerOfferDetailsVisible = ko.observable(false);
            this.firstAutoCrawlStarted = false;
            const scriptData = deps.scriptData;
            this.adminUrl = deps.adminUrl;
            this.pageStats = deps.pageStats();
            this.currentUserId = scriptData.currentUserId;
            this.crawlerConfig = { ...scriptData.crawlerConfig };
            this.geometryStore = new PanelGeometryStore(scriptData.siteCookiePath);
            this.recencyTracking = scriptData.recencyTracking;
            this.ajaxActions = AjawV2.createStrictActionMap(scriptData.ajaxActions, ['search', 'updateIndex', 'setCrawlerEnabled', 'getCrawlRecords', 'setCrawlRecords']);
            this.requestNavigation = (url, targetSelector, openInNewTab = false) => {
                //If it's a relative URL, make it absolute.
                if (!url.startsWith('http')) {
                    url = new URL(url, this.adminUrl).toString();
                }
                url = this.replaceUserPlaceholderInUrl(url);
                logger.log('New url:', url, 'Target selector:', targetSelector, 'Current url:', window.location.href);
                if (url === window.location.href) {
                    completeNavigation(targetSelector);
                    return;
                }
                //Pass the target selector to the next page. Then use completeNavigation() on
                //the next page to highlight the target and scroll it into view.
                const targetUrl = new URL(url, window.location.href);
                if (targetSelector) {
                    targetUrl.searchParams.set(AmeQuickSearchTool.NavigationSelectorParam, JSON.stringify({ selector: targetSelector, nonce: scriptData.navigationNonce }));
                }
                if (openInNewTab) {
                    window.open(targetUrl.toString(), '_blank');
                }
                else {
                    window.location.href = targetUrl.toString();
                }
            };
            //Show a large placeholder item while recent items are being loaded asynchronously.
            //This gives the app a reasonable initial height and mitigates some layout and positioning
            //issues caused by the height changing dramatically after the results finish loading.
            //If the user resizes the app this becomes unnecessary, but it's harmless.
            this.searchResults.push(new SearchResult(new LoadingPlaceholderItem(), 1));
            const adminMenuParserResult = this.parseAdminMenuItems();
            this.currentAdminMenuItem = adminMenuParserResult.currentMenuItem;
            //Save admin menu items - they'll be used by the crawler.
            adminMenuParserResult.items.forEach((item) => {
                if (item instanceof AdminMenuItem) {
                    this.presentAdminMenuItems.push(item);
                }
            });
            const initialItems = adminMenuParserResult.items;
            const findItemByRelativeUrl = (url) => {
                if (url in adminMenuParserResult.itemsByRelativeUrl) {
                    const item = adminMenuParserResult.itemsByRelativeUrl[url];
                    return item.getTitleParts();
                }
                return null;
            };
            const findEntityIconClass = (item) => {
                if (!scriptData.contentTypeIcons || !item.contentType || (item.kind === 'user')) {
                    return null;
                }
                if (typeof scriptData.contentTypeIcons[item.kind][item.contentType] === 'string') {
                    return scriptData.contentTypeIcons[item.kind][item.contentType];
                }
                return null;
            };
            const blacklist = this.crawlerUrlBlacklist();
            this.presentFilteredMenuUrls = new Set(this.presentAdminMenuItems
                .map((item) => item.getRelativeMenuUrl())
                .filter((url) => {
                if (!url) {
                    return false;
                }
                return !blacklist.isBlacklisted(url);
            }));
            //Add preloaded items.
            for (const itemData of scriptData.preloadedItems) {
                const item = unserializeItem(itemData);
                //Drop items that reference missing or blacklisted menus.
                if (item instanceof DashboardItem) {
                    const menuUrl = item.getMenuUrl();
                    if (menuUrl && !this.presentFilteredMenuUrls.has(menuUrl)) {
                        continue;
                    }
                }
                initialItems.push(item);
            }
            //Populate item used/visited timestamps.
            this.itemUsageTracker = deps.itemUsageTracker();
            const populateTimestamps = this.itemUsageTracker.populateUsageTimestamps(initialItems);
            this.searchEngine = new SearchEngine(initialItems, this.updateInitialResults, this.updateFullResults, findItemByRelativeUrl, findEntityIconClass, this.presentFilteredMenuUrls, this.ajaxActions.search);
            //Prepopulate the search results with recent items. We do this by searching for
            //and empty string. However, since timestamps are populated asynchronously, it's
            //theoretically possible that the user could have searched for something before
            //timestamps are done, so we need a "first request" check.
            populateTimestamps.finally(() => {
                //The search box starts out empty. Or, if the user has already searched for
                //something, this will refresh the results taking timestamps into account.
                this.runCurrentQuery();
            });
            this.searchInput.subscribe(() => {
                this.runCurrentQuery();
            });
            this.searchResults.subscribe((newValue) => {
                //Try to maintain the previous selection if it was the result of user input.
                //We assume that if the user moves selection away (down) from the first result,
                //they are likely to want to keep that selection.
                const previousSelectedResult = this.selectedResult();
                if (previousSelectedResult && (this.selectedResultIndex > 0)) {
                    const index = newValue.findIndex((result) => result.item === previousSelectedResult.item);
                    if (index >= 0) {
                        //The selection is still valid, just update the index.
                        this.selectedResultIndex = index;
                        return;
                    }
                    //Fall through to the default selection logic.
                }
                //Select the first result by default.
                if (newValue.length > 0) {
                    this.selectedResultIndex = 0;
                    this.selectedResult(newValue[0]);
                }
                else {
                    this.selectedResultIndex = -1;
                    this.selectedResult(null);
                }
            });
            this.removableQueryArgs = scriptData.removableQueryArgs;
            this.normalizeUrlForCrawling = (url, baseUrl) => {
                return normalizeUrl(url, baseUrl, this.removableQueryArgs, true).toString();
            };
            //Enable "last visited" and "last used" tracking when the user interacts with
            //the app for the first time.
            if (this.recencyTracking === 'enableOnFirstUse') {
                let searchSubscription = null;
                searchSubscription = this.searchInput.subscribe((query) => {
                    if (query !== '') {
                        searchSubscription?.dispose();
                        this.recencyTracking = 'enabled';
                        //This temporary cookie tells the server to enable recency tracking
                        //in module settings. Once done, the module will delete the cookie.
                        Cookies.set('ame-qs-recency-tracking', 'enabled', {
                            expires: 30,
                            sameSite: 'Lax',
                            path: scriptData.adminCookiePath
                        });
                        // noinspection JSIgnoredPromiseFromCall
                        deps.recordCurrentVisit().then(() => {
                            logger.log('First use visit recorded, recency tracking enabled.');
                        });
                    }
                });
            }
            this.crawlerOfferVisible = ko.observable(this.crawlerConfig.enabled === 'ask');
            if (!this.crawlerConfig.crawlerTabVisible) {
                this.tabs.hideTab('crawler');
            }
            //Automatically run an incremental crawl when the user opens the app.
            if (this.crawlerConfig.enabled === 'enabled') {
                //Add a small delay so as not to interfere with the initial search, if any.
                setTimeout(() => {
                    this.runIncrementalCrawl();
                }, 1000);
            }
        }
        display() {
            //Select the "Search" tab. This is usually the default tab, but if the user
            //has switched to another tab and then presses the hotkey again, we switch back.
            this.tabs.switchToTabBySlug('search');
            //Display the app.
            if (!this.isVisible()) {
                this.isVisible(true);
            }
            //Focus the search box.
            this.isSearchBoxFocused(true);
        }
        runCurrentQuery(onlyIfNonEmpty = false) {
            const query = this.searchInput();
            const isEmpty = (query.trim() === '');
            if (onlyIfNonEmpty && isEmpty) {
                return;
            }
            this.searchEngine.search(query, isEmpty ? this.recentItemsLimit : this.maxResults);
        }
        handleAppKeyDown(_, event) {
            // logger.log('App Key down', event);
            if (event.key === 'Escape') {
                // logger.log('Escape key pressed', event);
                this.isVisible(false);
                return false;
            }
            return true;
        }
        handleSearchBoxKeyDown(_, event) {
            switch (event.key) {
                case 'ArrowDown':
                    this.moveSelection(1);
                    return false;
                case 'ArrowUp':
                    this.moveSelection(-1);
                    return false;
                case 'Enter':
                    // logger.log('Enter key pressed', event);
                    this.performSelectionAction(event.ctrlKey);
                    return false;
            }
            return true;
        }
        performSelectionAction(newTab = false) {
            const selection = this.selectedResult();
            if (selection) {
                if (this.recencyTracking !== 'disabled') {
                    //To save time for the user, we don't wait for the timestamp update to complete.
                    // noinspection JSIgnoredPromiseFromCall
                    this.itemUsageTracker.recordItemAction(selection.item);
                }
                const done = selection.item.performAction(this.requestNavigation, newTab);
                if (done && !newTab) {
                    this.isVisible(false);
                }
            }
        }
        moveSelection(direction) {
            const currentResults = this.searchResults();
            if (currentResults.length === 0) {
                return;
            }
            this.selectedResultIndex = (this.selectedResultIndex + direction + currentResults.length) % currentResults.length;
            this.selectedResult(currentResults[this.selectedResultIndex]);
        }
        handleResultClick(result, newTab = false) {
            this.selectedResult(result);
            this.performSelectionAction(newTab);
        }
        replaceUserPlaceholderInUrl(url) {
            if (this.currentUserId && url.includes(userIdQueryPlaceholder)) {
                return url.replace(userIdQueryPlaceholder, this.currentUserId.toString());
            }
            return url;
        }
        parseAdminMenuItems() {
            const parserResult = this.deps.adminMenuParserResult();
            return {
                currentMenuItem: parserResult.currentMenuItem,
                items: parserResult.items,
                itemsByRelativeUrl: parserResult.itemsByRelativeUrl
            };
        }
        getCurrentAdminMenuItem() {
            return this.currentAdminMenuItem;
        }
        // noinspection JSUnusedGlobalSymbols -- Called from the KO template.
        stopCrawler() {
            const crawler = this.crawler();
            if (!crawler) {
                alert('Crawler not available.');
                return;
            }
            crawler.stop();
        }
        // noinspection JSUnusedGlobalSymbols -- Also called from the KO template.
        crawlCurrentPage() {
            const currentMenuItem = this.getCurrentAdminMenuItem();
            if (!currentMenuItem) {
                alert('Error: Could not find the current admin menu item.');
                return;
            }
            this.updateDashboardItemIndex([currentMenuItem], true)
                .then(r => logger.log('Current page crawl done:', r));
        }
        // noinspection JSUnusedGlobalSymbols - More KO template stuff.
        runIncrementalCrawl() {
            this.updateDashboardItemIndex(this.presentAdminMenuItems)
                .then(r => logger.log('Incremental crawl done:', r));
        }
        async updateDashboardItemIndex(menuItems, bypassSchedulingChecks = false) {
            this.isIndexUpdateRunning(true);
            this.lastIndexUpdateError('');
            this.lastIndexUpdateResult(null);
            return this._updateDashboardItemIndexInternal(menuItems, bypassSchedulingChecks)
                .then((result) => {
                if (result.isRight()) {
                    this.lastIndexUpdateResult(result.value);
                }
                else if (result.isLeft()) {
                    this.lastIndexUpdateError(result.value.message);
                }
                return result;
            })
                .catch((err) => {
                if (err instanceof Error) {
                    this.lastIndexUpdateError(err.message);
                    return Either.left(err);
                }
                else {
                    logger.error('An unknown error index update occurred:', err);
                    this.lastIndexUpdateError('An unknown error occurred.');
                    return Either.left(new Error('An unknown error occurred.'));
                }
            }).finally(() => {
                logger.info('Index update finished.');
                this.isIndexUpdateRunning(false);
            });
        }
        async _updateDashboardItemIndexInternal(menuItems, bypassSchedulingChecks = false) {
            logger.info('Starting ' + (bypassSchedulingChecks ? '' : 'incremental ') + 'index update for', menuItems.length, 'menu items...');
            const oldCrawler = this.crawler();
            if (oldCrawler && oldCrawler.isRunning()) {
                return Either.left(new Error('Crawler is already running.'));
            }
            //To avoid overloading the site, don't start a new crawl if one is already running
            //in another tab. We use a short-lived cookie to track this.
            const crawlerRunningCookie = 'ame-qs-crawler-running';
            if (Cookies.get(crawlerRunningCookie)) {
                return Either.left(new Error('Skipping index update because another crawl is already running.'));
            }
            const expirationInMinutes = 45;
            Cookies.set(crawlerRunningCookie, '1', {
                sameSite: 'Lax',
                expires: new Date(Date.now() + expirationInMinutes * 60 * 1000)
            });
            this.crawler(null);
            const blacklist = this.crawlerUrlBlacklist();
            const crawlerDb = new DashboardCrawlerDb(this.ajaxActions, this.crawlerConfig.preloadedRecords);
            //First, run checks that don't require network requests. This validates menu URLs,
            //checks them against the blacklist, and so on.
            const maybeCrawl = [];
            for (const item of menuItems) {
                const couldCrawl = this.couldCrawlMenuItem(item, blacklist);
                if (couldCrawl.isRight()) {
                    maybeCrawl.push(item);
                }
                else if (couldCrawl.isLeft()) {
                    const menuUrl = item.getRelativeMenuUrl();
                    this.menuCrawlerMeta.push(new MenuItemCrawlerInfo(item, couldCrawl.value, null, menuUrl ? crawlerDb.getCachedLastAttemptDate(menuUrl) : null));
                }
            }
            //Prefetch crawl records for all remaining URLs in one go.
            await crawlerDb.prefetchUrlRecords(maybeCrawl
                .map(item => item.getRelativeMenuUrl())
                .filter(url => !!url));
            //Now determine which of the valid items we want to crawl right now.
            const potentialRequests = [];
            //Prevent duplicate requests. Multiple menu items can have the same URL.
            const usedMenuUrls = new Set();
            for (const item of maybeCrawl) {
                const menuUrl = item.getRelativeMenuUrl();
                if (usedMenuUrls.has(menuUrl)) {
                    this.menuCrawlerMeta.push(new MenuItemCrawlerInfo(item, 'Duplicate URL'));
                    continue;
                }
                const reason = await this.shouldCrawlMenuItemNow(item, crawlerDb, bypassSchedulingChecks);
                const previousAttemptDate = crawlerDb.getCachedLastAttemptDate(menuUrl);
                if (reason.isLeft()) {
                    this.menuCrawlerMeta.push(new MenuItemCrawlerInfo(item, reason.value, null, previousAttemptDate));
                    continue;
                }
                if (reason.isRight()) {
                    const request = new CrawlRequest(menuUrl, this.normalizeUrlForCrawling(item.getUrl(), window.location.href), this.adminUrl, item.getLocation(), 0, reason.value, this.getMenuComponent(menuUrl));
                    const meta = new MenuItemCrawlerInfo(item, reason.value, request, previousAttemptDate);
                    potentialRequests.push([request, meta]);
                    usedMenuUrls.add(menuUrl);
                    this.menuCrawlerMeta.push(meta);
                }
            }
            if (potentialRequests.length < 1) {
                Cookies.remove(crawlerRunningCookie, { sameSite: 'Lax' });
                return Either.right(0);
            }
            const crawler = new Crawler(this.adminUrl, this.currentUserId, this.removableQueryArgs, this.normalizeUrlForCrawling, blacklist);
            this.crawler(crawler);
            let crawledPageCount = 0;
            for (const [request, meta] of potentialRequests) {
                if (crawler.addCrawlRequest(request)) {
                    request.onceStatus('loading', () => {
                        crawlerDb.recordMenuAttemptStart(request.relativePageUrl, request.depth, request.reason, request.componentAndVersion);
                    });
                    request.onceTreeStopped((anyRequestsAborted, summary) => {
                        const isFinished = !anyRequestsAborted;
                        logger.log('Tree stopped:', request.menuUrl, request.statusSummary());
                        let error = request.currentError
                            .map((err) => err.message)
                            .getOrElse(() => '');
                        if (!isFinished) {
                            const abortError = summary.statuses['aborted'] + ' of ' + summary.totalRequests
                                + ' request(s) aborted. Found items will be discarded.';
                            if (error) {
                                error = abortError + ' Additionally, there was a request error: ' + error;
                            }
                            else {
                                error = abortError;
                            }
                        }
                        crawlerDb.recordMenuAttemptEnd(request.relativePageUrl, request.status(), error, isFinished);
                        if (isFinished) {
                            const allItems = [...crawler.getFoundItems(request.menuUrl)];
                            logger.log('All items for menu URL "' + request.menuUrl + '":', allItems);
                            this.storeFoundItemsForMenu(request.menuUrl, allItems);
                            meta.totalUniqueItems(allItems.length);
                        }
                        crawledPageCount += summary.statuses.completed;
                    });
                }
                else {
                    request.markAsError(new Error('Could not add request to the crawl queue'));
                }
            }
            await crawler.start();
            Cookies.remove(crawlerRunningCookie, { sameSite: 'Lax' });
            return Either.right(crawledPageCount);
        }
        couldCrawlMenuItem(menuItem, blacklist) {
            const menuUrl = menuItem.getRelativeMenuUrl();
            if (!menuUrl) {
                return new Left('Menu URL is not a local admin page');
            }
            if (blacklist.isBlacklisted(menuUrl)) {
                return new Left('Menu URL matches the blacklist');
            }
            //The fully qualified URL must point to a page in the WordPress admin dashboard.
            //This is just a sanity check, as this should always be true for menu items that
            //have a non-empty relative URL property.
            const fullUrl = new URL(menuItem.getUrl(), window.location.href);
            if (!isAdminPageUrl(fullUrl, this.adminUrl)) {
                return new Left('Menu URL is not a local admin page (sanity check failed)');
            }
            //Exclude forms of relative links that would be different on every page.
            if (menuItem.targetElement) {
                const href = menuItem.targetElement.attr('href');
                //Empty hrefs link to the current page, so they're always duplicates.
                if (href === '') {
                    return new Left('Menu URL is an empty string');
                }
                //Relative links that only contain a query string or fragment would have
                //a different URL on every page.
                if (href && (href.startsWith('?') || href.startsWith('#'))) {
                    return new Left('Menu URL is only a query string or a fragment');
                }
            }
            return new Right(true);
        }
        async shouldCrawlMenuItemNow(menuItem, crawlerDb, bypassSchedulingChecks = false) {
            /*
            Scan the item if:
            - It has never been scanned before.
            - The plugin or theme that added it has been updated.
            - A different plugin or theme is now associated with it.
            - We don't know which plugin added it, and more than N days have passed since the last scan.
              (This should also apply to themes.)
            Do not scan if:
            - The URL is already in the scan queue (checked outside this function).
            - We tried to crawl the URL less than N hours ago (just in case something goes wrong with
              the other checks, this will prevent excessive crawling).
             */
            if (bypassSchedulingChecks) {
                return new Right('User triggered crawl job');
            }
            const menuUrl = menuItem.getRelativeMenuUrl();
            const timeSinceLastAttempt = await crawlerDb.getTimeSinceLastAttempt(menuUrl);
            const minIntervalInMs = this.crawlerConfig.minCrawlIntervalInHours * 60 * 60 * 1000;
            if (timeSinceLastAttempt < minIntervalInMs) {
                return new Left('Time since last crawl attempt is less than the minimum interval of '
                    + this.crawlerConfig.minCrawlIntervalInHours + ' hours');
            }
            const timeSinceLastFinishedCrawl = await crawlerDb.getTimeSinceLastFinishedCrawl(menuUrl);
            if (!Number.isFinite(timeSinceLastFinishedCrawl)) {
                return new Right('Menu URL has never been crawled');
            }
            const dayInMs = 24 * 60 * 60 * 1000;
            const elapsedInDays = Math.floor(timeSinceLastFinishedCrawl / dayInMs);
            const component = this.getMenuComponent(menuUrl);
            if (component) {
                const oldComponentVersion = await crawlerDb.getComponentVersion(menuUrl);
                const normalizedNewVersion = component || '';
                const normalizedOldVersion = oldComponentVersion || '';
                if (normalizedNewVersion !== normalizedOldVersion) {
                    return new Right('Component version changed from ' + JSON.stringify(oldComponentVersion)
                        + ' to ' + JSON.stringify(component));
                }
                else {
                    //Crawl even if the version hasn't changed, just with a longer interval. There are
                    //rare cases where the contents of a page can change without the version changing,
                    //e.g. if the contents depend on changes in plugin settings.
                    //The other main benefit of periodically crawling each menu is that it helps detect
                    //and clean up removed menus. Their crawl timestamps will eventually get very old.
                    const thresholdInMs = this.crawlerConfig.knownComponentCrawlIntervalInDays * dayInMs;
                    const componentNote = 'Component version has not changed (' + JSON.stringify(component) + ').';
                    if (timeSinceLastFinishedCrawl > thresholdInMs) {
                        return new Right('Time since last crawl is ' + elapsedInDays
                            + ' days, which is more than the threshold of '
                            + this.crawlerConfig.knownComponentCrawlIntervalInDays + ' days. ' + componentNote);
                    }
                    else {
                        return new Left('Time since last crawl is only ~' + elapsedInDays + ' days'
                            + ', but the threshold is '
                            + this.crawlerConfig.knownComponentCrawlIntervalInDays + ' days. ' + componentNote);
                    }
                }
            }
            else {
                const thresholdInMs = this.crawlerConfig.unknownComponentCrawlIntervalInDays * dayInMs;
                if (timeSinceLastFinishedCrawl > thresholdInMs) {
                    return new Right('Time since last crawl is ' + elapsedInDays
                        + ' days, which is more than the threshold of '
                        + this.crawlerConfig.unknownComponentCrawlIntervalInDays + ' days');
                }
                else {
                    return new Left('Time since last crawl is only ~' + elapsedInDays + ' days'
                        + ', but the threshold is '
                        + this.crawlerConfig.unknownComponentCrawlIntervalInDays + ' days');
                }
            }
        }
        getMenuComponent(relativeMenuUrl) {
            if (relativeMenuUrl in this.crawlerConfig.menuComponents) {
                return this.crawlerConfig.menuComponents[relativeMenuUrl];
            }
            return null;
        }
        storeFoundItemsForMenu(menuUrl, items) {
            this.pendingDashboardIndexUpdates.set(menuUrl, items);
            //Add the items to the cache if there's room.
            for (const item of items) {
                this.searchEngine.offerItem(item);
            }
            //Rerun the current query if the app is open. The results will be updated with the new items.
            if (this.isVisible()) {
                this.runCurrentQuery(true);
            }
            if (this.dashboardIndexUpdateTimeout) {
                clearTimeout(this.dashboardIndexUpdateTimeout);
            }
            this.dashboardIndexUpdateTimeout = window.setTimeout(() => {
                const updates = {};
                for (const [url, items] of this.pendingDashboardIndexUpdates) {
                    updates[url] = items.map((item) => item.toJs());
                }
                this.pendingDashboardIndexUpdates.clear();
                this.ajaxActions.updateIndex.post({ updates: JSON.stringify(updates) }, (response) => {
                    logger.log('Dashboard index update response:', response);
                });
            }, 1000);
        }
        // noinspection JSUnusedGlobalSymbols -- For debugging purposes; not used by the app itself.
        debugScanCurrentPage() {
            if (!this.currentAdminMenuItem || !this.currentUserId) {
                logger.error('No current admin menu item found.');
                return;
            }
            logger.log('Current admin menu item:', this.currentAdminMenuItem);
            const scanStart = performance.now();
            const testScanner = new PageScanner(builtinScanners);
            const adminUrl = this.adminUrl;
            const testItemGenerator = testScanner.scan(jQuery('body .wrap'), this.currentAdminMenuItem.getLocation(), window.location.href, adminUrl, this.currentAdminMenuItem.getRelativeMenuUrl(), this.removableQueryArgs, this.currentUserId.toString());
            const testItems = [];
            for (const item of testItemGenerator) {
                testItems.push(item);
            }
            logger.log('Scanner test items:', testItems);
            const scanEnd = performance.now();
            logger.log('Scan time:', scanEnd - scanStart, 'ms');
            //Specifically highlight items with long selectors.
            const suspiciousItems = testItems.filter((item) => {
                if (item instanceof DashboardItem) {
                    const selector = item.getSelector();
                    return selector && (selector.length > 50);
                }
                return false;
            });
            logger.log('Suspicious items:', suspiciousItems);
            this.itemUsageTracker.populateUsageTimestamps(testItems).finally(() => {
                //Add the scanned items to the search index.
                for (const item of testItems) {
                    this.searchEngine.addItem(item);
                }
                //Re-run the search to include the new items.
                this.runCurrentQuery();
            });
        }
        toggleCrawlerOfferDetails() {
            this.crawlerOfferDetailsVisible(!this.crawlerOfferDetailsVisible());
        }
        enableIncrementalCrawl() {
            const oldSetting = this.crawlerConfig.enabled;
            this.updateCrawlerEnabledSetting(true);
            //When the user enables the crawler, start the first crawl automatically.
            if ((oldSetting !== 'enabled') && !this.firstAutoCrawlStarted) {
                this.firstAutoCrawlStarted = true;
                this.runIncrementalCrawl();
            }
        }
        disableIncrementalCrawl() {
            this.updateCrawlerEnabledSetting(false);
        }
        updateCrawlerEnabledSetting(enabled) {
            const setting = enabled ? 'enabled' : 'disabled';
            this.crawlerConfig.enabled = setting;
            this.crawlerOfferVisible(false);
            this.ajaxActions.setCrawlerEnabled.post({ enabled: setting });
        }
    }
    AmeQuickSearchTool.QuickSearchApp = QuickSearchApp;
    class AppDependencyFactory {
        constructor(scriptData) {
            this.scriptData = scriptData;
            this.adminMenuParserResult = lazy(() => parseAdminMenuItems(jQuery('body'), this.adminUrl, this.scriptData.removableQueryArgs));
            this.itemUsageTracker = lazy(() => {
                //Get the site path from the admin URL. WordPress might be installed in a subdirectory,
                //and we want to include that in the store name to avoid conflicts with other sites
                //on the same domain.
                const sitePath = this.adminUrl.pathname.replace(/\/wp-admin\/?$/, '');
                return new ItemUsageTracker(sitePath, 200, this.scriptData.adminCookiePath);
            });
            this.recordCurrentVisit = lazy(() => {
                const promises = [];
                const tracker = this.itemUsageTracker();
                const currentMenuItem = this.adminMenuParserResult().currentMenuItem;
                if (currentMenuItem) {
                    const relativeMenuUrl = currentMenuItem.getRelativeMenuUrl();
                    if (relativeMenuUrl) {
                        promises.push(tracker.recordMenuVisit(relativeMenuUrl));
                    }
                }
                const currentPageUrl = getRelativeAdminPageUrl(window.location.href, this.adminUrl, '', this.scriptData.removableQueryArgs);
                if (currentPageUrl) {
                    promises.push(tracker.recordPageVisit(currentPageUrl));
                }
                return Promise.all(promises);
            });
            this.pageStats = lazy(() => {
                const stats = new PageStats();
                //Load any stats that were added to the global array before the app was initialized.
                if ((typeof wsAmeQuickSearchPageStats !== 'undefined') && Array.isArray(wsAmeQuickSearchPageStats)) {
                    for (const entry of wsAmeQuickSearchPageStats) {
                        stats.push(entry);
                    }
                }
                //Replace the global array with the actual object. This still allows other scripts
                //to add entries by calling the push() method.
                window['wsAmeQuickSearchPageStats'] = stats;
                return stats;
            });
            if (scriptData.adminUrl) {
                this.adminUrl = new URL(scriptData.adminUrl);
            }
            else {
                if (window.location.pathname.includes('/wp-admin/')) {
                    const pathParts = window.location.pathname.split('/wp-admin/', 2);
                    this.adminUrl = new URL(pathParts[0] + '/wp-admin/', window.location.origin);
                }
                else {
                    throw new Error('Admin URL not found.');
                }
            }
        }
    }
    AmeQuickSearchTool.AppDependencyFactory = AppDependencyFactory;
    ko.bindingHandlers['ameQuickSearchVisible'] = {
        update: function (element, valueAccessor) {
            const value = valueAccessor();
            const visible = value.visible;
            if (ko.unwrap(visible)) {
                //Show the search panel in the center of the screen.
                const $panel = jQuery(element).show();
                //Restore the previous position if available.
                const store = ko.unwrap(value.store);
                const position = store.getStoredPositionForJQuery()
                    .getOrElse(() => ({
                    my: 'center',
                    at: 'center center-10%'
                }));
                $panel.position({
                    my: position.my,
                    at: position.at,
                    of: window,
                    collision: 'fit'
                });
                //Ensure it doesn't overlap the Admin Bar.
                const adminBarHeight = jQuery('#wpadminbar').outerHeight() || 0;
                const panelTop = parseInt($panel.css('top'), 10);
                if (panelTop < adminBarHeight) {
                    $panel.css('top', adminBarHeight);
                }
                //Close the panel when the user clicks outside of it.
                if (ko.isObservable(visible)) {
                    jQuery(document).on('mousedown.ameQcSearchPanelHide', function (event) {
                        if (!jQuery(event.target).closest($panel).length) {
                            visible(false);
                        }
                    });
                }
            }
            else {
                jQuery(element).hide();
                //Remove the click outside listener.
                jQuery(document).off('mousedown.ameQcSearchPanelHide');
            }
        }
    };
    ko.bindingHandlers['ameQuickSearchInputFocused'] = {
        init: function (element, valueAccessor) {
            const value = valueAccessor();
            if (ko.isObservable(value)) {
                jQuery(element).on('focus.ameQSFocusBinding', function () {
                    value(true);
                }).on('blur.ameQSFocusBinding', function () {
                    value(false);
                });
            }
            ko.utils.domNodeDisposal.addDisposeCallback(element, function () {
                jQuery(element).off('focus.ameQSFocusBinding').off('blur.ameQSFocusBinding');
            });
        },
        update: function (element, valueAccessor) {
            const value = ko.unwrap(valueAccessor());
            if (value) {
                const $element = jQuery(element);
                if (!$element.is(':focus')) {
                    $element.trigger('focus').trigger('select');
                }
            }
        }
    };
    ko.bindingHandlers['ameAutoScrollToChild'] = {
        update: function (element, valueAccessor) {
            const value = ko.unwrap(valueAccessor());
            if (value === null) {
                return;
            }
            //Find the child that has the value as its KO data.
            const $child = jQuery(element).children().filter(function (_, child) {
                return ko.dataFor(child) === value;
            });
            //Scroll the child into view.
            if ($child.length > 0) {
                $child.get(0).scrollIntoView({ block: 'nearest' });
            }
        }
    };
})(AmeQuickSearchTool || (AmeQuickSearchTool = {}));
jQuery(function () {
    let app = null;
    const appDeps = new AmeQuickSearchTool.AppDependencyFactory(wsAmeQuickSearchData);
    const logger = log.getLogger('AmeQuickSearchTool');
    if (appDeps.scriptData.jsLogLevel) {
        logger.setDefaultLevel(appDeps.scriptData.jsLogLevel);
    }
    if (appDeps.scriptData.recencyTracking === 'enabled') {
        // noinspection JSIgnoredPromiseFromCall
        appDeps.recordCurrentVisit();
    }
    //Navigate to the target element if a selector was passed in the URL. The server-side script
    //will have verified the nonce and added the selector to scriptData.
    if (appDeps.scriptData.navigationTargetSelector) {
        AmeQuickSearchTool.completeNavigation(appDeps.scriptData.navigationTargetSelector);
    }
    //Remove the selector query parameter from the URL. Note that we do this even if the selector
    //was invalid and navigation didn't happen. This is to prevent potential conflicts with adding
    //unnecessary parameters to the URL.
    const url = new URL(window.location.href);
    if (url.searchParams.has(AmeQuickSearchTool.NavigationSelectorParam)) {
        url.searchParams.delete(AmeQuickSearchTool.NavigationSelectorParam);
        window.history.replaceState({}, '', url.toString());
    }
    function handleSearchTrigger() {
        if (!app) {
            const $appRoot = jQuery('#ame-quick-search-root');
            app = new AmeQuickSearchTool.QuickSearchApp(appDeps);
            ko.applyBindings(app, $appRoot.get(0));
            //Make the app resizable.
            const minSize = {
                width: 200,
                height: 100
            };
            const windowSize = {
                width: window.innerWidth,
                height: window.innerHeight
            };
            //Load the previous custom size from a cookie, limiting it to the window size.
            app.geometryStore.getStoredSize().map((size) => {
                let hasAnyCustomSize = false;
                if (size.width && (size.width >= minSize.width) && (size.width <= windowSize.width)) {
                    $appRoot.width(Math.round(size.width));
                    hasAnyCustomSize = true;
                }
                if (size.height && (size.height >= minSize.height) && (size.height <= windowSize.height)) {
                    $appRoot.height(Math.round(size.height));
                    hasAnyCustomSize = true;
                }
                if (hasAnyCustomSize) {
                    $appRoot.addClass('ame-qs-has-custom-size');
                }
            });
            $appRoot.resizable({
                minWidth: minSize.width,
                minHeight: minSize.height,
                stop: function (_, ui) {
                    app?.geometryStore.storeSize(ui.size);
                    $appRoot.addClass('ame-qs-has-custom-size');
                }
            });
            //Also make the app draggable.
            $appRoot.draggable({
                handle: '.ame-qs-header',
                cancel: '.ame-qs-tab-nav li, .ame-qs-stats-property',
                containment: 'window',
                scroll: false,
                stop: function (_, ui) {
                    app?.geometryStore.storePosition(ui.position.top, ui.position.left, ui.helper.outerWidth(), ui.helper.outerHeight());
                }
            });
            //Handle clicks on items in the search results.
            $appRoot.find('.ame-qs-search-results').on('click', '.ame-qs-search-result', function (event) {
                if (app) {
                    const item = ko.dataFor(event.target);
                    if (item) {
                        app.handleResultClick(item, event.ctrlKey);
                    }
                }
            });
        }
        logger.log('Displaying app');
        app.display();
        return false;
    }
    //The default action seems to be "keypress" which works for most shortcuts. However,
    //for some like "shift shift", we need to use "keyup" instead so that it doesn't
    //trigger when the key is just held down.
    const keyboardShortcut = wsAmeQuickSearchData.keyboardShortcut;
    const shortcutAction = keyboardShortcut.indexOf(' ') >= 0 ? 'keyup' : 'keypress';
    function toggleKeyboardShortcut(enabled) {
        if (enabled) {
            wsAmeMousetrap.bind(keyboardShortcut, handleSearchTrigger, shortcutAction);
        }
        else {
            wsAmeMousetrap.unbind(keyboardShortcut, shortcutAction);
        }
    }
    toggleKeyboardShortcut(true);
    //Let the settings tab script turn off the live hotkey while testing shortcut settings.
    jQuery(document).on('adminMenuEditor:qsDisableHotkey', function () {
        toggleKeyboardShortcut(false);
    }).on('adminMenuEditor:qsReEnableHotkey', function () {
        toggleKeyboardShortcut(true);
    });
    //Handle the Toolbar button.
    jQuery('#wp-admin-bar-ame-quick-search-tb')
        .on('click', handleSearchTrigger)
        .addClass('ame-qs-tb-ready');
});
//# sourceMappingURL=quick-search.js.map