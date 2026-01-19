namespace AmeProfileFieldDetector {
	declare const wsAmeProfileDetectorData: ScriptData | undefined;

	import AdvancedSelector = AmeAdvancedSelectors.AdvancedSelector;
	import SerializedAdvancedSelector = AmeAdvancedSelectors.SerializedAdvancedSelector;
	const $ = jQuery;

	interface DetectedField {
		label: string;
		selector: AdvancedSelector;
		parent?: string;
		tweakId?: string;
	}

	class Detector {
		private readonly excludedGenSelectorClasses = new Set([
			'hide-if-no-js',
			'hide-if-js',
			'hidden'
		]);

		private readonly headingTweakPrefix = 'hide-dpf-'; //"dpf" = "detected profile field"

		public findFields($pageContent: JQuery): DetectedField[] {
			const fields: DetectedField[] = [];

			const $sections = $pageContent.find('form').first().children('table, div');
			const $personalOptionsSection = $pageContent
				.find('tr.user-admin-bar-front-wrap, tr.user-admin-color-wrap')
				.closest('table')
				.first();

			let lastHeadingField: DetectedField | null = null;

			$sections.each((_, section) => {
				const $section = $(section);
				const sectionFields: DetectedField[] = [];

				if ($section.is('table')) {
					$section.find('> tbody > tr').each((_, row) => {
						const $row = $(row);
						const $rowHeading = $row.find('th').first();
						if ($rowHeading.length === 0) {
							return;
						}
						const labelText = $rowHeading.text().trim();
						if (labelText.length === 0) {
							return;
						}

						const selector = this.generateRowSelector($row, $pageContent);
						if (!selector) {
							//console.warn('No selector found for field:', labelText, $row);
							return;
						}

						sectionFields.push({
							label: labelText,
							selector: selector,
						});
					});
				}


				let hideSectionSelector: AdvancedSelector | null;
				if ($section.is($personalOptionsSection)) {
					//The "Personal Options" section has different fields depending on user permissions,
					//so the auto-generated selector is not reliable.
					hideSectionSelector = new AdvancedSelector([
						['find', 'tr.user-admin-bar-front-wrap,tr.user-admin-color-wrap'],
						['closest', 'table'],
						['first']
					]);
				} else {
					hideSectionSelector = this.generateSectionSelector($section, sectionFields);
				}

				//Usually, there is a heading before or inside the section.
				//Let's add a heading "field" that will be used to group the fields and
				//to hide the entire section.
				let $heading = $section.prev('h2, h3, h4').first();
				if ($heading.length > 0) {
					//Add the heading to the section selector.
					if (hideSectionSelector) {
						hideSectionSelector = hideSectionSelector
							.prev($heading.prop('tagName').toLowerCase())
							.addBack();
					}
				} else {
					//Sometimes, the heading is inside the section.
					//For example, the "Application Passwords" section.
					$heading = $section.find('h2, h3, h4').first();
				}

				let headingText = $heading.text().trim();
				if (($heading.length > 0) && (headingText.length > 0)) {
					//Generate tweak ID from the heading text.
					let headingTweakId: string = headingText
						.toLowerCase()
						.replace(/[^a-z0-9]/g, '-')
						.replace(/-{2,}/g, '-');
					if (headingTweakId.length > 0) {
						headingTweakId = this.headingTweakPrefix + headingTweakId;
					}

					if (headingTweakId && hideSectionSelector) {
						const headingField: DetectedField = {
							label: headingText,
							selector: hideSectionSelector,
							tweakId: headingTweakId
						};
						sectionFields.unshift(headingField);
						lastHeadingField = headingField;
					} else {
						//console.warn('No selector found for heading:', headingText, $heading);
					}
				} else {
					//The fields will be assigned to the last heading, if available.
					//This can come up if a plugin adds a section without a heading or interrupts
					//an existing section. For example, Classic Editor adds another .form-table after
					//the "Personal Options" table, making it look like it's part of that section.
				}

				for (const field of sectionFields) {
					//Set the parent ID, if available.
					if (
						lastHeadingField
						&& lastHeadingField.tweakId
						//Careful not to make the heading its own parent.
						&& (field.tweakId !== lastHeadingField.tweakId)
					) {
						field.parent = lastHeadingField.tweakId;
					}
					fields.push(field);
				}
			});

			return fields;
		}

		private generateRowSelector($row: JQuery, $pageContent: JQuery): AdvancedSelector | null {
			const tagName = $row.prop('tagName').toLowerCase();
			const rowId = $row.attr('id');

			const rowClassList = $row.get(0).classList;
			const rowClasses = Array.from(rowClassList)
				//Some fields have meta-data classes like "hide-if-js", which we want to ignore.
				.filter((className) => !this.excludedGenSelectorClasses.has(className))
				.map((className) => className.trim())
				.filter((className) => className.length > 0)
				//Sort for consistency. This way the same field should always have the same selector.
				.sort((a, b) => a.localeCompare(b));

			if (rowId) {
				//User the row ID as the selector, if available.
				return new AdvancedSelector(tagName + '#' + rowId);
			} else if (rowClasses.length > 0) {
				//Otherwise, use the class name(s).
				return new AdvancedSelector(
					tagName + '.' + rowClasses.slice(0, 2).join('.')
				);
			} else {
				//Often, the row will have a form field that has an ID or a name attribute.
				const $input = $row.find('input, textarea, select, button')
					.not('[type="hidden"]')
					.first();
				if ($input.length > 0) {
					const inputId = $input.attr('id');
					const inputName = $input.attr('name');
					const inputTagName = $input.prop('tagName').toLowerCase();

					let inputSelector: string | undefined;
					if (inputId) {
						inputSelector = inputTagName + '#' + inputId;
					} else if (inputName) {
						inputSelector = inputTagName + '[name="' + inputName + '"]';
					}

					//The selector needs to be unique.
					if (inputSelector) {
						const $foundInput = $pageContent.find(inputSelector);
						if ($foundInput.length > 1) {
							inputSelector = undefined;
						}
					}

					//Traverse from the field back to the row.
					if (inputSelector) {
						return new AdvancedSelector([
							['find', inputSelector],
							['closest', tagName]
						]);
					}
				}
			}

			return null;
		}

		private generateSectionSelector($section: JQuery, fields: DetectedField[] = []): AdvancedSelector | null {
			const sectionTagName = $section.prop('tagName').toLowerCase();

			//Default sections don't have IDs, but some - like "Customer billing address" from
			//WooCommerce - have them, so let's check.
			const sectionId = $section.attr('id');
			if (sectionId) {
				return new AdvancedSelector(sectionTagName + '#' + sectionId);
			}

			//Otherwise, select the first field, then walk up to the section.
			if (fields.length > 0) {
				const fieldSelector = fields[0].selector;
				return fieldSelector.closest(sectionTagName);
			}

			return null;
		}
	}

	interface ScriptData {
		knownSelectors: SerializedAdvancedSelector[];
		currentScreen: string;
		saveIfSame: boolean;
		ajaxAction: string;
	}

	jQuery(function () {
		const detector = new Detector();
		const $pageContainer = jQuery('#profile-page');
		if ($pageContainer.length && (typeof wsAmeProfileDetectorData !== 'undefined')) {
			const scriptData: ScriptData = wsAmeProfileDetectorData;

			const fields = detector.findFields($pageContainer);
			//console.log('Detected fields:', fields);

			//Did we find any new fields, or have any of the existing ones disappeared?
			//We use selectors to identify the fields.
			const knownSelectors = new Set(scriptData.knownSelectors.map(
				item => JSON.stringify(item))
			);
			const detectedSelectors = new Set(fields.map(
				item => JSON.stringify(item.selector))
			);

			let shouldSaveFields = scriptData.saveIfSame;
			if (knownSelectors.size !== detectedSelectors.size) {
				shouldSaveFields = true;
			} else {
				//Check if any of the known selectors are missing.
				for (const selector of knownSelectors) {
					if (!detectedSelectors.has(selector)) {
						shouldSaveFields = true;
						break;
					}
				}
			}

			if (shouldSaveFields) {
				//Send the detected fields to the server.
				AjawV2.getAction(scriptData.ajaxAction).post({
					fields: JSON.stringify(fields),
					currentScreen: scriptData.currentScreen
				});
			} else {
				//console.log('No changes detected.');
			}
		}
	});
}