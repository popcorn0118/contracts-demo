<?php
require_once AME_ROOT_DIR . '/extras/exportable-module.php';

/** @noinspection PhpUnused Actually used in menu-editor-core.php */

class ameMetaBoxEditor extends ameModule implements ameExportableModule {
	const OPTION_NAME = 'ws_ame_meta_boxes';
	const FORCE_REFRESH_PARAM = 'ame-force-meta-box-refresh';

	const CUSTOMIZATION_COMPONENT = 'metaboxes';

	protected static $supportedCptFeatures = array('title' => true, 'editor' => true);

	protected $tabSlug = 'metaboxes';
	protected $tabTitle = 'Meta Boxes';

	/**
	 * @var ameMetaBoxSettings
	 */
	private $settings = null;

	private $shouldRefreshMetaBoxes = false;
	private $hiddenBoxCache = array();

	/**
	 * @var ameCustomizationFeatureToggle
	 */
	private $customizationFeature;

	private $areSettingsCorrupted = false;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		$this->customizationFeature = new ameCustomizationFeatureToggle(
			self::CUSTOMIZATION_COMPONENT,
			$this->menuEditor,
			$this->tabSlug,
			function () {
				return [
					'You will see the default meta boxes in the editor.',
					'Meta box customization is disabled for your account.',
				];
			}
		);

		if ( !$this->isEnabledForRequest() ) {
			return;
		}

		add_action('add_meta_boxes', array($this, 'addDelayedMetaBoxHook'), 10, 1);
		add_filter('default_hidden_meta_boxes', array($this, 'filterDefaultHiddenBoxes'), 10, 2);
		//Gutenberg support.
		add_action('enqueue_block_editor_assets', array($this, 'enqueueGutenbergScripts'), 200);

		add_action('admin_menu_editor-header', array($this, 'handleFormSubmission'), 10, 2);

		//Clear caches when switching to another site or user.
		add_action('switch_blog', array($this, 'clearCache'), 10, 0);
		add_action('set_current_user', array($this, 'clearCache'), 10, 0);
		add_action('updated_user_meta', array($this, 'clearCache'), 10, 0);
		add_action('deleted_user_meta', array($this, 'clearCache'), 10, 0);

		add_action('current_screen', array($this, 'processPostTypeFeatures'), 200, 1);

		add_action('admin_menu_editor-register_hideable_items', array($this, 'registerHideableBoxes'), 10, 1);
		add_filter('admin_menu_editor-save_hideable_items-mb', array($this, 'saveHideableItems'), 10, 2);
	}

	protected function isEnabledForRequest() {
		return !is_network_admin();
	}

	public function addDelayedMetaBoxHook($postType) {
		/*
		 * Some plugins add their meta boxes using the "admin_head" action (example: WPML) or other non-standard hooks.
		 * Unfortunately, this means we can't reliably catch all boxes by using "add_meta_boxes" or "do_meta_boxes".
		 * We use the "in_admin_header" action instead because it runs after the meta box related actions and after most
		 * other header hooks.
		 *
		 * However, this workaround is not fully reliable because there are parts of WP admin that output meta boxes
		 * immediately after registering them. Examples:
		 *   /wp-admin/edit-form-comment.php
		 *   /wp-admin/edit-link-form.php
		 *
		 * Partial solution: Let's use the "in_admin_header" hook only on "Edit $CPT" pages.
		 */
		$latePriority = 2000;

		//Is the current page a post editing screen?
		$currentScreen = get_current_screen();
		if ( !empty($currentScreen) ) {
			if (
				isset($currentScreen->base)
				&& ($currentScreen->base === 'post')
				&& !empty($postType)
				&& !did_action('in_admin_header')
			) {
				add_action('in_admin_header', array($this, 'processMetaBoxes'), $latePriority, 0);
				return;
			}
		}

		add_action('add_meta_boxes_' . $postType, array($this, 'processMetaBoxes'), $latePriority, 0);
	}

	public function processMetaBoxes() {
		global $wp_meta_boxes;

		$currentScreen = get_current_screen();
		if ( empty($currentScreen) || $this->areSettingsCorrupted ) {
			return;
		}

		$currentUser = wp_get_current_user();

		$screenSettings = $this->getScreenSettings($currentScreen);
		$changesDetected = $screenSettings->mergeScreenInfo($currentScreen);

		//Get the box settings for the current screen.
		$metaBoxes = $screenSettings->getMetaBoxes();
		//Update existing boxes, add new boxes, flag stored boxes that no longer exist.
		$changesDetected = ($metaBoxes->merge(ameUtils::get($wp_meta_boxes, $currentScreen->id, array()))
			|| $changesDetected);

		//If anything has changed, save the updated box collection.
		if ( $changesDetected && $this->userCanEditMetaBoxes() ) {
			//Remove wrapped meta boxes where the file no longer exists.
			foreach ($metaBoxes->getMissingWrappedBoxes() as $missingBox) {
				$callbackFileName = $missingBox->getCallbackFileName();
				if ( !empty($callbackFileName) && !is_file($callbackFileName) ) {
					$metaBoxes->remove($missingBox->getId());
				}
			}

			//Also update the default list of hidden boxes.
			$metaBoxes->setHiddenByDefault($this->getUnmodifiedDefaultHiddenBoxes($currentScreen));

			$this->saveSettings();
		}

		//Exception: Don't hide any boxes if customization is disabled for this user.
		if ( $this->customizationFeature->isCustomizationDisabled() ) {
			return;
		}

		//Remove hidden boxes.
		foreach ($metaBoxes->getPresentBoxes() as $box) {
			if ( !$box->isAvailableTo($currentUser, $this->menuEditor) ) {
				remove_meta_box($box->getId(), $currentScreen, $box->getContext());
			}
		}
	}

	/**
	 * @param \WP_Screen $screen
	 * @return ameMbeScreenSettings
	 */
	protected function getScreenSettings($screen) {
		$this->loadSettings();

		$screenId = $screen->id;
		if ( isset($this->settings[$screenId]) ) {
			return $this->settings[$screenId];
		}

		$collection = new ameMbeScreenSettings($screenId, $screen);
		$this->settings[$screenId] = $collection;
		return $collection;
	}

	/**
	 * Change the default list of hidden meta boxes.
	 *
	 * @param array $hidden
	 * @param WP_Screen $screen
	 * @return array
	 */
	public function filterDefaultHiddenBoxes($hidden, $screen) {
		if ( empty($screen) || !($screen instanceof WP_Screen) || $this->areSettingsCorrupted ) {
			return $hidden;
		}
		if ( isset($this->hiddenBoxCache[$screen->id]) ) {
			return $this->hiddenBoxCache[$screen->id];
		}

		$metaBoxes = $this->getScreenSettings($screen)->getMetaBoxes();

		static $isUpdateDone = false;
		if ( !$isUpdateDone ) {
			$changesDetected = $metaBoxes->setHiddenByDefault($hidden);
			if ( $changesDetected ) {
				$this->saveSettings();
			}
			$isUpdateDone = true;
		}

		if ( $this->customizationFeature->isCustomizationDisabled() ) {
			return $hidden;
		}

		$user = wp_get_current_user();
		$visible = array();

		foreach ($metaBoxes->getPresentBoxes() as $box) {
			if ( $box->isVisibleByDefaultFor($user, $this->menuEditor) ) {
				$visible[] = $box->getId();
			} else {
				$hidden[] = $box->getId();
			}
		}

		$hidden = array_unique(array_diff($hidden, $visible));

		//Note: It might be a good idea to cache intermediate results (i.e. only custom hidden & visible settings)
		//instead of the final result. Consider that if there are plugin compatibility issues.
		$this->hiddenBoxCache[$screen->id] = $hidden;

		return $hidden;
	}

	private function getUnmodifiedDefaultHiddenBoxes(WP_Screen $screen) {
		//This is a slightly modified excerpt from the get_hidden_meta_boxes() core function in screen.php.
		$hidden = array();
		if ( 'post' == $screen->base ) {
			if ( in_array($screen->post_type, array('post', 'page', 'attachment')) ) {
				$hidden = array(
					'slugdiv',
					'trackbacksdiv',
					'postcustom',
					'postexcerpt',
					'commentstatusdiv',
					'commentsdiv',
					'authordiv',
					'revisionsdiv',
				);
			} else {
				$hidden = array('slugdiv');
			}
		}
		return apply_filters('default_hidden_meta_boxes', $hidden, $screen);
	}

	/**
	 * @param WP_Screen $screen
	 */
	public function processPostTypeFeatures($screen = null) {
		if (
			!isset($screen, $screen->post_type, $screen->id)
			|| empty($screen->post_type)
			|| $this->areSettingsCorrupted
		) {
			return;
		}
		//Scan only the "Add Item" and "Edit Item" screens.
		//The "All Items" screen also has a post type, but it contains no editor boxes.
		if ( isset($screen->base) && ($screen->base !== 'post') ) {
			return;
		}

		$currentFeatures = get_all_post_type_supports($screen->post_type);
		if ( empty($currentFeatures) || !is_array($currentFeatures) ) {
			return;
		}

		$screenSettings = $this->getScreenSettings($screen);
		$changesDetected = $screenSettings->mergeScreenInfo($screen);

		$currentFeatures = array_intersect_key($currentFeatures, self::$supportedCptFeatures);

		$featureSettings = $screenSettings->getPostTypeFeatures();
		$changesDetected = $featureSettings->merge($currentFeatures) || $changesDetected;

		if ( $changesDetected && $this->userCanEditMetaBoxes() ) {
			$this->saveSettings();
		}

		if ( $this->customizationFeature->isCustomizationDisabled() ) {
			return;
		}

		//Remove disabled features.
		$currentUser = wp_get_current_user();
		foreach ($featureSettings->getFeatures() as $feature) {
			if ( !$feature->isAvailableTo($currentUser, $this->menuEditor) ) {
				remove_post_type_support($screen->post_type, $feature->getId());
			}
		}
	}

	public function userCanEditMetaBoxes() {
		return $this->menuEditor->current_user_can_edit_menu();
	}

	private function saveSettings() {
		$json = $this->settings->toJSON();
		$lock = ameFileLock::create(__FILE__);
		$lock->acquire(1);
		$this->setScopedOption(self::OPTION_NAME, $json);
		$lock->release();
	}

	private function loadSettings() {
		if ( isset($this->settings) ) {
			return $this->settings;
		}

		/** @noinspection PhpRedundantOptionalArgumentInspection The default value could change, so make it explicit. */
		$json = $this->getScopedOption(self::OPTION_NAME, null);

		if ( empty($json) ) {
			$this->settings = new ameMetaBoxSettings();
		} else {
			try {
				$this->settings = ameMetaBoxSettings::fromJSON($json);
			} catch (ameInvalidJsonException $ex) {
				$this->areSettingsCorrupted = true;
				$this->settings = new ameMetaBoxSettings();
				if ( is_admin() && is_user_logged_in() && !did_action('all_admin_notices') ) {
					add_action('all_admin_notices', array($this, 'showSettingsCorruptionError'));
				}
			}
		}

		return $this->settings;
	}

	public function showSettingsCorruptionError() {
		if ( !$this->userCanEditMetaBoxes() ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			'<strong>Admin Menu Editor Pro error: Cannot load meta box settings. The data might be corrupted.</strong><br> '
			. sprintf(
				'If you have recently migrated this site to a new server, try restoring the <code>%s</code> option from backup.',
				esc_html(self::OPTION_NAME)
			)
		);
	}

	public function exportSettings() {
		$this->loadSettings();
		if ( $this->settings->isEmpty() ) {
			return null;
		}
		return $this->settings->toArray();
	}

	public function importSettings($newSettings) {
		if ( empty($newSettings) || !is_array($newSettings) ) {
			return;
		}

		$settings = ameMetaBoxSettings::fromArray($newSettings);
		$settings->setFirstRefreshState(true);
		$this->settings = $settings;
		$this->saveSettings();
	}

	public function getExportOptionLabel() {
		return 'Meta boxes';
	}

	public function getExportOptionDescription() {
		return '';
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();
		$this->loadSettings();

		//Automatically refresh the list of available meta boxes.
		$query = $this->menuEditor->get_query_params();
		$this->shouldRefreshMetaBoxes = empty($query['ame-meta-box-refresh-done'])
			&& (
				$this->settings->isEmpty()
				|| (!empty($query[self::FORCE_REFRESH_PARAM]) && check_admin_referer(self::FORCE_REFRESH_PARAM))
				|| (!$this->settings->isFirstRefreshDone())
			);

		if ( $this->shouldRefreshMetaBoxes ) {
			$pagesWithMetaBoxes = array();
			if ( get_option('link_manager_enabled') ) {
				$pagesWithMetaBoxes[] = 'link-add.php';
			}
			$postTypes = get_post_types(array('public' => true, 'show_ui' => true), 'objects', 'or');
			foreach ($postTypes as $postType) {
				$pagesWithMetaBoxes[] = add_query_arg(
					array(
						'post_type'   => $postType->name,
						'ame_mb_rand' => rand(1, 10000),
					),
					self_admin_url('post-new.php')
				);
			}

			//Include Media/attachments. This post type doesn't have a standard "new post" screen,
			//so lets use the edit URL of the most recently uploaded image instead.
			$attachments = get_posts(array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => 1,
				'post_status'    => null,
				'post_parent'    => 'any',
			));
			if ( !empty($attachments) ) {
				$firstAttachment = reset($attachments);
				$pagesWithMetaBoxes[] = get_edit_post_link($firstAttachment->ID, 'raw');
			}

			wp_enqueue_auto_versioned_script(
				'ame-refresh-meta-boxes',
				plugins_url('refresh-meta-boxes.js', __FILE__),
				array('jquery')
			);

			wp_localize_script(
				'ame-refresh-meta-boxes',
				'wsMetaBoxRefresherData',
				array(
					'editorUrl'          => $this->getTabUrl(array('ame-meta-box-refresh-done' => 1)),
					'pagesWithMetaBoxes' => $pagesWithMetaBoxes,
				)
			);
			return;
		}

		wp_register_auto_versioned_script(
			'ame-meta-box-editor',
			plugins_url('metabox-editor.js', __FILE__),
			array(
				'ame-lodash',
				'ame-knockout',
				'ame-pro-common-lib',
				'ame-actor-selector',
				'jquery',
				'ame-actor-manager',
			)
		);

		$settings = $this->loadSettings();
		wp_localize_script(
			'ame-meta-box-editor',
			'wsAmeMetaBoxEditorData',
			array(
				'settings'   => $settings->toArray(),
				'refreshUrl' => wp_nonce_url(
					$this->getTabUrl(array(
						self::FORCE_REFRESH_PARAM => 1,
						'ame-mb-random'           => rand(1, 10000),
					)),
					self::FORCE_REFRESH_PARAM
				),
			)
		);

		wp_enqueue_script('jquery-qtip');
		wp_enqueue_script('ame-meta-box-editor');

		wp_enqueue_auto_versioned_style(
			'ame-meta-box-editor-style',
			plugins_url('metabox-editor.css', __FILE__)
		);
	}

	public function handleFormSubmission($action, $post = array()) {
		//For debugging.
		if ( $action === 'ame_reset_meta_box_settings' && defined('WP_DEBUG') ) {
			$this->settings = new ameMetaBoxSettings();
			$this->saveSettings();
			return;
		}

		//Note: We don't need to check user permissions here because plugin core already did.
		if ( $action === 'ame_save_meta_boxes' ) {
			check_admin_referer($action);

			//Save settings.
			$settings = ameMetaBoxSettings::fromJSON($post['settings']);
			$settings->setFirstRefreshState(true);
			$this->settings = $settings;
			$this->saveSettings();

			wp_redirect($this->getTabUrl(ameUtils::withSelectedActor(['updated' => 1], $post)));
			exit;
		}
	}

	public function displaySettingsPage() {
		$this->loadSettings();

		if ( $this->areSettingsCorrupted ) {
			echo '<p>Meta box settings are not available.</p>';
			return;
		}

		if ( $this->shouldRefreshMetaBoxes ) {
			if ( !$this->settings->isFirstRefreshDone() ) {
				//Let's update the initial refresh flag before the refresh actually happens.
				//This helps prevent an infinite loop when the initial refresh fails.
				$this->settings->setFirstRefreshState(true);
				$this->saveSettings();
			}
			$this->outputTemplate('box-refresh');
		} else {
			//After saving settings, show the "customization disabled" notice if it applies to the user.
			//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( !empty($_GET['updated']) ) {
				$this->customizationFeature->onSettingsSaved();
			}

			parent::displaySettingsPage();
		}
	}

	protected function getWrapClasses() {
		return array_merge(parent::getWrapClasses(), ['ame-tab-list-bottom-margin-disabled']);
	}

	public function clearCache() {
		$this->hiddenBoxCache = array();
		$this->settings = null;
	}

	/**
	 * Add a script that will remove Gutenberg document panels that correspond to hidden meta boxes.
	 */
	public function enqueueGutenbergScripts() {
		//Some plugins load the Gutenberg editor outside the admin dashboard in which case
		//the get_current_screen() function might not be available.
		if ( !function_exists('get_current_screen') ) {
			return;
		}
		$currentScreen = get_current_screen();
		if ( empty($currentScreen) ) {
			return;
		}

		if ( $this->customizationFeature->isCustomizationDisabled() ) {
			return;
		}

		$currentUser = wp_get_current_user();

		$boxesToPanels = array(
			'slugdiv'          => 'post-link',
			'postexcerpt'      => 'post-excerpt',
			'postimagediv'     => 'featured-image',
			'commentstatusdiv' => 'discussion-panel',
			'categorydiv'      => 'taxonomy-panel-category',
			'pageparentdiv'    => 'page-attributes',
		);

		$boxesToSelectors = array(
			'submitdiv'    => '#editor .edit-post-post-schedule',
			'authordiv'    => '#editor .edit-post-post-author',
			'formatdiv'    => '#editor .editor-post-format, #editor .edit-post-post-schedule + .components-panel__row',
			'revisionsdiv' => '#editor .components-panel .edit-post-last-revision__panel',
		);

		$panelsToRemove = array();
		$selectorsToHide = array();
		$metaBoxes = $this->getScreenSettings($currentScreen)->getMetaBoxes();
		$presentBoxes = $metaBoxes->getPresentBoxes();
		foreach ($presentBoxes as $box) {
			if ( $box->isAvailableTo($currentUser, $this->menuEditor) ) {
				continue;
			}

			//What's the panel name for this box?
			$boxId = $box->getId();
			if ( isset($boxesToPanels[$boxId]) ) {
				$panelsToRemove[] = $boxesToPanels[$boxId];
			} else if ( preg_match('/^tagsdiv-(?P<taxonomy>.++)$/', $boxId, $matches) ) {
				$panelsToRemove[] = 'taxonomy-panel-' . $matches['taxonomy'];
			} else if ( isset($boxesToSelectors[$boxId]) ) {
				$selectorsToHide[] = $boxesToSelectors[$boxId];
			}
			//We deliberately skip non-core boxes. For now, the remove_meta_box() call
			//in processMetaBoxes() seems to remove them effectively.
		}

		if ( empty($panelsToRemove) && empty($selectorsToHide) ) {
			return;
		}

		//Enqueue after wp-edit-post if possible. This is a dependency, but we can't simply
		//always add it because the "Appearance -> Widgets" page also loads Gutenberg assets
		//(triggering this callback) but specifically disallows the wp-edit-post script.
		//AFAICT, there's no general way to detect when wp-edit-post is allowed or not.
		$dependencies = array('jquery', 'wp-data');
		/** @noinspection PhpRedundantOptionalArgumentInspection -- What WP core changes the default? */
		if ( wp_script_is('wp-edit-post', 'enqueued') ) {
			$dependencies[] = 'wp-edit-post';
		}

		wp_enqueue_auto_versioned_script(
			'ame-hide-gutenberg-panels',
			plugins_url('hide-gutenberg-panels.js', __FILE__),
			$dependencies,
			true
		);

		wp_localize_script(
			'ame-hide-gutenberg-panels',
			'wsAmeGutenbergPanelData',
			array(
				'panelsToRemove'  => $panelsToRemove,
				'selectorsToHide' => $selectorsToHide,
			)
		);
	}

	/**
	 * @param \YahnisElsts\AdminMenuEditor\EasyHide\HideableItemStore $store
	 */
	public function registerHideableBoxes($store) {
		$settings = $this->loadSettings();
		if ( $this->areSettingsCorrupted ) {
			return;
		}

		/*
		 * Each meta box will be put into *two* categories:
		 *
		 * 1) "Meta Boxes" / "Post Types (or Screens)" / "Post Type Name"
		 * 2) "Meta Boxes" / "Boxes" / "Box Name"
		 *
		 * The second category exists because often the same box is used
		 * on multiple screens / post types. It could be convenient to hide
		 * a box on all screens at once.
		 */
		$postEditorCategory = $store->getOrCreateCategory('post-editor', 'Editor', null, false, 0, 0);
		$baseCat = $store->getOrCreateCategory('meta-boxes', 'Meta Boxes', $postEditorCategory);
		$byScreenCat = $store->getOrCreateCategory('mb-post-types', 'Post Types', $baseCat, true, 0, 0);
		$byBoxCat = $store->getOrCreateCategory('mb-boxes', 'Boxes', $baseCat, true, 0, 0);

		$baseCat->enableTableView($byBoxCat, $byScreenCat);

		/** @var array<string,array<int,\YahnisElsts\AdminMenuEditor\EasyHide\Category|ameMetaBox>> $usedBoxCatLabels */
		$usedBoxCatLabels = array();

		/** @var ameMbeScreenSettings $screenSettings */
		foreach ($settings as $screenId => $screenSettings) {
			$boxes = $screenSettings->getMetaBoxes();
			if ( $boxes->isEmpty() ) {
				continue; //Skip screens that don't have any boxes. This can apparently happen.
			}

			$screenCat = $store->getOrCreateCategory(
				'meta-boxes/s/' . $screenId,
				ucfirst($screenId),
				$byScreenCat,
				true,
				0,
				0
			);

			/** @var \ameMetaBox $box */
			foreach ($boxes as $box) {
				$label = trim(strip_tags($box->getTitle()));
				if ( $label === '' ) {
					$label = '(No title)';
				}

				$boxCat = $store->getOrCreateCategory(
					'mb-boxes/' . $box->getId(),
					$label,
					$byBoxCat,
					true,
					0,
					0
				);

				$store->addItem(
					$this->makeHideableItemId($screenId, $box),
					$label,
					array($screenCat, $boxCat),
					null,
					$box->getGrantAccess(),
					'mb',
					$box->getId()
				);

				//Detect boxes that have the same name and give them subtitle.
				//For example, multiple plugins have a "Tags" meta box.
				if ( isset($usedBoxCatLabels[$label]) && ($usedBoxCatLabels[$label][0] !== $boxCat) ) {
					$boxCat->addSubtitle($box->getId());
					list($existingCategory, $firstBox) = $usedBoxCatLabels[$label];
					$existingCategory->addSubtitle($firstBox->getId());
				} else {
					$usedBoxCatLabels[$label] = array($boxCat, $box);
				}
			}
		}
	}

	/**
	 * @param string $screenId
	 * @param ameMetaBox $box
	 * @return string
	 */
	private function makeHideableItemId($screenId, $box) {
		return 'meta-boxes/' . $screenId . '/' . $box->getId();
	}

	public function saveHideableItems($errors, $items) {
		$settings = $this->loadSettings();
		if ( $this->areSettingsCorrupted ) {
			$errors[] = new WP_Error(
				'ame_corrupted_boxes',
				'Existing meta box settings are corrupted and cannot be changed.'
			);
			return $errors;
		}

		$anySettingsModified = false;

		foreach ($settings as $screenId => $screenSettings) {
			/** @var \ameMetaBox $box */
			foreach ($screenSettings->getMetaBoxes() as $box) {
				$id = $this->makeHideableItemId($screenId, $box);
				if ( isset($items[$id]) ) {
					$enabled = isset($items[$id]['enabled']) ? $items[$id]['enabled'] : array();
					$boxModified = $box->setGrantAccess($enabled);
					$anySettingsModified = $anySettingsModified || $boxModified;
				}
			}
		}

		if ( $anySettingsModified ) {
			$this->settings = $settings;
			$this->saveSettings();
		}

		return $errors;
	}
}