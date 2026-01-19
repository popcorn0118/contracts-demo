///<reference path="../js/jquery.d.ts"/>
///<reference path="../js/jquery.biscuit.d.ts"/>
'use strict';

declare var wsAmeProAdminHelperData: Record<string, any>;

(function ($) {
	let isHeadingStateRestored = false;

	function setCollapsedState($heading: JQuery, isCollapsed: boolean) {
		$heading.toggleClass('ame-is-collapsed-heading', isCollapsed);

		//Show/hide all menu items between this heading and the next one.
		const containedItems = $heading.nextUntil('li.ame-menu-heading-item, #collapse-menu', 'li.menu-top,li.wp-menu-separator');
		containedItems.toggle(!isCollapsed);
	}

	/**
	 * Save the collapsed/expanded state of menu headings.
	 */
	function saveCollapsedHeadings($adminMenu: JQuery) {
		let collapsedHeadings = loadCollapsedHeadings();
		const currentTime = Date.now();

		$adminMenu.find('li[id].ame-collapsible-heading').each(function (this: HTMLElement) {
			const $heading = $(this), id = $heading.attr('id');
			if (id) {
				if ($heading.hasClass('ame-is-collapsed-heading')) {
					collapsedHeadings[id] = currentTime;
				} else if (collapsedHeadings.hasOwnProperty(id)) {
					delete collapsedHeadings[id];
				}
			}
		});

		//Discard stored data associated with headings that haven't been seen in a long time.
		//It's likely that the headings no longer exist.
		if (Object.keys) {
			const threshold = currentTime - (90 * 24 * 3600 * 1000);
			let headingIds = Object.keys(collapsedHeadings);
			for (let i = 0; i < headingIds.length; i++) {
				const id = headingIds[i];
				if (!collapsedHeadings.hasOwnProperty(id)) {
					continue;
				}
				if (collapsedHeadings[id] < threshold) {
					delete collapsedHeadings[id];
				}
			}
		}

		$.cookie('ame-collapsed-menu-headings', JSON.stringify(collapsedHeadings), {expires: 90});
	}

	function loadCollapsedHeadings(): Record<string, number> {
		let defaultValue = {};
		if (!$.cookie) {
			return defaultValue;
		}

		try {
			let settings = JSON.parse($.cookie('ame-collapsed-menu-headings'));
			if (typeof settings === 'object') {
				return settings;
			}
			return defaultValue;
		} catch {
			return defaultValue;
		}
	}

	/**
	 * Restore the previous collapsed/expanded state of menu headings.
	 */
	function restoreCollapsedHeadings() {
		isHeadingStateRestored = true;

		const previouslyCollapsedHeadings = loadCollapsedHeadings();
		const $adminMenu = $('#adminmenumain #adminmenu');
		for (let id in previouslyCollapsedHeadings) {
			if (!previouslyCollapsedHeadings.hasOwnProperty(id)) {
				continue;
			}
			const $heading = $adminMenu.find('#' + id);
			if ($heading.length > 0) {
				setCollapsedState($heading, true);
			}
		}
	}

	$(document).on('restoreCollapsedHeadings.adminMenuEditor', function () {
		if (!isHeadingStateRestored) {
			restoreCollapsedHeadings();
		}
	});

	jQuery(function ($: JQueryStatic) {
		//Menu headings: Handle clicks.
		const $adminMenu = $('#adminmenumain #adminmenu');

		$adminMenu.find('li.ame-menu-heading-item > a').on('click', function (this: HTMLElement) {
			const $heading = $(this).closest('li');
			const canBeCollapsed = $heading.hasClass('ame-collapsible-heading');

			if (!canBeCollapsed) {
				//By default, do nothing. The heading is implemented as a link due to how the admin menu
				//works, but we don't want it to go to a different URL on click.
				return false;
			}

			let isCollapsed = !$heading.hasClass('ame-is-collapsed-heading');
			setCollapsedState($heading, isCollapsed);

			//Remember the collapsed/expanded state.
			if (typeof $.cookie !== 'undefined') {
				setTimeout(saveCollapsedHeadings.bind(window, $adminMenu), 50);
			}

			return false;
		});

		if (!isHeadingStateRestored) {
			restoreCollapsedHeadings();
		}

		if (typeof wsAmeProAdminHelperData === 'undefined') {
			return;
		}

		//Menu headings: If the user hasn't specified a custom text color, make sure the color
		//doesn't change on hover/focus.
		if (wsAmeProAdminHelperData.setHeadingHoverColor && (typeof Array.prototype.map !== 'undefined')) {
			let baseTextColor;

			//Look at the first N menu items to discover the default text color.
			const $menus = $('#adminmenumain #adminmenu li.menu-top')
				.not('.wp-menu-separator')
				.not('.ame-menu-heading-item')
				.slice(0, 10)
				.find('> a .wp-menu-name');

			let mostCommonColor = '#eeeeee', seenColors: Record<string, number> = {};
			seenColors[mostCommonColor] = 0;

			$menus.each(function (this: HTMLElement) {
				const color = $(this).css('color');
				if (color) {
					if (seenColors.hasOwnProperty(color)) {
						seenColors[color] = seenColors[color] + 1;
					} else {
						seenColors[color] = 1;
					}

					if (seenColors[color] > seenColors[mostCommonColor]) {
						mostCommonColor = color;
					}
				}
			});

			baseTextColor = mostCommonColor;

			//We want to override the default menu colors, but not per-item styles.
			const parentSelector = '#adminmenu li.ame-menu-heading-item';
			let selectors = [
				':hover',
				':active',
				':focus',
				' > a:hover',
				' > a:active',
				' > a:focus',
				'.opensub > a.menu-top' //Hovering over a sub-menu item. WP has a separate rule for this.
			].map(function (suffix) {
				return parentSelector + suffix;
			});
			//Icon hover color.
			selectors = selectors.concat(
				[
					':hover div.wp-menu-image::before',
					' > a:focus div.wp-menu-image::before',
					'.opensub div.wp-menu-image::before'
				].map(function (suffix) {
						return parentSelector + suffix;
					}
				));

			const $newStyle = $('<style>')
				.text(selectors.join(',\n') + ' { color: ' + baseTextColor + '; }');
			const $adminCssNode = $('link#admin-menu-css').first();
			if ($adminCssNode.length === 1) {
				$newStyle.insertAfter($adminCssNode);
			} else {
				$newStyle.appendTo('head');
			}
		}
	});
})(jQuery);

namespace AmeAdvancedSelectors {
	type OperationWithSelector = 'find' | 'closest' | 'prev';
	type OperationWithoutSelector = 'first' | 'addBack';
	type Operation = OperationWithSelector | OperationWithoutSelector;

	type JQueryTraversalStep = [OperationWithSelector, string] | [OperationWithoutSelector];

	export type JQueryTraversalPath = JQueryTraversalStep[];
	export type SerializedAdvancedSelector = string | JQueryTraversalPath;

	export class AdvancedSelector {
		protected readonly path: ReadonlyArray<JQueryTraversalStep>;

		constructor(selectorOrPath: string | ReadonlyArray<JQueryTraversalStep>) {
			if (typeof selectorOrPath === 'string') {
				this.path = [['find', selectorOrPath]];
			} else {
				this.path = selectorOrPath;
			}
		}

		public get length(): number {
			return this.path.length;
		}

		private withOperation(operation: Operation, selector?: string): AmeAdvancedSelectors.AdvancedSelector {
			const newPath = this.path.slice();
			const step: string[] = [operation];
			if (typeof selector !== 'undefined') {
				step.push(selector);
			}
			newPath.push(step as JQueryTraversalStep);
			return new AdvancedSelector(newPath);
		}

		public closest(selector: string): AdvancedSelector {
			return this.withOperation('closest', selector);
		}

		public prev(selector: string): AdvancedSelector {
			return this.withOperation('prev', selector);
		}

		public addBack(): AdvancedSelector {
			return this.withOperation('addBack');
		}

		public getPath(): Readonly<JQueryTraversalPath> {
			return this.path;
		}

		public toJSON(): SerializedAdvancedSelector {
			//A single "find" selector can be simplified to a basic CSS selector string.
			if ((this.path.length === 1) && (this.path[0][0] === 'find')) {
				return this.path[0][1];
			} else {
				return this.path.slice(); //To allow mutation without affecting the original.
			}
		}

		[Symbol.iterator](): Iterator<JQueryTraversalStep> {
			return this.path[Symbol.iterator]();
		}
	}

	function queryTraversalPath(path: JQueryTraversalPath | Readonly<JQueryTraversalPath>): JQuery {
		if (path.length === 0) {
			return jQuery();
		}

		let $current = jQuery('body');
		for (const step of path) {
			switch (step[0]) {
				case 'find':
					$current = $current.find(step[1]);
					break;
				case 'closest':
					$current = $current.closest(step[1]);
					break;
				case "prev":
					$current = $current.prev(step[1]);
					break;
				case "addBack":
					$current = $current.addBack();
					break;
				case "first":
					$current = $current.first();
					break;
				default:
					const exhaustiveCheck: never = step[0];
					throw new Error(`Unhandled traversal operation: ${exhaustiveCheck}`);
			}

			if ($current.length === 0) {
				return jQuery();
			}
		}

		return $current;
	}

	// noinspection JSUnusedGlobalSymbols -- Actually used at least in ameJqueryTweakManager
	export function queryAdvancedSelector(selector: AdvancedSelector | SerializedAdvancedSelector): JQuery {
		if (typeof selector === 'string') {
			return jQuery(selector);
		} else if (Array.isArray(selector)) {
			return queryTraversalPath(selector);
		} else {
			return queryTraversalPath(selector.getPath());
		}
	}
}