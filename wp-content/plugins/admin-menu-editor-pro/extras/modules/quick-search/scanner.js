import { getCssSelector } from 'css-selector-generator';
import { condenseWhitespace, getElementTextForItemLabel, getRelativeAdminPageUrl, queryAdvancedSelector } from './utils';
import { AdminMenuItem, DashboardItem } from './items';
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
    let selector = getCssSelector($element.get(0), {
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
                if (item instanceof DashboardItem) {
                    const selector = item.getSelector();
                    if ((typeof selector === 'string') && selector.startsWith('#')) {
                        const potentialPath = [
                            { selector, operation: 'find' },
                            { selector: generalContainerSelector, operation: 'closest' }
                        ];
                        //Check if the path actually leads to the element,
                        //and *only* to this element.
                        const $found = queryAdvancedSelector(potentialPath);
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
        text = getElementTextForItemLabel($label, ' ');
    }
    return condenseWhitespace(text);
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
            label = getElementTextForItemLabel($customLabelElement, ' ');
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
export const userIdQueryPlaceholder = '_ame_qs_current_user_id_';
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
    return getRelativeAdminPageUrl(urlCopy.href, context.adminUrl, context.pageUrl, context.removableQueryArgs);
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
            let relativeUrl = getRelativeAdminPageUrl(url, context.adminUrl, context.pageUrl, context.removableQueryArgs);
            if (!relativeUrl) {
                return; //Skip external and non-admin links.
            }
            const parsedUrl = new URL(url, context.pageUrl);
            relativeUrl = replaceUserIdWIthPlaceholder(relativeUrl, parsedUrl, context);
            if (!relativeUrl) {
                return;
            }
            const item = new DashboardItem({
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
            const item = new DashboardItem({
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
                const item = new DashboardItem({
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
                const item = new DashboardItem({
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
        const item = new DashboardItem({
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
            items.push(new DashboardItem({
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
            const item = new DashboardItem({
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
            let relativeUrl = getRelativeAdminPageUrl(url, context.adminUrl, context.pageUrl, context.removableQueryArgs);
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
            const item = new DashboardItem({
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
export const builtinScanners = [
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
export class PageScanner {
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
        const relativePageUrl = getRelativeAdminPageUrl(currentPageUrl, adminUrl);
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
            const item = new DashboardItem({
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
            const text = getElementTextForItemLabel($heading, ' ');
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
export function parseAdminMenuItems($root, adminUrl, removableQueryArgs = []) {
    function getMenuTitle($titleContainer) {
        if ($titleContainer.is('.menu-top')) {
            const $name = $titleContainer.find('> .wp-menu-name').first();
            if ($name.length > 0) {
                return getMenuTitle($name);
            }
        }
        return getElementTextForItemLabel($titleContainer, '');
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
        const url = $link.prop('href') || '';
        const itemTitle = getMenuTitle($link);
        const titleParts = [itemTitle];
        //Find the parent, if any, and include it in the title.
        const $parent = $item.parent().closest('li.menu-top');
        let relativeParentUrl = null;
        if ($parent.length > 0) {
            const parentTitle = getMenuTitle($parent.find('> a .wp-menu-name').first());
            titleParts.unshift(parentTitle);
            const parentUrl = $parent.find('> a').prop('href') || '';
            relativeParentUrl = getRelativeAdminPageUrl(parentUrl, adminUrl, '', removableQueryArgs);
        }
        const relativeMenuUrl = getRelativeAdminPageUrl(url, adminUrl, '', removableQueryArgs);
        const item = new AdminMenuItem({
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
//# sourceMappingURL=scanner.js.map