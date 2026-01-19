import {AdminMenuItem, DashboardItem, SearchableItem} from './items';
import {CrawlRequestStatus, FinalCrawlRequestStatuses} from './crawler';
import Option = AmeMiniFunc.Option;
import none = AmeMiniFunc.none;
import some = AmeMiniFunc.some;
import {PromiseQueueItem} from './utils';
import * as _ from 'lodash-es';
import Cookies from 'js-cookie';
import forEachObjectKey = AmeMiniFunc.forEachObjectKey;

type UsageStores = 'menuVisits' | 'pageVisits' | 'itemActions';

export class ItemUsageTracker {
	private readonly storeValues: Record<UsageStores, Map<string, number> | null> = {
		menuVisits: null,
		pageVisits: null,
		itemActions: null
	};

	private readonly hardSizeLimit: number;

	constructor(
		private readonly storePrefix: string, //Should be unique to the site on the current domain.
		private readonly softSizeLimit: number,
		private readonly cookiePath: string
	) {
		if (softSizeLimit < 1) {
			throw new Error('The soft size limit must be at least 1.');
		}
		this.hardSizeLimit = softSizeLimit * 2 + 1;
	}

	async populateUsageTimestamps(items: Iterable<SearchableItem>) {
		//Load all the caches at once to avoid having to use multiple async calls
		//for each item.
		const caches = {
			menuVisits: await this.getStore('menuVisits'),
			pageVisits: await this.getStore('pageVisits'),
			itemActions: await this.getStore('itemActions')
		};

		for (const item of items) {
			if (item.lastUsedAt < 0) {
				const value = caches.itemActions.get(item.getUniqueId());
				item.lastUsedAt = (typeof value === 'number') ? value : 0;
			}

			if (item.lastVisitedAt < 0) {
				if (item instanceof AdminMenuItem) {
					const value = caches.menuVisits.get(item.getRelativeMenuUrl());
					item.lastVisitedAt = (typeof value === 'number') ? value : 0;
				} else if (item instanceof DashboardItem) {
					//Does this item link to a page as a whole, not to a specific element?
					//In that case, we can use the "last visited" timestamp for the page.
					const pageUrl = item.getEffectiveTargetUrl();
					if (pageUrl && !item.getSelector()) {
						const value = caches.pageVisits.get(pageUrl);
						item.lastVisitedAt = (typeof value === 'number') ? value : 0;
					}
				}
			}
		}
	}

	async recordMenuVisit(relativeMenuUrl: string) {
		await this.setTimestampInStore('menuVisits', relativeMenuUrl, Date.now());
	}

	async recordPageVisit(relativePageUrl: string) {
		await this.setTimestampInStore('pageVisits', relativePageUrl, Date.now());
	}

	async recordItemAction(item: SearchableItem) {
		/*
		In addition to storing the last-used timestamp locally, let's also let the server know
		when an item is used. The plugin will later use this to preload recent items so that
		they're available without waiting for an AJAX request.

		We store timestamps in a session cookie. The PHP script will read the cookie and update
		the database on the next page load, then delete the cookie.
		*/

		const trackable = item.getUsageTrackingRef();
		if (trackable.isDefined()) {
			let pendingUpdates: {
				_v: number;
				items: Record<string, number>;
			} = {_v: 2, items: {}};

			const cookieName = 'ame-qs-used-db-items';
			const serializedUpdates = Cookies.get(cookieName);
			if (serializedUpdates) {
				try {
					pendingUpdates = JSON.parse(serializedUpdates);
					if (!pendingUpdates || (typeof pendingUpdates !== 'object') || (pendingUpdates._v !== 2)) {
						pendingUpdates = {_v: 2, items: {}};
					}
				} catch (e) {
					console.error('Failed to parse pending item updates:', e);
				}
			}

			const ref = trackable.get();
			pendingUpdates.items[JSON.stringify(ref)] = Math.round(Date.now() / 1000);

			Cookies.set(cookieName, JSON.stringify(pendingUpdates), {sameSite: 'Lax', path: this.cookiePath});
		}

		await this.setTimestampInStore('itemActions', item.getUniqueId(), Date.now());
	}

	private async getStore(store: UsageStores): Promise<Map<string, number>> {
		let cache = this.storeValues[store];
		if (!cache) {
			//Try to load from local storage.
			const serialized = localStorage.getItem(this.getFullStoreKey(store));
			if (serialized) {
				try {
					const parsed = JSON.parse(serialized);
					if (parsed && (typeof parsed === 'object')) {
						cache = new Map<string, number>(Object.entries(parsed));
					}
				} catch (e) {
					console.error('Failed to parse usage store:', e);
				}
			}

			if (!cache) {
				cache = new Map<string, number>();
				this.storeValues[store] = cache;
			}
		}
		return cache;
	}

	private async getTimestampFromStore(store: UsageStores, key: string): Promise<number> {
		const cache = await this.getStore(store);
		const value = cache.get(key);
		if ((typeof value === 'number') && (value > 0)) {
			return value;
		}
		return 0;
	}

	private async setTimestampInStore(store: UsageStores, key: string, timestamp: number) {
		const cache = await this.getStore(store);
		cache.set(key, timestamp);

		//Prune old entries if over the hard limit.
		if (cache.size > this.hardSizeLimit) {
			const entries = Array.from(cache.entries());
			entries.sort((a, b) => a[1] - b[1]);
			const toDelete = entries.slice(0, entries.length - this.softSizeLimit);
			for (const [key] of toDelete) {
				cache.delete(key);
			}
		}

		//Save to local storage.
		const serialized = JSON.stringify(Object.fromEntries(cache.entries()));
		localStorage.setItem(this.getFullStoreKey(store), serialized);
	}

	private getFullStoreKey(store: UsageStores): string {
		return 'ameQsTs:' + this.storePrefix + ':' + store;
	}
}

export interface UrlCrawlRecord {
	url: string;
	isMenuItem: boolean;

	lastAttemptAt: number;
	lastAttemptStatus: CrawlRequestStatus | null;
	lastFinishedAttemptAt: number;
	lastFinishedAttemptStatus: CrawlRequestStatus | null;

	//These properties are for the last attempt.
	depth: number;
	reason: string | null;
	componentAndVersion: string | null; //E.g. "plugin:foo/bar.php:1.2.3"
	errorMessage: string | null;
}

export class DashboardCrawlerDb {
	private readonly records: Map<string, Option<UrlCrawlRecord>> = new Map();

	constructor(
		private readonly ajaxActions: { getCrawlRecords: AjawV2.AjaxAction, setCrawlRecords: AjawV2.AjaxAction },
		preloadedRecords?: Record<string, UrlCrawlRecord | null>
	) {
		if (preloadedRecords) {
			forEachObjectKey(preloadedRecords, (url, record) => {
				if (record) {
					this.records.set(url, some(record));
				} else {
					this.records.set(url, none);
				}
			});
		}
	}

	async prefetchUrlRecords(urls: string[]) {
		const uniqueUrls = Array.from(new Set(urls));

		const missingUrls = uniqueUrls.filter(url => !this.records.has(url));
		if (missingUrls.length > 0) {
			console.log('Prefetching URL records:', missingUrls);
			const promises = missingUrls.map(url => this.getRecord(url));
			const results = await Promise.all(promises);
			console.log('Prefetched URL records:', missingUrls, results);
		}
	}

	async getRecord(url: string): Promise<Option<UrlCrawlRecord>> {
		const record = this.records.get(url);
		if (record) {
			return record;
		}

		const fetched = await this.fetchCrawlRecord(url);
		//Check if the record was already fetched by another call while we were waiting.
		const existing = this.records.get(url);
		if (existing) {
			return existing;
		} else {
			//Cache the fetched record.
			this.records.set(url, fetched);
			return fetched;
		}
	}

	async setRecord(record: UrlCrawlRecord) {
		this.records.set(record.url, some(record));
		this.queueCrawlRecordSave(record.url);
	}

	/**
	 * Returns the time since the last finished crawl attempt for the given URL.
	 *
	 * @param url
	 * @returns Time in milliseconds, or Infinity if the URL has never been crawled.
	 */
	async getTimeSinceLastFinishedCrawl(url: string): Promise<number> {
		const record = await this.getRecord(url);
		if (record.isDefined()) {
			return Date.now() - (record.get().lastFinishedAttemptAt * 1000);
		}
		return Number.POSITIVE_INFINITY;
	}

	async getTimeSinceLastAttempt(url: string): Promise<number> {
		const record = await this.getRecord(url);
		if (record.isDefined()) {
			return Date.now() - (record.get().lastAttemptAt * 1000);
		}
		return Number.POSITIVE_INFINITY;
	}

	async getComponentVersion(url: string): Promise<string | null> {
		const record = await this.getRecord(url);
		if (record.isDefined()) {
			return record.get().componentAndVersion;
		}
		return null;
	}

	async getLastAttemptDate(url: string): Promise<Date | null> {
		const recordOption = await this.getRecord(url);
		if (recordOption.isDefined()) {
			const record = recordOption.get();
			if (record.lastAttemptAt > 0) {
				return new Date(record.lastAttemptAt * 1000);
			}
			return null;
		}
		return null;
	}

	/**
	 * Returns the last attempt date for the given URL, if it's cached.
	 * Does not fetch the record from the server, so it's synchronous and fast.
	 *
	 * @param url
	 */
	getCachedLastAttemptDate(url: string): Date | null {
		const recordOption = this.records.get(url);
		if (recordOption && recordOption.isDefined()) {
			const record = recordOption.get();
			if (record.lastAttemptAt > 0) {
				return new Date(record.lastAttemptAt * 1000);
			}
			return null;
		}
		return null;
	}

	async recordMenuAttemptStart(
		url: string,
		depth: number = 0,
		reason: string | null,
		componentAndVersion: string | null
	) {
		let record = (await this.getRecord(url)).getOrElse(() => {
			return {
				url: url,
				isMenuItem: true,
				lastAttemptAt: 0,
				lastAttemptStatus: null,
				lastFinishedAttemptAt: 0,
				lastFinishedAttemptStatus: null,
				depth: 0,
				reason: null,
				componentAndVersion: null,
				errorMessage: null
			} satisfies UrlCrawlRecord;
		});

		record.isMenuItem = true;
		record.lastAttemptAt = Math.round(Date.now() / 1000);
		record.lastAttemptStatus = null;
		record.depth = depth;
		record.reason = reason;
		record.componentAndVersion = componentAndVersion;
		record.errorMessage = null;

		await this.setRecord(record);
	}

	async recordMenuAttemptEnd(
		url: string,
		status: CrawlRequestStatus,
		errorMessage: string = '',
		finished: boolean | null = null
	) {
		if (finished === null) {
			finished = (status !== 'aborted') && FinalCrawlRequestStatuses.includes(status);
		}

		const maybeRecord = await this.getRecord(url);
		if (maybeRecord.isEmpty()) {
			console.error('Cannot record attempt end for a previously unsaved URL:', url, status);
			return;
		}
		const record = maybeRecord.get();

		record.lastAttemptStatus = status;
		record.errorMessage = errorMessage;

		if (finished) {
			record.lastFinishedAttemptAt = Math.round(Date.now() / 1000);
			record.lastFinishedAttemptStatus = status;
		}

		await this.setRecord(record);
	}

	private readonly pendingCrawlRecordFetches: Map<string, PromiseQueueItem<Option<UrlCrawlRecord>>[]> = new Map();

	private fetchPendingCrawlRecords() {
		const queue = new Map(this.pendingCrawlRecordFetches.entries());
		this.pendingCrawlRecordFetches.clear();

		if (queue.size === 0) {
			return;
		}

		const urls = Array.from(queue.keys());
		console.log('Fetching crawl records for URLs:', urls);

		const request = this.ajaxActions.getCrawlRecords.ajax({
			method: 'POST', //GET would be more appropriate, but we may have too many URLs.
			data: {
				urls: JSON.stringify(urls)
			},
			dataType: 'json'
		});

		request.done((response: any) => {
			if (!response.success) {
				console.error('Failed to fetch crawl records (response.success is not true):', response);
				const error = new Error('Failed to fetch crawl records.');
				queue.forEach((items) => {
					items.forEach(item => item.reject(error));
				});
				return;
			}

			const data: Record<string, UrlCrawlRecord> = response.data;
			queue.forEach((items, urls) => {
				const record = data[urls];
				if (record) {
					items.forEach(item => item.resolve(some(record)));
				} else {
					items.forEach(item => item.resolve(none));
				}
			});
		});

		request.fail((_, textStatus, errorThrown) => {
			console.error('Failed to fetch crawl records:', textStatus, errorThrown);
			const error = new Error('Failed to fetch crawl records.');
			queue.forEach((items) => {
				items.forEach(item => item.reject(error));
			});
		});
	}

	private readonly throttledFetchCrawlRecords = _.debounce(
		_.throttle(
			() => this.fetchPendingCrawlRecords(),
			2000,
			{leading: true, trailing: true}
		),
		200,
		{leading: false, trailing: true}
	);

	private fetchCrawlRecord(urls: string): Promise<Option<UrlCrawlRecord>> {
		return new Promise((resolve, reject) => {
			const queueItem: PromiseQueueItem<Option<UrlCrawlRecord>> = {
				resolve: resolve,
				reject: reject
			};

			const queue = this.pendingCrawlRecordFetches.get(urls);
			if (queue) {
				queue.push(queueItem);
			} else {
				this.pendingCrawlRecordFetches.set(urls, [queueItem]);
			}

			this.throttledFetchCrawlRecords();
		});
	}

	private readonly pendingCrawlRecordSaves: Set<string> = new Set();

	private storePendingCrawlRecords() {
		const urls = Array.from(this.pendingCrawlRecordSaves);
		this.pendingCrawlRecordSaves.clear();

		const records: Record<string, UrlCrawlRecord> = {};
		let anyRecordsFound: boolean = false;
		urls.forEach(url => {
			const record = this.records.get(url);
			if (record && record.isDefined()) {
				records[url] = record.get();
				anyRecordsFound = true;
			}
		});

		if (!anyRecordsFound) {
			return;
		}

		console.log('Saving modified crawl records:', records);

		const request = this.ajaxActions.setCrawlRecords.ajax({
			method: 'POST',
			data: {
				records: JSON.stringify(records)
			},
			dataType: 'json'
		});

		request.done((response: any) => {
			if (!response.success) {
				console.error('Failed to save crawl records (response.success is not true):', response);
			}
		});

		request.fail((_, textStatus, errorThrown) => {
			console.error('Failed to save crawl records:', textStatus, errorThrown);
		});
	}

	private readonly throttledSaveCrawlRecords = _.throttle(
		() => this.storePendingCrawlRecords(),
		2000,
		{leading: true, trailing: true}
	);

	private queueCrawlRecordSave(ur: string) {
		this.pendingCrawlRecordSaves.add(ur);
		this.throttledSaveCrawlRecords();
	}
}