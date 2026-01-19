import * as _ from 'lodash-es';

export function getRelativeAdminPageUrl(
	url: string,
	adminUrl: URL,
	baseUrl: string | URL = '',
	removableQueryArgs: string[] = []
): string | null {
	if (!baseUrl) {
		baseUrl = window.location.href;
	}

	const parsedUrl = normalizeUrl(url, baseUrl, removableQueryArgs);
	//Must be a dashboard/admin page URL.
	if (!isAdminPageUrl(parsedUrl, adminUrl)) {
		return null;
	}

	//Get the URL relative to the admin URL.
	const relativeUrl = parsedUrl.pathname + parsedUrl.search;
	if (relativeUrl.startsWith(adminUrl.pathname)) {
		return relativeUrl.substring(adminUrl.pathname.length);
	}

	return null;
}

export function isAdminPageUrl(inputUrl: URL, adminUrl: URL): boolean {
	//Origin must match the admin URL origin.
	if (inputUrl.origin !== adminUrl.origin) {
		return false;
	}
	//Path must contain "/wp-admin/".
	if (!inputUrl.pathname.includes('/wp-admin/')) {
		return false;
	}
	//Path must start with the adminUrl path.
	return inputUrl.pathname.startsWith(adminUrl.pathname);
}

export function normalizeUrl(
	inputUrl: string | URL,
	baseUrl: string | URL = '',
	removableQueryParams: string[] = [],
	removeHash: boolean = true
): URL {
	const parsedUrl = (typeof inputUrl === 'string')
		? new URL(inputUrl, baseUrl)
		: new URL(inputUrl.toString(), baseUrl);

	if (removeHash) {
		//URLs that only differ by hash are considered the same.
		parsedUrl.hash = '';
	}

	//Remove "updated", "return" and similar query parameters. They are either temporary or vary from
	//page to page, which means they're not useful for identifying menu items.
	for (const param of removableQueryParams) {
		parsedUrl.searchParams.delete(param);
	}

	//Sort the search parameters to ensure consistent ordering.
	//This also triggers re-encoding of parameter values, which is important for consistency since
	//we may later modify the params again when generating a relative admin URL.
	//See https://developer.mozilla.org/en-US/docs/Web/API/URL/searchParams
	parsedUrl.searchParams.sort();

	return parsedUrl;
}

export function condenseWhitespace(text: string): string {
	return text.replace(/\s+/g, ' ').trim();
}

export function getElementTextForItemLabel(
	$element: JQuery,
	textPartSeparator: string = ' ',
	childBlacklist: string[] = [
		'.hide-if-js', '.awaiting-mod', '.update-plugins', '.menu-counter',
		'.CodeMirror', '.CodeMirror-wrap',
	]
): string {
	const blacklistedSelectors = childBlacklist.join(', ');

	const parts: string[] = [];
	$element.contents().each((_, node) => {
		if (node.nodeType === 3) {
			parts.push(node.nodeValue || '');
		} else {
			const $node = jQuery(node);
			if ($node.is(blacklistedSelectors)) {
				return;
			}

			if ($node.is('select')) {
				//For <select> elements, jQuery(...).text() would combine all the
				//options' text, which is not what we want. Instead, let's use either
				//the name or the selected option.
				const name = $node.prop('name');
				if (name) {
					parts.push(' [' + name + '] ');
				} else {
					const $selected = $node.find('option:selected');
					if ($selected.length > 0) {
						parts.push($selected.text());
					} else {
						parts.push(' [...] ');
					}
				}
			} else if ($node.is('input[type="number"], input[type="text"]')) {
				const name = $node.prop('name');
				if (name) {
					parts.push(' [' + name + '] ');
				} else {
					parts.push(' [...] ');
				}
			} else {
				parts.push($node.text());
			}
		}
	});

	return condenseWhitespace(parts.join(textPartSeparator));
}

interface JQueryTraversalStep {
	selector: string;
	operation: 'find' | 'closest';
}

type JQueryTraversalPath = JQueryTraversalStep[];
export type Selector = string | JQueryTraversalPath;

export function queryAdvancedSelector(selector: Selector): JQuery {
	if (typeof selector === 'string') {
		return jQuery(selector);
	} else {
		if (selector.length === 0) {
			return jQuery();
		}

		let $current = jQuery('body');
		for (const step of selector) {
			switch (step.operation) {
				case 'find':
					$current = $current.find(step.selector);
					break;
				case 'closest':
					$current = $current.closest(step.selector);
					break;
			}

			if ($current.length === 0) {
				return jQuery();
			}
		}

		return $current;
	}
}

export class KoObservableSet<T> implements Iterable<T> {
	private readonly _set: Set<T> = new Set();
	private readonly _observable: KnockoutObservableArray<T>;

	constructor() {
		this._observable = ko.observableArray(Array.from(this._set));
	}

	[Symbol.iterator](): Iterator<T, void> {
		//Touch the observable to create a dependency when the iterator
		//is used from Knockout bindings and computed observables.
		this._observable();
		return this._set.values();
	}

	add(value: T): void {
		if (!this._set.has(value)) {
			this._set.add(value);
			this._observable.push(value);
		}
	}

	delete(value: T): void {
		if (this._set.has(value)) {
			this._set.delete(value);
			this._observable.remove(value);
		}
	}

	items(): KnockoutObservableArray<T> {
		return this._observable;
	}

	get size(): number {
		//We use the observable array instead of the underlying set so KO can track changes.
		//For example, this way a computed observable that depends on the size of the set will
		//be re-evaluated when an item is added or removed.
		return this._observable().length;
	}

	shift(): T | undefined {
		const value = this._observable.shift();
		if (value) {
			this._set.delete(value);
		}
		return value;
	}
}

class ListNode<K, V> {
	key: K;
	value: V;
	prev: ListNode<K, V> | null = null;
	next: ListNode<K, V> | null = null;

	constructor(key: K, value: V) {
		this.key = key;
		this.value = value;
	}
}

export class LRUCache<K, V> {
	private readonly capacity: number;
	private cache: Map<K, ListNode<K, V>>;
	private head: ListNode<K, V> | null = null;
	private tail: ListNode<K, V> | null = null;

	constructor(capacity: number) {
		this.capacity = capacity;
		this.cache = new Map<K, ListNode<K, V>>();
	}

	get(key: K): V | undefined {
		const node = this.cache.get(key);
		if (!node) {
			return undefined;
		}

		// Move accessed node to the head (most recently used)
		this.removeNode(node);
		this.addToHead(node);

		return node.value;
	}

	put(key: K, value: V): void {
		if (this.cache.has(key)) {
			// Update existing node
			const node = this.cache.get(key)!;
			node.value = value;
			this.removeNode(node);
			this.addToHead(node);
		} else {
			// Add new node
			const newNode = new ListNode(key, value);
			if (this.cache.size >= this.capacity) {
				// Remove least recently used item (tail)
				if (this.tail) {
					this.cache.delete(this.tail.key);
					this.removeNode(this.tail);
				}
			}
			this.addToHead(newNode);
			this.cache.set(key, newNode);
		}
	}

	/**
	 * Check if the cache contains the given key.
	 *
	 * Unlike get(), this method does not update the order of the cache.
	 *
	 * @param key
	 */
	has(key: K): boolean {
		return this.cache.has(key);
	}

	private removeNode(node: ListNode<K, V>): void {
		if (node.prev) {
			node.prev.next = node.next;
		}
		if (node.next) {
			node.next.prev = node.prev;
		}
		if (node === this.head) {
			this.head = node.next;
		}
		if (node === this.tail) {
			this.tail = node.prev;
		}
	}

	private addToHead(node: ListNode<K, V>): void {
		node.next = this.head;
		node.prev = null;
		if (this.head) {
			this.head.prev = node;
		}
		this.head = node;
		if (!this.tail) {
			this.tail = node;
		}
	}

	size(): number {
		return this.cache.size;
	}

	forEach(callback: (value: V, key: K) => void): void {
		this.cache.forEach((node, key) => {
			callback(node.value, key);
		});
	}

	isFull(): boolean {
		return this.cache.size >= this.capacity;
	}
}

const UninitializedLazyValue = Symbol('UninitializedLazyValue');

export function lazy<T>(factory: () => T): () => T {
	let value: T | typeof UninitializedLazyValue = UninitializedLazyValue;
	return () => {
		if (value === UninitializedLazyValue) {
			value = factory();
		}
		return value;
	};
}

export type PromiseQueueItem<T> = {
	resolve: (value: T) => void;
	reject: (reason: Error) => void;
};

export function throttleBatchProcessor<T, R>(
	handler: (
		inputs: Set<T>,
		promiseFunctions: PromiseQueueItem<R>[]
	) => void,
	waitTime: number
): (input: T) => Promise<R> {
	const queue: Set<T> = new Set();
	const promiseFunctions: PromiseQueueItem<R>[] = [];

	const throttledHandler = _.throttle(() => {
		const inputs = new Set(queue);
		const functions = [...promiseFunctions];
		queue.clear();
		promiseFunctions.length = 0;

		handler(inputs, functions);
	}, waitTime, {leading: true, trailing: true});

	return (input: T): Promise<R> => {
		return new Promise<R>((resolve, reject) => {
			queue.add(input);
			promiseFunctions.push({resolve, reject});
			throttledHandler();
		});
	}
}

export function escapeRegExp(string: string) {
	return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // $& means the whole matched string
}