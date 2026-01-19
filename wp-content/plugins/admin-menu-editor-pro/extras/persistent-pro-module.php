<?php

use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;

class amePersistentProModule extends amePersistentModule implements ameExportableModule {

	/**
	 * @param array $importedData
	 * @internal
	 */
	public function handleDataImport($importedData) {
		//Action: admin_menu_editor-import_data
		if ( !empty($this->moduleId) && isset($importedData, $importedData[$this->moduleId]) ) {
			$this->importSettings($importedData[$this->moduleId]);
		}
	}

	public function exportSettings() {
		if ( isset($this->moduleId) ) {
			if ( $this->settingsWrapperEnabled ) {
				$settings = $this->loadSettings();
				if ( $settings instanceof AbstractSettingsDictionary ) {
					return $settings->toArray();
				} else {
					return null;
				}
			} else {
				return $this->loadSettings();
			}
		}
		return null;
	}

	public function importSettings($newSettings) {
		if ( !is_array($newSettings) || empty($newSettings) ) {
			return;
		}

		$this->mergeSettingsWith($newSettings);
		$this->saveSettings();
	}

	/**
	 * @return string
	 */
	public function getExportOptionLabel() {
		return $this->getTabTitle();
	}

	public function getExportOptionDescription() {
		return '';
	}
}