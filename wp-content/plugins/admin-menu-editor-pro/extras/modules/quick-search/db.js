import { AdminMenuItem, DashboardItem } from './items';
import { FinalCrawlRequestStatuses } from './crawler';
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;
import * as _ from 'lodash-es';
import Cookies from 'js-cookie';
var forEachObjectKey = AmeMiniFunc.forEachObjectKey;
export class ItemUsageTracker {
    constructor(storePrefix, //Should be unique to the site on the current domain.
    softSizeLimit, cookiePath) {
        this.storePrefix = storePrefix;
        this.softSizeLimit = softSizeLimit;
        this.cookiePath = cookiePath;
        this.storeValues = {
            menuVisits: null,
            pageVisits: null,
            itemActions: null
        };
        if (softSizeLimit < 1) {
            throw new Error('The soft size limit must be at least 1.');
        }
        this.hardSizeLimit = softSizeLimit * 2 + 1;
    }
    async populateUsageTimestamps(items) {
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
                }
                else if (item instanceof DashboardItem) {
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
    async recordMenuVisit(relativeMenuUrl) {
        await this.setTimestampInStore('menuVisits', relativeMenuUrl, Date.now());
    }
    async recordPageVisit(relativePageUrl) {
        await this.setTimestampInStore('pageVisits', relativePageUrl, Date.now());
    }
    async recordItemAction(item) {
        if (item instanceof DashboardItem) {
            /*
            In addition to storing the last-used timestamp locally, let's also let the server know
            when a dashboard item is used. The plugin will later use this to preload recent items
            so that they're available without waiting for an AJAX request.

            We store timestamps in a session cookie. The PHP script will read the cookie and update
            the database on the next page load, then delete the cookie.
            */
            let pendingUpdates = {};
            const cookieName = 'ame-qs-used-db-items';
            const serializedUpdates = Cookies.get(cookieName);
            if (serializedUpdates) {
                try {
                    pendingUpdates = JSON.parse(serializedUpdates);
                    if (!pendingUpdates || (typeof pendingUpdates !== 'object')) {
                        pendingUpdates = {};
                    }
                }
                catch (e) {
                    console.error('Failed to parse pending item updates:', e);
                }
            }
            const key = item.getMenuUrl() + '\n' + item.getRelativeId();
            pendingUpdates[key] = Math.round(Date.now() / 1000);
            Cookies.set(cookieName, JSON.stringify(pendingUpdates), { sameSite: 'Lax', path: this.cookiePath });
        }
        await this.setTimestampInStore('itemActions', item.getUniqueId(), Date.now());
    }
    async getStore(store) {
        let cache = this.storeValues[store];
        if (!cache) {
            //Try to load from local storage.
            const serialized = localStorage.getItem(this.getFullStoreKey(store));
            if (serialized) {
                try {
                    const parsed = JSON.parse(serialized);
                    if (parsed && (typeof parsed === 'object')) {
                        cache = new Map(Object.entries(parsed));
                    }
                }
                catch (e) {
                    console.error('Failed to parse usage store:', e);
                }
            }
            if (!cache) {
                cache = new Map();
                this.storeValues[store] = cache;
            }
        }
        return cache;
    }
    async getTimestampFromStore(store, key) {
        const cache = await this.getStore(store);
        const value = cache.get(key);
        if ((typeof value === 'number') && (value > 0)) {
            return value;
        }
        return 0;
    }
    async setTimestampInStore(store, key, timestamp) {
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
    getFullStoreKey(store) {
        return 'ameQsTs:' + this.storePrefix + ':' + store;
    }
}
export class DashboardCrawlerDb {
    constructor(ajaxActions, preloadedRecords) {
        this.ajaxActions = ajaxActions;
        this.records = new Map();
        this.pendingCrawlRecordFetches = new Map();
        this.throttledFetchCrawlRecords = _.debounce(_.throttle(() => this.fetchPendingCrawlRecords(), 2000, { leading: true, trailing: true }), 200, { leading: false, trailing: true });
        this.pendingCrawlRecordSaves = new Set();
        this.throttledSaveCrawlRecords = _.throttle(() => this.storePendingCrawlRecords(), 2000, { leading: true, trailing: true });
        if (preloadedRecords) {
            forEachObjectKey(preloadedRecords, (url, record) => {
                if (record) {
                    this.records.set(url, some(record));
                }
                else {
                    this.records.set(url, none);
                }
            });
        }
    }
    async prefetchUrlRecords(urls) {
        const uniqueUrls = Array.from(new Set(urls));
        const missingUrls = uniqueUrls.filter(url => !this.records.has(url));
        if (missingUrls.length > 0) {
            console.log('Prefetching URL records:', missingUrls);
            const promises = missingUrls.map(url => this.getRecord(url));
            const results = await Promise.all(promises);
            console.log('Prefetched URL records:', missingUrls, results);
        }
    }
    async getRecord(url) {
        const record = this.records.get(url);
        if (record) {
            return record;
        }
        const fetched = await this.fetchCrawlRecord(url);
        //Check if the record was already fetched by another call while we were waiting.
        const existing = this.records.get(url);
        if (existing) {
            return existing;
        }
        else {
            //Cache the fetched record.
            this.records.set(url, fetched);
            return fetched;
        }
    }
    async setRecord(record) {
        this.records.set(record.url, some(record));
        this.queueCrawlRecordSave(record.url);
    }
    /**
     * Returns the time since the last finished crawl attempt for the given URL.
     *
     * @param url
     * @returns Time in milliseconds, or Infinity if the URL has never been crawled.
     */
    async getTimeSinceLastFinishedCrawl(url) {
        const record = await this.getRecord(url);
        if (record.isDefined()) {
            return Date.now() - (record.get().lastFinishedAttemptAt * 1000);
        }
        return Number.POSITIVE_INFINITY;
    }
    async getTimeSinceLastAttempt(url) {
        const record = await this.getRecord(url);
        if (record.isDefined()) {
            return Date.now() - (record.get().lastAttemptAt * 1000);
        }
        return Number.POSITIVE_INFINITY;
    }
    async getComponentVersion(url) {
        const record = await this.getRecord(url);
        if (record.isDefined()) {
            return record.get().componentAndVersion;
        }
        return null;
    }
    async getLastAttemptDate(url) {
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
    getCachedLastAttemptDate(url) {
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
    async recordMenuAttemptStart(url, depth = 0, reason, componentAndVersion) {
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
            };
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
    async recordMenuAttemptEnd(url, status, errorMessage = '', finished = null) {
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
    fetchPendingCrawlRecords() {
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
        request.done((response) => {
            if (!response.success) {
                console.error('Failed to fetch crawl records (response.success is not true):', response);
                const error = new Error('Failed to fetch crawl records.');
                queue.forEach((items) => {
                    items.forEach(item => item.reject(error));
                });
                return;
            }
            const data = response.data;
            queue.forEach((items, urls) => {
                const record = data[urls];
                if (record) {
                    items.forEach(item => item.resolve(some(record)));
                }
                else {
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
    fetchCrawlRecord(urls) {
        return new Promise((resolve, reject) => {
            const queueItem = {
                resolve: resolve,
                reject: reject
            };
            const queue = this.pendingCrawlRecordFetches.get(urls);
            if (queue) {
                queue.push(queueItem);
            }
            else {
                this.pendingCrawlRecordFetches.set(urls, [queueItem]);
            }
            this.throttledFetchCrawlRecords();
        });
    }
    storePendingCrawlRecords() {
        const urls = Array.from(this.pendingCrawlRecordSaves);
        this.pendingCrawlRecordSaves.clear();
        const records = {};
        let anyRecordsFound = false;
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
        request.done((response) => {
            if (!response.success) {
                console.error('Failed to save crawl records (response.success is not true):', response);
            }
        });
        request.fail((_, textStatus, errorThrown) => {
            console.error('Failed to save crawl records:', textStatus, errorThrown);
        });
    }
    queueCrawlRecordSave(ur) {
        this.pendingCrawlRecordSaves.add(ur);
        this.throttledSaveCrawlRecords();
    }
}
//# sourceMappingURL=db.js.map