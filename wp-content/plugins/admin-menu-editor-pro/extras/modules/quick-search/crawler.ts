import Option = AmeMiniFunc.Option;
import none = AmeMiniFunc.none;
import some = AmeMiniFunc.some;

import {DashboardItem, ItemLocation, ItemTargetType, SearchableItem} from './items';
import {builtinScanners, PageScanner, parseAdminMenuItems} from './scanner';
import {KoObservableSet} from './utils';

interface CrawlRequestResults {
	request: CrawlRequest;
	items: SearchableItem[];
}

type CrawlerState = 'idle' | 'running' | 'completed' | 'stopped';

export type NormalizeUrlForCrawling = (url: string, baseUrl: string) => string;

export class Crawler {
	private loadNextCallNumber: number = 0;

	private readonly state: KnockoutObservable<CrawlerState> = ko.observable<CrawlerState>('idle');
	private stopWhenAllRequestsFinished: boolean = true;

	private readonly pendingCrawlRequests: KoObservableSet<CrawlRequest> = new KoObservableSet<CrawlRequest>();
	public readonly crawlRequestQueue: KoObservableSet<CrawlRequest> = new KoObservableSet<CrawlRequest>();
	public readonly finishedRequests: KoObservableSet<CrawlRequest> = new KoObservableSet<CrawlRequest>();

	readonly activeRequests: KnockoutComputed<CrawlRequest[]>;

	private readonly pendingTimeouts: Set<SkippableTimeout> = new Set<SkippableTimeout>();
	public readonly isRunning: KnockoutComputed<boolean>;

	private addedUrls: Set<string> = new Set<string>();
	private readonly crawlDepthLimit: number = 3;

	private itemsByMenuUrl: Map<string, Map<string, SearchableItem>> = new Map();

	constructor(
		private readonly adminUrl: URL,
		private readonly currentUserId: number | null,
		private readonly removableQueryParams: string[],
		private readonly normalizeUrl: NormalizeUrlForCrawling,
		private readonly urlBlacklist: CrawlerUrlBlacklist,
		private readonly onRequestAdded: (request: CrawlRequest) => void = () => {
		}
	) {
		this.activeRequests = ko.computed(() => {
			const activeRequests: CrawlRequest[] = [];
			for (const request of this.pendingCrawlRequests) {
				const status = request.status();
				if ((status !== 'queued') && !request.isFinished) {
					activeRequests.push(request);
				}
			}
			return activeRequests;
		})

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
			} else {
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

	private async* scanPages(): AsyncGenerator<CrawlRequest, void, undefined> {
		for await (const page of this.batchLoadPages()) {
			if (!this.isRunning()) {
				return;
			}
			const request = page.crawlRequest;
			request.transitionStatus('scanning');

			//Detect the admin menu that's marked as active in the target page.
			const adminMenuParserResult = parseAdminMenuItems(
				page.$root,
				request.adminUrl
			);
			//Use the detected menu URL if possible.
			let menuUrl: string;
			if (adminMenuParserResult.currentMenuItem) {
				menuUrl = adminMenuParserResult.currentMenuItem.getRelativeMenuUrl();
			} else {
				menuUrl = request.menuUrl;
			}

			//If we end up on a different menu item, the location is probably no longer valid.
			let location = request.location;
			if (menuUrl !== request.menuUrl) {
				location = [];
			}

			const scanner = new PageScanner(builtinScanners);
			const items = scanner.scan(
				page.$root.find('body .wrap'),
				location,
				request.pageUrl,
				request.adminUrl,
				menuUrl,
				this.removableQueryParams,
				this.currentUserId ? this.currentUserId.toString() : null
			);

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

	private async* batchLoadPages(maxConcurrentLoads: number = 3): AsyncGenerator<LoadedPage, void, undefined> {
		console.log('batchLoadPages(): Entering method.');

		interface Wrapper {
			pageOption: Option<LoadedPage>
			key: Object;
		}

		const activePages: Map<Object, Promise<Wrapper>> = new Map();
		let hasMorePages: boolean = true;

		while (this.isRunning() && ((activePages.size > 0) || hasMorePages)) {
			console.log('batchLoadPages(): Inside main loop. Active pages:', activePages.size, 'Has more pages:', hasMorePages);
			//Load the next page(s) if we have room.
			while ((activePages.size < maxConcurrentLoads) && hasMorePages) {
				console.log('batchLoadPages(): Adding another page to activePages.');
				//Since Promise.any() doesn't provide the resolved promise, we need this
				//hacky workaround to remove the promise when it resolves.
				const nextPage = this.loadNextPage();
				const key = {}
				activePages.set(
					key,
					nextPage.then(pageOption => {
						return {pageOption, key};
					})
				);
			}

			//Wait for any page to finish.
			console.log('batchLoadPages(): Waiting for any page to finish.');
			const waitOption = await this.withTimeout(300, Promise.any(activePages.values()));
			if (waitOption.isDefined()) {
				const {pageOption, key} = waitOption.get();
				activePages.delete(key);
				console.log('batchLoadPages(): Page finished loading.', pageOption);

				if (pageOption.isDefined()) {
					yield pageOption.get();
				} else {
					hasMorePages = false;
				}
			}
		}
	}

	private async loadNextPage(): Promise<Option<LoadedPage>> {
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
			} else {
				console.log('loadNextPage(): Calling loadPageFor().', thisCallNumber);
				const result = await this.loadPageFor(firstRequest);
				if (result.isDefined()) {
					console.log('loadNextPage(): Page loaded successfully.', thisCallNumber);
					return result;
				} else {
					console.log('loadNextPage(): Page failed to load.', thisCallNumber);
				}
				//Otherwise, move on and try the next request.
			}
		}

		return none;
	}

	private async loadPageFor(request: CrawlRequest): Promise<Option<LoadedPage>> {
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

		const frameLoadPromise = new Promise<JQuery>((resolve, reject) => {
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
		})

		$iframe.prop('src', request.pageUrl);
		jQuery('body').append($iframe);

		const timeout = this.createSkippableTimeout(10000);

		//If the request is aborted, cancel the timeout. This will immediately resolve the promise.
		const koSubscription = request.status.subscribe(status => {
			if (status === 'aborted') {
				koSubscription.dispose();
				timeout.skip();
			}
		})

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
			} else {
				console.log('Frame loaded:', request.pageUrl, outcome);
				return some(new LoadedPage(request, outcome, $iframe));
			}
		} catch (error) {
			console.error('Frame load failed:', request.pageUrl, error);
			if (error instanceof Error) {
				request.markAsError(error);
			} else {
				request.markAsError(new Error('Unknown error'));
			}
			$iframe.remove();
			return none;
		} finally {
			koSubscription.dispose();
		}
	}

	addCrawlRequest(request: CrawlRequest): boolean {
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

	private nextAnimationFramePromise(): Promise<number> {
		return new Promise(resolve => requestAnimationFrame(resolve));
	}

	private async withTimeout<T>(timeout: number, promise?: Promise<T>): Promise<Option<T>> {
		const timeoutInstance = this.createSkippableTimeout(timeout);

		const promises: Promise<typeof PromiseTimeoutMarker | T>[] = [timeoutInstance.promise];
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

	private createSkippableTimeout(duration: number): SkippableTimeout {
		const timeout = new SkippableTimeout(duration);
		this.pendingTimeouts.add(timeout);
		timeout.promise.finally(() => {
			this.pendingTimeouts.delete(timeout);
		});
		return timeout;
	}

	private maybeAddToQueue(item: SearchableItem, originatingRequest: CrawlRequest) {
		if (!(item instanceof DashboardItem)) {
			return;
		}

		if (originatingRequest.depth >= this.crawlDepthLimit) {
			return;
		}

		let reason: string = '';

		//For now, we only recognize tabs and filters. Admin menu items are handled elsewhere.
		const targetType = item.getTargetType();
		const allowedTargetTypes: ItemTargetType[] = ['tab', 'filter'];
		if (allowedTargetTypes.includes(targetType)) {
			const displayLabel = item.ownLabel || item.label;
			reason = 'Found ' + targetType + ' "' + displayLabel + '" on page ' + originatingRequest.relativePageUrl;
		} else {
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

		const newRequest = new CrawlRequest(
			item.getMenuUrl() || '',
			targetUrl,
			this.adminUrl,
			location,
			originatingRequest.depth + 1,
			reason
		);

		if (this.addCrawlRequest(newRequest)) {
			originatingRequest.addChildRequest(newRequest);
		}
	}

	private canCrawlUrl(url: string | URL): boolean {
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

	private addFoundItem(menuUrl: string, item: SearchableItem) {
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

	getFoundItems(menuUrl: string): Iterable<SearchableItem> {
		const items = this.itemsByMenuUrl.get(menuUrl);
		if (items) {
			return items.values();
		}
		return [];
	}

	private stopCrawler(reason: 'completed' | 'stopped') {
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

	getState(): CrawlerState {
		return this.state();
	}

	getFinishedRequestCount(): number {
		return this.finishedRequests.size;
	}

	getTotalRequestCount(): number {
		return this.pendingCrawlRequests.size + this.finishedRequests.size;
	}
}


const PromiseTimeoutMarker: unique symbol = Symbol('AmePromiseTimeoutMarker');

class SkippableTimeout {
	private timeoutId: ReturnType<typeof setTimeout> | null = null;
	public readonly promise: Promise<typeof PromiseTimeoutMarker>;
	private resolve: null | ((value: typeof PromiseTimeoutMarker) => void) = null;

	constructor(duration: number) {
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

export type CrawlRequestStatus = 'queued' | 'loading' | 'loaded' | 'scanning' | 'error' | 'aborted' | 'completed';
const AllCrawlRequestStatuses: CrawlRequestStatus[] = ['queued', 'loading', 'loaded', 'scanning', 'error', 'aborted', 'completed'];
const RequestProgressSequence: CrawlRequestStatus[] = ['queued', 'loading', 'loaded', 'scanning'];
export const FinalCrawlRequestStatuses: CrawlRequestStatus[] = ['error', 'aborted', 'completed'];

interface RequestTreeStatusSummary {
	selfStopped: boolean;
	treeStopped: boolean;
	totalRequests: number;
	statuses: Record<CrawlRequestStatus, number>;
}

const EmptyStatusCounts: Record<CrawlRequestStatus, number> = {
	queued: 0,
	loading: 0,
	loaded: 0,
	scanning: 0,
	error: 0,
	aborted: 0,
	completed: 0
};

export class CrawlRequest {
	private readonly internalStatus: KnockoutObservable<CrawlRequestStatus> = ko.observable<CrawlRequestStatus>('queued');
	public readonly status: KnockoutComputed<CrawlRequestStatus> = ko.pureComputed(() => {
		return this.internalStatus();
	});

	private _currentError: Option<Error> = none;

	private readonly foundItems: KoObservableSet<SearchableItem> = new KoObservableSet<SearchableItem>();
	public readonly totalItems: KnockoutComputed<number> = ko.pureComputed(() => {
		return this.foundItems.size;
	});
	public readonly totalTreeItems: KnockoutComputed<number> = ko.pureComputed(() => {
		let total = this.totalItems();
		for (const child of this.childRequests) {
			total += child.totalTreeItems();
		}
		return total;
	});

	public readonly relativePageUrl: string;
	public readonly childRequests: KoObservableSet<CrawlRequest> = new KoObservableSet<CrawlRequest>();
	public readonly itemCssClass: KnockoutComputed<string>;

	public readonly statusSummary: KnockoutComputed<RequestTreeStatusSummary> = ko.pureComputed(() => {
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

	public readonly isTreeInProgress: KnockoutComputed<boolean> = ko.pureComputed(() => {
		const children = this.childRequests.items()();
		return (!this.isFinished || children.some(child => child.isTreeInProgress()));
	});

	public readonly isTreeCompletedOrError: KnockoutComputed<boolean> = ko.pureComputed(() => {
		//Note that the "aborted" status is not included here. An aborted request is no longer
		//in progress, but it's not really "done" since it was stopped prematurely. Any results
		//should be considered incomplete.

		const thisIsDone = ((this.status() === 'completed') || (this.status() === 'error'));
		const children = this.childRequests.items()();
		return thisIsDone && children.every(child => child.isTreeCompletedOrError());
	});

	constructor(
		/**
		 * Relative menu item URL.
		 */
		public readonly menuUrl: string,
		/**
		 * Full page URL.
		 */
		public readonly pageUrl: string,
		public readonly adminUrl: URL,
		public readonly location: ItemLocation,
		public readonly depth: number = 0,
		/**
		 * Human-readable reason for the request being created.
		 * For example, it could mention that a tab or filter was found on a specific page.
		 */
		public readonly reason: string = '',
		public readonly componentAndVersion: string | null = null
	) {
		this.itemCssClass = ko.pureComputed(() => {
			return 'ame-qs-crawl-request-is-' + this.internalStatus();
		});

		const adminUrlString = adminUrl.toString();
		if (pageUrl.startsWith(adminUrlString)) {
			this.relativePageUrl = pageUrl.slice(adminUrlString.length);
		} else {
			this.relativePageUrl = pageUrl;
		}
	}

	transitionStatus(newStatus: CrawlRequestStatus) {
		const currentStatus = this.internalStatus();
		if (currentStatus === newStatus) {
			return true;
		}

		if (this.isFinalStatus(currentStatus)) {
			console.error(
				`Cannot transition to new status "${newStatus}" after reaching final status "${this.internalStatus()}".`,
				this
			);
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
			console.error(
				`Cannot transition from status "${currentStatus}" to new status "${newStatus}": out of order.`,
				this
			);
			return false;
		}

		this.internalStatus(newStatus);
		return true;
	}

	abort() {
		this.transitionStatus('aborted');
	}

	private isFinalStatus(status: CrawlRequestStatus): boolean {
		return FinalCrawlRequestStatuses.includes(status);
	}

	markAsError(error: Error) {
		const currentStatus = this.internalStatus();
		if (!this.isFinalStatus(currentStatus)) {
			this._currentError = some(error);
			this.transitionStatus('error');
		}
	}

	get currentError(): AmeMiniFunc.Option<Error> {
		return this._currentError;
	}

	get errorMessage(): string {
		return this._currentError.map(error => error.message).getOrElse(() => '');
	}

	get isFinished(): boolean {
		return this.isFinalStatus(this.internalStatus());
	}

	addItem(item: SearchableItem) {
		this.foundItems.add(item);
	}

	getItems(): Iterable<SearchableItem> {
		return this.foundItems;
	}

	addChildRequest(request: CrawlRequest) {
		this.childRequests.add(request);
	}

	onceStatus(status: CrawlRequestStatus | CrawlRequestStatus[], callback: () => void) {
		if (!Array.isArray(status)) {
			status = [status];
		}

		if (status.includes(this.status())) {
			callback();
			return;
		}

		let subscription: KnockoutSubscription | null = null;
		subscription = this.status.subscribe(newStatus => {
			if (status.includes(newStatus)) {
				subscription?.dispose();
				callback();
			}
		});
	}

	onceStopped(callback: () => void) {
		this.onceStatus(['aborted', 'error', 'completed'], callback);
	}

	onceTreeStopped(callback: (anyRequestsAborted: boolean, summary: RequestTreeStatusSummary) => void) {
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
		} else {
			isStopped.subscribe(value => {
				if (value) {
					isStopped.dispose();
				}
			});
		}
	}
}

class LoadedPage {
	constructor(
		public readonly crawlRequest: CrawlRequest,
		public readonly $root: JQuery,
		public readonly $iframe: JQuery
	) {
		crawlRequest.transitionStatus('loaded');
	}
}

export class CrawlerUrlBlacklist {
	constructor(private readonly blacklist: RegExp[]) {
	}

	isBlacklisted(relativeAdminUrl: string): boolean {
		//As of this writing, all WordPress admin page URLs I've seen point to PHP files.
		//If the URL doesn't contain ".php", it's probably not a valid admin page.
		if (!this.containsPhpExtension(relativeAdminUrl)) {
			return true;
		}

		//Check the blacklist.
		return this.blacklist.some(pattern => pattern.test(relativeAdminUrl));
	}

	containsPhpExtension(input: string): boolean {
		return input.includes('.php');
	}
}

