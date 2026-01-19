class AmeActorAccessDictionary {
	items: { [actorId: string]: KnockoutObservable<boolean>; } = {};
	private readonly numberOfObservables: KnockoutObservable<number>;

	constructor(initialData?: AmeDictionary<boolean>) {
		this.numberOfObservables = ko.observable(0);
		if (initialData) {
			this.setAll(initialData);
		}
	}

	get(actor: string, defaultValue: boolean | null = null): boolean | null {
		if (this.items.hasOwnProperty(actor)) {
			return this.items[actor]();
		}
		this.numberOfObservables(); //Establish a dependency.
		return defaultValue;
	}

	set(actor: string, value: boolean) {
		if (!this.items.hasOwnProperty(actor)) {
			this.items[actor] = ko.observable(value);
			this.numberOfObservables(this.numberOfObservables() + 1);
		} else {
			this.items[actor](value);
		}
	}

	getAll(): AmeDictionary<boolean> {
		let result: AmeDictionary<boolean> = {};
		for (let actorId in this.items) {
			if (this.items.hasOwnProperty(actorId)) {
				result[actorId] = this.items[actorId]();
			}
		}
		return result;
	}

	setAll(values: AmeDictionary<boolean>) {
		for (let actorId in values) {
			if (values.hasOwnProperty(actorId)) {
				this.set(actorId, values[actorId]);
			}
		}
	}
}

namespace AmeFrameLoader {
	const $ = jQuery;

	interface LoaderConfig {
		pages: string[];
		progressBarSelector?: string;
		pageLoadTimeout?: number;
		waitAfterLoad?: number;
		doneRedirectUrl?: string;
		frameParentSelector?: string;
	}

	interface PageState {
		url: string;
		done: boolean;
		isError: boolean;
		frameElement: JQuery;
		loadStartTime: Date;
		timeout: number;
		timeoutHandle: ReturnType<Window['setTimeout']> | ReturnType<typeof globalThis['setTimeout']> | null;
		finalizeTimeoutHandle: ReturnType<Window['setTimeout']> | ReturnType<typeof globalThis['setTimeout']> | null;
	}

	export class Loader {
		private readonly frameParent: JQuery;
		private readonly progressBar: JQuery | null;
		private started: boolean = false;
		private isDone: boolean = false;

		private readonly pageLoadTimeout: number;
		private readonly waitAfterLoad: number;
		private readonly doneRedirectUrl: string | null;
		private readonly batchSize: number = 1;

		private donePages: number = 0;
		private totalPages: number = 0;

		private readonly pages: string[];
		private readonly pendingPages: string[] = [];
		private loadingPages: Set<PageState> = new Set();

		private progressInterval: ReturnType<Window['setInterval']> | ReturnType<typeof globalThis['setInterval']> | null = null;

		constructor(config: LoaderConfig) {
			if (config.progressBarSelector) {
				this.progressBar = $(config.progressBarSelector);
			} else {
				this.progressBar = null;
			}

			if (config.frameParentSelector) {
				this.frameParent = $(config.frameParentSelector);
				if (this.frameParent.length === 0) {
					throw new Error('Frame parent selector "' + config.frameParentSelector + '" does not match any elements.');
				}
			} else {
				const defaultFrameParentSelectors = ['#wpwrap', 'body'];
				//jQuery returns elements in DOM order, not the order their selectors are listed in
				//a combined selector ('#a, .b, .c'). So if we want priority, we need to check each
				//selector individually instead of joining them together.
				let foundParent: JQuery | null = null;
				for (const selector of defaultFrameParentSelectors) {
					foundParent = $(selector);
					if (foundParent.length > 0) {
						break;
					}
				}
				if (foundParent && (foundParent.length > 0)) {
					this.frameParent = foundParent;
				} else {
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
					clearInterval(this.progressInterval!);
					this.progressInterval = null;
				}
			}, 300);

			this.pendingPages.push(...this.pages);
			this.step();
		}

		private step() {
			while (this.loadingPages.size < this.batchSize) {
				const pageUrl = this.pendingPages.shift();
				if (!pageUrl) {
					if (this.loadingPages.size === 0) {
						this.done();
					}
					return;
				}

				const frame = $('<iframe></iframe>');
				const state: PageState = {
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

		private done() {
			this.isDone = true;
			if (this.doneRedirectUrl) {
				window.location.href = this.doneRedirectUrl;
			}
		}

		private updateProgress() {
			if (!this.progressBar) {
				return;
			}

			let workDone = this.donePages;
			const currentTime = new Date();
			for (const state of this.loadingPages) {
				if (state.done) {
					workDone++;
				} else if (this.pageLoadTimeout || this.waitAfterLoad) {
					const timeElapsed = currentTime.getTime() - state.loadStartTime.getTime();
					const maxTime = this.pageLoadTimeout + this.waitAfterLoad;
					workDone += Math.min(0.99999, timeElapsed / maxTime);
				}
			}

			this.progressBar.prop('max', this.totalPages);
			this.progressBar.prop('value', workDone);
		}

		private finalizePage(state: PageState) {
			if (this.waitAfterLoad > 0) {
				state.finalizeTimeoutHandle = setTimeout(() => {
					state.finalizeTimeoutHandle = null;
					this.pageDone(state);
				}, this.waitAfterLoad);
			} else {
				this.pageDone(state);
			}
		}


		private pageDone(state: PageState) {
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
}