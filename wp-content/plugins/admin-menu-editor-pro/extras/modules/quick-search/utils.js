import * as _ from 'lodash-es';
export function getRelativeAdminPageUrl(url, adminUrl, baseUrl = '', removableQueryArgs = []) {
    if (!baseUrl) {
        baseUrl = window.location.href;
    }
    const parsedUrl = new URL(url, baseUrl);
    //Must be a dashboard/admin page URL.
    if (!isAdminPageUrl(parsedUrl, adminUrl)) {
        return null;
    }
    //Remove "updated", "return" and similar query parameters. They are either temporary or vary from
    //page to page, which means they're not useful for identifying menu items.
    for (const param of removableQueryArgs) {
        parsedUrl.searchParams.delete(param);
    }
    //Remove the fragment, if any.
    parsedUrl.hash = '';
    //Get the URL relative to the admin URL.
    const relativeUrl = parsedUrl.pathname + parsedUrl.search;
    if (relativeUrl.startsWith(adminUrl.pathname)) {
        return relativeUrl.substring(adminUrl.pathname.length);
    }
    return null;
}
export function isAdminPageUrl(inputUrl, adminUrl) {
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
export function condenseWhitespace(text) {
    return text.replace(/\s+/g, ' ').trim();
}
export function getElementTextForItemLabel($element, textPartSeparator = ' ', childBlacklist = [
    '.hide-if-js', '.awaiting-mod', '.update-plugins', '.menu-counter',
    '.CodeMirror', '.CodeMirror-wrap',
]) {
    const blacklistedSelectors = childBlacklist.join(', ');
    const parts = [];
    $element.contents().each((_, node) => {
        if (node.nodeType === 3) {
            parts.push(node.nodeValue || '');
        }
        else {
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
                }
                else {
                    const $selected = $node.find('option:selected');
                    if ($selected.length > 0) {
                        parts.push($selected.text());
                    }
                    else {
                        parts.push(' [...] ');
                    }
                }
            }
            else if ($node.is('input[type="number"], input[type="text"]')) {
                const name = $node.prop('name');
                if (name) {
                    parts.push(' [' + name + '] ');
                }
                else {
                    parts.push(' [...] ');
                }
            }
            else {
                parts.push($node.text());
            }
        }
    });
    return condenseWhitespace(parts.join(textPartSeparator));
}
export function queryAdvancedSelector(selector) {
    if (typeof selector === 'string') {
        return jQuery(selector);
    }
    else {
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
export class KoObservableSet {
    constructor() {
        this._set = new Set();
        this._observable = ko.observableArray(Array.from(this._set));
    }
    [Symbol.iterator]() {
        //Touch the observable to create a dependency when the iterator
        //is used from Knockout bindings and computed observables.
        this._observable();
        return this._set.values();
    }
    add(value) {
        if (!this._set.has(value)) {
            this._set.add(value);
            this._observable.push(value);
        }
    }
    delete(value) {
        if (this._set.has(value)) {
            this._set.delete(value);
            this._observable.remove(value);
        }
    }
    items() {
        return this._observable;
    }
    get size() {
        //We use the observable array instead of the underlying set so KO can track changes.
        //For example, this way a computed observable that depends on the size of the set will
        //be re-evaluated when an item is added or removed.
        return this._observable().length;
    }
    shift() {
        const value = this._observable.shift();
        if (value) {
            this._set.delete(value);
        }
        return value;
    }
}
class ListNode {
    constructor(key, value) {
        this.prev = null;
        this.next = null;
        this.key = key;
        this.value = value;
    }
}
export class LRUCache {
    constructor(capacity) {
        this.head = null;
        this.tail = null;
        this.capacity = capacity;
        this.cache = new Map();
    }
    get(key) {
        const node = this.cache.get(key);
        if (!node) {
            return undefined;
        }
        // Move accessed node to the head (most recently used)
        this.removeNode(node);
        this.addToHead(node);
        return node.value;
    }
    put(key, value) {
        if (this.cache.has(key)) {
            // Update existing node
            const node = this.cache.get(key);
            node.value = value;
            this.removeNode(node);
            this.addToHead(node);
        }
        else {
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
    has(key) {
        return this.cache.has(key);
    }
    removeNode(node) {
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
    addToHead(node) {
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
    size() {
        return this.cache.size;
    }
    forEach(callback) {
        this.cache.forEach((node, key) => {
            callback(node.value, key);
        });
    }
    isFull() {
        return this.cache.size >= this.capacity;
    }
}
const UninitializedLazyValue = Symbol('UninitializedLazyValue');
export function lazy(factory) {
    let value = UninitializedLazyValue;
    return () => {
        if (value === UninitializedLazyValue) {
            value = factory();
        }
        return value;
    };
}
export function throttleBatchProcessor(handler, waitTime) {
    const queue = new Set();
    const promiseFunctions = [];
    const throttledHandler = _.throttle(() => {
        const inputs = new Set(queue);
        const functions = [...promiseFunctions];
        queue.clear();
        promiseFunctions.length = 0;
        handler(inputs, functions);
    }, waitTime, { leading: true, trailing: true });
    return (input) => {
        return new Promise((resolve, reject) => {
            queue.add(input);
            promiseFunctions.push({ resolve, reject });
            throttledHandler();
        });
    };
}
export function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // $& means the whole matched string
}
//# sourceMappingURL=utils.js.map