var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;
import { DashboardItem } from './items';
import { builtinScanners, PageScanner, parseAdminMenuItems } from './scanner';
import { KoObservableSet } from './utils';
export class Crawler {
    constructor(adminUrl, currentUserId, removableQueryParams, urlBlacklist, onRequestAdded = () => {
    }) {
        this.adminUrl = adminUrl;
        this.currentUserId = currentUserId;
        this.removableQueryParams = removableQueryParams;
        this.urlBlacklist = urlBlacklist;
        this.onRequestAdded = onRequestAdded;
        this.loadNextCallNumber = 0;
        this.state = ko.observable('idle');
        this.stopWhenAllRequestsFinished = true;
        this.pendingCrawlRequests = new KoObservableSet();
        this.crawlRequestQueue = new KoObservableSet();
        this.finishedRequests = new KoObservableSet();
        this.pendingTimeouts = new Set();
        this.addedUrls = new Set();
        this.crawlDepthLimit = 3;
        this.itemsByMenuUrl = new Map();
        this.activeRequests = ko.computed(() => {
            const activeRequests = [];
            for (const request of this.pendingCrawlRequests) {
                const status = request.status();
                if ((status !== 'queued') && !request.isFinished) {
                    activeRequests.push(request);
                }
            }
            return activeRequests;
        });
        this.isRunning = ko.pureComputed(() => {
            return this.state() === 'running';
        });
    }
    async start() {
        if (this.isRunning()) {
            throw new Error('Crawler is already running.');
        }
        this.state('running');
        if (this.stopWhenAllRequestsFinished) {
            console.log('Creating stop subscription.');
            const shouldStop = ko.computed(() => {
                return (this.pendingCrawlRequests.size === 0);
            });
            if (!shouldStop()) {
                shouldStop.subscribe((value) => {
                    if (value && this.isRunning()) {
                        console.log('All requests finished, stopping the crawler.');
                        shouldStop.dispose();
                        this.stopCrawler('completed');
                    }
                });
            }
            else {
                console.log('All requests already finished upon start, stopping the crawler.');
                this.stopCrawler('completed');
            }
            console.log('Stop subscription created.');
        }
        console.log('start(): Entering scanPages loop.');
        for await (const request of this.scanPages()) {
            console.log('Crawl request completed:', request);
            if (!this.isRunning()) {
                console.log('Exiting scanPages loop due crawler being stopped.');
                return;
            }
        }
        if (this.state() === 'running') {
            this.stopCrawler('completed');
        }
    }
    async *scanPages() {
        for await (const page of this.batchLoadPages()) {
            if (!this.isRunning()) {
                return;
            }
            const request = page.crawlRequest;
            request.transitionStatus('scanning');
            //Detect the admin menu that's marked as active in the target page.
            const adminMenuParserResult = parseAdminMenuItems(page.$root, request.adminUrl);
            //Use the detected menu URL if possible.
            let menuUrl;
            if (adminMenuParserResult.currentMenuItem) {
                menuUrl = adminMenuParserResult.currentMenuItem.getRelativeMenuUrl();
            }
            else {
                menuUrl = request.menuUrl;
            }
            //If we end up on a different menu item, the location is probably no longer valid.
            let location = request.location;
            if (menuUrl !== request.menuUrl) {
                location = [];
            }
            const scanner = new PageScanner(builtinScanners);
            const items = scanner.scan(page.$root.find('body .wrap'), location, request.pageUrl, request.adminUrl, menuUrl, this.removableQueryParams, this.currentUserId ? this.currentUserId.toString() : null);
            let currentBatchStart = performance.now();
            const maxBatchDuration = 1000 / 30;
            for await (const item of items) {
                request.addItem(item);
                this.addFoundItem(menuUrl, item);
                if (!this.isRunning()) {
                    return;
                }
                this.maybeAddToQueue(item, request);
                if ((performance.now() - currentBatchStart) > maxBatchDuration) {
                    await this.nextAnimationFramePromise();
                    currentBatchStart = performance.now();
                }
            }
            page.$iframe.remove();
            request.transitionStatus('completed');
            yield request;
        }
    }
    async *batchLoadPages(maxConcurrentLoads = 3) {
        console.log('batchLoadPages(): Entering method.');
        const activePages = new Map();
        let hasMorePages = true;
        while (this.isRunning() && ((activePages.size > 0) || hasMorePages)) {
            console.log('batchLoadPages(): Inside main loop. Active pages:', activePages.size, 'Has more pages:', hasMorePages);
            //Load the next page(s) if we have room.
            while ((activePages.size < maxConcurrentLoads) && hasMorePages) {
                console.log('batchLoadPages(): Adding another page to activePages.');
                //Since Promise.any() doesn't provide the resolved promise, we need this
                //hacky workaround to remove the promise when it resolves.
                const nextPage = this.loadNextPage();
                const key = {};
                activePages.set(key, nextPage.then(pageOption => {
                    return { pageOption, key };
                }));
            }
            //Wait for any page to finish.
            console.log('batchLoadPages(): Waiting for any page to finish.');
            const waitOption = await this.withTimeout(300, Promise.any(activePages.values()));
            if (waitOption.isDefined()) {
                const { pageOption, key } = waitOption.get();
                activePages.delete(key);
                console.log('batchLoadPages(): Page finished loading.', pageOption);
                if (pageOption.isDefined()) {
                    yield pageOption.get();
                }
                else {
                    hasMorePages = false;
                }
            }
        }
    }
    async loadNextPage() {
        this.loadNextCallNumber++;
        if (this.loadNextCallNumber > 100) {
            throw new Error('loadNextPage(): Too many calls to loadNextPage(): ' + this.loadNextCallNumber);
        }
        const thisCallNumber = this.loadNextCallNumber;
        let totalLoops = 0;
        while (this.isRunning()) {
            totalLoops++;
            console.log('loadNextPage(): Top of main loop.', thisCallNumber, totalLoops);
            if (totalLoops > 100) {
                throw new Error('loadNextPage(): Too many loop iterations. Call #' + thisCallNumber);
            }
            const firstRequest = this.crawlRequestQueue.shift();
            if (!firstRequest) {
                console.log('loadNextPage(): No requests in the queue. Waiting.', thisCallNumber);
                const start = performance.now();
                await this.withTimeout(500);
                const elapsed = performance.now() - start;
                console.log('loadNextPage(): Resuming after timeout.', thisCallNumber, elapsed);
            }
            else {
                console.log('loadNextPage(): Calling loadPageFor().', thisCallNumber);
                const result = await this.loadPageFor(firstRequest);
                if (result.isDefined()) {
                    console.log('loadNextPage(): Page loaded successfully.', thisCallNumber);
                    return result;
                }
                else {
                    console.log('loadNextPage(): Page failed to load.', thisCallNumber);
                }
                //Otherwise, move on and try the next request.
            }
        }
        return none;
    }
    async loadPageFor(request) {
        console.log('Loading page:', request.pageUrl);
        if (!request.transitionStatus('loading')) {
            console.error('Failed to transition request to loading status', request);
            return none;
        }
        //Load the page in a hidden iframe.
        //Note: The frame must be *technically* visible or all elements inside it will count as
        //hidden and the scanner will ignore all of them, finding no items.
        const $iframe = jQuery('<iframe/>', {
            style: 'width: 1200; height: 800; position: absolute; top: -10000px; left: -10000px;'
        });
        const frameLoadPromise = new Promise((resolve, reject) => {
            $iframe.on('load', () => {
                console.log('Frame loaded:', request.pageUrl);
                const $root = $iframe.contents();
                if ($root.length === 0) {
                    reject(new Error('Failed to load page: no iframe content document.'));
                    return;
                }
                console.log('Page loaded:', request.pageUrl);
                const $body = $root.find('body');
                if ($body.length === 0) {
                    reject(new Error('Failed to load page: no body element.'));
                    return;
                }
                //Currently, we only support scanning standard admin pages. All of them have
                //the "wp-admin" class on the body element since at least 2009.
                //For example, this check is helpful in cases where an admin menu item has
                //a syntactically valid but non-existent /wp-admin/... URL that shows a 404 page.
                if (!$body.hasClass('wp-admin')) {
                    reject(new Error('Failed to load page: not a standard WordPress admin page.'));
                    return;
                }
                //Give any page scripts a chance to run.
                setTimeout(() => {
                    resolve($root);
                }, 150);
            });
            $iframe.on('error', () => {
                console.error('Frame error:', request.pageUrl);
                reject(new Error('Failed to load page: error event on iframe.'));
            });
        });
        $iframe.prop('src', request.pageUrl);
        jQuery('body').append($iframe);
        const timeout = this.createSkippableTimeout(10000);
        //If the request is aborted, cancel the timeout. This will immediately resolve the promise.
        const koSubscription = request.status.subscribe(status => {
            if (status === 'aborted') {
                koSubscription.dispose();
                timeout.skip();
            }
        });
        try {
            console.log('Waiting for frame load:', request.pageUrl);
            const outcome = await Promise.any([frameLoadPromise, timeout.promise]);
            if (outcome === PromiseTimeoutMarker) {
                console.error('Frame load timed out:', request.pageUrl);
                if (request.status() === 'loading') {
                    request.markAsError(new Error('Failed to load page: timed out.'));
                }
                $iframe.remove();
                return none;
            }
            else {
                console.log('Frame loaded:', request.pageUrl, outcome);
                return some(new LoadedPage(request, outcome, $iframe));
            }
        }
        catch (error) {
            console.error('Frame load failed:', request.pageUrl, error);
            if (error instanceof Error) {
                request.markAsError(error);
            }
            else {
                request.markAsError(new Error('Unknown error'));
            }
            $iframe.remove();
            return none;
        }
        finally {
            koSubscription.dispose();
        }
    }
    addCrawlRequest(request) {
        if (!this.canCrawlUrl(request.pageUrl)) {
            console.warn('Cannot add request to queue: URL is not allowed.', request.pageUrl);
            return false;
        }
        if (!request.transitionStatus('queued')) {
            throw new Error('Cannot add request to queue: invalid request status "' + request.status() + '".');
        }
        this.addedUrls.add(request.pageUrl);
        this.pendingCrawlRequests.add(request);
        this.crawlRequestQueue.add(request);
        this.onRequestAdded(request);
        const koSubscription = request.status.subscribe(status => {
            if (FinalCrawlRequestStatuses.includes(status)) {
                this.finishedRequests.add(request);
                this.crawlRequestQueue.delete(request);
                this.pendingCrawlRequests.delete(request);
                koSubscription.dispose();
            }
        });
        return true;
    }
    nextAnimationFramePromise() {
        return new Promise(resolve => requestAnimationFrame(resolve));
    }
    async withTimeout(timeout, promise) {
        const timeoutInstance = this.createSkippableTimeout(timeout);
        const promises = [timeoutInstance.promise];
        if (promise) {
            promises.push(promise);
        }
        const promiseValue = await Promise.any(promises);
        //Stop the timeout if it hasn't already been finished.
        timeoutInstance.skip();
        if (promiseValue === PromiseTimeoutMarker) {
            return none;
        }
        return some(promiseValue);
    }
    createSkippableTimeout(duration) {
        const timeout = new SkippableTimeout(duration);
        this.pendingTimeouts.add(timeout);
        timeout.promise.finally(() => {
            this.pendingTimeouts.delete(timeout);
        });
        return timeout;
    }
    maybeAddToQueue(item, originatingRequest) {
        if (!(item instanceof DashboardItem)) {
            return;
        }
        if (originatingRequest.depth >= this.crawlDepthLimit) {
            return;
        }
        let reason = '';
        //For now, we only recognize tabs and filters. Admin menu items are handled elsewhere.
        const targetType = item.getTargetType();
        const allowedTargetTypes = ['tab', 'filter'];
        if (allowedTargetTypes.includes(targetType)) {
            const displayLabel = item.ownLabel || item.label;
            reason = 'Found ' + targetType + ' "' + displayLabel + '" on page ' + originatingRequest.relativePageUrl;
        }
        else {
            return;
        }
        const possiblyRelativeUrl = item.getEffectiveTargetUrl();
        if (!possiblyRelativeUrl) {
            return;
        }
        const targetUrl = this.normalizeUrl(possiblyRelativeUrl, originatingRequest.pageUrl);
        // console.log('Checking target URL:', targetUrl);
        if (!this.canCrawlUrl(targetUrl)) {
            return;
        }
        const location = item.getLocation().slice();
        //Add the name of the tab or filter to the location. This will be used to display a more
        //detailed path in the search results. For example, "Menu -> Submenu -> Tab Name".
        if ((targetType === 'tab') || (targetType === 'filter')) {
            if (item.ownLabel) {
                location.push(item.ownLabel);
            }
        }
        const newRequest = new CrawlRequest(item.getMenuUrl() || '', targetUrl, this.adminUrl, location, originatingRequest.depth + 1, reason);
        if (this.addCrawlRequest(newRequest)) {
            originatingRequest.addChildRequest(newRequest);
        }
    }
    normalizeUrl(url, baseUrl) {
        const parsed = new URL(url, baseUrl);
        //URLs that only differ by hash are considered the same.
        parsed.hash = '';
        //Remove known temporary query parameters.
        for (const param of this.removableQueryParams) {
            parsed.searchParams.delete(param);
        }
        return parsed.toString();
    }
    canCrawlUrl(url) {
        if (typeof url === 'string') {
            url = new URL(url);
        }
        //Only HTTP(S) URLs are allowed.
        if (!['http:', 'https:'].includes(url.protocol)) {
            return false;
        }
        //The URL should have the same origin as the admin URL.
        if (url.origin !== this.adminUrl.origin) {
            return false;
        }
        //And also the same path prefix (e.g. "/wp-admin/").
        if (!url.pathname.startsWith(this.adminUrl.pathname)) {
            return false;
        }
        //Check the blacklist.
        if (this.urlBlacklist.isBlacklisted(url.pathname)) {
            return false;
        }
        return !this.addedUrls.has(url.toString());
    }
    addFoundItem(menuUrl, item) {
        if (item instanceof DashboardItem) {
            const itemMenuUrl = item.getMenuUrl();
            if (itemMenuUrl) {
                menuUrl = itemMenuUrl;
            }
        }
        let menuItems = this.itemsByMenuUrl.get(menuUrl);
        if (!menuItems) {
            menuItems = new Map();
            this.itemsByMenuUrl.set(menuUrl, menuItems);
        }
        const id = item.getUniqueId();
        if (!menuItems.has(id)) {
            menuItems.set(id, item);
        }
    }
    getFoundItems(menuUrl) {
        const items = this.itemsByMenuUrl.get(menuUrl);
        if (items) {
            return items.values();
        }
        return [];
    }
    stopCrawler(reason) {
        this.state(reason);
        for (const timeout of this.pendingTimeouts) {
            timeout.skip();
        }
        for (const request of this.crawlRequestQueue) {
            request.abort();
        }
        for (const request of this.pendingCrawlRequests) {
            request.abort();
        }
    }
    stop() {
        this.stopCrawler('stopped');
    }
    getState() {
        return this.state();
    }
    getFinishedRequestCount() {
        return this.finishedRequests.size;
    }
    getTotalRequestCount() {
        return this.pendingCrawlRequests.size + this.finishedRequests.size;
    }
}
const PromiseTimeoutMarker = Symbol('AmePromiseTimeoutMarker');
class SkippableTimeout {
    constructor(duration) {
        this.timeoutId = null;
        this.resolve = null;
        this.promise = new Promise(resolve => {
            this.resolve = resolve;
            this.timeoutId = setTimeout(() => {
                this.timeoutId = null;
                this.resolve = null;
                resolve(PromiseTimeoutMarker);
            }, duration);
        });
    }
    skip() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }
        if (this.resolve) {
            this.resolve(PromiseTimeoutMarker);
            this.resolve = null;
        }
    }
}
const AllCrawlRequestStatuses = ['queued', 'loading', 'loaded', 'scanning', 'error', 'aborted', 'completed'];
const RequestProgressSequence = ['queued', 'loading', 'loaded', 'scanning'];
export const FinalCrawlRequestStatuses = ['error', 'aborted', 'completed'];
const EmptyStatusCounts = {
    queued: 0,
    loading: 0,
    loaded: 0,
    scanning: 0,
    error: 0,
    aborted: 0,
    completed: 0
};
export class CrawlRequest {
    constructor(
    /**
     * Relative menu item URL.
     */
    menuUrl, 
    /**
     * Full page URL.
     */
    pageUrl, adminUrl, location, depth = 0, 
    /**
     * Human-readable reason for the request being created.
     * For example, it could mention that a tab or filter was found on a specific page.
     */
    reason = '', componentAndVersion = null) {
        this.menuUrl = menuUrl;
        this.pageUrl = pageUrl;
        this.adminUrl = adminUrl;
        this.location = location;
        this.depth = depth;
        this.reason = reason;
        this.componentAndVersion = componentAndVersion;
        this.internalStatus = ko.observable('queued');
        this.status = ko.pureComputed(() => {
            return this.internalStatus();
        });
        this._currentError = none;
        this.foundItems = new KoObservableSet();
        this.totalItems = ko.pureComputed(() => {
            return this.foundItems.size;
        });
        this.totalTreeItems = ko.pureComputed(() => {
            let total = this.totalItems();
            for (const child of this.childRequests) {
                total += child.totalTreeItems();
            }
            return total;
        });
        this.childRequests = new KoObservableSet();
        this.statusSummary = ko.pureComputed(() => {
            const statuses = Object.assign({}, EmptyStatusCounts);
            statuses[this.status()]++;
            const selfStopped = this.isFinished;
            let treeStopped = selfStopped;
            let totalRequests = 1;
            for (const child of this.childRequests) {
                const childSummary = child.statusSummary();
                for (const status of AllCrawlRequestStatuses) {
                    statuses[status] += childSummary.statuses[status];
                }
                totalRequests += childSummary.totalRequests;
                treeStopped = treeStopped && childSummary.treeStopped;
            }
            return {
                selfStopped,
                treeStopped,
                totalRequests,
                statuses
            };
        });
        this.isTreeInProgress = ko.pureComputed(() => {
            const children = this.childRequests.items()();
            return (!this.isFinished || children.some(child => child.isTreeInProgress()));
        });
        this.isTreeCompletedOrError = ko.pureComputed(() => {
            //Note that the "aborted" status is not included here. An aborted request is no longer
            //in progress, but it's not really "done" since it was stopped prematurely. Any results
            //should be considered incomplete.
            const thisIsDone = ((this.status() === 'completed') || (this.status() === 'error'));
            const children = this.childRequests.items()();
            return thisIsDone && children.every(child => child.isTreeCompletedOrError());
        });
        this.itemCssClass = ko.pureComputed(() => {
            return 'ame-qs-crawl-request-is-' + this.internalStatus();
        });
        const adminUrlString = adminUrl.toString();
        if (pageUrl.startsWith(adminUrlString)) {
            this.relativePageUrl = pageUrl.slice(adminUrlString.length);
        }
        else {
            this.relativePageUrl = pageUrl;
        }
    }
    transitionStatus(newStatus) {
        const currentStatus = this.internalStatus();
        if (currentStatus === newStatus) {
            return true;
        }
        if (this.isFinalStatus(currentStatus)) {
            console.error(`Cannot transition to new status "${newStatus}" after reaching final status "${this.internalStatus()}".`, this);
            return false;
        }
        if (this.isFinalStatus(newStatus)) {
            this.internalStatus(newStatus);
            return true;
        }
        //Both the current and new statuses are non-final. Is this a valid transition?
        const currentStatusIndex = RequestProgressSequence.indexOf(currentStatus);
        const newStatusIndex = RequestProgressSequence.indexOf(newStatus);
        if (newStatusIndex <= currentStatusIndex) {
            console.error(`Cannot transition from status "${currentStatus}" to new status "${newStatus}": out of order.`, this);
            return false;
        }
        this.internalStatus(newStatus);
        return true;
    }
    abort() {
        this.transitionStatus('aborted');
    }
    isFinalStatus(status) {
        return FinalCrawlRequestStatuses.includes(status);
    }
    markAsError(error) {
        const currentStatus = this.internalStatus();
        if (!this.isFinalStatus(currentStatus)) {
            this._currentError = some(error);
            this.transitionStatus('error');
        }
    }
    get currentError() {
        return this._currentError;
    }
    get errorMessage() {
        return this._currentError.map(error => error.message).getOrElse(() => '');
    }
    get isFinished() {
        return this.isFinalStatus(this.internalStatus());
    }
    addItem(item) {
        this.foundItems.add(item);
    }
    getItems() {
        return this.foundItems;
    }
    addChildRequest(request) {
        this.childRequests.add(request);
    }
    onceStatus(status, callback) {
        if (!Array.isArray(status)) {
            status = [status];
        }
        if (status.includes(this.status())) {
            callback();
            return;
        }
        let subscription = null;
        subscription = this.status.subscribe(newStatus => {
            if (status.includes(newStatus)) {
                subscription?.dispose();
                callback();
            }
        });
    }
    onceStopped(callback) {
        this.onceStatus(['aborted', 'error', 'completed'], callback);
    }
    onceTreeStopped(callback) {
        let isStopped = ko.computed(() => {
            const summary = this.statusSummary();
            if (summary.treeStopped) {
                callback(summary.statuses.aborted > 0, summary);
                return true;
            }
            return false;
        });
        if (isStopped()) {
            isStopped.dispose();
        }
        else {
            isStopped.subscribe(value => {
                if (value) {
                    isStopped.dispose();
                }
            });
        }
    }
}
class LoadedPage {
    constructor(crawlRequest, $root, $iframe) {
        this.crawlRequest = crawlRequest;
        this.$root = $root;
        this.$iframe = $iframe;
        crawlRequest.transitionStatus('loaded');
    }
}
export class CrawlerUrlBlacklist {
    constructor(blacklist) {
        this.blacklist = blacklist;
    }
    isBlacklisted(relativeAdminUrl) {
        //As of this writing, all WordPress admin page URLs I've seen point to PHP files.
        //If the URL doesn't contain ".php", it's probably not a valid admin page.
        if (!this.containsPhpExtension(relativeAdminUrl)) {
            return true;
        }
        //Check the blacklist.
        return this.blacklist.some(pattern => pattern.test(relativeAdminUrl));
    }
    containsPhpExtension(input) {
        return input.includes('.php');
    }
}
//# sourceMappingURL=crawler.js.map