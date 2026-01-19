"use strict";
class AmeActorAccessDictionary {
    constructor(initialData) {
        this.items = {};
        this.numberOfObservables = ko.observable(0);
        if (initialData) {
            this.setAll(initialData);
        }
    }
    get(actor, defaultValue = null) {
        if (this.items.hasOwnProperty(actor)) {
            return this.items[actor]();
        }
        this.numberOfObservables(); //Establish a dependency.
        return defaultValue;
    }
    set(actor, value) {
        if (!this.items.hasOwnProperty(actor)) {
            this.items[actor] = ko.observable(value);
            this.numberOfObservables(this.numberOfObservables() + 1);
        }
        else {
            this.items[actor](value);
        }
    }
    getAll() {
        let result = {};
        for (let actorId in this.items) {
            if (this.items.hasOwnProperty(actorId)) {
                result[actorId] = this.items[actorId]();
            }
        }
        return result;
    }
    setAll(values) {
        for (let actorId in values) {
            if (values.hasOwnProperty(actorId)) {
                this.set(actorId, values[actorId]);
            }
        }
    }
}
var AmeFrameLoader;
(function (AmeFrameLoader) {
    const $ = jQuery;
    class Loader {
        constructor(config) {
            this.started = false;
            this.isDone = false;
            this.batchSize = 1;
            this.donePages = 0;
            this.totalPages = 0;
            this.pendingPages = [];
            this.loadingPages = new Set();
            this.progressInterval = null;
            if (config.progressBarSelector) {
                this.progressBar = $(config.progressBarSelector);
            }
            else {
                this.progressBar = null;
            }
            if (config.frameParentSelector) {
                this.frameParent = $(config.frameParentSelector);
                if (this.frameParent.length === 0) {
                    throw new Error('Frame parent selector "' + config.frameParentSelector + '" does not match any elements.');
                }
            }
            else {
                const defaultFrameParentSelectors = ['#wpwrap', 'body'];
                //jQuery returns elements in DOM order, not the order their selectors are listed in
                //a combined selector ('#a, .b, .c'). So if we want priority, we need to check each
                //selector individually instead of joining them together.
                let foundParent = null;
                for (const selector of defaultFrameParentSelectors) {
                    foundParent = $(selector);
                    if (foundParent.length > 0) {
                        break;
                    }
                }
                if (foundParent && (foundParent.length > 0)) {
                    this.frameParent = foundParent;
                }
                else {
                    throw new Error('No default frame parent selector matches any elements.');
                }
            }
            this.pageLoadTimeout = (typeof config.pageLoadTimeout === 'number') ? config.pageLoadTimeout : 30000;
            this.waitAfterLoad = config.waitAfterLoad || 0;
            this.doneRedirectUrl = config.doneRedirectUrl || null;
            this.pages = config.pages;
        }
        run() {
            if (this.started) {
                throw new Error('Loading has already started. This loader is not reusable.');
            }
            this.started = true;
            this.totalPages = this.pages.length;
            //Update the progress bar while loading.
            this.progressInterval = setInterval(() => {
                this.updateProgress();
                if (this.isDone) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }
            }, 300);
            this.pendingPages.push(...this.pages);
            this.step();
        }
        step() {
            while (this.loadingPages.size < this.batchSize) {
                const pageUrl = this.pendingPages.shift();
                if (!pageUrl) {
                    if (this.loadingPages.size === 0) {
                        this.done();
                    }
                    return;
                }
                const frame = $('<iframe></iframe>');
                const state = {
                    url: pageUrl,
                    done: false,
                    isError: false,
                    frameElement: frame,
                    timeout: this.pageLoadTimeout,
                    timeoutHandle: null,
                    finalizeTimeoutHandle: null,
                    loadStartTime: new Date()
                };
                this.loadingPages.add(state);
                if (state.timeout > 0) {
                    state.timeoutHandle = setTimeout(() => {
                        if (state.done) {
                            return; //Already done.
                        }
                        // console.error('Frame load timeout:', pageUrl);
                        state.isError = true;
                        state.timeoutHandle = null;
                        this.pageDone(state);
                    }, state.timeout);
                }
                frame.on('load', () => {
                    // console.log('Frame loaded:', pageUrl);
                    if (state.done) {
                        return; //Shouldn't happen, but just in case.
                    }
                    this.finalizePage(state);
                });
                frame.on('error', () => {
                    if (console && console.error) {
                        console.error('Frame error:', pageUrl);
                    }
                    if (state.done) {
                        return;
                    }
                    state.isError = true;
                    this.pageDone(state);
                });
                frame.prop({
                    'src': pageUrl,
                    'width': 1,
                    'height': 1
                });
                frame.css('visibility', 'hidden');
                frame.appendTo(this.frameParent);
            }
            this.updateProgress();
        }
        done() {
            this.isDone = true;
            if (this.doneRedirectUrl) {
                window.location.href = this.doneRedirectUrl;
            }
        }
        updateProgress() {
            if (!this.progressBar) {
                return;
            }
            let workDone = this.donePages;
            const currentTime = new Date();
            for (const state of this.loadingPages) {
                if (state.done) {
                    workDone++;
                }
                else if (this.pageLoadTimeout || this.waitAfterLoad) {
                    const timeElapsed = currentTime.getTime() - state.loadStartTime.getTime();
                    const maxTime = this.pageLoadTimeout + this.waitAfterLoad;
                    workDone += Math.min(0.99999, timeElapsed / maxTime);
                }
            }
            this.progressBar.prop('max', this.totalPages);
            this.progressBar.prop('value', workDone);
        }
        finalizePage(state) {
            if (this.waitAfterLoad > 0) {
                state.finalizeTimeoutHandle = setTimeout(() => {
                    state.finalizeTimeoutHandle = null;
                    this.pageDone(state);
                }, this.waitAfterLoad);
            }
            else {
                this.pageDone(state);
            }
        }
        pageDone(state) {
            if (state.timeoutHandle) {
                clearTimeout(state.timeoutHandle);
                state.timeoutHandle = null;
            }
            if (state.finalizeTimeoutHandle) {
                clearTimeout(state.finalizeTimeoutHandle);
                state.finalizeTimeoutHandle = null;
            }
            state.done = true;
            state.frameElement.remove();
            this.loadingPages.delete(state);
            this.donePages++;
            this.updateProgress();
            this.step();
        }
    }
    AmeFrameLoader.Loader = Loader;
})(AmeFrameLoader || (AmeFrameLoader = {}));
//# sourceMappingURL=pro-common-lib.js.map