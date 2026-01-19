(self["wsAmeWebpackChunk"] = self["wsAmeWebpackChunk"] || []).push([["quick-search"],{

/***/ "./node_modules/loglevel/lib/loglevel.js":
/*!***********************************************!*\
  !*** ./node_modules/loglevel/lib/loglevel.js ***!
  \***********************************************/
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;/*
* loglevel - https://github.com/pimterry/loglevel
*
* Copyright (c) 2013 Tim Perry
* Licensed under the MIT license.
*/
(function (root, definition) {
    "use strict";
    if (true) {
        !(__WEBPACK_AMD_DEFINE_FACTORY__ = (definition),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
    } else {}
}(this, function () {
    "use strict";

    // Slightly dubious tricks to cut down minimized file size
    var noop = function() {};
    var undefinedType = "undefined";
    var isIE = (typeof window !== undefinedType) && (typeof window.navigator !== undefinedType) && (
        /Trident\/|MSIE /.test(window.navigator.userAgent)
    );

    var logMethods = [
        "trace",
        "debug",
        "info",
        "warn",
        "error"
    ];

    var _loggersByName = {};
    var defaultLogger = null;

    // Cross-browser bind equivalent that works at least back to IE6
    function bindMethod(obj, methodName) {
        var method = obj[methodName];
        if (typeof method.bind === 'function') {
            return method.bind(obj);
        } else {
            try {
                return Function.prototype.bind.call(method, obj);
            } catch (e) {
                // Missing bind shim or IE8 + Modernizr, fallback to wrapping
                return function() {
                    return Function.prototype.apply.apply(method, [obj, arguments]);
                };
            }
        }
    }

    // Trace() doesn't print the message in IE, so for that case we need to wrap it
    function traceForIE() {
        if (console.log) {
            if (console.log.apply) {
                console.log.apply(console, arguments);
            } else {
                // In old IE, native console methods themselves don't have apply().
                Function.prototype.apply.apply(console.log, [console, arguments]);
            }
        }
        if (console.trace) console.trace();
    }

    // Build the best logging method possible for this env
    // Wherever possible we want to bind, not wrap, to preserve stack traces
    function realMethod(methodName) {
        if (methodName === 'debug') {
            methodName = 'log';
        }

        if (typeof console === undefinedType) {
            return false; // No method possible, for now - fixed later by enableLoggingWhenConsoleArrives
        } else if (methodName === 'trace' && isIE) {
            return traceForIE;
        } else if (console[methodName] !== undefined) {
            return bindMethod(console, methodName);
        } else if (console.log !== undefined) {
            return bindMethod(console, 'log');
        } else {
            return noop;
        }
    }

    // These private functions always need `this` to be set properly

    function replaceLoggingMethods() {
        /*jshint validthis:true */
        var level = this.getLevel();

        // Replace the actual methods.
        for (var i = 0; i < logMethods.length; i++) {
            var methodName = logMethods[i];
            this[methodName] = (i < level) ?
                noop :
                this.methodFactory(methodName, level, this.name);
        }

        // Define log.log as an alias for log.debug
        this.log = this.debug;

        // Return any important warnings.
        if (typeof console === undefinedType && level < this.levels.SILENT) {
            return "No console available for logging";
        }
    }

    // In old IE versions, the console isn't present until you first open it.
    // We build realMethod() replacements here that regenerate logging methods
    function enableLoggingWhenConsoleArrives(methodName) {
        return function () {
            if (typeof console !== undefinedType) {
                replaceLoggingMethods.call(this);
                this[methodName].apply(this, arguments);
            }
        };
    }

    // By default, we use closely bound real methods wherever possible, and
    // otherwise we wait for a console to appear, and then try again.
    function defaultMethodFactory(methodName, _level, _loggerName) {
        /*jshint validthis:true */
        return realMethod(methodName) ||
               enableLoggingWhenConsoleArrives.apply(this, arguments);
    }

    function Logger(name, factory) {
      // Private instance variables.
      var self = this;
      /**
       * The level inherited from a parent logger (or a global default). We
       * cache this here rather than delegating to the parent so that it stays
       * in sync with the actual logging methods that we have installed (the
       * parent could change levels but we might not have rebuilt the loggers
       * in this child yet).
       * @type {number}
       */
      var inheritedLevel;
      /**
       * The default level for this logger, if any. If set, this overrides
       * `inheritedLevel`.
       * @type {number|null}
       */
      var defaultLevel;
      /**
       * A user-specific level for this logger. If set, this overrides
       * `defaultLevel`.
       * @type {number|null}
       */
      var userLevel;

      var storageKey = "loglevel";
      if (typeof name === "string") {
        storageKey += ":" + name;
      } else if (typeof name === "symbol") {
        storageKey = undefined;
      }

      function persistLevelIfPossible(levelNum) {
          var levelName = (logMethods[levelNum] || 'silent').toUpperCase();

          if (typeof window === undefinedType || !storageKey) return;

          // Use localStorage if available
          try {
              window.localStorage[storageKey] = levelName;
              return;
          } catch (ignore) {}

          // Use session cookie as fallback
          try {
              window.document.cookie =
                encodeURIComponent(storageKey) + "=" + levelName + ";";
          } catch (ignore) {}
      }

      function getPersistedLevel() {
          var storedLevel;

          if (typeof window === undefinedType || !storageKey) return;

          try {
              storedLevel = window.localStorage[storageKey];
          } catch (ignore) {}

          // Fallback to cookies if local storage gives us nothing
          if (typeof storedLevel === undefinedType) {
              try {
                  var cookie = window.document.cookie;
                  var cookieName = encodeURIComponent(storageKey);
                  var location = cookie.indexOf(cookieName + "=");
                  if (location !== -1) {
                      storedLevel = /^([^;]+)/.exec(
                          cookie.slice(location + cookieName.length + 1)
                      )[1];
                  }
              } catch (ignore) {}
          }

          // If the stored level is not valid, treat it as if nothing was stored.
          if (self.levels[storedLevel] === undefined) {
              storedLevel = undefined;
          }

          return storedLevel;
      }

      function clearPersistedLevel() {
          if (typeof window === undefinedType || !storageKey) return;

          // Use localStorage if available
          try {
              window.localStorage.removeItem(storageKey);
          } catch (ignore) {}

          // Use session cookie as fallback
          try {
              window.document.cookie =
                encodeURIComponent(storageKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
          } catch (ignore) {}
      }

      function normalizeLevel(input) {
          var level = input;
          if (typeof level === "string" && self.levels[level.toUpperCase()] !== undefined) {
              level = self.levels[level.toUpperCase()];
          }
          if (typeof level === "number" && level >= 0 && level <= self.levels.SILENT) {
              return level;
          } else {
              throw new TypeError("log.setLevel() called with invalid level: " + input);
          }
      }

      /*
       *
       * Public logger API - see https://github.com/pimterry/loglevel for details
       *
       */

      self.name = name;

      self.levels = { "TRACE": 0, "DEBUG": 1, "INFO": 2, "WARN": 3,
          "ERROR": 4, "SILENT": 5};

      self.methodFactory = factory || defaultMethodFactory;

      self.getLevel = function () {
          if (userLevel != null) {
            return userLevel;
          } else if (defaultLevel != null) {
            return defaultLevel;
          } else {
            return inheritedLevel;
          }
      };

      self.setLevel = function (level, persist) {
          userLevel = normalizeLevel(level);
          if (persist !== false) {  // defaults to true
              persistLevelIfPossible(userLevel);
          }

          // NOTE: in v2, this should call rebuild(), which updates children.
          return replaceLoggingMethods.call(self);
      };

      self.setDefaultLevel = function (level) {
          defaultLevel = normalizeLevel(level);
          if (!getPersistedLevel()) {
              self.setLevel(level, false);
          }
      };

      self.resetLevel = function () {
          userLevel = null;
          clearPersistedLevel();
          replaceLoggingMethods.call(self);
      };

      self.enableAll = function(persist) {
          self.setLevel(self.levels.TRACE, persist);
      };

      self.disableAll = function(persist) {
          self.setLevel(self.levels.SILENT, persist);
      };

      self.rebuild = function () {
          if (defaultLogger !== self) {
              inheritedLevel = normalizeLevel(defaultLogger.getLevel());
          }
          replaceLoggingMethods.call(self);

          if (defaultLogger === self) {
              for (var childName in _loggersByName) {
                _loggersByName[childName].rebuild();
              }
          }
      };

      // Initialize all the internal levels.
      inheritedLevel = normalizeLevel(
          defaultLogger ? defaultLogger.getLevel() : "WARN"
      );
      var initialLevel = getPersistedLevel();
      if (initialLevel != null) {
          userLevel = normalizeLevel(initialLevel);
      }
      replaceLoggingMethods.call(self);
    }

    /*
     *
     * Top-level API
     *
     */

    defaultLogger = new Logger();

    defaultLogger.getLogger = function getLogger(name) {
        if ((typeof name !== "symbol" && typeof name !== "string") || name === "") {
            throw new TypeError("You must supply a name when creating a logger.");
        }

        var logger = _loggersByName[name];
        if (!logger) {
            logger = _loggersByName[name] = new Logger(
                name,
                defaultLogger.methodFactory
            );
        }
        return logger;
    };

    // Grab the current global log variable in case of overwrite
    var _log = (typeof window !== undefinedType) ? window.log : undefined;
    defaultLogger.noConflict = function() {
        if (typeof window !== undefinedType &&
               window.log === defaultLogger) {
            window.log = _log;
        }

        return defaultLogger;
    };

    defaultLogger.getLoggers = function getLoggers() {
        return _loggersByName;
    };

    // ES6 default export, for compatibility
    defaultLogger['default'] = defaultLogger;

    return defaultLogger;
}));


/***/ }),

/***/ "./extras/modules/quick-search/app-geometry.ts":
/*!*****************************************************!*\
  !*** ./extras/modules/quick-search/app-geometry.ts ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PanelGeometryStore: () => (/* binding */ PanelGeometryStore)
/* harmony export */ });
/* harmony import */ var js_cookie__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! js-cookie */ "./node_modules/js-cookie/dist/js.cookie.mjs");
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;

const geometryCookieName = 'ameQuickSearchGeometry';
function parseGeometryCookie(cookieValue) {
    function validatePositionAxis(value, axis) {
        if (!value || typeof value !== 'object') {
            return false;
        }
        const position = value;
        if (typeof position.side !== 'string' || typeof position.offset !== 'number') {
            return false;
        }
        if (axis === 'horizontal') {
            return position.side === 'left' || position.side === 'right';
        }
        else {
            return position.side === 'top' || position.side === 'bottom';
        }
    }
    try {
        const parsed = JSON.parse(cookieValue);
        if (!parsed || typeof parsed !== 'object') {
            return none;
        }
        //At least one of "size" and "position" must be present. The cookie can
        //technically be valid without either, but it would be useless.
        if (!parsed.size && !parsed.position) {
            return none;
        }
        //If "size" is present, it must be an object with "width" and "height" properties.
        if (parsed.size) {
            const size = parsed.size;
            if (typeof size === 'object' && size.width && size.height) {
                if (typeof size.width === 'number' && typeof size.height === 'number') {
                    //Size is valid.
                }
                else {
                    return none;
                }
            }
            else {
                return none;
            }
        }
        //If "position" is present, it must also have the correct shape.
        if (parsed.position) {
            const position = parsed.position;
            if (!validatePositionAxis(position.horizontal, 'horizontal')
                || !validatePositionAxis(position.vertical, 'vertical')) {
                return none;
            }
        }
        return some(parsed);
    }
    catch (e) {
        return none;
    }
}
class PanelGeometryStore {
    constructor(cookiePath) {
        this.cookiePath = cookiePath;
    }
    storeSize(size) {
        const value = this.getOrCreateGeometry();
        value.size = size;
        this.updateCookie(value);
    }
    storePosition(top, left, width, height) {
        const value = this.getOrCreateGeometry();
        const centerX = left + width / 2;
        const centerY = top + height / 2;
        const $window = jQuery(window);
        const windowWidth = $window.width() || 0;
        const windowHeight = $window.height() || 0;
        const horizontalSide = centerX < windowWidth / 2 ? 'left' : 'right';
        const horizontalOffset = (horizontalSide === 'left') ? left : -(windowWidth - left - width);
        const verticalSide = centerY < windowHeight / 2 ? 'top' : 'bottom';
        const verticalOffset = (verticalSide === 'top') ? top : -(windowHeight - top - height);
        value.position = {
            horizontal: {
                side: horizontalSide,
                offset: horizontalOffset
            },
            vertical: {
                side: verticalSide,
                offset: verticalOffset
            }
        };
        this.updateCookie(value);
    }
    getStoredSize() {
        const value = this.getStoredGeometry();
        return value.flatMap((geometry) => geometry.size ? some(geometry.size) : none);
    }
    getStoredPositionForJQuery() {
        return this.getStoredGeometry()
            .flatMap((geometry) => geometry.position ? some(geometry.position) : none)
            .map((geometry) => {
            const my = geometry.horizontal.side + ' ' + geometry.vertical.side;
            const at = (geometry.horizontal.side
                + ((geometry.horizontal.offset >= 0) ? '+' : '-')
                + Math.abs(geometry.horizontal.offset)
                + ' '
                + geometry.vertical.side
                + ((geometry.vertical.offset >= 0) ? '+' : '-')
                + Math.abs(geometry.vertical.offset));
            return { my, at };
        });
    }
    getStoredGeometry() {
        const cookieValue = js_cookie__WEBPACK_IMPORTED_MODULE_0__["default"].get(geometryCookieName);
        if (cookieValue) {
            const result = parseGeometryCookie(cookieValue);
            if (result.isDefined()) {
                return result;
            }
            else {
                //Delete invalid cookie.
                js_cookie__WEBPACK_IMPORTED_MODULE_0__["default"].remove(geometryCookieName, { path: this.cookiePath });
            }
        }
        return none;
    }
    updateCookie(value) {
        console.log('Storing geometry:', value);
        js_cookie__WEBPACK_IMPORTED_MODULE_0__["default"].set(geometryCookieName, JSON.stringify(value), {
            expires: 90,
            path: this.cookiePath,
            sameSite: 'Lax'
        });
    }
    getOrCreateGeometry() {
        return this.getStoredGeometry().getOrElse(() => ({}));
    }
}


/***/ }),

/***/ "./extras/modules/quick-search/crawler.ts":
/*!************************************************!*\
  !*** ./extras/modules/quick-search/crawler.ts ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CrawlRequest: () => (/* binding */ CrawlRequest),
/* harmony export */   Crawler: () => (/* binding */ Crawler),
/* harmony export */   CrawlerUrlBlacklist: () => (/* binding */ CrawlerUrlBlacklist),
/* harmony export */   FinalCrawlRequestStatuses: () => (/* binding */ FinalCrawlRequestStatuses)
/* harmony export */ });
/* harmony import */ var _items__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./items */ "./extras/modules/quick-search/items.ts");
/* harmony import */ var _scanner__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./scanner */ "./extras/modules/quick-search/scanner.ts");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utils */ "./extras/modules/quick-search/utils.ts");
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;



class Crawler {
    constructor(adminUrl, currentUserId, removableQueryParams, normalizeUrl, urlBlacklist, onRequestAdded = () => {
    }) {
        this.adminUrl = adminUrl;
        this.currentUserId = currentUserId;
        this.removableQueryParams = removableQueryParams;
        this.normalizeUrl = normalizeUrl;
        this.urlBlacklist = urlBlacklist;
        this.onRequestAdded = onRequestAdded;
        this.loadNextCallNumber = 0;
        this.state = ko.observable('idle');
        this.stopWhenAllRequestsFinished = true;
        this.pendingCrawlRequests = new _utils__WEBPACK_IMPORTED_MODULE_2__.KoObservableSet();
        this.crawlRequestQueue = new _utils__WEBPACK_IMPORTED_MODULE_2__.KoObservableSet();
        this.finishedRequests = new _utils__WEBPACK_IMPORTED_MODULE_2__.KoObservableSet();
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
            const adminMenuParserResult = (0,_scanner__WEBPACK_IMPORTED_MODULE_1__.parseAdminMenuItems)(page.$root, request.adminUrl);
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
            const scanner = new _scanner__WEBPACK_IMPORTED_MODULE_1__.PageScanner(_scanner__WEBPACK_IMPORTED_MODULE_1__.builtinScanners);
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
        if (!(item instanceof _items__WEBPACK_IMPORTED_MODULE_0__.DashboardItem)) {
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
        if (item instanceof _items__WEBPACK_IMPORTED_MODULE_0__.DashboardItem) {
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
const FinalCrawlRequestStatuses = ['error', 'aborted', 'completed'];
const EmptyStatusCounts = {
    queued: 0,
    loading: 0,
    loaded: 0,
    scanning: 0,
    error: 0,
    aborted: 0,
    completed: 0
};
class CrawlRequest {
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
        this.foundItems = new _utils__WEBPACK_IMPORTED_MODULE_2__.KoObservableSet();
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
        this.childRequests = new _utils__WEBPACK_IMPORTED_MODULE_2__.KoObservableSet();
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
class CrawlerUrlBlacklist {
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


/***/ }),

/***/ "./extras/modules/quick-search/db.ts":
/*!*******************************************!*\
  !*** ./extras/modules/quick-search/db.ts ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DashboardCrawlerDb: () => (/* binding */ DashboardCrawlerDb),
/* harmony export */   ItemUsageTracker: () => (/* binding */ ItemUsageTracker)
/* harmony export */ });
/* harmony import */ var _items__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./items */ "./extras/modules/quick-search/items.ts");
/* harmony import */ var _crawler__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./crawler */ "./extras/modules/quick-search/crawler.ts");
/* harmony import */ var lodash_es__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! lodash-es */ "./node_modules/lodash-es/debounce.js");
/* harmony import */ var lodash_es__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! lodash-es */ "./node_modules/lodash-es/throttle.js");
/* harmony import */ var js_cookie__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! js-cookie */ "./node_modules/js-cookie/dist/js.cookie.mjs");


var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;


var forEachObjectKey = AmeMiniFunc.forEachObjectKey;
class ItemUsageTracker {
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
                if (item instanceof _items__WEBPACK_IMPORTED_MODULE_0__.AdminMenuItem) {
                    const value = caches.menuVisits.get(item.getRelativeMenuUrl());
                    item.lastVisitedAt = (typeof value === 'number') ? value : 0;
                }
                else if (item instanceof _items__WEBPACK_IMPORTED_MODULE_0__.DashboardItem) {
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
        /*
        In addition to storing the last-used timestamp locally, let's also let the server know
        when an item is used. The plugin will later use this to preload recent items so that
        they're available without waiting for an AJAX request.

        We store timestamps in a session cookie. The PHP script will read the cookie and update
        the database on the next page load, then delete the cookie.
        */
        const trackable = item.getUsageTrackingRef();
        if (trackable.isDefined()) {
            let pendingUpdates = { _v: 2, items: {} };
            const cookieName = 'ame-qs-used-db-items';
            const serializedUpdates = js_cookie__WEBPACK_IMPORTED_MODULE_2__["default"].get(cookieName);
            if (serializedUpdates) {
                try {
                    pendingUpdates = JSON.parse(serializedUpdates);
                    if (!pendingUpdates || (typeof pendingUpdates !== 'object') || (pendingUpdates._v !== 2)) {
                        pendingUpdates = { _v: 2, items: {} };
                    }
                }
                catch (e) {
                    console.error('Failed to parse pending item updates:', e);
                }
            }
            const ref = trackable.get();
            pendingUpdates.items[JSON.stringify(ref)] = Math.round(Date.now() / 1000);
            js_cookie__WEBPACK_IMPORTED_MODULE_2__["default"].set(cookieName, JSON.stringify(pendingUpdates), { sameSite: 'Lax', path: this.cookiePath });
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
class DashboardCrawlerDb {
    constructor(ajaxActions, preloadedRecords) {
        this.ajaxActions = ajaxActions;
        this.records = new Map();
        this.pendingCrawlRecordFetches = new Map();
        this.throttledFetchCrawlRecords = lodash_es__WEBPACK_IMPORTED_MODULE_3__["default"](lodash_es__WEBPACK_IMPORTED_MODULE_4__["default"](() => this.fetchPendingCrawlRecords(), 2000, { leading: true, trailing: true }), 200, { leading: false, trailing: true });
        this.pendingCrawlRecordSaves = new Set();
        this.throttledSaveCrawlRecords = lodash_es__WEBPACK_IMPORTED_MODULE_4__["default"](() => this.storePendingCrawlRecords(), 2000, { leading: true, trailing: true });
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
            finished = (status !== 'aborted') && _crawler__WEBPACK_IMPORTED_MODULE_1__.FinalCrawlRequestStatuses.includes(status);
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
            method: 'POST',
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


/***/ }),

/***/ "./extras/modules/quick-search/items.ts":
/*!**********************************************!*\
  !*** ./extras/modules/quick-search/items.ts ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AdminMenuItem: () => (/* binding */ AdminMenuItem),
/* harmony export */   DashboardItem: () => (/* binding */ DashboardItem),
/* harmony export */   EntityItem: () => (/* binding */ EntityItem),
/* harmony export */   LoadingPlaceholderItem: () => (/* binding */ LoadingPlaceholderItem),
/* harmony export */   SearchableItem: () => (/* binding */ SearchableItem),
/* harmony export */   unserializeItem: () => (/* binding */ unserializeItem)
/* harmony export */ });
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;
let itemInstanceCounter = 0;
class SearchableItem {
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
    performAction(requestNavigation, newTab = false) {
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
    getUsageTrackingRef() {
        return none;
    }
    toJs() {
        return {
            type: this.getSerializedType(),
            label: this.label,
            location: this.location,
        };
    }
}
class DashboardItem extends SearchableItem {
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
    performAction(requestNavigation, newTab = false) {
        console.log('Navigating to dashboard item:', this.origin, this.target);
        const targetUrl = this.getEffectiveTargetUrl();
        requestNavigation(targetUrl, this.target.selector || '', newTab);
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
    getUsageTrackingRef() {
        return some({
            engine: 'dashboard',
            item: {
                url: this.getMenuUrl(),
                id: this.getRelativeId(),
            },
        });
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
class AdminMenuItem extends SearchableItem {
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
    performAction(requestNavigation, newTab = false) {
        //If a new tab was requested, we can't use the "trigger a click" approach since that
        //doesn't provide a way to open a link in a new tab.
        if (newTab && this.url) {
            requestNavigation(this.url, '', newTab);
            return true;
        }
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
        return super.performAction(requestNavigation, newTab);
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
class EntityItem extends SearchableItem {
    constructor(props) {
        super(props);
        this.kind = props.kind;
        this.contentType = props.contentType;
        this.url = props.url;
        this.internalId = props.internalId;
    }
    getSerializedType() {
        return 'entity';
    }
    getStatusBarText(urlFormatter) {
        if (this.url) {
            return urlFormatter(this.url);
        }
        return super.getStatusBarText(urlFormatter);
    }
    performAction(requestNavigation, newTab = false) {
        if (this.url) {
            console.log('Navigating to entity URL:', this.url);
            requestNavigation(this.url, '', newTab);
            return true;
        }
        return super.performAction(requestNavigation, newTab);
    }
    getUniqueId() {
        if (this.internalId) {
            return `entity/${this.kind}:${this.contentType || ''}:${this.internalId}`;
        }
        return super.getUniqueId();
    }
    getUsageTrackingRef() {
        let ref = {
            id: this.internalId,
        };
        if (this.contentType) {
            ref['contentType'] = this.contentType;
        }
        return some({
            engine: this.kind,
            item: ref,
        });
    }
}
class LoadingPlaceholderItem extends SearchableItem {
    constructor() {
        super({
            label: 'Loading...',
        });
    }
    performAction(_, _newTab = false) {
        return false; //Do nothing and don't close the search box.
    }
    getSerializedType() {
        throw new Error('Placeholder items cannot be serialized');
    }
}
function unserializeItem(data) {
    switch (data.type) {
        case 'dashboardItem':
            return new DashboardItem(data);
        case 'entity':
            return new EntityItem(data);
        default:
            const invalidType = data.type;
            throw new Error(`Unsupported item type: ${invalidType}`);
    }
}


/***/ }),

/***/ "./extras/modules/quick-search/quick-search.ts":
/*!*****************************************************!*\
  !*** ./extras/modules/quick-search/quick-search.ts ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var lodash_es__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! lodash-es */ "./node_modules/lodash-es/throttle.js");
/* harmony import */ var loglevel__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! loglevel */ "./node_modules/loglevel/lib/loglevel.js");
/* harmony import */ var loglevel__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(loglevel__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _scanner__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./scanner */ "./extras/modules/quick-search/scanner.ts");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utils */ "./extras/modules/quick-search/utils.ts");
/* harmony import */ var _items__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./items */ "./extras/modules/quick-search/items.ts");
/* harmony import */ var _crawler__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./crawler */ "./extras/modules/quick-search/crawler.ts");
/* harmony import */ var _app_geometry__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./app-geometry */ "./extras/modules/quick-search/app-geometry.ts");
/* harmony import */ var _db__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./db */ "./extras/modules/quick-search/db.ts");
/* harmony import */ var js_cookie__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! js-cookie */ "./node_modules/js-cookie/dist/js.cookie.mjs");









/*
This annoying js-cookie library doesn't seem to work with any import syntax that doesn't
require allowSyntheticDefaultImports to be enabled in tsconfig.json.

For example, "import * as Cookies from 'js-cookie';" compiles, but the types are wrong.
The real export is at Cookies.default, while TypeScript thinks Cookies itself is the default
export. Importing as below works, for some reason.
 */

var AmeQuickSearchTool;
(function (AmeQuickSearchTool) {
    var Either = AmeMiniFunc.Either;
    var Left = AmeMiniFunc.Left;
    var Right = AmeMiniFunc.Right;
    const logger = loglevel__WEBPACK_IMPORTED_MODULE_0___default().getLogger('AmeQuickSearchTool');
    class SearchResult {
        constructor(item, score, findMenuTitle = () => null, findEntityIconClass = () => null) {
            this.findMenuTitle = findMenuTitle;
            this.iconClass = 'ame-fa ame-fa-puzzle-piece';
            this.itemClass = '';
            this.item = item;
            this.score = score;
            if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.DashboardItem) {
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
            else if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.AdminMenuItem) {
                this.iconClass = 'ame-fa ame-fa-bars';
            }
            else if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.EntityItem) {
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
            if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.LoadingPlaceholderItem) {
                this.itemClass = 'ame-qs-loading-placeholder';
            }
        }
        get metaLabel() {
            let label = this.item.getMetaLabel();
            if (label !== '') {
                return label;
            }
            let location = this.item.getLocation();
            if (this.item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.DashboardItem) {
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
            this.resultCache = new _utils__WEBPACK_IMPORTED_MODULE_2__.LRUCache(200);
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
            this.queryResponseCache = new _utils__WEBPACK_IMPORTED_MODULE_2__.LRUCache(20);
            this.activeServerQueries = ko.observable(0);
            this.throttledServerQuery = lodash_es__WEBPACK_IMPORTED_MODULE_8__["default"](this.sendPendingServerQuery, 1000, { leading: true, trailing: true });
            this.itemCache = new _utils__WEBPACK_IMPORTED_MODULE_2__.LRUCache(cacheCapacity);
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
                    const item = (0,_items__WEBPACK_IMPORTED_MODULE_3__.unserializeItem)(itemData);
                    if (item) {
                        //Exclude items associated with missing menu items.
                        if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.DashboardItem) {
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
        let $target = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.queryAdvancedSelector)(targetSelector);
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
            this.crawlerUrlBlacklist = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.lazy)(() => new _crawler__WEBPACK_IMPORTED_MODULE_4__.CrawlerUrlBlacklist([
                /^(post-new|media-new|link-add|user-new)\.php/,
                /^(update-core|plugin-install|plugin-editor|theme-editor|customize|site-editor|widgets)\.php/,
                /^(import|export|site-health)\.php$/,
                /^(erase-personal-data|export-personal-data)\.php/,
                //Some table view links, like the "Mine" link on the "Posts" page, contain a user ID.
                //Let's exclude all links like that. Otherwise, we would get a lot of duplicate pages.
                /[?&](author|user_id)=\d+/,
                //Some scanners will replace the current user ID with a placeholder. Skip those as well.
                new RegExp((0,_utils__WEBPACK_IMPORTED_MODULE_2__.escapeRegExp)(_scanner__WEBPACK_IMPORTED_MODULE_1__.userIdQueryPlaceholder)),
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
            this.geometryStore = new _app_geometry__WEBPACK_IMPORTED_MODULE_5__.PanelGeometryStore(scriptData.siteCookiePath);
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
            this.searchResults.push(new SearchResult(new _items__WEBPACK_IMPORTED_MODULE_3__.LoadingPlaceholderItem(), 1));
            const adminMenuParserResult = this.parseAdminMenuItems();
            this.currentAdminMenuItem = adminMenuParserResult.currentMenuItem;
            //Save admin menu items - they'll be used by the crawler.
            adminMenuParserResult.items.forEach((item) => {
                if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.AdminMenuItem) {
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
                const item = (0,_items__WEBPACK_IMPORTED_MODULE_3__.unserializeItem)(itemData);
                //Drop items that reference missing or blacklisted menus.
                if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.DashboardItem) {
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
                return (0,_utils__WEBPACK_IMPORTED_MODULE_2__.normalizeUrl)(url, baseUrl, this.removableQueryArgs, true).toString();
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
                        js_cookie__WEBPACK_IMPORTED_MODULE_7__["default"].set('ame-qs-recency-tracking', 'enabled', {
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
            if (this.currentUserId && url.includes(_scanner__WEBPACK_IMPORTED_MODULE_1__.userIdQueryPlaceholder)) {
                return url.replace(_scanner__WEBPACK_IMPORTED_MODULE_1__.userIdQueryPlaceholder, this.currentUserId.toString());
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
            if (js_cookie__WEBPACK_IMPORTED_MODULE_7__["default"].get(crawlerRunningCookie)) {
                return Either.left(new Error('Skipping index update because another crawl is already running.'));
            }
            const expirationInMinutes = 45;
            js_cookie__WEBPACK_IMPORTED_MODULE_7__["default"].set(crawlerRunningCookie, '1', {
                sameSite: 'Lax',
                expires: new Date(Date.now() + expirationInMinutes * 60 * 1000)
            });
            this.crawler(null);
            const blacklist = this.crawlerUrlBlacklist();
            const crawlerDb = new _db__WEBPACK_IMPORTED_MODULE_6__.DashboardCrawlerDb(this.ajaxActions, this.crawlerConfig.preloadedRecords);
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
                    const request = new _crawler__WEBPACK_IMPORTED_MODULE_4__.CrawlRequest(menuUrl, this.normalizeUrlForCrawling(item.getUrl(), window.location.href), this.adminUrl, item.getLocation(), 0, reason.value, this.getMenuComponent(menuUrl));
                    const meta = new MenuItemCrawlerInfo(item, reason.value, request, previousAttemptDate);
                    potentialRequests.push([request, meta]);
                    usedMenuUrls.add(menuUrl);
                    this.menuCrawlerMeta.push(meta);
                }
            }
            if (potentialRequests.length < 1) {
                js_cookie__WEBPACK_IMPORTED_MODULE_7__["default"].remove(crawlerRunningCookie, { sameSite: 'Lax' });
                return Either.right(0);
            }
            const crawler = new _crawler__WEBPACK_IMPORTED_MODULE_4__.Crawler(this.adminUrl, this.currentUserId, this.removableQueryArgs, this.normalizeUrlForCrawling, blacklist);
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
            js_cookie__WEBPACK_IMPORTED_MODULE_7__["default"].remove(crawlerRunningCookie, { sameSite: 'Lax' });
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
            if (!(0,_utils__WEBPACK_IMPORTED_MODULE_2__.isAdminPageUrl)(fullUrl, this.adminUrl)) {
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
            const testScanner = new _scanner__WEBPACK_IMPORTED_MODULE_1__.PageScanner(_scanner__WEBPACK_IMPORTED_MODULE_1__.builtinScanners);
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
                if (item instanceof _items__WEBPACK_IMPORTED_MODULE_3__.DashboardItem) {
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
            this.adminMenuParserResult = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.lazy)(() => (0,_scanner__WEBPACK_IMPORTED_MODULE_1__.parseAdminMenuItems)(jQuery('body'), this.adminUrl, this.scriptData.removableQueryArgs));
            this.itemUsageTracker = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.lazy)(() => {
                //Get the site path from the admin URL. WordPress might be installed in a subdirectory,
                //and we want to include that in the store name to avoid conflicts with other sites
                //on the same domain.
                const sitePath = this.adminUrl.pathname.replace(/\/wp-admin\/?$/, '');
                return new _db__WEBPACK_IMPORTED_MODULE_6__.ItemUsageTracker(sitePath, 200, this.scriptData.adminCookiePath);
            });
            this.recordCurrentVisit = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.lazy)(() => {
                const promises = [];
                const tracker = this.itemUsageTracker();
                const currentMenuItem = this.adminMenuParserResult().currentMenuItem;
                if (currentMenuItem) {
                    const relativeMenuUrl = currentMenuItem.getRelativeMenuUrl();
                    if (relativeMenuUrl) {
                        promises.push(tracker.recordMenuVisit(relativeMenuUrl));
                    }
                }
                const currentPageUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.getRelativeAdminPageUrl)(window.location.href, this.adminUrl, '', this.scriptData.removableQueryArgs);
                if (currentPageUrl) {
                    promises.push(tracker.recordPageVisit(currentPageUrl));
                }
                return Promise.all(promises);
            });
            this.pageStats = (0,_utils__WEBPACK_IMPORTED_MODULE_2__.lazy)(() => {
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
    const logger = loglevel__WEBPACK_IMPORTED_MODULE_0___default().getLogger('AmeQuickSearchTool');
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


/***/ }),

/***/ "./extras/modules/quick-search/scanner.ts":
/*!************************************************!*\
  !*** ./extras/modules/quick-search/scanner.ts ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PageScanner: () => (/* binding */ PageScanner),
/* harmony export */   builtinScanners: () => (/* binding */ builtinScanners),
/* harmony export */   parseAdminMenuItems: () => (/* binding */ parseAdminMenuItems),
/* harmony export */   userIdQueryPlaceholder: () => (/* binding */ userIdQueryPlaceholder)
/* harmony export */ });
/* harmony import */ var css_selector_generator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! css-selector-generator */ "./node_modules/css-selector-generator/esm/index.js");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils */ "./extras/modules/quick-search/utils.ts");
/* harmony import */ var _items__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./items */ "./extras/modules/quick-search/items.ts");



const $ = jQuery;
/**
 * Generate a unique CSS selector or traversal path for the given element.
 *
 * The optional childItems parameter can be used to generate more complex selector chains
 * that traverse the DOM from a child item to the parent element.
 *
 * @param $element
 * @param childItems Optional. This list should only include actual descendants (and not, for
 *                   example, items that follow a heading but are not its children in the DOM).
 * @param generalContainerSelector
 */
function generateCssSelectorFor($element, childItems = [], generalContainerSelector) {
    //While getCssSelector() is generally good at generating unique selectors, it can be
    //slow, and it tends to overuse nth-child selectors. We try a few other methods first
    //and try to constrain the behavior of getCssSelector().
    //Just use the ID if available.
    const id = $element.prop('id');
    if (id) {
        return `#${id}`;
    }
    //"css-selector-generator" tends to overuse nth-child selectors for form controls that
    //don't have IDs. Instead, we could use the name and value attributes for checkbox and
    //radio inputs.
    const $root = $element.closest('body');
    if ($element.is('input[type="checkbox"], input[type="radio"]')) {
        const name = $element.prop('name');
        const value = $element.prop('value');
        if (name && value) {
            const possibleSelector = `input[name="${name}"][value="${value}"]`;
            if ($root.find(possibleSelector).length === 1) {
                return possibleSelector;
            }
        }
    }
    else if ($element.is('select')) {
        //Select elements could also be identified by their name attribute.
        const name = $element.prop('name');
        if (name) {
            const possibleSelector = `select[name="${name}"]`;
            if ($root.find(possibleSelector).length === 1) {
                return possibleSelector;
            }
        }
    }
    //Note: I've tried to improve performance by finding a unique parent with an ID first
    //and passing it to getCssSelector() as the root element, but this seems to occasionally
    //cause getCssSelector() fall back to ":root" followed by a long selector chain.
    //Unclear why this happens, I'm not familiar with the internals of the library.
    let selector = (0,css_selector_generator__WEBPACK_IMPORTED_MODULE_0__.getCssSelector)($element.get(0), {
        selectors: [
            'id', 'class', 'attribute', 'tag',
            'nthchild'
        ],
        whitelist: [
            'settings_page_*',
            '*-php',
            '[name=*]',
            '[value=*]',
            '.subsubsub'
        ],
        blacklist: [
            '[checked]',
            '[selected]',
            '[disabled]',
            '[readonly]',
            '[type="hidden"]',
            "[checked='checked']",
            '[data-*]',
        ],
        maxCombinations: 50,
        maxCandidates: 10
    });
    //todo: Look into text fragments.
    // https://developer.mozilla.org/en-US/docs/Web/Text_fragments
    if (selector.includes('nth-child')) {
        //Avoid nth-child selectors if possible as they are likely to break if anything
        //is added to or removed from the page.
        //If the element contains any items with unique ID selectors, use those combined
        //with a "closest" operation to find the element.
        if ((childItems.length > 0) && generalContainerSelector) {
            for (const item of childItems) {
                if (item instanceof _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem) {
                    const selector = item.getSelector();
                    if ((typeof selector === 'string') && selector.startsWith('#')) {
                        const potentialPath = [
                            { selector, operation: 'find' },
                            { selector: generalContainerSelector, operation: 'closest' }
                        ];
                        //Check if the path actually leads to the element,
                        //and *only* to this element.
                        const $found = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.queryAdvancedSelector)(potentialPath);
                        if (($found.length === 1) && $found.is($element)) {
                            return potentialPath;
                        }
                    }
                }
            }
        }
        //contains() is also an option since selectors are passed to jQuery,
        //but it's likely slower and more likely to get duplicate matches.
    }
    return selector;
}
function findLabelElementFor($field, $container, ignoreParentLabel = false) {
    if (!ignoreParentLabel) {
        const $label = $field.closest('label');
        if ($label.length > 0) {
            return $label;
        }
    }
    const id = $field.prop('id');
    if (id) {
        const selector = `label[for="${id}"]`;
        const $label = $container ? $container.find(selector) : jQuery(selector);
        if ($label.length > 0) {
            return $label;
        }
    }
    return jQuery();
}
function getFormFieldLabel($field, $container, ignoreParentLabel = false) {
    let text = '';
    if ($field.is('input[type="button"]')) {
        text = $field.val() || '';
    }
    else {
        const $label = findLabelElementFor($field, $container, ignoreParentLabel);
        if ($label.length < 1) {
            return '';
        }
        text = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getElementTextForItemLabel)($label, ' ');
    }
    return (0,_utils__WEBPACK_IMPORTED_MODULE_1__.condenseWhitespace)(text);
    //todo: Limit max label length.
}
const complexControls = [
    {
        selector: '.wp-picker-container',
        description: 'Color picker',
        getLabelElement: ($control, $container) => {
            const hiddenInput = $control.find('input[type="text"].wp-color-picker').first();
            const id = hiddenInput.prop('id');
            if (id) {
                const labelSelector = `label[for="${id}"]`;
                return $container.find(labelSelector);
            }
            return jQuery();
        }
    },
    { selector: '.CodeMirror, .CodeMirror-wrap', description: 'Code editor' },
    { selector: '.wp-editor-wrap', description: 'TinyMCE editor' },
];
const complexControlSelectors = complexControls.map((control) => control.selector).join(', ');
function findControls($container, context, includeUnlabelledControls = false) {
    //Look for form controls in general. Skip already included controls.
    const $controls = $container.find('input, select, textarea, .CodeMirror, .wp-editor-wrap')
        .not('[type="hidden"], [type="submit"], [type="reset"], .button-primary');
    if ($controls.length === 0) {
        return [];
    }
    const acceptedCollapsibleContainers = [
        '.postbox',
        '.ws-ame-postbox',
    ];
    const acceptedCollapsibleSelectors = acceptedCollapsibleContainers.join(', ');
    const foundElements = new Set();
    const results = [];
    $controls.each((_, control) => {
        let $control = $(control);
        if (context.isElementFound($control) || context.isInBlacklistedContainer($control)) {
            return;
        }
        let $customLabelElement = null;
        //If the control is inside a complex control, select the complex control instead.
        const $complexControl = $control.closest(complexControlSelectors);
        if ($complexControl.length > 0) {
            $control = $complexControl;
            const complexControl = complexControls.find((spec) => $control.is(spec.selector));
            if (complexControl && complexControl.getLabelElement) {
                $customLabelElement = complexControl.getLabelElement($control, $container);
            }
        }
        //Skip controls that we've already included in the results during this run. This can
        //happen if a complex control - e.g. a color picker - contains multiple form controls.
        if (foundElements.has($control.get(0))) {
            return;
        }
        //Skip explicitly hidden controls ("display: none" or "visibility: hidden").
        if (($control.css('display') === 'none') || ($control.css('visibility') === 'hidden')) {
            // console.log('Skipping explicitly hidden control:', $control);
            return;
        }
        //Skip invisible controls unless they're inside a container that we know can be opened
        //by the user. Some invisible controls are used as templates or placeholders, so we don't
        //want to include all of them in the search results.
        if (!$control.is(':visible') && ($control.closest(acceptedCollapsibleSelectors).length === 0)) {
            // console.log('Skipping invisible control:', $control);
            return;
        }
        let label;
        if ($customLabelElement) {
            label = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getElementTextForItemLabel)($customLabelElement, ' ');
        }
        else {
            label = getFormFieldLabel($control, context.$container);
        }
        if ((label === '') && !includeUnlabelledControls) {
            // console.log('Skipping control with empty label:', $control);
            return;
        }
        results.push({ $element: $control, label });
        foundElements.add($control.get(0));
    });
    return results;
}
const userIdParams = ['user_id', 'user', 'author', 'author_id'];
const userIdQueryPlaceholder = '_ame_qs_current_user_id_';
function replaceUserIdWIthPlaceholder(relativeUrl, parsedUrl, context) {
    if (!context.currentUserId) {
        return relativeUrl;
    }
    const foundParams = userIdParams.filter((param) => parsedUrl.searchParams.has(param) && (parsedUrl.searchParams.get(param) === context.currentUserId));
    if (foundParams.length === 0) {
        return relativeUrl;
    }
    const urlCopy = new URL(parsedUrl.href);
    for (const param of foundParams) {
        urlCopy.searchParams.set(param, userIdQueryPlaceholder);
    }
    return (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(urlCopy.href, context.adminUrl, context.pageUrl, context.removableQueryArgs);
}
//region Scanner functions
//I've defined these as separate, named functions to make it easier to jump to the relevant code
//during development. They could be inline in the array below, but that's less convenient.
function* scanClassicTabs(context) {
    const items = [];
    context.$container.find('.nav-tab-wrapper .nav-tab').each((_, element) => {
        const $tab = $(element);
        if (context.isElementFound($tab) || context.isInBlacklistedContainer($tab)) {
            return;
        }
        const $link = $tab.is('a') ? $tab : $tab.find('a').first();
        const label = $tab.text().trim();
        const url = $link.prop('href') || '';
        if (url) {
            let relativeUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(url, context.adminUrl, context.pageUrl, context.removableQueryArgs);
            if (!relativeUrl) {
                return; //Skip external and non-admin links.
            }
            const parsedUrl = new URL(url, context.pageUrl);
            relativeUrl = replaceUserIdWIthPlaceholder(relativeUrl, parsedUrl, context);
            if (!relativeUrl) {
                return;
            }
            const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: label,
                ownLabel: label,
                target: {
                    url: relativeUrl,
                    type: 'tab',
                },
                location: context.location,
                origin: context.dashboardItemOrigin,
                relativeId: context.idRegistry.generateId({
                    label,
                    url: relativeUrl,
                    $element: $tab,
                    typePrefix: 'tab:'
                })
            });
            items.push(item);
            context.markElementAsFound($tab);
        }
        else {
            const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: label,
                target: {
                    url: context.relativePageUrl,
                    selector: generateCssSelectorFor($tab),
                    type: 'tab',
                },
                origin: context.dashboardItemOrigin,
                location: context.location,
                relativeId: context.idRegistry.generateId({
                    label,
                    $element: $tab,
                    typePrefix: 'tab:'
                })
            });
            items.push(item);
            context.markElementAsFound($tab);
        }
    });
    yield* items;
}
function* scanFormTableFields(context) {
    const items = [];
    context.$container.find('table.form-table').each((_, element) => {
        const $table = $(element);
        if (context.isInBlacklistedContainer($table)) {
            return;
        }
        const section = context.findParentSection($table);
        const tableLocation = context.location.slice();
        if (section) {
            tableLocation.push(section.headerText);
        }
        $table.find('> tbody > tr').each((_, row) => {
            // console.log('Row:', row);
            //We expect a <th> followed by a <td> in each row.
            //In rare cases, there can be a single <td> instead, like the "Organize my
            //uploads into month- and year-based folders" setting in "Settings -> Media".
            const $row = $(row);
            const $td = $row.find('> td').first();
            const $th = $td.prev('th');
            if ($td.length === 0) {
                return;
            }
            const rowHeading = $th.text().trim();
            const controls = findControls($td, context, true);
            if (controls.length === 0) {
                return;
            }
            const itemsInRow = [];
            for (const control of controls) {
                let label = control.label;
                if (label === '') {
                    //If there is only one control in the row, the row heading is the label.
                    if (controls.length === 1) {
                        label = rowHeading;
                    }
                    else {
                        return; //Skip unlabeled controls.
                    }
                }
                else if (rowHeading !== '') {
                    //Add the row heading to the label unless the heading already contains the label.
                    //Some admin pages do this; a field's <label> can be inside the <th>.
                    if (!label.startsWith(rowHeading)) {
                        label = rowHeading + ': ' + label;
                    }
                }
                const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                    label: label,
                    target: {
                        url: context.relativePageUrl,
                        selector: generateCssSelectorFor(control.$element),
                        type: 'control',
                    },
                    location: tableLocation,
                    origin: context.dashboardItemOrigin,
                    relativeId: context.idRegistry.generateId({
                        label,
                        $element: control.$element,
                        section
                    })
                });
                items.push(item);
                context.markElementAsFound(control.$element);
                itemsInRow.push(item);
                if (section) {
                    section.detectedItemCount++;
                }
            }
            //Add the row itself as an item if it contains multiple controls.
            if (itemsInRow.length > 1) {
                const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                    label: rowHeading,
                    target: {
                        url: context.relativePageUrl,
                        selector: generateCssSelectorFor($row, itemsInRow, 'tr'),
                        type: 'group',
                    },
                    location: tableLocation,
                    origin: context.dashboardItemOrigin,
                    relativeId: context.idRegistry.generateId({
                        label: rowHeading,
                        $element: $row,
                        section,
                        typePrefix: 'row:'
                    })
                });
                items.push(item);
                context.markElementAsFound($row);
            }
        });
    });
    yield* items;
}
function* scanGeneralFormControls(context) {
    //Look for form controls in general.
    const controls = findControls(context.$container, context);
    if (controls.length === 0) {
        return;
    }
    const items = [];
    const foundSections = new Map();
    for (const control of controls) {
        const $control = control.$element;
        let location = context.location.slice();
        const section = context.findParentSection($control);
        if (section) {
            location.push(section.headerText);
        }
        const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
            label: control.label,
            target: {
                url: context.relativePageUrl,
                selector: generateCssSelectorFor($control),
                type: 'control',
            },
            location: location,
            origin: context.dashboardItemOrigin,
            relativeId: context.idRegistry.generateId({
                label: control.label,
                $element: $control,
                section
            })
        });
        context.markElementAsFound($control);
        if (section) {
            const sectionItems = foundSections.get(section) || [];
            sectionItems.push(item);
            foundSections.set(section, sectionItems);
            section.detectedItemCount++;
        }
        else {
            items.push(item);
        }
    }
    for (const [section, sectionItems] of foundSections.entries()) {
        if (sectionItems.length < 1) {
            continue;
        }
        //Add non-empty sections as items.
        const $sectionElement = jQuery(section.element);
        if (!context.isElementFound($sectionElement)) {
            items.push(new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: section.headerText,
                target: {
                    url: context.relativePageUrl,
                    selector: generateCssSelectorFor($sectionElement, sectionItems, section.genericContainerSelector),
                    type: 'section',
                },
                location: context.location,
                origin: context.dashboardItemOrigin,
                relativeId: context.idRegistry.generateId({
                    label: section.headerText,
                    $element: $sectionElement,
                    section,
                    typePrefix: 's:'
                })
            }));
            context.markElementAsFound($sectionElement);
        }
        //If a section contains a large number of controls, the controls might not be individually
        //important. For example, it could be a list of checkboxes for all post categories, or
        //visibility settings for sidebar widgets, etc. In such cases, we should include the section
        //itself as an item but not the individual controls.
        const controlsCount = sectionItems.length;
        const bigSectionControlCountThreshold = 20;
        //Similarly, if most of the controls in a section have short labels, that's another
        //sign they're not individually important.
        const shortLabelsControlCountThreshold = 5;
        const shortLabelLength = 20;
        const shortLabelItemFraction = 0.8;
        let shortLabelsFound = 0;
        for (const item of sectionItems) {
            if (item.label.length <= shortLabelLength) {
                shortLabelsFound++;
            }
        }
        const shouldOnlyAddSection = (controlsCount > bigSectionControlCountThreshold)
            || ((controlsCount > shortLabelsControlCountThreshold)
                && ((shortLabelsFound / controlsCount) > shortLabelItemFraction));
        if (shouldOnlyAddSection) {
            console.log('Controls in section will be skipped:', section.headerText);
            console.log('Control count:', controlsCount, 'Short labels:', shortLabelsFound);
            console.log(sectionItems);
            continue;
        }
        //Otherwise, include all the controls found in the section.
        for (const item of sectionItems) {
            items.push(item);
        }
    }
    yield* items;
}
function* scanSubFilters(context) {
    //Find .subsubsub filters in the page.
    const items = [];
    context.$container.find('.subsubsub li a').each((_, element) => {
        const $link = jQuery(element);
        if (context.isElementFound($link) || context.isInBlacklistedContainer($link)) {
            return;
        }
        const hrefAttr = $link.attr('href') || '';
        const url = $link.prop('href') || '';
        let label = $link.text().trim();
        //Remove the count bubble, if any.
        label = label.replace(/\s*\(\d+\)\s*$/, '').trim();
        const ownLabel = label;
        //The filter labels alone are usually not unique enough to identify the filter
        //at a glance. Let's add the  parent section, location, or page heading to the label.
        const section = context.findParentSection($link);
        if (section) {
            label = section.headerText + ': ' + label;
        }
        else if (context.location.length > 0) {
            label = context.location[context.location.length - 1] + ': ' + label;
        }
        else {
            const headingSelector = context.$container.is('.wrap') ? 'h1' : '.wrap h1';
            const $firstHeading = context.$container.find(headingSelector).first();
            if ($firstHeading.length > 0) {
                const headingText = $firstHeading.text().trim();
                if (headingText.length > 2) {
                    label = headingText + ': ' + label;
                }
            }
        }
        if ((hrefAttr === '') || hrefAttr.startsWith('#')) {
            const selector = generateCssSelectorFor($link);
            const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: label,
                ownLabel: ownLabel,
                target: {
                    url: context.relativePageUrl,
                    selector: selector,
                    type: 'filter',
                },
                location: context.location,
                origin: context.dashboardItemOrigin,
                relativeId: context.idRegistry.generateId({
                    label,
                    $element: $link,
                    typePrefix: 'f:'
                })
            });
            items.push(item);
            context.markElementAsFound($link);
        }
        else {
            let relativeUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(url, context.adminUrl, context.pageUrl, context.removableQueryArgs);
            if (!relativeUrl) {
                return;
            }
            //Skip some useless/redundant filters, e.g. filters that effectively show the same thing
            //as clicking on the menu item. I don't see a reliable way to skip all filters like that -
            //for example, the "All" filter on the "Plugins" page doesn't have the same URL as the
            //menu item, but shows the same results. But we can skip some common cases.
            //Skip the "All" filter on "Posts", "Pages", and other post type item lists.
            const parsedUrl = new URL(url, context.pageUrl);
            if (context.pageUrl.pathname.endsWith('edit.php') && relativeUrl.startsWith('edit.php')) {
                const pagePostType = context.pageUrl.searchParams.get('post_type') || 'post';
                const filterPostType = parsedUrl.searchParams.get('post_type') || 'post';
                const paramsExcludingPostType = parsedUrl.searchParams.size - (parsedUrl.searchParams.has('post_type') ? 1 : 0);
                if ((pagePostType === filterPostType) && (paramsExcludingPostType === 0)) {
                    return;
                }
            }
            relativeUrl = replaceUserIdWIthPlaceholder(relativeUrl, parsedUrl, context);
            if (!relativeUrl) {
                return;
            }
            //Skip the "All" filter on "Plugins -> Installed Plugins".
            if (relativeUrl === 'plugins.php?plugin_status=all') {
                return;
            }
            const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: label,
                target: {
                    url: relativeUrl,
                    type: 'filter',
                },
                location: context.location,
                origin: context.dashboardItemOrigin,
                relativeId: context.idRegistry.generateId({
                    label,
                    url: relativeUrl,
                    $element: $link,
                    typePrefix: 'f:'
                })
            });
            items.push(item);
            context.markElementAsFound($link);
        }
    });
    yield* items;
}
const builtinScanners = [
    scanClassicTabs,
    scanFormTableFields,
    scanGeneralFormControls,
    scanSubFilters
];
const MaxSlugLength = 50;
function stringToSlug(str, disallowedCharsRegex) {
    return str
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(disallowedCharsRegex ?? /[^a-z0-9_=-]/g, '')
        .substring(0, MaxSlugLength);
}
class ItemIdRegistry {
    constructor(prefix) {
        this.prefix = prefix;
        this.idsWithoutPrefix = new Set();
        this.counters = new Map();
    }
    generateId(params) {
        let partialId = this.generateIdWithoutPrefix(params);
        if (!partialId) {
            //This should never happen. The ID generator should always have a fallback.
            throw new Error('Could not generate a partial ID for the item.');
        }
        this.idsWithoutPrefix.add(partialId);
        return this.prefix + partialId;
    }
    generateIdWithoutPrefix(params) {
        let possibleNonUniqueId = '';
        for (const generator of ItemIdRegistry.idGenerators) {
            let id = generator(params);
            if (id.length >= ItemIdRegistry.minPartialIdLength) {
                if (params.typePrefix) {
                    id = params.typePrefix + id;
                }
                if (this.isUnique(id)) {
                    return id;
                }
                else if (id && !possibleNonUniqueId) {
                    possibleNonUniqueId = id;
                }
            }
        }
        if (!possibleNonUniqueId) {
            possibleNonUniqueId = 'item';
        }
        //Try adding the section and see if that makes the ID unique.
        if (params.section) {
            const sectionId = stringToSlug(params.section.headerText);
            const combinedId = sectionId + '/' + possibleNonUniqueId;
            if (this.isUnique(combinedId)) {
                return combinedId;
            }
            else {
                possibleNonUniqueId = combinedId;
            }
        }
        //If no unique ID was found, add a counter to the first non-unique ID.
        let counter = this.counters.get(possibleNonUniqueId) || 0;
        counter++;
        this.counters.set(possibleNonUniqueId, counter);
        return possibleNonUniqueId + '-' + counter;
    }
    isUnique(id) {
        return !this.idsWithoutPrefix.has(id);
    }
}
ItemIdRegistry.minPartialIdLength = 3;
ItemIdRegistry.idGenerators = [
    (params) => {
        if (params.suggestedUniqueString) {
            return stringToSlug(params.suggestedUniqueString);
        }
        return '';
    },
    (params) => {
        if (params.$element && (params.$element.length > 0)) {
            const id = params.$element.prop('id');
            if (id) {
                return stringToSlug(id);
            }
        }
        return '';
    },
    (params) => {
        if (params.url) {
            return 'url=' + params.url;
        }
        return '';
    },
    (params) => {
        if (params.$element
            && (params.$element.length > 0)
            && params.$element.is('input[type="checkbox"], input[type="radio"]')) {
            const name = params.$element.prop('name');
            const value = params.$element.prop('value');
            if (name && value) {
                return stringToSlug('fld-' + name + '=' + value);
            }
        }
        return '';
    },
    (params) => {
        if (!params.label) {
            return '';
        }
        let labelSlug = stringToSlug(params.label);
        //Pad with "=" if too short.
        while (labelSlug.length < ItemIdRegistry.minPartialIdLength) {
            labelSlug += '=';
        }
        return labelSlug;
    }
];
class PageScanner {
    constructor(scanners, containerSelectorBlacklist = [
        //Special class that lets us and other plugins disable scanning of specific parts
        //of a page.
        '.ame-quick-search-no-scan',
        //Actor selector.
        '#ws_actor_selector_container',
        //"Add Menu Items" area in "Appearance -> Menus".
        '#nav-menu-meta',
        //Various tables and their navigation controls.
        'table.widefat',
        'table.wp-list-table',
        '.tablenav',
        '#posts-filter',
        //Classic editor. Also matches the comment editor.
        'form#post',
        //Gutenberg editor.
        '#editor', '.block-editor',
        //Link editor.
        'form#addlink', 'form#editlink',
        //Category and tag editor.
        'form#addtag', 'form#edittag',
        //Just the entire theme editor.
        'body.theme-editor-php',
        //Site editor.
        '#site-editor',
        //"Add User" form. The user profile page is not blacklisted; it could be useful.
        'form#createuser',
        //Certain built-in dashboard widgets. For example, the default "Quick Draft" widget
        //is probably not needed, but a custom dashboard widget might be useful.
        '#dashboard_quick_press', '#dashboard_primary',
    ], minItemLabelLength = 4) {
        this.scanners = scanners;
        this.containerSelectorBlacklist = containerSelectorBlacklist;
        this.minItemLabelLength = minItemLabelLength;
    }
    *scan($container, location, currentPageUrl, adminUrl, currentMenuRelativeUrl, removableQueryArgs, currentUserId, customIdPrefix //For sub-containers like tabs or sections on the same page.
    ) {
        const relativePageUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(currentPageUrl, adminUrl);
        if (!relativePageUrl) {
            return; //Skip non-admin pages.
        }
        console.log('Scanning container:', $container);
        const foundElements = new Set();
        const combinedContainerBlacklist = this.containerSelectorBlacklist.join(', ');
        const idRegistry = new ItemIdRegistry(customIdPrefix ?? '');
        const potentialSections = this.findPotentialSections($container);
        console.log('Potential sections:', potentialSections);
        const context = {
            $container, location, relativePageUrl, adminUrl, removableQueryArgs, currentUserId,
            pageUrl: new URL(currentPageUrl, adminUrl),
            idRegistry,
            dashboardItemOrigin: Object.freeze({
                menuUrl: currentMenuRelativeUrl,
                pageUrl: relativePageUrl
            }),
            isElementFound: ($element) => {
                return foundElements.has($element.get(0));
            },
            markElementAsFound: ($element) => {
                $element.each((_, element) => {
                    foundElements.add(element);
                });
            },
            isInBlacklistedContainer: ($element) => {
                if (!combinedContainerBlacklist) {
                    return false;
                }
                return ($element.closest(combinedContainerBlacklist).length > 0);
            },
            findParentSection: ($element, $context) => {
                let lastNonContainerSectionBeforeElement = null;
                const inputElement = $element.get(0);
                const contextElement = $context ? $context.get(0) : $container.get(0);
                for (const section of potentialSections) {
                    //Skip sections that are outside the context element.
                    if (!$.contains(contextElement, section.element)) {
                        continue;
                    }
                    if (section.isContainer) {
                        if ($.contains(section.element, inputElement)) {
                            return section;
                        }
                    }
                    else {
                        //A non-container section like an <h2> or <h3> heading can be
                        //the "parent" if it's the last section before the element.
                        const compareResult = section.element.compareDocumentPosition(inputElement);
                        if ((compareResult & Node.DOCUMENT_POSITION_FOLLOWING) > 0) {
                            lastNonContainerSectionBeforeElement = section;
                        }
                    }
                }
                return lastNonContainerSectionBeforeElement;
            }
        };
        const items = [];
        for (const scanner of this.scanners) {
            const scannedItems = scanner(context);
            for (const item of scannedItems) {
                //Skip items with very short labels.
                if ((this.minItemLabelLength > 0) && (item.label.length < this.minItemLabelLength)) {
                    continue;
                }
                items.push(item);
            }
        }
        //Add any sections that have items and haven't been added yet.
        for (const section of potentialSections) {
            if ((section.detectedItemCount < 1) || foundElements.has(section.element)) {
                continue;
            }
            const $sectionElement = jQuery(section.element);
            const item = new _items__WEBPACK_IMPORTED_MODULE_2__.DashboardItem({
                label: section.headerText,
                target: {
                    url: relativePageUrl,
                    selector: generateCssSelectorFor($sectionElement),
                    type: 'section',
                },
                location: location,
                origin: context.dashboardItemOrigin,
                relativeId: idRegistry.generateId({
                    label: section.headerText,
                    $element: $sectionElement,
                    typePrefix: 's:'
                })
            });
            items.push(item);
        }
        console.log('Scanner found ' + items.length + ' items.');
        //todo: Eventually, each item will need a unique ID.
        yield* items;
    }
    findPotentialSections($container) {
        const sections = [];
        //Find loose headings (i.e. not inside a .postbox or similar container).
        const $headings = $container.find('h2, h3').not('.nav-tab-wrapper, .screen-reader-text');
        $headings.each((_, element) => {
            const $heading = $(element);
            //In addition to containers, also exclude headings in temporary locations like admin notices.
            if ($heading.closest('.postbox, .ws-ame-postbox, .notice, .updated').length > 0) {
                return;
            }
            if (!$heading.is(':visible')) {
                return;
            }
            const text = $heading.text().trim();
            if (text) {
                sections.push({
                    element,
                    headerText: text,
                    isContainer: false,
                    detectedItemCount: 0
                });
            }
        });
        //Find .postbox and similar containers.
        const boxSelectors = ['.postbox', '.ws-ame-postbox'];
        $container.find(boxSelectors.join(', ')).each((_, element) => {
            const $postbox = $(element);
            if (!$postbox.is(':visible')) {
                return;
            }
            const $heading = $postbox.find('h2, h3, .hndle').first();
            const text = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getElementTextForItemLabel)($heading, ' ');
            if (text) {
                //Which box selector did this section match?
                let genericContainerSelector;
                for (const selector of boxSelectors) {
                    if ($postbox.is(selector)) {
                        genericContainerSelector = selector;
                        break;
                    }
                }
                sections.push({
                    element,
                    headerText: text,
                    isContainer: true,
                    detectedItemCount: 0,
                    genericContainerSelector
                });
            }
        });
        return sections;
    }
}
//region Admin menu scanning
//This is a bit different from the other scanners. The admin menu is usually parsed first, and then
//the retrieved info is used to provide context (e.g. the current menu URL) for the other scanners.
//Also, admin menu items are not permanently stored, just parsed on the fly when needed.
function parseAdminMenuItems($root, adminUrl, removableQueryArgs = []) {
    function getMenuTitle($titleContainer) {
        if ($titleContainer.is('.menu-top')) {
            const $name = $titleContainer.find('> .wp-menu-name').first();
            if ($name.length > 0) {
                return getMenuTitle($name);
            }
        }
        return (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getElementTextForItemLabel)($titleContainer, '');
    }
    const menuItems = [];
    const itemById = {};
    const itemsByRelativeUrl = {};
    let currentMenuItem = null;
    $root.find('#adminmenu li > a').each((_, element) => {
        const $link = jQuery(element);
        const $item = $link.closest('li');
        //Skip items that are hidden via CSS. They are probably not meant to be accessed directly.
        if ($item.is(':hidden') || $link.is(':hidden')) {
            return;
        }
        let url = $link.prop('href') || '';
        if (url) {
            url = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.normalizeUrl)(url, window.location.href, [], false).toString();
        }
        const itemTitle = getMenuTitle($link);
        const titleParts = [itemTitle];
        //Find the parent, if any, and include it in the title.
        const $parent = $item.parent().closest('li.menu-top');
        let relativeParentUrl = null;
        if ($parent.length > 0) {
            const parentTitle = getMenuTitle($parent.find('> a .wp-menu-name').first());
            titleParts.unshift(parentTitle);
            const parentUrl = $parent.find('> a').prop('href') || '';
            relativeParentUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(parentUrl, adminUrl, '', removableQueryArgs);
        }
        const relativeMenuUrl = (0,_utils__WEBPACK_IMPORTED_MODULE_1__.getRelativeAdminPageUrl)(url, adminUrl, '', removableQueryArgs);
        const item = new _items__WEBPACK_IMPORTED_MODULE_2__.AdminMenuItem({
            label: titleParts.join(' → '),
            url: url
        }, titleParts, $link, relativeMenuUrl, relativeParentUrl);
        menuItems.push(item);
        itemById[item.getUniqueId()] = item;
        if (relativeMenuUrl) {
            itemsByRelativeUrl[relativeMenuUrl] = item;
        }
        if ($link.is('.current')) {
            currentMenuItem = item;
        }
    });
    return {
        currentMenuItem,
        items: menuItems,
        itemsByRelativeUrl: itemsByRelativeUrl,
        itemsById: itemById
    };
}
//endregion


/***/ }),

/***/ "./extras/modules/quick-search/utils.ts":
/*!**********************************************!*\
  !*** ./extras/modules/quick-search/utils.ts ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   KoObservableSet: () => (/* binding */ KoObservableSet),
/* harmony export */   LRUCache: () => (/* binding */ LRUCache),
/* harmony export */   condenseWhitespace: () => (/* binding */ condenseWhitespace),
/* harmony export */   escapeRegExp: () => (/* binding */ escapeRegExp),
/* harmony export */   getElementTextForItemLabel: () => (/* binding */ getElementTextForItemLabel),
/* harmony export */   getRelativeAdminPageUrl: () => (/* binding */ getRelativeAdminPageUrl),
/* harmony export */   isAdminPageUrl: () => (/* binding */ isAdminPageUrl),
/* harmony export */   lazy: () => (/* binding */ lazy),
/* harmony export */   normalizeUrl: () => (/* binding */ normalizeUrl),
/* harmony export */   queryAdvancedSelector: () => (/* binding */ queryAdvancedSelector),
/* harmony export */   throttleBatchProcessor: () => (/* binding */ throttleBatchProcessor)
/* harmony export */ });
/* harmony import */ var lodash_es__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! lodash-es */ "./node_modules/lodash-es/throttle.js");

function getRelativeAdminPageUrl(url, adminUrl, baseUrl = '', removableQueryArgs = []) {
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
function isAdminPageUrl(inputUrl, adminUrl) {
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
function normalizeUrl(inputUrl, baseUrl = '', removableQueryParams = [], removeHash = true) {
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
function condenseWhitespace(text) {
    return text.replace(/\s+/g, ' ').trim();
}
function getElementTextForItemLabel($element, textPartSeparator = ' ', childBlacklist = [
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
function queryAdvancedSelector(selector) {
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
class KoObservableSet {
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
class LRUCache {
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
function lazy(factory) {
    let value = UninitializedLazyValue;
    return () => {
        if (value === UninitializedLazyValue) {
            value = factory();
        }
        return value;
    };
}
function throttleBatchProcessor(handler, waitTime) {
    const queue = new Set();
    const promiseFunctions = [];
    const throttledHandler = lodash_es__WEBPACK_IMPORTED_MODULE_0__["default"](() => {
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
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); // $& means the whole matched string
}


/***/ }),

/***/ "./node_modules/css-selector-generator/esm/constants.js":
/*!**************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/constants.js ***!
  \**************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   INVALID_CLASS_RE: () => (/* binding */ INVALID_CLASS_RE),
/* harmony export */   INVALID_ID_RE: () => (/* binding */ INVALID_ID_RE),
/* harmony export */   SELECTOR_PATTERN: () => (/* binding */ SELECTOR_PATTERN),
/* harmony export */   SELECTOR_SEPARATOR: () => (/* binding */ SELECTOR_SEPARATOR)
/* harmony export */ });
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./types.js */ "./node_modules/css-selector-generator/esm/types.js");

const SELECTOR_SEPARATOR = ", ";
// RegExp that will match invalid patterns that can be used in ID attribute.
const INVALID_ID_RE = new RegExp([
    "^$", // empty or not set
    "\\s", // contains whitespace
].join("|"));
// RegExp that will match invalid patterns that can be used in class attribute.
const INVALID_CLASS_RE = new RegExp([
    "^$", // empty or not set
].join("|"));
// Order in which a combined selector is constructed.
const SELECTOR_PATTERN = [
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.nthoftype,
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.tag,
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.id,
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.class,
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.attribute,
    _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.nthchild,
];
//# sourceMappingURL=constants.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/index.js":
/*!**********************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/index.js ***!
  \**********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__),
/* harmony export */   getCssSelector: () => (/* binding */ getCssSelector)
/* harmony export */ });
/* harmony import */ var _selector_fallback_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./selector-fallback.js */ "./node_modules/css-selector-generator/esm/selector-fallback.js");
/* harmony import */ var _utilities_options_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-options.js */ "./node_modules/css-selector-generator/esm/utilities-options.js");
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");
/* harmony import */ var _utilities_dom_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./utilities-dom.js */ "./node_modules/css-selector-generator/esm/utilities-dom.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");





/**
 * Generates unique CSS selector for an element.
 */
function getCssSelector(needle, custom_options = {}) {
    const elements = (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_2__.sanitizeSelectorNeedle)(needle);
    const options = (0,_utilities_options_js__WEBPACK_IMPORTED_MODULE_1__.sanitizeOptions)(elements[0], custom_options);
    let partialSelector = "";
    let currentRoot = options.root;
    /**
     * Utility function to make subsequent calls shorter.
     */
    function updateIdentifiableParent() {
        return (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_2__.getClosestIdentifiableParent)(elements, currentRoot, partialSelector, options);
    }
    let closestIdentifiableParent = updateIdentifiableParent();
    while (closestIdentifiableParent) {
        const { foundElements, selector } = closestIdentifiableParent;
        if ((0,_utilities_dom_js__WEBPACK_IMPORTED_MODULE_3__.testSelector)(elements, selector, options.root)) {
            return selector;
        }
        currentRoot = foundElements[0];
        partialSelector = selector;
        closestIdentifiableParent = updateIdentifiableParent();
    }
    // if failed to find single selector matching all elements, try to find
    // selector for each standalone element and join them together
    if (elements.length > 1) {
        return elements
            .map((element) => getCssSelector(element, options))
            .join(_constants_js__WEBPACK_IMPORTED_MODULE_4__.SELECTOR_SEPARATOR);
    }
    return (0,_selector_fallback_js__WEBPACK_IMPORTED_MODULE_0__.getFallbackSelector)(elements);
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (getCssSelector);
//# sourceMappingURL=index.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-attribute.js":
/*!***********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-attribute.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   attributeBlacklistMatch: () => (/* binding */ attributeBlacklistMatch),
/* harmony export */   attributeNodeToSelector: () => (/* binding */ attributeNodeToSelector),
/* harmony export */   attributeNodeToSimplifiedSelector: () => (/* binding */ attributeNodeToSimplifiedSelector),
/* harmony export */   getAttributeSelectors: () => (/* binding */ getAttributeSelectors),
/* harmony export */   getElementAttributeSelectors: () => (/* binding */ getElementAttributeSelectors),
/* harmony export */   isValidAttributeNode: () => (/* binding */ isValidAttributeNode)
/* harmony export */ });
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");


// List of attributes to be ignored. These are handled by different selector types.
const attributeBlacklistMatch = (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.createPatternMatcher)([
    "class",
    "id",
    // Angular attributes
    "ng-*",
]);
/**
 * Get simplified attribute selector for an element.
 */
function attributeNodeToSimplifiedSelector({ name, }) {
    return `[${name}]`;
}
/**
 * Get attribute selector for an element.
 */
function attributeNodeToSelector({ name, value, }) {
    return `[${name}='${value}']`;
}
/**
 * Checks whether attribute should be used as a selector.
 */
function isValidAttributeNode({ nodeName }, element) {
    // form input value should not be used as a selector
    const tagName = element.tagName.toLowerCase();
    if (["input", "option"].includes(tagName) && nodeName === "value") {
        return false;
    }
    return !attributeBlacklistMatch(nodeName);
}
/**
 * Sanitize all attribute data. We want to do it once, before we start to generate simplified/full selectors from the same data.
 */
function sanitizeAttributeData({ nodeName, nodeValue }) {
    return {
        name: (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__.sanitizeSelectorItem)(nodeName),
        value: (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__.sanitizeSelectorItem)(nodeValue),
    };
}
/**
 * Get attribute selectors for an element.
 */
function getElementAttributeSelectors(element) {
    const validAttributes = Array.from(element.attributes)
        .filter((attributeNode) => isValidAttributeNode(attributeNode, element))
        .map(sanitizeAttributeData);
    return [
        ...validAttributes.map(attributeNodeToSimplifiedSelector),
        ...validAttributes.map(attributeNodeToSelector),
    ];
}
/**
 * Get attribute selectors matching all elements.
 */
function getAttributeSelectors(elements) {
    const elementSelectors = elements.map(getElementAttributeSelectors);
    return (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.getIntersection)(elementSelectors);
}
//# sourceMappingURL=selector-attribute.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-class.js":
/*!*******************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-class.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getClassSelectors: () => (/* binding */ getClassSelectors),
/* harmony export */   getElementClassSelectors: () => (/* binding */ getElementClassSelectors)
/* harmony export */ });
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");



/**
 * Get class selectors for an element.
 */
function getElementClassSelectors(element) {
    return (element.getAttribute("class") || "")
        .trim()
        .split(/\s+/)
        .filter((item) => !_constants_js__WEBPACK_IMPORTED_MODULE_1__.INVALID_CLASS_RE.test(item))
        .map((item) => `.${(0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__.sanitizeSelectorItem)(item)}`);
}
/**
 * Get class selectors matching all elements.
 */
function getClassSelectors(elements) {
    const elementSelectors = elements.map(getElementClassSelectors);
    return (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_2__.getIntersection)(elementSelectors);
}
//# sourceMappingURL=selector-class.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-fallback.js":
/*!**********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-fallback.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementFallbackSelector: () => (/* binding */ getElementFallbackSelector),
/* harmony export */   getFallbackSelector: () => (/* binding */ getFallbackSelector)
/* harmony export */ });
/* harmony import */ var _utilities_dom_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-dom.js */ "./node_modules/css-selector-generator/esm/utilities-dom.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./types.js */ "./node_modules/css-selector-generator/esm/types.js");
/* harmony import */ var _utilities_element_data_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./utilities-element-data.js */ "./node_modules/css-selector-generator/esm/utilities-element-data.js");




/**
 * Creates fallback selector for single element.
 */
function getElementFallbackSelector(element) {
    const parentElements = (0,_utilities_dom_js__WEBPACK_IMPORTED_MODULE_0__.getElementParents)(element).reverse();
    const elementsData = parentElements.map((element) => {
        const elementData = (0,_utilities_element_data_js__WEBPACK_IMPORTED_MODULE_3__.createElementData)(element, [_types_js__WEBPACK_IMPORTED_MODULE_2__.CSS_SELECTOR_TYPE.nthchild], _types_js__WEBPACK_IMPORTED_MODULE_2__.OPERATOR.CHILD);
        elementData.selectors.nthchild.forEach((selectorData) => {
            selectorData.include = true;
        });
        return elementData;
    });
    return [":root", ...elementsData.map(_utilities_element_data_js__WEBPACK_IMPORTED_MODULE_3__.constructElementSelector)].join("");
}
/**
 * Creates chain of :nth-child selectors from root to the elements.
 */
function getFallbackSelector(elements) {
    return elements.map(getElementFallbackSelector).join(_constants_js__WEBPACK_IMPORTED_MODULE_1__.SELECTOR_SEPARATOR);
}
//# sourceMappingURL=selector-fallback.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-id.js":
/*!****************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-id.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementIdSelectors: () => (/* binding */ getElementIdSelectors),
/* harmony export */   getIdSelector: () => (/* binding */ getIdSelector)
/* harmony export */ });
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");
/* harmony import */ var _utilities_dom_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-dom.js */ "./node_modules/css-selector-generator/esm/utilities-dom.js");



/**
 * Get ID selector for an element.
 * */
function getElementIdSelectors(element) {
    const id = element.getAttribute("id") || "";
    const selector = `#${(0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__.sanitizeSelectorItem)(id)}`;
    const rootNode = element.getRootNode({ composed: false });
    return !_constants_js__WEBPACK_IMPORTED_MODULE_1__.INVALID_ID_RE.test(id) && (0,_utilities_dom_js__WEBPACK_IMPORTED_MODULE_2__.testSelector)([element], selector, rootNode)
        ? [selector]
        : [];
}
/**
 * Get ID selector for an element.
 */
function getIdSelector(elements) {
    return elements.length === 0 || elements.length > 1
        ? []
        : getElementIdSelectors(elements[0]);
}
//# sourceMappingURL=selector-id.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-nth-child.js":
/*!***********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-nth-child.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementNthChildSelector: () => (/* binding */ getElementNthChildSelector),
/* harmony export */   getNthChildSelector: () => (/* binding */ getNthChildSelector)
/* harmony export */ });
/* harmony import */ var _utilities_iselement_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-iselement.js */ "./node_modules/css-selector-generator/esm/utilities-iselement.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");


/**
 * Get nth-child selector for an element.
 */
function getElementNthChildSelector(element) {
    const parent = element.parentNode;
    if (parent) {
        const siblings = Array.from(parent.childNodes).filter(_utilities_iselement_js__WEBPACK_IMPORTED_MODULE_0__.isElement);
        const elementIndex = siblings.indexOf(element);
        if (elementIndex > -1) {
            return [`:nth-child(${elementIndex + 1})`];
        }
    }
    return [];
}
/**
 * Get nth-child selector matching all elements.
 */
function getNthChildSelector(elements) {
    return (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.getIntersection)(elements.map(getElementNthChildSelector));
}
//# sourceMappingURL=selector-nth-child.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-nth-of-type.js":
/*!*************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-nth-of-type.js ***!
  \*************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementNthOfTypeSelector: () => (/* binding */ getElementNthOfTypeSelector),
/* harmony export */   getNthOfTypeSelector: () => (/* binding */ getNthOfTypeSelector)
/* harmony export */ });
/* harmony import */ var _selector_tag_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./selector-tag.js */ "./node_modules/css-selector-generator/esm/selector-tag.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");


/**
 * Get nth-of-type selector for an element.
 */
function getElementNthOfTypeSelector(element) {
    const tag = (0,_selector_tag_js__WEBPACK_IMPORTED_MODULE_0__.getTagSelector)([element])[0];
    const parentElement = element.parentElement;
    if (parentElement) {
        const siblings = Array.from(parentElement.children).filter((element) => element.tagName.toLowerCase() === tag);
        const elementIndex = siblings.indexOf(element);
        if (elementIndex > -1) {
            return [
                `${tag}:nth-of-type(${elementIndex + 1})`,
            ];
        }
    }
    return [];
}
/**
 * Get Nth-of-type selector matching all elements.
 */
function getNthOfTypeSelector(elements) {
    return (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.getIntersection)(elements.map(getElementNthOfTypeSelector));
}
//# sourceMappingURL=selector-nth-of-type.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/selector-tag.js":
/*!*****************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/selector-tag.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementTagSelectors: () => (/* binding */ getElementTagSelectors),
/* harmony export */   getTagSelector: () => (/* binding */ getTagSelector)
/* harmony export */ });
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");


/**
 * Get tag selector for an element.
 */
function getElementTagSelectors(element) {
    return [
        (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_0__.sanitizeSelectorItem)(element.tagName.toLowerCase()),
    ];
}
/**
 * Get tag selector for list of elements.
 */
function getTagSelector(elements) {
    const selectors = [
        ...new Set((0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.flattenArray)(elements.map(getElementTagSelectors))),
    ];
    return selectors.length === 0 || selectors.length > 1 ? [] : [selectors[0]];
}
//# sourceMappingURL=selector-tag.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/types.js":
/*!**********************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/types.js ***!
  \**********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CSS_SELECTOR_TYPE: () => (/* binding */ CSS_SELECTOR_TYPE),
/* harmony export */   OPERATOR: () => (/* binding */ OPERATOR)
/* harmony export */ });
const OPERATOR = {
    NONE: "",
    DESCENDANT: " ",
    CHILD: " > ",
};
const CSS_SELECTOR_TYPE = {
    id: "id",
    class: "class",
    tag: "tag",
    attribute: "attribute",
    nthchild: "nthchild",
    nthoftype: "nthoftype",
};
//# sourceMappingURL=types.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-cartesian.js":
/*!************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-cartesian.js ***!
  \************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getCartesianProduct: () => (/* binding */ getCartesianProduct)
/* harmony export */ });
/**
 * Generates cartesian product out of input object.
 */
function getCartesianProduct(input = {}) {
    let result = [];
    Object.entries(input).forEach(([key, values]) => {
        result = values.flatMap((value) => {
            if (result.length === 0) {
                return [{ [key]: value }];
            }
            else {
                return result.map((memo) => (Object.assign(Object.assign({}, memo), { [key]: value })));
            }
        });
    });
    return result;
}
//# sourceMappingURL=utilities-cartesian.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-data.js":
/*!*******************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-data.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createPatternMatcher: () => (/* binding */ createPatternMatcher),
/* harmony export */   flattenArray: () => (/* binding */ flattenArray),
/* harmony export */   getIntersection: () => (/* binding */ getIntersection),
/* harmony export */   wildcardToRegExp: () => (/* binding */ wildcardToRegExp)
/* harmony export */ });
/* harmony import */ var _utilities_options_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-options.js */ "./node_modules/css-selector-generator/esm/utilities-options.js");
/* harmony import */ var _utilities_messages_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-messages.js */ "./node_modules/css-selector-generator/esm/utilities-messages.js");


/**
 * Creates array containing only items included in all input arrays.
 */
function getIntersection(items = []) {
    const [firstItem = [], ...otherItems] = items;
    if (otherItems.length === 0) {
        return firstItem;
    }
    return otherItems.reduce((accumulator, currentValue) => {
        return accumulator.filter((item) => currentValue.includes(item));
    }, firstItem);
}
/**
 * Converts array of arrays into a flat array.
 */
function flattenArray(input) {
    return [].concat(...input);
}
/**
 * Convert string that can contain wildcards (asterisks) to RegExp source.
 */
function wildcardToRegExp(input) {
    return (input
        // convert all special characters used by RegExp, except an asterisk
        .replace(/[|\\{}()[\]^$+?.]/g, "\\$&")
        // convert asterisk to pattern that matches anything
        .replace(/\*/g, ".+"));
}
/**
 * Creates function that will test list of provided matchers against input.
 * Used for white/blacklist functionality.
 */
function createPatternMatcher(list) {
    const matchFunctions = list.map((item) => {
        if ((0,_utilities_options_js__WEBPACK_IMPORTED_MODULE_0__.isRegExp)(item)) {
            return (input) => item.test(input);
        }
        if (typeof item === "function") {
            return (input) => {
                const result = item(input);
                if (typeof result !== "boolean") {
                    // eslint-disable-next-line max-len
                    (0,_utilities_messages_js__WEBPACK_IMPORTED_MODULE_1__.showWarning)("pattern matcher function invalid", "Provided pattern matching function does not return boolean. It's result will be ignored.", item);
                    return false;
                }
                return result;
            };
        }
        if (typeof item === "string") {
            const re = new RegExp("^" + wildcardToRegExp(item) + "$");
            return (input) => re.test(input);
        }
        // eslint-disable-next-line max-len
        (0,_utilities_messages_js__WEBPACK_IMPORTED_MODULE_1__.showWarning)("pattern matcher invalid", "Pattern matching only accepts strings, regular expressions and/or functions. This item is invalid and will be ignored.", item);
        return () => false;
    });
    return (input) => matchFunctions.some((matchFunction) => matchFunction(input));
}
//# sourceMappingURL=utilities-data.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-dom.js":
/*!******************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-dom.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getElementParents: () => (/* binding */ getElementParents),
/* harmony export */   getParents: () => (/* binding */ getParents),
/* harmony export */   getRootNode: () => (/* binding */ getRootNode),
/* harmony export */   testMultiSelector: () => (/* binding */ testMultiSelector),
/* harmony export */   testSelector: () => (/* binding */ testSelector)
/* harmony export */ });
/* harmony import */ var _utilities_iselement_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utilities-iselement.js */ "./node_modules/css-selector-generator/esm/utilities-iselement.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");
/* harmony import */ var _utilities_options_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-options.js */ "./node_modules/css-selector-generator/esm/utilities-options.js");



/**
 * Check whether element is matched uniquely by selector.
 */
function testSelector(elements, selector, root) {
    const result = Array.from((0,_utilities_options_js__WEBPACK_IMPORTED_MODULE_2__.sanitizeRoot)(root, elements[0]).querySelectorAll(selector));
    return (result.length === elements.length &&
        elements.every((element) => result.includes(element)));
}
/**
 * Test whether selector targets element. It does not have to be a unique match.
 */
function testMultiSelector(element, selector, root) {
    const result = Array.from((0,_utilities_options_js__WEBPACK_IMPORTED_MODULE_2__.sanitizeRoot)(root, element).querySelectorAll(selector));
    return result.includes(element);
}
/**
 * Find all parents of a single element.
 */
function getElementParents(element, root) {
    root = root !== null && root !== void 0 ? root : getRootNode(element);
    const result = [];
    let parent = element;
    while ((0,_utilities_iselement_js__WEBPACK_IMPORTED_MODULE_0__.isElement)(parent) && parent !== root) {
        result.push(parent);
        parent = parent.parentElement;
    }
    return result;
}
/**
 * Find all common parents of elements.
 */
function getParents(elements, root) {
    return (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_1__.getIntersection)(elements.map((element) => getElementParents(element, root)));
}
/**
 * Returns root node for given element. This needs to be used because of document-less environments, e.g. jsdom.
 */
function getRootNode(element) {
    return element.ownerDocument.querySelector(":root");
}
//# sourceMappingURL=utilities-dom.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-element-data.js":
/*!***************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-element-data.js ***!
  \***************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   constructElementSelector: () => (/* binding */ constructElementSelector),
/* harmony export */   createElementData: () => (/* binding */ createElementData),
/* harmony export */   createElementSelectorData: () => (/* binding */ createElementSelectorData)
/* harmony export */ });
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./types.js */ "./node_modules/css-selector-generator/esm/types.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");
/* harmony import */ var _utilities_selectors_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-selectors.js */ "./node_modules/css-selector-generator/esm/utilities-selectors.js");



/**
 * Creates data describing a specific selector.
 */
function createElementSelectorData(selector) {
    return {
        value: selector,
        include: false,
    };
}
/**
 * Creates data describing an element within CssSelector chain.
 */
function createElementData(element, selectorTypes, operator = _types_js__WEBPACK_IMPORTED_MODULE_0__.OPERATOR.NONE) {
    const selectors = {};
    selectorTypes.forEach((selectorType) => {
        Reflect.set(selectors, selectorType, (0,_utilities_selectors_js__WEBPACK_IMPORTED_MODULE_2__.getElementSelectorsByType)(element, selectorType).map(createElementSelectorData));
    });
    return {
        element,
        operator,
        selectors,
    };
}
/**
 * Constructs selector from element data.
 */
function constructElementSelector({ selectors, operator, }) {
    let pattern = [..._constants_js__WEBPACK_IMPORTED_MODULE_1__.SELECTOR_PATTERN];
    // `nthoftype` already contains tag
    if (selectors[_types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.tag] &&
        selectors[_types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.nthoftype]) {
        pattern = pattern.filter((item) => item !== _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.tag);
    }
    let selector = "";
    pattern.forEach((selectorType) => {
        const selectorsOfType = selectors[selectorType] || [];
        selectorsOfType.forEach(({ value, include }) => {
            if (include) {
                selector += value;
            }
        });
    });
    return (operator + selector);
}
//# sourceMappingURL=utilities-element-data.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-iselement.js":
/*!************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-iselement.js ***!
  \************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isElement: () => (/* binding */ isElement)
/* harmony export */ });
/**
 * Guard function that checks if provided `input` is an Element.
 */
function isElement(input) {
    return (typeof input === "object" &&
        input !== null &&
        input.nodeType === Node.ELEMENT_NODE);
}
//# sourceMappingURL=utilities-iselement.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-messages.js":
/*!***********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-messages.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   showWarning: () => (/* binding */ showWarning)
/* harmony export */ });
const libraryName = "CssSelectorGenerator";
/**
 * Convenient wrapper for `console.warn` using consistent formatting.
 */
function showWarning(id = "unknown problem", ...args) {
    // eslint-disable-next-line no-console
    console.warn(`${libraryName}: ${id}`, ...args);
}
//# sourceMappingURL=utilities-messages.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-options.js":
/*!**********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-options.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DEFAULT_OPTIONS: () => (/* binding */ DEFAULT_OPTIONS),
/* harmony export */   isCssSelectorMatch: () => (/* binding */ isCssSelectorMatch),
/* harmony export */   isNode: () => (/* binding */ isNode),
/* harmony export */   isParentNode: () => (/* binding */ isParentNode),
/* harmony export */   isRegExp: () => (/* binding */ isRegExp),
/* harmony export */   sanitizeCssSelectorMatchList: () => (/* binding */ sanitizeCssSelectorMatchList),
/* harmony export */   sanitizeMaxNumber: () => (/* binding */ sanitizeMaxNumber),
/* harmony export */   sanitizeOptions: () => (/* binding */ sanitizeOptions),
/* harmony export */   sanitizeRoot: () => (/* binding */ sanitizeRoot),
/* harmony export */   sanitizeSelectorTypes: () => (/* binding */ sanitizeSelectorTypes)
/* harmony export */ });
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./types.js */ "./node_modules/css-selector-generator/esm/types.js");
/* harmony import */ var _utilities_typescript_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utilities-typescript.js */ "./node_modules/css-selector-generator/esm/utilities-typescript.js");
/* harmony import */ var _utilities_messages_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utilities-messages.js */ "./node_modules/css-selector-generator/esm/utilities-messages.js");



const DEFAULT_OPTIONS = {
    selectors: [
        _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.id,
        _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.class,
        _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.tag,
        _types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE.attribute,
    ],
    // if set to true, always include tag name
    includeTag: false,
    whitelist: [],
    blacklist: [],
    combineWithinSelector: true,
    combineBetweenSelectors: true,
    root: null,
    maxCombinations: Number.POSITIVE_INFINITY,
    maxCandidates: Number.POSITIVE_INFINITY,
};
/**
 * Makes sure returned value is a list containing only valid selector types.
 * @param input
 */
function sanitizeSelectorTypes(input) {
    if (!Array.isArray(input)) {
        return [];
    }
    return input.filter((item) => (0,_utilities_typescript_js__WEBPACK_IMPORTED_MODULE_1__.isEnumValue)(_types_js__WEBPACK_IMPORTED_MODULE_0__.CSS_SELECTOR_TYPE, item));
}
/**
 * Checks whether provided value is of type RegExp.
 */
function isRegExp(input) {
    return input instanceof RegExp;
}
/**
 * Checks whether provided value is usable in whitelist or blacklist.
 * @param input
 */
function isCssSelectorMatch(input) {
    return ["string", "function"].includes(typeof input) || isRegExp(input);
}
/**
 * Converts input to a list of valid values for whitelist or blacklist.
 */
function sanitizeCssSelectorMatchList(input) {
    if (!Array.isArray(input)) {
        return [];
    }
    return input.filter(isCssSelectorMatch);
}
/**
 * Checks whether provided value is valid Node.
 */
function isNode(input) {
    return input instanceof Node;
}
/**
 * Checks whether provided value is valid ParentNode.
 */
function isParentNode(input) {
    const validParentNodeTypes = [
        Node.DOCUMENT_NODE,
        Node.DOCUMENT_FRAGMENT_NODE, // this includes Shadow DOM root
        Node.ELEMENT_NODE,
    ];
    return isNode(input) && validParentNodeTypes.includes(input.nodeType);
}
/**
 * Makes sure that the root node in options is valid.
 */
function sanitizeRoot(input, element) {
    if (isParentNode(input)) {
        if (!input.contains(element)) {
            // eslint-disable-next-line max-len
            (0,_utilities_messages_js__WEBPACK_IMPORTED_MODULE_2__.showWarning)("element root mismatch", "Provided root does not contain the element. This will most likely result in producing a fallback selector using element's real root node. If you plan to use the selector using provided root (e.g. `root.querySelector`), it will nto work as intended.");
        }
        return input;
    }
    const rootNode = element.getRootNode({ composed: false });
    if (isParentNode(rootNode)) {
        if (rootNode !== document) {
            // eslint-disable-next-line max-len
            (0,_utilities_messages_js__WEBPACK_IMPORTED_MODULE_2__.showWarning)("shadow root inferred", "You did not provide a root and the element is a child of Shadow DOM. This will produce a selector using ShadowRoot as a root. If you plan to use the selector using document as a root (e.g. `document.querySelector`), it will not work as intended.");
        }
        return rootNode;
    }
    return element.ownerDocument.querySelector(":root");
}
/**
 * Makes sure that the output is a number, usable as `maxResults` option in
 * powerset generator.
 */
function sanitizeMaxNumber(input) {
    return typeof input === "number" ? input : Number.POSITIVE_INFINITY;
}
/**
 * Makes sure the options object contains all required keys.
 */
function sanitizeOptions(element, custom_options = {}) {
    const options = Object.assign(Object.assign({}, DEFAULT_OPTIONS), custom_options);
    return {
        selectors: sanitizeSelectorTypes(options.selectors),
        whitelist: sanitizeCssSelectorMatchList(options.whitelist),
        blacklist: sanitizeCssSelectorMatchList(options.blacklist),
        root: sanitizeRoot(options.root, element),
        combineWithinSelector: !!options.combineWithinSelector,
        combineBetweenSelectors: !!options.combineBetweenSelectors,
        includeTag: !!options.includeTag,
        maxCombinations: sanitizeMaxNumber(options.maxCombinations),
        maxCandidates: sanitizeMaxNumber(options.maxCandidates),
    };
}
//# sourceMappingURL=utilities-options.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-powerset.js":
/*!***********************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-powerset.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getPowerSet: () => (/* binding */ getPowerSet),
/* harmony export */   powerSetGenerator: () => (/* binding */ powerSetGenerator)
/* harmony export */ });
function* powerSetGenerator(input = [], { maxResults = Number.POSITIVE_INFINITY } = {}) {
    let resultCounter = 0;
    let offsets = generateOffsets(1);
    while (offsets.length <= input.length && resultCounter < maxResults) {
        resultCounter += 1;
        const result = offsets.map((offset) => input[offset]);
        yield result;
        offsets = bumpOffsets(offsets, input.length - 1);
    }
}
/**
 * Generates power set of input items.
 */
function getPowerSet(input = [], { maxResults = Number.POSITIVE_INFINITY } = {}) {
    return Array.from(powerSetGenerator(input, { maxResults }));
}
/**
 * Helper function used by `getPowerSet`. Updates internal pointers.
 */
function bumpOffsets(offsets = [], maxValue = 0) {
    const size = offsets.length;
    if (size === 0) {
        return [];
    }
    const result = [...offsets];
    result[size - 1] += 1;
    for (let index = size - 1; index >= 0; index--) {
        if (result[index] > maxValue) {
            if (index === 0) {
                return generateOffsets(size + 1);
            }
            else {
                result[index - 1]++;
                result[index] = result[index - 1] + 1;
            }
        }
    }
    if (result[size - 1] > maxValue) {
        return generateOffsets(size + 1);
    }
    return result;
}
/**
 * Generates array of size N, filled with numbers sequence starting from 0.
 */
function generateOffsets(size = 1) {
    return Array.from(Array(size).keys());
}
//# sourceMappingURL=utilities-powerset.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-selectors.js":
/*!************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-selectors.js ***!
  \************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ELEMENT_SELECTOR_TYPE_GETTERS: () => (/* binding */ ELEMENT_SELECTOR_TYPE_GETTERS),
/* harmony export */   ESCAPED_COLON: () => (/* binding */ ESCAPED_COLON),
/* harmony export */   SELECTOR_TYPE_GETTERS: () => (/* binding */ SELECTOR_TYPE_GETTERS),
/* harmony export */   SPECIAL_CHARACTERS_RE: () => (/* binding */ SPECIAL_CHARACTERS_RE),
/* harmony export */   combineSelectorTypes: () => (/* binding */ combineSelectorTypes),
/* harmony export */   constructSelector: () => (/* binding */ constructSelector),
/* harmony export */   constructSelectorType: () => (/* binding */ constructSelectorType),
/* harmony export */   constructSelectors: () => (/* binding */ constructSelectors),
/* harmony export */   filterSelectors: () => (/* binding */ filterSelectors),
/* harmony export */   getAllSelectors: () => (/* binding */ getAllSelectors),
/* harmony export */   getClosestIdentifiableParent: () => (/* binding */ getClosestIdentifiableParent),
/* harmony export */   getElementSelectorsByType: () => (/* binding */ getElementSelectorsByType),
/* harmony export */   getSelectorWithinRoot: () => (/* binding */ getSelectorWithinRoot),
/* harmony export */   getSelectorsByType: () => (/* binding */ getSelectorsByType),
/* harmony export */   getSelectorsList: () => (/* binding */ getSelectorsList),
/* harmony export */   getSelectorsToGet: () => (/* binding */ getSelectorsToGet),
/* harmony export */   getTypeCombinations: () => (/* binding */ getTypeCombinations),
/* harmony export */   legacySanitizeSelectorItem: () => (/* binding */ legacySanitizeSelectorItem),
/* harmony export */   orderSelectors: () => (/* binding */ orderSelectors),
/* harmony export */   sanitizeSelectorItem: () => (/* binding */ sanitizeSelectorItem),
/* harmony export */   sanitizeSelectorNeedle: () => (/* binding */ sanitizeSelectorNeedle)
/* harmony export */ });
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants.js */ "./node_modules/css-selector-generator/esm/constants.js");
/* harmony import */ var _selector_attribute_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./selector-attribute.js */ "./node_modules/css-selector-generator/esm/selector-attribute.js");
/* harmony import */ var _selector_class_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selector-class.js */ "./node_modules/css-selector-generator/esm/selector-class.js");
/* harmony import */ var _selector_id_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./selector-id.js */ "./node_modules/css-selector-generator/esm/selector-id.js");
/* harmony import */ var _selector_nth_child_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./selector-nth-child.js */ "./node_modules/css-selector-generator/esm/selector-nth-child.js");
/* harmony import */ var _selector_nth_of_type_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./selector-nth-of-type.js */ "./node_modules/css-selector-generator/esm/selector-nth-of-type.js");
/* harmony import */ var _selector_tag_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./selector-tag.js */ "./node_modules/css-selector-generator/esm/selector-tag.js");
/* harmony import */ var _utilities_data_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./utilities-data.js */ "./node_modules/css-selector-generator/esm/utilities-data.js");
/* harmony import */ var _utilities_dom_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./utilities-dom.js */ "./node_modules/css-selector-generator/esm/utilities-dom.js");
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./types.js */ "./node_modules/css-selector-generator/esm/types.js");
/* harmony import */ var _utilities_iselement_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./utilities-iselement.js */ "./node_modules/css-selector-generator/esm/utilities-iselement.js");
/* harmony import */ var _utilities_powerset_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./utilities-powerset.js */ "./node_modules/css-selector-generator/esm/utilities-powerset.js");
/* harmony import */ var _utilities_cartesian_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./utilities-cartesian.js */ "./node_modules/css-selector-generator/esm/utilities-cartesian.js");













const ESCAPED_COLON = ":".charCodeAt(0).toString(16).toUpperCase();
// Square brackets need to be escaped, but eslint has a problem with that.
/* eslint-disable-next-line no-useless-escape */
const SPECIAL_CHARACTERS_RE = /[ !"#$%&'()\[\]{|}<>*+,./;=?@^`~\\]/;
/**
 * Escapes special characters used by CSS selector items.
 */
function sanitizeSelectorItem(input = "") {
    var _a, _b;
    return (_b = (_a = CSS === null || CSS === void 0 ? void 0 : CSS.escape) === null || _a === void 0 ? void 0 : _a.call(CSS, input)) !== null && _b !== void 0 ? _b : legacySanitizeSelectorItem(input);
}
/**
 * Legacy version of escaping utility, originally used for IE11-. Should
 * probably be replaced by a polyfill:
 * https://github.com/mathiasbynens/CSS.escape
 */
function legacySanitizeSelectorItem(input = "") {
    return input
        .split("")
        .map((character) => {
        if (character === ":") {
            return `\\${ESCAPED_COLON} `;
        }
        if (SPECIAL_CHARACTERS_RE.test(character)) {
            return `\\${character}`;
        }
        return escape(character).replace(/%/g, "\\");
    })
        .join("");
}
const SELECTOR_TYPE_GETTERS = {
    tag: _selector_tag_js__WEBPACK_IMPORTED_MODULE_6__.getTagSelector,
    id: _selector_id_js__WEBPACK_IMPORTED_MODULE_3__.getIdSelector,
    class: _selector_class_js__WEBPACK_IMPORTED_MODULE_2__.getClassSelectors,
    attribute: _selector_attribute_js__WEBPACK_IMPORTED_MODULE_1__.getAttributeSelectors,
    nthchild: _selector_nth_child_js__WEBPACK_IMPORTED_MODULE_4__.getNthChildSelector,
    nthoftype: _selector_nth_of_type_js__WEBPACK_IMPORTED_MODULE_5__.getNthOfTypeSelector,
};
const ELEMENT_SELECTOR_TYPE_GETTERS = {
    tag: _selector_tag_js__WEBPACK_IMPORTED_MODULE_6__.getElementTagSelectors,
    id: _selector_id_js__WEBPACK_IMPORTED_MODULE_3__.getElementIdSelectors,
    class: _selector_class_js__WEBPACK_IMPORTED_MODULE_2__.getElementClassSelectors,
    attribute: _selector_attribute_js__WEBPACK_IMPORTED_MODULE_1__.getElementAttributeSelectors,
    nthchild: _selector_nth_child_js__WEBPACK_IMPORTED_MODULE_4__.getElementNthChildSelector,
    nthoftype: _selector_nth_of_type_js__WEBPACK_IMPORTED_MODULE_5__.getElementNthOfTypeSelector,
};
/**
 * Creates selector of given type for single element.
 */
function getElementSelectorsByType(element, selectorType) {
    return ELEMENT_SELECTOR_TYPE_GETTERS[selectorType](element);
}
/**
 * Returns list of selectors of given type for the element.
 */
function getSelectorsByType(elements, selector_type) {
    var _a;
    const getter = (_a = SELECTOR_TYPE_GETTERS[selector_type]) !== null && _a !== void 0 ? _a : (() => []);
    return getter(elements);
}
/**
 * Remove blacklisted selectors from list.
 */
function filterSelectors(list = [], matchBlacklist, matchWhitelist) {
    return list.filter((item) => matchWhitelist(item) || !matchBlacklist(item));
}
/**
 * Prioritise whitelisted selectors in list.
 */
function orderSelectors(list = [], matchWhitelist) {
    return list.sort((a, b) => {
        const a_is_whitelisted = matchWhitelist(a);
        const b_is_whitelisted = matchWhitelist(b);
        if (a_is_whitelisted && !b_is_whitelisted) {
            return -1;
        }
        if (!a_is_whitelisted && b_is_whitelisted) {
            return 1;
        }
        return 0;
    });
}
/**
 * Returns list of unique selectors applicable to given element.
 */
function getAllSelectors(elements, root, options) {
    const selectors_list = getSelectorsList(elements, options);
    const type_combinations = getTypeCombinations(selectors_list, options);
    const all_selectors = (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_7__.flattenArray)(type_combinations);
    return [...new Set(all_selectors)];
}
/**
 * Creates object containing all selector types and their potential values.
 */
function getSelectorsList(elements, options) {
    const { blacklist, whitelist, combineWithinSelector, maxCombinations } = options;
    const matchBlacklist = (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_7__.createPatternMatcher)(blacklist);
    const matchWhitelist = (0,_utilities_data_js__WEBPACK_IMPORTED_MODULE_7__.createPatternMatcher)(whitelist);
    const reducer = (data, selector_type) => {
        const selectors_by_type = getSelectorsByType(elements, selector_type);
        const filtered_selectors = filterSelectors(selectors_by_type, matchBlacklist, matchWhitelist);
        const found_selectors = orderSelectors(filtered_selectors, matchWhitelist);
        data[selector_type] = combineWithinSelector
            ? (0,_utilities_powerset_js__WEBPACK_IMPORTED_MODULE_11__.getPowerSet)(found_selectors, { maxResults: maxCombinations })
            : found_selectors.map((item) => [item]);
        return data;
    };
    return getSelectorsToGet(options).reduce(reducer, {});
}
/**
 * Creates list of selector types that we will need to generate the selector.
 */
function getSelectorsToGet(options) {
    const { selectors, includeTag } = options;
    const selectors_to_get = [].concat(selectors);
    if (includeTag && !selectors_to_get.includes("tag")) {
        selectors_to_get.push("tag");
    }
    return selectors_to_get;
}
/**
 * Adds "tag" to a list, if it does not contain it. Used to modify selectors
 * list when includeTag option is enabled to make sure all results contain the
 * TAG part.
 */
function addTagTypeIfNeeded(list) {
    return list.includes(_types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.tag) ||
        list.includes(_types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.nthoftype)
        ? [...list]
        : [...list, _types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.tag];
}
/**
 * Generates list of possible selector type combinations.
 */
function combineSelectorTypes(options) {
    const { selectors, combineBetweenSelectors, includeTag, maxCandidates } = options;
    const combinations = combineBetweenSelectors
        ? (0,_utilities_powerset_js__WEBPACK_IMPORTED_MODULE_11__.getPowerSet)(selectors, { maxResults: maxCandidates })
        : selectors.map((item) => [item]);
    return includeTag ? combinations.map(addTagTypeIfNeeded) : combinations;
}
/**
 * Generates list of combined CSS selectors.
 */
function getTypeCombinations(selectors_list, options) {
    return combineSelectorTypes(options)
        .map((item) => {
        return constructSelectors(item, selectors_list);
    })
        .filter((item) => item.length > 0);
}
/**
 * Generates all variations of possible selectors from provided data.
 */
function constructSelectors(selector_types, selectors_by_type) {
    const data = {};
    selector_types.forEach((selector_type) => {
        const selector_variants = selectors_by_type[selector_type];
        if (selector_variants.length > 0) {
            data[selector_type] = selector_variants;
        }
    });
    const combinations = (0,_utilities_cartesian_js__WEBPACK_IMPORTED_MODULE_12__.getCartesianProduct)(data);
    return combinations.map(constructSelector);
}
/**
 * Creates selector for given selector type. Combines several parts if needed.
 */
function constructSelectorType(selector_type, selectors_data) {
    return selectors_data[selector_type]
        ? selectors_data[selector_type].join("")
        : "";
}
/**
 * Converts selector data object to a selector.
 */
function constructSelector(selectorData = {}) {
    const pattern = [..._constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTOR_PATTERN];
    // selector "nthoftype" already contains "tag"
    if (selectorData[_types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.tag] &&
        selectorData[_types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.nthoftype]) {
        pattern.splice(pattern.indexOf(_types_js__WEBPACK_IMPORTED_MODULE_9__.CSS_SELECTOR_TYPE.tag), 1);
    }
    return pattern
        .map((type) => constructSelectorType(type, selectorData))
        .join("");
}
/**
 * Generates combinations of child and descendant selectors within root
 * selector.
 */
function generateCandidateCombinations(selectors, rootSelector) {
    return [
        ...selectors.map((selector) => rootSelector + _types_js__WEBPACK_IMPORTED_MODULE_9__.OPERATOR.DESCENDANT + selector),
        ...selectors.map((selector) => rootSelector + _types_js__WEBPACK_IMPORTED_MODULE_9__.OPERATOR.CHILD + selector),
    ];
}
/**
 * Generates a list of selector candidates that can potentially match target
 * element.
 */
function generateCandidates(selectors, rootSelector) {
    return rootSelector === ""
        ? selectors
        : generateCandidateCombinations(selectors, rootSelector);
}
/**
 * Tries to find an unique CSS selector for element within given parent.
 */
function getSelectorWithinRoot(elements, root, rootSelector = "", options) {
    const elementSelectors = getAllSelectors(elements, options.root, options);
    const selectorCandidates = generateCandidates(elementSelectors, rootSelector);
    for (const candidateSelector of selectorCandidates) {
        if ((0,_utilities_dom_js__WEBPACK_IMPORTED_MODULE_8__.testSelector)(elements, candidateSelector, options.root)) {
            return candidateSelector;
        }
    }
    return null;
}
/**
 * Climbs through parents of the element and tries to find the one that is
 * identifiable by unique CSS selector.
 */
function getClosestIdentifiableParent(elements, root, rootSelector = "", options) {
    if (elements.length === 0) {
        return null;
    }
    const candidatesList = [
        elements.length > 1 ? elements : [],
        ...(0,_utilities_dom_js__WEBPACK_IMPORTED_MODULE_8__.getParents)(elements, root).map((element) => [element]),
    ];
    for (const currentElements of candidatesList) {
        const result = getSelectorWithinRoot(currentElements, root, rootSelector, options);
        if (result) {
            return {
                foundElements: currentElements,
                selector: result,
            };
        }
    }
    return null;
}
/**
 * Converts input into list of elements, removing duplicates and non-elements.
 */
function sanitizeSelectorNeedle(needle) {
    if (needle instanceof NodeList || needle instanceof HTMLCollection) {
        needle = Array.from(needle);
    }
    const elements = (Array.isArray(needle) ? needle : [needle]).filter(_utilities_iselement_js__WEBPACK_IMPORTED_MODULE_10__.isElement);
    return [...new Set(elements)];
}
//# sourceMappingURL=utilities-selectors.js.map

/***/ }),

/***/ "./node_modules/css-selector-generator/esm/utilities-typescript.js":
/*!*************************************************************************!*\
  !*** ./node_modules/css-selector-generator/esm/utilities-typescript.js ***!
  \*************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isEnumValue: () => (/* binding */ isEnumValue)
/* harmony export */ });
/**
 * Checks whether value is one of the enum's values.
 */
function isEnumValue(haystack, needle) {
    return Object.values(haystack).includes(needle);
}
//# sourceMappingURL=utilities-typescript.js.map

/***/ }),

/***/ "./node_modules/js-cookie/dist/js.cookie.mjs":
/*!***************************************************!*\
  !*** ./node_modules/js-cookie/dist/js.cookie.mjs ***!
  \***************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ api)
/* harmony export */ });
/*! js-cookie v3.0.5 | MIT */
/* eslint-disable no-var */
function assign (target) {
  for (var i = 1; i < arguments.length; i++) {
    var source = arguments[i];
    for (var key in source) {
      target[key] = source[key];
    }
  }
  return target
}
/* eslint-enable no-var */

/* eslint-disable no-var */
var defaultConverter = {
  read: function (value) {
    if (value[0] === '"') {
      value = value.slice(1, -1);
    }
    return value.replace(/(%[\dA-F]{2})+/gi, decodeURIComponent)
  },
  write: function (value) {
    return encodeURIComponent(value).replace(
      /%(2[346BF]|3[AC-F]|40|5[BDE]|60|7[BCD])/g,
      decodeURIComponent
    )
  }
};
/* eslint-enable no-var */

/* eslint-disable no-var */

function init (converter, defaultAttributes) {
  function set (name, value, attributes) {
    if (typeof document === 'undefined') {
      return
    }

    attributes = assign({}, defaultAttributes, attributes);

    if (typeof attributes.expires === 'number') {
      attributes.expires = new Date(Date.now() + attributes.expires * 864e5);
    }
    if (attributes.expires) {
      attributes.expires = attributes.expires.toUTCString();
    }

    name = encodeURIComponent(name)
      .replace(/%(2[346B]|5E|60|7C)/g, decodeURIComponent)
      .replace(/[()]/g, escape);

    var stringifiedAttributes = '';
    for (var attributeName in attributes) {
      if (!attributes[attributeName]) {
        continue
      }

      stringifiedAttributes += '; ' + attributeName;

      if (attributes[attributeName] === true) {
        continue
      }

      // Considers RFC 6265 section 5.2:
      // ...
      // 3.  If the remaining unparsed-attributes contains a %x3B (";")
      //     character:
      // Consume the characters of the unparsed-attributes up to,
      // not including, the first %x3B (";") character.
      // ...
      stringifiedAttributes += '=' + attributes[attributeName].split(';')[0];
    }

    return (document.cookie =
      name + '=' + converter.write(value, name) + stringifiedAttributes)
  }

  function get (name) {
    if (typeof document === 'undefined' || (arguments.length && !name)) {
      return
    }

    // To prevent the for loop in the first place assign an empty array
    // in case there are no cookies at all.
    var cookies = document.cookie ? document.cookie.split('; ') : [];
    var jar = {};
    for (var i = 0; i < cookies.length; i++) {
      var parts = cookies[i].split('=');
      var value = parts.slice(1).join('=');

      try {
        var found = decodeURIComponent(parts[0]);
        jar[found] = converter.read(value, found);

        if (name === found) {
          break
        }
      } catch (e) {}
    }

    return name ? jar[name] : jar
  }

  return Object.create(
    {
      set,
      get,
      remove: function (name, attributes) {
        set(
          name,
          '',
          assign({}, attributes, {
            expires: -1
          })
        );
      },
      withAttributes: function (attributes) {
        return init(this.converter, assign({}, this.attributes, attributes))
      },
      withConverter: function (converter) {
        return init(assign({}, this.converter, converter), this.attributes)
      }
    },
    {
      attributes: { value: Object.freeze(defaultAttributes) },
      converter: { value: Object.freeze(converter) }
    }
  )
}

var api = init(defaultConverter, { path: '/' });
/* eslint-enable no-var */




/***/ }),

/***/ "./node_modules/lodash-es/_Symbol.js":
/*!*******************************************!*\
  !*** ./node_modules/lodash-es/_Symbol.js ***!
  \*******************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _root_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_root.js */ "./node_modules/lodash-es/_root.js");


/** Built-in value references. */
var Symbol = _root_js__WEBPACK_IMPORTED_MODULE_0__["default"].Symbol;

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Symbol);


/***/ }),

/***/ "./node_modules/lodash-es/_baseGetTag.js":
/*!***********************************************!*\
  !*** ./node_modules/lodash-es/_baseGetTag.js ***!
  \***********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _Symbol_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_Symbol.js */ "./node_modules/lodash-es/_Symbol.js");
/* harmony import */ var _getRawTag_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./_getRawTag.js */ "./node_modules/lodash-es/_getRawTag.js");
/* harmony import */ var _objectToString_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./_objectToString.js */ "./node_modules/lodash-es/_objectToString.js");




/** `Object#toString` result references. */
var nullTag = '[object Null]',
    undefinedTag = '[object Undefined]';

/** Built-in value references. */
var symToStringTag = _Symbol_js__WEBPACK_IMPORTED_MODULE_0__["default"] ? _Symbol_js__WEBPACK_IMPORTED_MODULE_0__["default"].toStringTag : undefined;

/**
 * The base implementation of `getTag` without fallbacks for buggy environments.
 *
 * @private
 * @param {*} value The value to query.
 * @returns {string} Returns the `toStringTag`.
 */
function baseGetTag(value) {
  if (value == null) {
    return value === undefined ? undefinedTag : nullTag;
  }
  return (symToStringTag && symToStringTag in Object(value))
    ? (0,_getRawTag_js__WEBPACK_IMPORTED_MODULE_1__["default"])(value)
    : (0,_objectToString_js__WEBPACK_IMPORTED_MODULE_2__["default"])(value);
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (baseGetTag);


/***/ }),

/***/ "./node_modules/lodash-es/_baseTrim.js":
/*!*********************************************!*\
  !*** ./node_modules/lodash-es/_baseTrim.js ***!
  \*********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _trimmedEndIndex_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_trimmedEndIndex.js */ "./node_modules/lodash-es/_trimmedEndIndex.js");


/** Used to match leading whitespace. */
var reTrimStart = /^\s+/;

/**
 * The base implementation of `_.trim`.
 *
 * @private
 * @param {string} string The string to trim.
 * @returns {string} Returns the trimmed string.
 */
function baseTrim(string) {
  return string
    ? string.slice(0, (0,_trimmedEndIndex_js__WEBPACK_IMPORTED_MODULE_0__["default"])(string) + 1).replace(reTrimStart, '')
    : string;
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (baseTrim);


/***/ }),

/***/ "./node_modules/lodash-es/_freeGlobal.js":
/*!***********************************************!*\
  !*** ./node_modules/lodash-es/_freeGlobal.js ***!
  \***********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/** Detect free variable `global` from Node.js. */
var freeGlobal = typeof global == 'object' && global && global.Object === Object && global;

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (freeGlobal);


/***/ }),

/***/ "./node_modules/lodash-es/_getRawTag.js":
/*!**********************************************!*\
  !*** ./node_modules/lodash-es/_getRawTag.js ***!
  \**********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _Symbol_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_Symbol.js */ "./node_modules/lodash-es/_Symbol.js");


/** Used for built-in method references. */
var objectProto = Object.prototype;

/** Used to check objects for own properties. */
var hasOwnProperty = objectProto.hasOwnProperty;

/**
 * Used to resolve the
 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
 * of values.
 */
var nativeObjectToString = objectProto.toString;

/** Built-in value references. */
var symToStringTag = _Symbol_js__WEBPACK_IMPORTED_MODULE_0__["default"] ? _Symbol_js__WEBPACK_IMPORTED_MODULE_0__["default"].toStringTag : undefined;

/**
 * A specialized version of `baseGetTag` which ignores `Symbol.toStringTag` values.
 *
 * @private
 * @param {*} value The value to query.
 * @returns {string} Returns the raw `toStringTag`.
 */
function getRawTag(value) {
  var isOwn = hasOwnProperty.call(value, symToStringTag),
      tag = value[symToStringTag];

  try {
    value[symToStringTag] = undefined;
    var unmasked = true;
  } catch (e) {}

  var result = nativeObjectToString.call(value);
  if (unmasked) {
    if (isOwn) {
      value[symToStringTag] = tag;
    } else {
      delete value[symToStringTag];
    }
  }
  return result;
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (getRawTag);


/***/ }),

/***/ "./node_modules/lodash-es/_objectToString.js":
/*!***************************************************!*\
  !*** ./node_modules/lodash-es/_objectToString.js ***!
  \***************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/** Used for built-in method references. */
var objectProto = Object.prototype;

/**
 * Used to resolve the
 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
 * of values.
 */
var nativeObjectToString = objectProto.toString;

/**
 * Converts `value` to a string using `Object.prototype.toString`.
 *
 * @private
 * @param {*} value The value to convert.
 * @returns {string} Returns the converted string.
 */
function objectToString(value) {
  return nativeObjectToString.call(value);
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (objectToString);


/***/ }),

/***/ "./node_modules/lodash-es/_root.js":
/*!*****************************************!*\
  !*** ./node_modules/lodash-es/_root.js ***!
  \*****************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _freeGlobal_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_freeGlobal.js */ "./node_modules/lodash-es/_freeGlobal.js");


/** Detect free variable `self`. */
var freeSelf = typeof self == 'object' && self && self.Object === Object && self;

/** Used as a reference to the global object. */
var root = _freeGlobal_js__WEBPACK_IMPORTED_MODULE_0__["default"] || freeSelf || Function('return this')();

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (root);


/***/ }),

/***/ "./node_modules/lodash-es/_trimmedEndIndex.js":
/*!****************************************************!*\
  !*** ./node_modules/lodash-es/_trimmedEndIndex.js ***!
  \****************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/** Used to match a single whitespace character. */
var reWhitespace = /\s/;

/**
 * Used by `_.trim` and `_.trimEnd` to get the index of the last non-whitespace
 * character of `string`.
 *
 * @private
 * @param {string} string The string to inspect.
 * @returns {number} Returns the index of the last non-whitespace character.
 */
function trimmedEndIndex(string) {
  var index = string.length;

  while (index-- && reWhitespace.test(string.charAt(index))) {}
  return index;
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (trimmedEndIndex);


/***/ }),

/***/ "./node_modules/lodash-es/debounce.js":
/*!********************************************!*\
  !*** ./node_modules/lodash-es/debounce.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _isObject_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./isObject.js */ "./node_modules/lodash-es/isObject.js");
/* harmony import */ var _now_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./now.js */ "./node_modules/lodash-es/now.js");
/* harmony import */ var _toNumber_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./toNumber.js */ "./node_modules/lodash-es/toNumber.js");




/** Error message constants. */
var FUNC_ERROR_TEXT = 'Expected a function';

/* Built-in method references for those with the same name as other `lodash` methods. */
var nativeMax = Math.max,
    nativeMin = Math.min;

/**
 * Creates a debounced function that delays invoking `func` until after `wait`
 * milliseconds have elapsed since the last time the debounced function was
 * invoked. The debounced function comes with a `cancel` method to cancel
 * delayed `func` invocations and a `flush` method to immediately invoke them.
 * Provide `options` to indicate whether `func` should be invoked on the
 * leading and/or trailing edge of the `wait` timeout. The `func` is invoked
 * with the last arguments provided to the debounced function. Subsequent
 * calls to the debounced function return the result of the last `func`
 * invocation.
 *
 * **Note:** If `leading` and `trailing` options are `true`, `func` is
 * invoked on the trailing edge of the timeout only if the debounced function
 * is invoked more than once during the `wait` timeout.
 *
 * If `wait` is `0` and `leading` is `false`, `func` invocation is deferred
 * until to the next tick, similar to `setTimeout` with a timeout of `0`.
 *
 * See [David Corbacho's article](https://css-tricks.com/debouncing-throttling-explained-examples/)
 * for details over the differences between `_.debounce` and `_.throttle`.
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Function
 * @param {Function} func The function to debounce.
 * @param {number} [wait=0] The number of milliseconds to delay.
 * @param {Object} [options={}] The options object.
 * @param {boolean} [options.leading=false]
 *  Specify invoking on the leading edge of the timeout.
 * @param {number} [options.maxWait]
 *  The maximum time `func` is allowed to be delayed before it's invoked.
 * @param {boolean} [options.trailing=true]
 *  Specify invoking on the trailing edge of the timeout.
 * @returns {Function} Returns the new debounced function.
 * @example
 *
 * // Avoid costly calculations while the window size is in flux.
 * jQuery(window).on('resize', _.debounce(calculateLayout, 150));
 *
 * // Invoke `sendMail` when clicked, debouncing subsequent calls.
 * jQuery(element).on('click', _.debounce(sendMail, 300, {
 *   'leading': true,
 *   'trailing': false
 * }));
 *
 * // Ensure `batchLog` is invoked once after 1 second of debounced calls.
 * var debounced = _.debounce(batchLog, 250, { 'maxWait': 1000 });
 * var source = new EventSource('/stream');
 * jQuery(source).on('message', debounced);
 *
 * // Cancel the trailing debounced invocation.
 * jQuery(window).on('popstate', debounced.cancel);
 */
function debounce(func, wait, options) {
  var lastArgs,
      lastThis,
      maxWait,
      result,
      timerId,
      lastCallTime,
      lastInvokeTime = 0,
      leading = false,
      maxing = false,
      trailing = true;

  if (typeof func != 'function') {
    throw new TypeError(FUNC_ERROR_TEXT);
  }
  wait = (0,_toNumber_js__WEBPACK_IMPORTED_MODULE_0__["default"])(wait) || 0;
  if ((0,_isObject_js__WEBPACK_IMPORTED_MODULE_1__["default"])(options)) {
    leading = !!options.leading;
    maxing = 'maxWait' in options;
    maxWait = maxing ? nativeMax((0,_toNumber_js__WEBPACK_IMPORTED_MODULE_0__["default"])(options.maxWait) || 0, wait) : maxWait;
    trailing = 'trailing' in options ? !!options.trailing : trailing;
  }

  function invokeFunc(time) {
    var args = lastArgs,
        thisArg = lastThis;

    lastArgs = lastThis = undefined;
    lastInvokeTime = time;
    result = func.apply(thisArg, args);
    return result;
  }

  function leadingEdge(time) {
    // Reset any `maxWait` timer.
    lastInvokeTime = time;
    // Start the timer for the trailing edge.
    timerId = setTimeout(timerExpired, wait);
    // Invoke the leading edge.
    return leading ? invokeFunc(time) : result;
  }

  function remainingWait(time) {
    var timeSinceLastCall = time - lastCallTime,
        timeSinceLastInvoke = time - lastInvokeTime,
        timeWaiting = wait - timeSinceLastCall;

    return maxing
      ? nativeMin(timeWaiting, maxWait - timeSinceLastInvoke)
      : timeWaiting;
  }

  function shouldInvoke(time) {
    var timeSinceLastCall = time - lastCallTime,
        timeSinceLastInvoke = time - lastInvokeTime;

    // Either this is the first call, activity has stopped and we're at the
    // trailing edge, the system time has gone backwards and we're treating
    // it as the trailing edge, or we've hit the `maxWait` limit.
    return (lastCallTime === undefined || (timeSinceLastCall >= wait) ||
      (timeSinceLastCall < 0) || (maxing && timeSinceLastInvoke >= maxWait));
  }

  function timerExpired() {
    var time = (0,_now_js__WEBPACK_IMPORTED_MODULE_2__["default"])();
    if (shouldInvoke(time)) {
      return trailingEdge(time);
    }
    // Restart the timer.
    timerId = setTimeout(timerExpired, remainingWait(time));
  }

  function trailingEdge(time) {
    timerId = undefined;

    // Only invoke if we have `lastArgs` which means `func` has been
    // debounced at least once.
    if (trailing && lastArgs) {
      return invokeFunc(time);
    }
    lastArgs = lastThis = undefined;
    return result;
  }

  function cancel() {
    if (timerId !== undefined) {
      clearTimeout(timerId);
    }
    lastInvokeTime = 0;
    lastArgs = lastCallTime = lastThis = timerId = undefined;
  }

  function flush() {
    return timerId === undefined ? result : trailingEdge((0,_now_js__WEBPACK_IMPORTED_MODULE_2__["default"])());
  }

  function debounced() {
    var time = (0,_now_js__WEBPACK_IMPORTED_MODULE_2__["default"])(),
        isInvoking = shouldInvoke(time);

    lastArgs = arguments;
    lastThis = this;
    lastCallTime = time;

    if (isInvoking) {
      if (timerId === undefined) {
        return leadingEdge(lastCallTime);
      }
      if (maxing) {
        // Handle invocations in a tight loop.
        clearTimeout(timerId);
        timerId = setTimeout(timerExpired, wait);
        return invokeFunc(lastCallTime);
      }
    }
    if (timerId === undefined) {
      timerId = setTimeout(timerExpired, wait);
    }
    return result;
  }
  debounced.cancel = cancel;
  debounced.flush = flush;
  return debounced;
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (debounce);


/***/ }),

/***/ "./node_modules/lodash-es/isObject.js":
/*!********************************************!*\
  !*** ./node_modules/lodash-es/isObject.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Checks if `value` is the
 * [language type](http://www.ecma-international.org/ecma-262/7.0/#sec-ecmascript-language-types)
 * of `Object`. (e.g. arrays, functions, objects, regexes, `new Number(0)`, and `new String('')`)
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is an object, else `false`.
 * @example
 *
 * _.isObject({});
 * // => true
 *
 * _.isObject([1, 2, 3]);
 * // => true
 *
 * _.isObject(_.noop);
 * // => true
 *
 * _.isObject(null);
 * // => false
 */
function isObject(value) {
  var type = typeof value;
  return value != null && (type == 'object' || type == 'function');
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (isObject);


/***/ }),

/***/ "./node_modules/lodash-es/isObjectLike.js":
/*!************************************************!*\
  !*** ./node_modules/lodash-es/isObjectLike.js ***!
  \************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Checks if `value` is object-like. A value is object-like if it's not `null`
 * and has a `typeof` result of "object".
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is object-like, else `false`.
 * @example
 *
 * _.isObjectLike({});
 * // => true
 *
 * _.isObjectLike([1, 2, 3]);
 * // => true
 *
 * _.isObjectLike(_.noop);
 * // => false
 *
 * _.isObjectLike(null);
 * // => false
 */
function isObjectLike(value) {
  return value != null && typeof value == 'object';
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (isObjectLike);


/***/ }),

/***/ "./node_modules/lodash-es/isSymbol.js":
/*!********************************************!*\
  !*** ./node_modules/lodash-es/isSymbol.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _baseGetTag_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./_baseGetTag.js */ "./node_modules/lodash-es/_baseGetTag.js");
/* harmony import */ var _isObjectLike_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./isObjectLike.js */ "./node_modules/lodash-es/isObjectLike.js");



/** `Object#toString` result references. */
var symbolTag = '[object Symbol]';

/**
 * Checks if `value` is classified as a `Symbol` primitive or object.
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is a symbol, else `false`.
 * @example
 *
 * _.isSymbol(Symbol.iterator);
 * // => true
 *
 * _.isSymbol('abc');
 * // => false
 */
function isSymbol(value) {
  return typeof value == 'symbol' ||
    ((0,_isObjectLike_js__WEBPACK_IMPORTED_MODULE_0__["default"])(value) && (0,_baseGetTag_js__WEBPACK_IMPORTED_MODULE_1__["default"])(value) == symbolTag);
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (isSymbol);


/***/ }),

/***/ "./node_modules/lodash-es/now.js":
/*!***************************************!*\
  !*** ./node_modules/lodash-es/now.js ***!
  \***************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _root_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./_root.js */ "./node_modules/lodash-es/_root.js");


/**
 * Gets the timestamp of the number of milliseconds that have elapsed since
 * the Unix epoch (1 January 1970 00:00:00 UTC).
 *
 * @static
 * @memberOf _
 * @since 2.4.0
 * @category Date
 * @returns {number} Returns the timestamp.
 * @example
 *
 * _.defer(function(stamp) {
 *   console.log(_.now() - stamp);
 * }, _.now());
 * // => Logs the number of milliseconds it took for the deferred invocation.
 */
var now = function() {
  return _root_js__WEBPACK_IMPORTED_MODULE_0__["default"].Date.now();
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (now);


/***/ }),

/***/ "./node_modules/lodash-es/throttle.js":
/*!********************************************!*\
  !*** ./node_modules/lodash-es/throttle.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _debounce_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./debounce.js */ "./node_modules/lodash-es/debounce.js");
/* harmony import */ var _isObject_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./isObject.js */ "./node_modules/lodash-es/isObject.js");



/** Error message constants. */
var FUNC_ERROR_TEXT = 'Expected a function';

/**
 * Creates a throttled function that only invokes `func` at most once per
 * every `wait` milliseconds. The throttled function comes with a `cancel`
 * method to cancel delayed `func` invocations and a `flush` method to
 * immediately invoke them. Provide `options` to indicate whether `func`
 * should be invoked on the leading and/or trailing edge of the `wait`
 * timeout. The `func` is invoked with the last arguments provided to the
 * throttled function. Subsequent calls to the throttled function return the
 * result of the last `func` invocation.
 *
 * **Note:** If `leading` and `trailing` options are `true`, `func` is
 * invoked on the trailing edge of the timeout only if the throttled function
 * is invoked more than once during the `wait` timeout.
 *
 * If `wait` is `0` and `leading` is `false`, `func` invocation is deferred
 * until to the next tick, similar to `setTimeout` with a timeout of `0`.
 *
 * See [David Corbacho's article](https://css-tricks.com/debouncing-throttling-explained-examples/)
 * for details over the differences between `_.throttle` and `_.debounce`.
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Function
 * @param {Function} func The function to throttle.
 * @param {number} [wait=0] The number of milliseconds to throttle invocations to.
 * @param {Object} [options={}] The options object.
 * @param {boolean} [options.leading=true]
 *  Specify invoking on the leading edge of the timeout.
 * @param {boolean} [options.trailing=true]
 *  Specify invoking on the trailing edge of the timeout.
 * @returns {Function} Returns the new throttled function.
 * @example
 *
 * // Avoid excessively updating the position while scrolling.
 * jQuery(window).on('scroll', _.throttle(updatePosition, 100));
 *
 * // Invoke `renewToken` when the click event is fired, but not more than once every 5 minutes.
 * var throttled = _.throttle(renewToken, 300000, { 'trailing': false });
 * jQuery(element).on('click', throttled);
 *
 * // Cancel the trailing throttled invocation.
 * jQuery(window).on('popstate', throttled.cancel);
 */
function throttle(func, wait, options) {
  var leading = true,
      trailing = true;

  if (typeof func != 'function') {
    throw new TypeError(FUNC_ERROR_TEXT);
  }
  if ((0,_isObject_js__WEBPACK_IMPORTED_MODULE_0__["default"])(options)) {
    leading = 'leading' in options ? !!options.leading : leading;
    trailing = 'trailing' in options ? !!options.trailing : trailing;
  }
  return (0,_debounce_js__WEBPACK_IMPORTED_MODULE_1__["default"])(func, wait, {
    'leading': leading,
    'maxWait': wait,
    'trailing': trailing
  });
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (throttle);


/***/ }),

/***/ "./node_modules/lodash-es/toNumber.js":
/*!********************************************!*\
  !*** ./node_modules/lodash-es/toNumber.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _baseTrim_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./_baseTrim.js */ "./node_modules/lodash-es/_baseTrim.js");
/* harmony import */ var _isObject_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./isObject.js */ "./node_modules/lodash-es/isObject.js");
/* harmony import */ var _isSymbol_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./isSymbol.js */ "./node_modules/lodash-es/isSymbol.js");




/** Used as references for various `Number` constants. */
var NAN = 0 / 0;

/** Used to detect bad signed hexadecimal string values. */
var reIsBadHex = /^[-+]0x[0-9a-f]+$/i;

/** Used to detect binary string values. */
var reIsBinary = /^0b[01]+$/i;

/** Used to detect octal string values. */
var reIsOctal = /^0o[0-7]+$/i;

/** Built-in method references without a dependency on `root`. */
var freeParseInt = parseInt;

/**
 * Converts `value` to a number.
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to process.
 * @returns {number} Returns the number.
 * @example
 *
 * _.toNumber(3.2);
 * // => 3.2
 *
 * _.toNumber(Number.MIN_VALUE);
 * // => 5e-324
 *
 * _.toNumber(Infinity);
 * // => Infinity
 *
 * _.toNumber('3.2');
 * // => 3.2
 */
function toNumber(value) {
  if (typeof value == 'number') {
    return value;
  }
  if ((0,_isSymbol_js__WEBPACK_IMPORTED_MODULE_0__["default"])(value)) {
    return NAN;
  }
  if ((0,_isObject_js__WEBPACK_IMPORTED_MODULE_1__["default"])(value)) {
    var other = typeof value.valueOf == 'function' ? value.valueOf() : value;
    value = (0,_isObject_js__WEBPACK_IMPORTED_MODULE_1__["default"])(other) ? (other + '') : other;
  }
  if (typeof value != 'string') {
    return value === 0 ? value : +value;
  }
  value = (0,_baseTrim_js__WEBPACK_IMPORTED_MODULE_2__["default"])(value);
  var isBinary = reIsBinary.test(value);
  return (isBinary || reIsOctal.test(value))
    ? freeParseInt(value.slice(2), isBinary ? 2 : 8)
    : (reIsBadHex.test(value) ? NAN : +value);
}

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (toNumber);


/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ var __webpack_exports__ = (__webpack_exec__("./extras/modules/quick-search/quick-search.ts"));
/******/ }
]);
//# sourceMappingURL=quick-search.bundle.js.map