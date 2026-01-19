let itemInstanceCounter = 0;
export class SearchableItem {
    constructor(props) {
        this.location = [];
        this.lastUsedAt = -1;
        this.lastVisitedAt = -1;
        this.instanceId = itemInstanceCounter++;
        this.label = props.label;
        if (props.location) {
            this.location = props.location;
        }
    }
    getUniqueId() {
        return this.constructor.name + '-' + this.instanceId;
    }
    getSearchableText() {
        return this.label;
    }
    performAction(requestNavigation) {
        console.log('Action performed for item:', this);
        return true;
    }
    getMetaLabel() {
        return '';
    }
    getLocation() {
        return this.location;
    }
    getStatusBarText(urlFormatter) {
        return '';
    }
    toJs() {
        return {
            type: this.getSerializedType(),
            label: this.label,
            location: this.location,
        };
    }
}
export class DashboardItem extends SearchableItem {
    constructor(props) {
        super(props);
        this.origin = props.origin;
        this.relativeId = props.relativeId;
        this.target = props.target;
        this.ownLabel = props.ownLabel || null;
    }
    getSerializedType() {
        return 'dashboardItem';
    }
    getUniqueId() {
        const originUrl = this.origin.menuUrl;
        /* ^ pageUrl may seem more appropriate here, but then we run into problems with items
         * that appear on multiple sub-pages of the same menu item, like post type filters.
         * Each filter has its own URL, and when you go to that URL, the page shows all the filters
         * again. So by using the page URL in the ID, you would end up with duplicate items.
         *
         * Instead, we use the menu URL, which is the same for all the filters (etc) on the same
         * page. The downside is that genuinely different sub-pages (e.g. tabs) could have different
         * items that have the same ID. Hopefully, that will be rare.
         *
         * If that becomes an issue, maybe the ID registry could be managed at the menu item level,
         * not the page level. That way similar IDs on different pages under the same menu item
         * would get deduplicated.
         */
        return 'p:' + originUrl + ':' + this.relativeId;
    }
    performAction(requestNavigation) {
        console.log('Navigating to dashboard item:', this.origin, this.target);
        const targetUrl = this.getEffectiveTargetUrl();
        requestNavigation(targetUrl, this.target.selector || '');
        return true;
    }
    getEffectiveTargetUrl() {
        return this.target.url || this.origin.pageUrl || this.origin.menuUrl;
    }
    getMenuUrl() {
        return this.origin.menuUrl;
    }
    getStatusBarText(urlFormatter) {
        const targetUrl = this.getEffectiveTargetUrl();
        if (targetUrl) {
            return urlFormatter(targetUrl);
        }
        return '';
    }
    getSelector() {
        return this.target.selector || null;
    }
    getTargetType() {
        return this.target.type;
    }
    getRelativeId() {
        return this.relativeId;
    }
    toJs() {
        const result = super.toJs();
        //Serialize the origin and target properties. Filter out redundant URL properties.
        const origin = {
            menuUrl: this.origin.menuUrl
        };
        if (this.origin.pageUrl && (this.origin.pageUrl !== this.origin.menuUrl)) {
            origin.pageUrl = this.origin.pageUrl;
        }
        const originUrl = origin.pageUrl || origin.menuUrl;
        const target = {
            type: this.target.type
        };
        if (this.target.url && (this.target.url !== originUrl)) {
            target.url = this.target.url;
        }
        if (this.target.selector) {
            target.selector = this.target.selector;
        }
        result.origin = origin;
        result.target = target;
        result.relativeId = this.relativeId;
        return result;
    }
}
export class AdminMenuItem extends SearchableItem {
    constructor(props, titleParts, targetElement = null, relativeUrl = null, relativeParentUrl = null) {
        super(props);
        this.titleParts = titleParts;
        this.targetElement = targetElement;
        this.relativeUrl = relativeUrl;
        this.menuItemId = null;
        this.url = props.url;
        if (this.relativeUrl) {
            let id = this.relativeUrl;
            if (relativeParentUrl) {
                id = relativeParentUrl + '>' + id;
            }
            this.menuItemId = 'adminMenu/' + id;
        }
    }
    performAction(requestNavigation) {
        if (this.targetElement && this.targetElement.is('a')) {
            //Follow the link. Counterintuitively, triggering a "click" event in jQuery doesn't
            //actually do this. We need to get the DOM element and call click() on it.
            const link = this.targetElement.get(0);
            console.log('Clicking target link:', link);
            link.click();
        }
        if (this.url) {
            console.log('Navigating to URL:', this.url);
            window.location.href = this.url;
            return true;
        }
        return super.performAction(requestNavigation);
    }
    getMetaLabel() {
        return 'Admin Menu';
    }
    getUniqueId() {
        if (this.menuItemId) {
            return this.menuItemId;
        }
        return super.getUniqueId();
    }
    getTitleParts() {
        return this.titleParts;
    }
    getUrl() {
        return this.url;
    }
    getRelativeMenuUrl() {
        return this.relativeUrl || '';
    }
    getStatusBarText(urlFormatter) {
        if (this.relativeUrl) {
            return urlFormatter(this.relativeUrl);
        }
        return '';
    }
    getSerializedType() {
        return 'adminMenuItem';
    }
}
export class LoadingPlaceholderItem extends SearchableItem {
    constructor() {
        super({
            label: 'Loading...',
        });
    }
    performAction(_) {
        return false; //Do nothing and don't close the search box.
    }
    getSerializedType() {
        throw new Error('Placeholder items cannot be serialized');
    }
}
export function unserializeItem(data) {
    switch (data.type) {
        case 'dashboardItem':
            return new DashboardItem(data);
        default:
            const invalidType = data.type;
            throw new Error(`Unsupported item type: ${invalidType}`);
    }
}
//# sourceMappingURL=items.js.map