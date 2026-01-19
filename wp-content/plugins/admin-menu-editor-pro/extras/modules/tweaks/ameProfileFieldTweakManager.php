<?php

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaFactory;
use YahnisElsts\AjaxActionWrapper\v2\Action;
use YahnisElsts\AjaxActionWrapper\v2\ActionBuilder;
use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

class ameProfileFieldTweakManager {
	const AJAX_UPDATE_ACTION = 'ws_ame_save_detected_prof_fields';
	const PROFILE_SCREENS = ['profile', 'user-edit'];
	const PROFILE_PAGES = ['profile.php', 'user-edit.php'];

	/**
	 * @var WPMenuEditor
	 */
	private $menuEditor;
	/**
	 * @var ameLockedGlobalOption
	 */
	private $detectedFieldsOption;

	/**
	 * @var Action|null
	 */
	private $updateAjaxAction = null;

	public function __construct($menuEditor) {
		$this->menuEditor = $menuEditor;
		$this->detectedFieldsOption = new ameLockedGlobalOption(
			'ws_ame_detected_profile_fields',
			__FILE__,
			1.0,
			false
		);

		if ( is_admin() ) {
			add_action('admin_enqueue_scripts', array($this, 'maybeEnqueueDetectorScript'), 10, 1);

			$this->updateAjaxAction = ActionBuilder::create(self::AJAX_UPDATE_ACTION)
				->method('post')
				->paramParser(static function ($rawParams) {
					$s = new SchemaFactory();
					$fieldsSchema = $s->arr(
						$s->struct([
							'label'    => $s->string()->min(1),
							'selector' => $s->union([
								$s->string()->min(1),
								$s->arr($s->arr($s->string()))->min(1),
							]),
							'parent'   => $s->string(),
							'tweakId'  => $s->string(),
						])->required(['label', 'selector'])
					);
					$paramsSchema = $s->struct([
						'fields'        => $s->json($fieldsSchema),
						'currentScreen' => $s->enum(self::PROFILE_SCREENS),
					])->required();
					return $paramsSchema->parse($rawParams);
				})
				->permissionCallback([$this, 'userCanUpdateDetectedFields'])
				->handler([$this, 'ajaxUpdateDetectedFields'])
				->register();
		}

		add_action('admin-menu-editor-register_tweaks', array($this, 'registerProfileTweaks'), 10, 2);
	}

	public function maybeEnqueueDetectorScript($hook = '') {
		//Enqueue the detector script on profile and "edit user" pages.
		if (
			in_array($hook, self::PROFILE_PAGES, true)
			&& $this->shouldDetectFields()
		) {
			$script = ScriptDependency::create(
				plugins_url('profile-field-detector.js', __FILE__),
				'ame-profile-field-detector',
				__DIR__ . '/profile-field-detector.js',
				['jquery']
			);

			if ( $this->updateAjaxAction ) {
				$script->addDependencies($this->updateAjaxAction->getRegisteredScriptHandle());
			}

			$currentScreen = get_current_screen();
			if ( $currentScreen ) {
				$currentScreenId = $currentScreen->id;
			} else {
				$currentScreenId = str_replace('.php', '', strval($hook));
			}

			$knownFields = $this->getDetectedFields();
			$knownSelectors = [];
			foreach ($knownFields as $field) {
				if (
					!empty($field['selector'])
					//Include only selectors that would be present on this screen.
					&& !empty($field['screens'][$currentScreenId])
				) {
					$knownSelectors[] = $field['selector'];
				}
			}

			$script->addJsVariable(
				'wsAmeProfileDetectorData',
				[
					'knownSelectors' => $knownSelectors,
					'currentScreen'  => $currentScreenId,
					'saveIfSame'     => $this->componentHashHasChanged(),
					'ajaxAction'     => $this->updateAjaxAction->getAction(),
				]
			);

			$script->enqueue();
		}
	}

	private function shouldDetectFields() {
		//The "edit user" page in the network admin can have different, and often fewer, fields.
		//For example, some plugins that add fields are not active in the network admin.
		//We'll only run the detector on individual sites. This is not a perfect solution,
		//but it's not clear what the best solution would be.
		if ( is_network_admin() ) {
			return false;
		}
		return $this->userCanUpdateDetectedFields();
	}

	private function generateComponentHash() {
		//WordPress version.
		$components = ['WordPress ' . (isset($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : 'unknown')];

		//File modification time as a stand-in for the plugin version.
		//Extracting the version header is more complicated.
		$components[] = strval(filemtime(__FILE__));

		//Ideally, we'd also include the version of each active plugin and theme here, but that
		//seems relatively expensive since it involves a bunch of file operations.

		return md5(implode('|', $components));
	}

	private function componentHashHasChanged() {
		$data = $this->getDetectionData();
		if ( empty($data['componentHash']) ) {
			return true;
		}

		$hash = $this->generateComponentHash();
		return ($hash !== $data['componentHash']);
	}

	public function userCanUpdateDetectedFields() {
		//Only allow users who can edit menus to update the detected fields.
		return $this->menuEditor->current_user_can_edit_menu();
	}

	public function ajaxUpdateDetectedFields($params) {
		$fields = $params['fields'];
		$currentScreen = $params['currentScreen'];

		if ( !is_array($fields) ) {
			//This should never happen due to the schema validation, but let's check in case of a bug.
			wp_send_json_error(new WP_Error('invalid_fields', 'Invalid fields - expected an array'), 400);
			exit;
		}

		$previousFields = $this->getDetectedFields();
		$usedIds = [];
		$tweakIdMap = [];
		$childPrefixes = [];

		//Fields that represent sections/headers should always be first in the list because
		//other fields use them as parents. The script that generates the fields is currently
		//designed to do this, but we'll enforce it here as well just in case.
		$headerFields = [];
		$otherFields = [];

		foreach ($fields as $field) {
			//Parent ID (checked before the tweak ID because the tweak ID may need the child
			//prefix of the parent tweak).
			$parentId = !empty($field['parent']) ? $field['parent'] : null;
			//Remap parent IDs.
			if ( $parentId && isset($tweakIdMap[$parentId]) ) {
				$parentId = $tweakIdMap[$parentId];
				$field['parent'] = $parentId;
			}

			//Tweak ID
			$storedId = !empty($field['tweakId']) ? $field['tweakId'] : null;
			//For backwards compatibility, use a predefined tweak ID for this field if possible.
			$predefinedTweakId = $this->getPredefinedTweakIdForSelector($field['selector']);
			if ( $predefinedTweakId ) {
				$tweakId = $predefinedTweakId;
				if ( $storedId ) {
					$tweakIdMap[$storedId] = $tweakId;
				}
			} else if ( $storedId ) {
				$tweakId = $storedId;
			} else {
				$tweakId = sanitize_key($field['label']);
				if ( $parentId && isset($childPrefixes[$parentId]) ) {
					$tweakId = $childPrefixes[$parentId] . $tweakId;
				} else {
					$tweakId = 'hide-dpf-noparent-' . $tweakId;
				}
			}

			//IDs must be unique. If we have a duplicate, we'll append a number to the ID.
			$baseId = $tweakId;
			$counter = 1;
			while (!empty($usedIds[$tweakId]) && ($counter <= 20)) {
				$counter++;
				$suffix = '-' . $counter;
				$tweakId = $baseId . $suffix;
			}
			if ( !empty($usedIds[$tweakId]) ) {
				//This should not happen in practice, but if it does, let's just skip this field.
				continue;
			}

			$field['tweakId'] = $tweakId;
			$usedIds[$tweakId] = $tweakId;

			if ( !$parentId ) {
				//Tweaks without a parent are usually section headers that will have other fields
				//as children. To make child IDs more unique (and shorter), we'll generate a prefix
				//that includes the first letter of each word in the header.
				$labelWords = explode(' ', $field['label'], 10);
				$abbreviation = '';
				foreach ($labelWords as $word) {
					$word = trim($word);
					if ( empty($word) ) {
						continue;
					}
					$abbreviation .= substr($word, 0, 1);
				}
				$abbreviation = sanitize_key($abbreviation);
				$childPrefixes[$tweakId] = 'hide-dpf-' . $abbreviation . '-';
			}

			//Remember which screens had this field. Some fields are only shown on the "Edit User"
			//page, not on the profile page. Once a field disappears from all screens, we can
			//remove it.
			$field['screens'] = ameUtils::get($previousFields, [$tweakId, 'screens'], []);
			$field['screens'][$currentScreen] = true;

			if ( empty($field['parent']) ) {
				$headerFields[$tweakId] = $field;
			} else {
				//Remap the parent ID.
				if ( isset($tweakIdMap[$field['parent']]) ) {
					$field['parent'] = $tweakIdMap[$field['parent']];
				}
				$otherFields[$tweakId] = $field;
			}
		}
		$fields = array_merge($headerFields, $otherFields);

		//Add any previously seen fields that are missing from the current screen but are still
		//present on other screens.
		$lastKeptFieldId = null;
		foreach ($previousFields as $field) {
			if ( empty($field['tweakId']) ) { //Invalid field.
				continue;
			}

			$tweakId = $field['tweakId'];
			if ( isset($fields[$tweakId]) ) { //Already present.
				$lastKeptFieldId = $tweakId;
				continue;
			}

			//This field is not present on the current screen.
			if ( isset($field['screens']) ) {
				unset($field['screens'][$currentScreen]);
			}

			//If this field is still present on some screens, we need to keep it.
			if ( !empty($field['screens']) ) {
				if ( !empty($lastKeptFieldId) ) {
					//Insert it after the last kept field.
					$offset = array_search($lastKeptFieldId, array_keys($fields), true);
					if ( $offset !== false ) {
						$fields = array_merge(
							array_slice($fields, 0, $offset + 1, true),
							[$tweakId => $field],
							array_slice($fields, $offset + 1, null, true)
						);
					} else {
						$fields[$tweakId] = $field;
					}
				} else {
					//Add it at the end of the list.
					$fields[$tweakId] = $field;
				}

				$lastKeptFieldId = $tweakId;
			}
		}

		$detectionData = [
			'fields'        => $fields,
			'componentHash' => $this->generateComponentHash(),
		];
		if ( $this->saveDetectionData($detectionData) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error(new WP_Error('save_failed', 'Failed to save detected profile fields'), 500);
		}
		exit;
	}

	private function getDetectedFields() {
		$data = $this->getDetectionData();
		return is_array($data['fields']) ? $data['fields'] : [];
	}

	private function getDetectionData() {
		$data = $this->detectedFieldsOption->get([]);

		if ( !is_array($data) ) {
			$data = [];
		}
		return array_merge(
			['componentHash' => '', 'fields' => []],
			$data
		);
	}

	private function saveDetectionData($newData) {
		return $this->detectedFieldsOption->set($newData);
	}

	/**
	 * @param ameTweakManager $manager
	 * @param array|null $tweakFilter
	 */
	public function registerProfileTweaks($manager, $tweakFilter = null) {
		//Optimization: Only register the tweaks on profile pages and on AME pages that show tweak
		//settings. $tweakFilter is null on settings pages.
		$isTweakSettingsPage = ($tweakFilter === null);
		global $pagenow;
		if ( !$isTweakSettingsPage && !in_array($pagenow, self::PROFILE_PAGES, true) ) {
			return;
		}

		//Optimization: Sections are purely organizational and don't need to be registered
		//if we're not on a settings page.
		if ( $isTweakSettingsPage ) {
			$sectionId = 'profile';
			$manager->addSection($sectionId, 'Hide Profile Fields', 80);
		} else {
			$sectionId = null;
		}

		foreach ($this->getTweakDefinitions() as $tweakId => $definition) {
			$selector = $definition['selector'];
			if ( is_string($selector) ) {
				$tweak = new ameHideSelectorTweak($tweakId, $definition['label'], $selector);
			} else if ( is_array($selector) ) {
				$tweak = new ameHideJquerySelectorTweak($tweakId, $definition['label'], $selector);
			} else {
				continue; //Invalid selector.
			}

			$tweak->setScreens(self::PROFILE_SCREENS);
			if ( $sectionId ) {
				$tweak->setSectionId($sectionId);
			}
			if ( !empty($definition['parent']) ) {
				$tweak->setParentId($definition['parent']);
			}

			$manager->addTweak($tweak);
		}
	}

	private function getTweakDefinitions() {
		$detectedFieldDefs = ameUtils::collectionPick(
			$this->getDetectedFields(),
			['label', 'selector', 'parent']
		);

		//Add any predefined tweaks that have not been used yet.
		//Note: Unlike array_merge(), the plus operator will not overwrite existing values.
		//It will just skip keys that already exist in the first array.
		return $detectedFieldDefs + $this->getDefaultTweakDefinitions();
	}

	private function getDefaultTweakDefinitions() {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}

		$profileTweaks = [
			'hide-profile-group-personal-info'   => [
				'label'    => 'Personal Options',
				'selector' => [
					['find', 'tr.user-admin-bar-front-wrap,tr.user-admin-color-wrap'],
					['closest', 'table'],
					['first'],
					['prev', 'h2'],
					['addBack'],
				],
			],
			'hide-profile-visual-editor'         => [
				'label'    => 'Visual Editor',
				'selector' => 'tr.user-rich-editing-wrap',
				'parent'   => 'hide-profile-group-personal-info',
			],
			'hide-profile-syntax-highlighting'   => [
				'label'    => 'Syntax Highlighting',
				'selector' => 'tr.user-syntax-highlighting-wrap',
				'parent'   => 'hide-profile-group-personal-info',
			],
			'hide-profile-color-scheme-selector' => [
				'label'    => 'Admin Color Scheme',
				'selector' => 'tr.user-admin-color-wrap',
				'parent'   => 'hide-profile-group-personal-info',
			],
			'hide-profile-keyboard-shortcuts'    => [
				'label'    => 'Keyboard Shortcuts',
				'selector' => 'tr.user-comment-shortcuts-wrap',
				'parent'   => 'hide-profile-group-personal-info',
			],
			'hide-profile-toolbar-toggle'        => [
				'label'    => 'Toolbar',
				'selector' => 'tr.show-admin-bar.user-admin-bar-front-wrap',
				'parent'   => 'hide-profile-group-personal-info',
			],

			'hide-profile-group-name'   => [
				'label'    => 'Name',
				'selector' => [
					['find', 'tr.user-user-login-wrap'],
					['closest', 'table'],
					['prev', 'h2'],
					['addBack'],
				],
			],
			'hide-profile-user-login'   => [
				'label'    => 'Username',
				'selector' => 'tr.user-user-login-wrap',
				'parent'   => 'hide-profile-group-name',
			],
			'hide-profile-first-name'   => [
				'label'    => 'First Name',
				'selector' => 'tr.user-first-name-wrap',
				'parent'   => 'hide-profile-group-name',
			],
			'hide-profile-last-name'    => [
				'label'    => 'Last Name',
				'selector' => 'tr.user-last-name-wrap',
				'parent'   => 'hide-profile-group-name',
			],
			'hide-profile-nickname'     => [
				'label'    => 'Nickname',
				'selector' => 'tr.user-nickname-wrap',
				'parent'   => 'hide-profile-group-name',
			],
			'hide-profile-display-name' => [
				'label'    => 'Display name',
				'selector' => 'tr.user-display-name-wrap',
				'parent'   => 'hide-profile-group-name',
			],

			'hide-profile-group-contact-info' => [
				'label'    => 'Contact Info',
				'selector' => [
					['find', 'tr.user-email-wrap'],
					['closest', 'table'],
					['prev', 'h2'],
					['addBack'],
				],
			],
			'hide-profile-email'              => [
				'label'    => 'Email',
				'selector' => 'tr.user-email-wrap',
				'parent'   => 'hide-profile-group-contact-info',
			],
			'hide-profile-url'                => [
				'label'    => 'Website',
				'selector' => 'tr.user-url-wrap',
				'parent'   => 'hide-profile-group-contact-info',
			],
		];

		//Find user contact methods and add them to the list of hideable profile fields.
		if ( is_callable('wp_get_user_contact_methods') ) {
			$contactMethods = wp_get_user_contact_methods();
			foreach ($contactMethods as $contactMethodId => $contactMethod) {
				$profileTweaks['hide-profile-cm-' . $contactMethodId] = [
					'label'    => $contactMethod,
					'selector' => 'tr.user-' . $contactMethodId . '-wrap',
					'parent'   => 'hide-profile-group-contact-info',
				];
			}
		}

		//"About Yourself" section.
		$aboutYourself = [
			'hide-profile-group-about-yourself' => [
				'label'    => 'About Yourself',
				'selector' => [
					['find', 'tr.user-description-wrap'],
					['closest', 'table'],
					['prev', 'h2'],
					['addBack'],
				],
			],
			'hide-profile-user-description'     => [
				'label'    => 'Biographical Info',
				'selector' => 'tr.user-description-wrap',
				'parent'   => 'hide-profile-group-about-yourself',
			],

			'hide-profile-picture' => [
				'label'    => 'Profile Picture',
				'selector' => 'tr.user-profile-picture',
				'parent'   => 'hide-profile-group-about-yourself',
			],
		];

		$cached = array_merge($profileTweaks, $aboutYourself);
		return $cached;
	}

	private $cachedIdBySelector = null;

	/**
	 * @param string|array $selector
	 * @return string|null
	 */
	private function getPredefinedTweakIdForSelector($selector) {
		if ( is_array($selector) ) {
			$selector = wp_json_encode($selector);
		} else if ( !is_string($selector) ) {
			return null;
		}

		if ( $this->cachedIdBySelector === null ) {
			$this->cachedIdBySelector = [];
			foreach ($this->getDefaultTweakDefinitions() as $id => $def) {
				if ( !empty($def['selector']) ) {
					if ( is_string($def['selector']) ) {
						$serializedSelector = $def['selector'];
					} else if ( is_array($def['selector']) ) {
						$serializedSelector = wp_json_encode($def['selector']);
					} else {
						continue;
					}
					$this->cachedIdBySelector[$serializedSelector] = $id;
				}
			}
		}

		if ( isset($this->cachedIdBySelector[$selector]) ) {
			return $this->cachedIdBySelector[$selector];
		}

		return null;
	}
}