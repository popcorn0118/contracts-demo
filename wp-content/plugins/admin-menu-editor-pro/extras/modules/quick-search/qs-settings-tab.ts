'use strict';

import * as Mousetrap from 'mousetrap';

declare const wsAmeMousetrap: Mousetrap.MousetrapStatic;
declare const wsAmeQuickSearchSettingsData: AmeQuickSearchSettingsTab.SearchSettingsScriptData;

namespace AmeQuickSearchSettingsTab {
	export interface SearchSettingsScriptData {
		idPrefix: string;
		settings: Record<string, unknown>;
	}

	export class SettingsHelperVm {
		public readonly isInfoBoxOpen: KnockoutComputed<boolean>;
		private readonly settingsObservables: Record<string, KnockoutObservable<any>> = {};

		private readonly hotkeyTestingInProgress = ko.observable<boolean>(false);
		private readonly hotkeyTestStatus = ko.observable<string>('');
		private hotkeyTestShortcut: string | null = null;
		private hotkeyTestAction: string = 'keypress';

		constructor(
			private readonly initialSettings: Record<string, unknown>,
			private readonly idPrefix: string
		) {
			const initialState = jQuery.cookie('ame_qs_info_box_open');
			const _isBoxOpen = ko.observable<boolean>(typeof initialState === 'undefined' ? true : initialState === '1');

			this.isInfoBoxOpen = ko.computed<boolean>({
				read: (): boolean => {
					return _isBoxOpen();
				},
				write: (value: boolean) => {
					jQuery.cookie('ame_qs_info_box_open', value ? '1' : '0', {expires: 90});
					_isBoxOpen(value);
				}
			});

			//Stop hotkey test if the user changes the shortcut.
			const stopHotkeyTest = () => {
				if (this.hotkeyTestingInProgress()) {
					this.toggleHotkeyTest();
				}
			};
			this.getSettingObservable('keyboardShortcut', '').subscribe(stopHotkeyTest);
			this.getSettingObservable('customShortcut', '').subscribe(stopHotkeyTest);

			//Re-enable the live hotkey when the test is stopped.
			this.hotkeyTestingInProgress.subscribe((inProgress) => {
				if (!inProgress) {
					jQuery(document).trigger('adminMenuEditor:qsReEnableHotkey');
				}
			});
		}

		// noinspection JSUnusedGlobalSymbols -- Used by auto-generated KO bindings for settings.
		getSettingObservable(settingId: string, defaultValue: unknown): KnockoutObservable<any> {
			if (settingId.startsWith(this.idPrefix)) {
				settingId = settingId.substring(this.idPrefix.length);
			}

			if (!this.settingsObservables[settingId]) {
				const value = this.initialSettings.hasOwnProperty(settingId) ? this.initialSettings[settingId] : defaultValue;
				this.settingsObservables[settingId] = ko.observable(value);
			}
			return this.settingsObservables[settingId];
		}

		toggleInfoBox() {
			this.isInfoBoxOpen(!this.isInfoBoxOpen());
		}

		toggleHotkeyTest() {
			const wasInProgress = this.hotkeyTestingInProgress();
			let inProgress = !wasInProgress;

			if (wasInProgress) {
				if (this.hotkeyTestShortcut) {
					wsAmeMousetrap.unbind(this.hotkeyTestShortcut, this.hotkeyTestAction);
				}
				this.hotkeyTestingInProgress(false);
				this.hotkeyTestStatus('');
			} else {
				let keyboardShortcut = this.getSettingObservable('keyboardShortcut', '')();
				const customShortcut = this.getSettingObservable('customShortcut', '')();
				if (keyboardShortcut === '_custom') {
					keyboardShortcut = customShortcut;
				}

				if (typeof keyboardShortcut !== 'string') {
					keyboardShortcut = '';
				}
				keyboardShortcut = keyboardShortcut.trim();
				if (keyboardShortcut.length === 0) {
					this.hotkeyTestStatus('No keyboard shortcut configured.');
					inProgress = false;
				} else {
					//The default action is "keypress" which works for most shortcuts. However,
					//for some like "shift shift", we need to use "keyup" instead so that it doesn't
					//trigger when the key is just held down.
					this.hotkeyTestAction = keyboardShortcut.indexOf(' ') >= 0 ? 'keyup' : 'keypress';

					try {
						//Disable the live hotkey while the test is in progress.
						jQuery(document).trigger('adminMenuEditor:qsDisableHotkey');

						wsAmeMousetrap.bind(keyboardShortcut, () => {
							this.hotkeyTestStatus('Keyboard shortcut pressed at ' + new Date().toLocaleTimeString());
							return false;
						}, this.hotkeyTestAction);

						this.hotkeyTestShortcut = keyboardShortcut;
						this.hotkeyTestStatus('Press the key combination now...');
					} catch (e) {
						this.hotkeyTestStatus('Invalid keyboard shortcut: ' + e);
						inProgress = false;
					}
				}
			}

			this.hotkeyTestingInProgress(inProgress);
		}
	}
}

jQuery(function () {
	const settingsHelperVm = new AmeQuickSearchSettingsTab.SettingsHelperVm(
		wsAmeQuickSearchSettingsData.settings,
		wsAmeQuickSearchSettingsData.idPrefix
	);
	ko.applyBindings(settingsHelperVm, document.getElementById('ame-qs-settings-page-container'));
});