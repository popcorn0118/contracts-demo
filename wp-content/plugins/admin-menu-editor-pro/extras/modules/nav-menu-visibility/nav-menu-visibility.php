<?php

namespace YahnisElsts\AdminMenuEditor\NavMenuVisibility;

class NavMenuModule extends \ameModule {
	const REQUIRED_CAPABILITY = 'edit_theme_options';
	const SAVE_SETTINGS_ACTION = 'ame-save-nav-menu-visibility';

	protected $tabSlug = 'nav-menus';
	protected $tabTitle = 'Nav Menus';
	protected $tabOrder = 10;
	protected $settingsFormAction = self::SAVE_SETTINGS_ACTION;

	/**
	 * @var MenuAdapter[]
	 */
	private $adapters = [];

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);
		$this->installAdapterHooks();

		//todo: "Easy Hide" integration.
	}

	public function installAdapterHooks() {
		foreach ($this->getAllNavMenuAdapters() as $adapter) {
			$adapter->installHooks();
		}
	}

	public function displaySettingsPage() {
		if ( !current_user_can(self::REQUIRED_CAPABILITY) ) {
			wp_die('Error: You do not have permission to edit navigation menus.');
		}
		parent::displaySettingsPage();
	}


	public function enqueueTabScripts() {
		parent::enqueueTabScripts();

		$scriptHandle = 'ame-nv-settings';
		wp_enqueue_auto_versioned_script(
			$scriptHandle,
			plugins_url('nav-settings.js', __FILE__),
			['jquery', 'ame-knockout', 'ame-actor-manager', 'ame-actor-selector']
		);

		//Tell the script to re-select the previously selected actor.
		$previouslySelectedActor = \ameUtils::get($this->menuEditor->get_query_params(), 'selectedActor', null);
		if ( !is_string($previouslySelectedActor) && ($previouslySelectedActor !== null) ) {
			$previouslySelectedActor = null;
		}

		//Does the current theme use full site editing?
		$isFseTheme = function_exists('wp_is_block_theme') && wp_is_block_theme();
		if ( $isFseTheme ) {
			$activeAdapter = $this->getNavMenuAdapterFor('block');
		} else {
			$activeAdapter = $this->getNavMenuAdapterFor('classic');
		}

		$scriptData = [
			'navigationMenus' => $activeAdapter->getNavigationMenus(),
			'saveNonce'       => wp_create_nonce(self::SAVE_SETTINGS_ACTION),
			'selectedActor'   => $previouslySelectedActor,
		];
		wp_add_inline_script(
			$scriptHandle,
			'var wsAmeNavMenuVisibilityData = ' . wp_json_encode($scriptData) . ';', 'before'
		);
	}

	public function enqueueTabStyles() {
		parent::enqueueTabStyles();

		wp_enqueue_auto_versioned_style(
			'ame-nv-settings',
			plugins_url('nav-settings.css', __FILE__)
		);
	}

	public function handleSettingsForm($post = []) {
		if ( !current_user_can(self::REQUIRED_CAPABILITY) ) {
			wp_die('Error: You do not have permission to edit navigation menus.');
		}

		parent::handleSettingsForm($post);

		$submittedSettings = json_decode($post['settings'], true);
		if ( !is_array($submittedSettings) || !is_array($submittedSettings['menus']) ) {
			wp_die('Error: Invalid settings data. The "menus" field is missing or not an array.');
		}

		$updatedMenus = 0;
		foreach ($submittedSettings['menus'] as $menu) {
			$menuType = (string)\ameUtils::get($menu, 'type', '');
			$adapter = $this->getNavMenuAdapterFor($menuType);
			if ( !$adapter ) {
				wp_die('Error: Unsupported navigation menu type "' . esc_html($menuType) . '".');
			}

			$menuId = (int)\ameUtils::get($menu, 'id', 0);
			if ( $menuId <= 0 ) {
				wp_die('Error: Invalid menu ID.');
			}

			$items = array_map(
				function ($item) {
					return StorableNavigationItemData::fromJs($item);
				},
				\ameUtils::get($menu, 'items', [])
			);

			try {
				$adapter->updateNavigationMenuVisibility($menuId, $items);
			} catch (\RuntimeException $e) {
				wp_die(esc_html($e->getMessage()));
			}

			$updatedMenus++;
		}

		//Redirect back to the settings page.
		$redirectParams = ['updated' => 1, 'updatedMenus' => $updatedMenus];

		//Re-select the previously selected actor, if any.
		$actorId = (string)\ameUtils::get($post, 'selectedActor', '');
		if ( !empty($actorId) ) {
			$redirectParams['selectedActor'] = $actorId;
		}

		wp_safe_redirect(add_query_arg($redirectParams, $this->getTabUrl()));
		exit;
	}

	private function getAllNavMenuAdapters() {
		if ( !empty($this->adapters) ) {
			return $this->adapters;
		}

		$cleanupEnabled = (bool)$this->menuEditor->get_plugin_option('delete_orphan_actor_settings');

		$adapters = [
			new ClassicNavigationMenuAdapter([$this, 'currentUserCanSeeItem'], $cleanupEnabled),
			new BlockMenuAdapter([$this, 'currentUserCanSeeItem'], $cleanupEnabled),
		];
		foreach ($adapters as $adapter) {
			$this->adapters[$adapter->getMenuType()] = $adapter;
		}

		return $this->adapters;
	}

	/**
	 * @param string $type
	 * @return MenuAdapter|null
	 */
	private function getNavMenuAdapterFor($type) {
		$adapters = $this->getAllNavMenuAdapters();
		if ( isset($adapters[$type]) ) {
			return $adapters[$type];
		}
		return null;
	}

	public function currentUserCanSeeItem(VisibilitySettings $visibilitySettings) {
		static $visibilityChecker = null;

		if ( is_user_logged_in() ) {
			if ( !$visibilitySettings->loggedInUsersEnabled() ) {
				return false;
			}

			if ( $visibilityChecker === null ) {
				$visibilityChecker = \ameAccessEvaluatorBuilder::create($this->menuEditor)
					->roleDefault(true)       //All roles can see all nav items by default.
					->superAdminDefault(null) //No special treatment for super admins.
					->defaultResult(true)     //If no other rules apply, the item is visible.
					->buildForUser(wp_get_current_user());
			}

			return $visibilityChecker->userHasAccess($visibilitySettings->getGrantAccess());
		} else {
			return $visibilitySettings->anonymousUsersEnabled();
		}
	}
}

class VisibilitySettings implements \JsonSerializable {
	private $grantAccess;
	private $loggedInUsers;
	private $anonymousUsers;

	public function __construct($grantAccess = [], $loggedInUsers = true, $anonymousUsers = true) {
		$this->grantAccess = $grantAccess;
		$this->loggedInUsers = $loggedInUsers;
		$this->anonymousUsers = $anonymousUsers;
	}

	/**
	 * @param array $data
	 * @return self
	 */
	public static function fromArray($data) {
		//The IDE thinks these ternaries are suboptimal, but it's more readable this way.
		/** @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection */
		return new self(
			isset($data['grantAccess']) ? (array)$data['grantAccess'] : [],
			isset($data['loggedInUsers']) ? (bool)($data['loggedInUsers']) : true,
			isset($data['anonymousUsers']) ? (bool)($data['anonymousUsers']) : true
		);
	}

	/**
	 * @return array|mixed
	 */
	public function getGrantAccess() {
		return $this->grantAccess;
	}

	/**
	 * @return mixed|true
	 */
	public function loggedInUsersEnabled() {
		return $this->loggedInUsers;
	}

	/**
	 * @return mixed|true
	 */
	public function anonymousUsersEnabled() {
		return $this->anonymousUsers;
	}

	/**
	 * Check if all settings are at their default values.
	 *
	 * @return bool
	 */
	public function isDefault() {
		return empty($this->grantAccess) && $this->loggedInUsers && $this->anonymousUsers;
	}

	/**
	 * Remove any actor settings that are the same as the defaults.
	 *
	 * Optionally, you can pass in a $cleaner object to also remove references to missing
	 * or invalid actors, such as deleted users.
	 *
	 * @param \ameActorAccessCleaner $cleaner
	 * @return void
	 */
	public function prune($cleaner = null) {
		foreach (array_keys($this->grantAccess) as $actorId) {
			$value = $this->grantAccess[$actorId];

			//All roles can see all nav items by default, so we don't need to store "true"
			//values for roles.
			if ( $value && \ameUtils::stringStartsWith($actorId, 'role:') ) {
				unset($this->grantAccess[$actorId]);
			}
		}

		if ( $cleaner ) {
			$this->grantAccess = $cleaner->cleanUpDictionary($this->grantAccess);
		}
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = new \stdClass();

		if ( !empty($this->grantAccess) ) {
			$data->grantAccess = (object)$this->grantAccess;
		}

		//To save space, store the logged-in/out settings only if they're different from the defaults.
		if ( !$this->loggedInUsers ) {
			$data->loggedInUsers = false;
		}
		if ( !$this->anonymousUsers ) {
			$data->anonymousUsers = false;
		}

		return $data;
	}
}

/**
 * Internal representation of a navigation menu.
 */
class NavigationMenu implements \JsonSerializable {
	private $id;
	private $label;
	private $type;
	private $items;

	/**
	 * @param int $id
	 * @param string $label
	 * @param string $type
	 * @param NavigationMenuItem[] $items
	 */
	public function __construct($id, $label, $type, $items) {
		$this->id = $id;
		$this->label = $label;
		$this->type = $type;
		$this->items = $items;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id'    => $this->id,
			'label' => $this->label,
			'type'  => $this->type,
			'items' => $this->items,
		];
	}
}

/**
 * Internal representation of a navigation menu item.
 */
class NavigationMenuItem implements \JsonSerializable {
	private $label;
	private $type;
	private $settings;
	private $children;
	private $passThroughProps;

	/**
	 * @param string $label
	 * @param string $type
	 * @param VisibilitySettings $settings
	 * @param NavigationMenuItem[] $children
	 * @param array $passThroughProps
	 */
	public function __construct($label, $type, $settings, $children, $passThroughProps) {
		$this->label = $label;
		$this->type = $type;
		$this->settings = $settings;
		$this->children = $children;
		$this->passThroughProps = $passThroughProps;
	}

	/**
	 * @param NavigationMenuItem $child
	 */
	public function addChild($child) {
		$this->children[] = $child;
	}

	public function getProp($name, $default = null) {
		return isset($this->passThroughProps[$name]) ? $this->passThroughProps[$name] : $default;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'label'            => $this->label,
			'type'             => $this->type,
			'settings'         => $this->settings,
			'children'         => $this->children,
			'passThroughProps' => $this->passThroughProps,
		];
	}
}

class StorableNavigationItemData {
	private $settings;
	private $passThroughProps;

	public function __construct($settings, $passThroughProps) {
		$this->settings = $settings;
		$this->passThroughProps = $passThroughProps;
	}

	public static function fromJs($data) {
		$data = (array)$data;
		return new self(
			VisibilitySettings::fromArray($data['settings']),
			$data['passThroughProps']
		);
	}

	/**
	 * @return VisibilitySettings
	 */
	public function getSettings() {
		return $this->settings;
	}

	public function getProp($name, $default = null) {
		return isset($this->passThroughProps[$name]) ? $this->passThroughProps[$name] : $default;
	}
}

abstract class MenuAdapter {
	/** @var callable */
	protected $checkVisibilityCallback;
	/**
	 * @var bool
	 */
	private $deleteOrphansOnSave;

	/**
	 * @var \ameActorAccessCleaner|null
	 */
	private $accessCleaner = null;

	public function __construct($checkVisibilityCallback, $deleteOrphansOnSave = false) {
		$this->checkVisibilityCallback = $checkVisibilityCallback;
		$this->deleteOrphansOnSave = $deleteOrphansOnSave;
	}

	protected function isItemVisible(VisibilitySettings $visibilitySettings) {
		return call_user_func($this->checkVisibilityCallback, $visibilitySettings);
	}

	/**
	 * @return \ameActorAccessCleaner|null
	 */
	protected function getAccessCleaner() {
		if ( !$this->deleteOrphansOnSave ) {
			return null;
		}

		if ( $this->accessCleaner === null ) {
			$this->accessCleaner = new \ameActorAccessCleaner();
		}
		return $this->accessCleaner;
	}

	/**
	 * @return NavigationMenu[]
	 */
	abstract public function getNavigationMenus();

	/**
	 * @param int $menuId
	 * @param StorableNavigationItemData[] $newItemData
	 */
	abstract public function updateNavigationMenuVisibility($menuId, $newItemData);

	/**
	 * @return string
	 */
	abstract public function getMenuType();

	abstract public function installHooks();
}

class BlockMenuAdapter extends MenuAdapter {
	const BLOCK_VISIBILITY_ATTRIBUTE = 'ameNavMenuVisibility';
	const IDENTIFYING_BLOCK_PROPERTIES = [
		'blockName',
		'attrs.id',
		'attrs.type',
		'attrs.kind',
		'attrs.url',
	];

	public function getNavigationMenus() {
		$navs = get_posts([
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => -1,
		]);

		$menus = [];
		foreach ($navs as $navigationMenu) {
			$blocks = parse_blocks($navigationMenu->post_content);
			$items = self::navigationBlockListToItemList($blocks);

			if ( !empty($items) ) {
				$menus[] = new NavigationMenu(
					$navigationMenu->ID,
					$navigationMenu->post_title,
					$this->getMenuType(),
					$items
				);
			}
		}
		return $menus;
	}

	public function updateNavigationMenuVisibility($menuId, $newItemData) {
		//echo 'Attempting to update navigation menu visibility for block menu ', $menuId, '...<br>';

		//Fetch the post.
		$navigationMenu = get_post($menuId);
		if ( !$navigationMenu ) {
			throw new \RuntimeException(sprintf('Error: Could not retrieve navigation menu %d', $menuId));
		}
		//Is this actually a navigation menu?
		if ( $navigationMenu->post_type !== 'wp_navigation' ) {
			throw new \RuntimeException(sprintf('Error: Post %d is not a navigation menu.', $menuId));
		}

		//echo 'Original post content: <pre>', esc_html($navigationMenu->post_content), '</pre><br>';

		//Parse the blocks.
		$blocks = parse_blocks($navigationMenu->post_content);
		if ( empty($blocks) ) {
			throw new \RuntimeException(sprintf('Error: Navigation menu %d has no blocks.', $menuId));
		}

		//Depending on plugin configuration, we might clean up orphaned settings before saving.
		$cleaner = $this->getAccessCleaner();

		foreach ($newItemData as $item) {
			$path = $item->getProp('path');
			$block = self::getBlockByPath($blocks, $path);
			if ( $block === null ) {
				throw new \RuntimeException(sprintf('Error: Could not find block at path %s.', wp_json_encode($path)));
			}

			$settings = $item->getSettings();
			$settings->prune($cleaner);

			if ( $settings->isDefault() ) {
				//If using the default settings, we can remove the attribute entirely to save space.
				unset($block['attrs'][self::BLOCK_VISIBILITY_ATTRIBUTE]);
			} else {
				//Otherwise, serialize the settings and store them in the attribute.
				$block['attrs'][self::BLOCK_VISIBILITY_ATTRIBUTE] = wp_json_encode($settings);
			}

			$success = self::replaceBlockByPath($blocks, $path, $block);
			if ( !$success ) {
				throw new \RuntimeException(sprintf('Error: Could not update block at path %s.', wp_json_encode($path)));
			}
		}

		//Save the updated blocks back to the post.
		$navigationMenu->post_content = serialize_blocks($blocks);
		//echo 'New post content: <pre>', esc_html($navigationMenu->post_content), '</pre><br>';

		wp_update_post($navigationMenu);
	}

	private static function navigationBlockListToItemList($blocks, $parentPath = []) {
		$results = [];
		foreach ($blocks as $index => $block) {
			//Skip empty "null" blocks. We don't filter them out beforehand because we need
			//to keep the indexes in sync with the original block list.
			if ( !isset($block['blockName']) ) {
				continue;
			}

			$results[] = self::parseNavigationMenuBlock($block, $index, $parentPath);
		}
		return $results;
	}

	private static function parseNavigationMenuBlock($block, $index, $parentPath = []) {
		$path = array_merge($parentPath, [$index]);
		$blockName = $block['blockName'];

		if ( !empty($block['attrs']['label']) ) {
			$label = $block['attrs']['label'];
		} else if ( $blockName === 'core/social-link' ) {
			$label = 'Social Link';
			$service = ucwords(\ameUtils::get($block, ['attrs', 'service'], ''));
			if ( !empty($service) ) {
				$label .= ' (' . $service . ')';
			}
		} else {
			$defaultLabels = [
				'core/search'       => 'Search',
				'core/site-title'   => 'Site Title',
				'core/site-logo'    => 'Site Logo',
				'core/home-link'    => 'Home Link',
				'core/social-links' => 'Social Icons',
				'core/page-list'    => 'Page List',
				'core/spacer'       => '⸻ Spacer ⸻',
			];
			$label = isset($defaultLabels[$blockName]) ? $defaultLabels[$blockName] : $blockName;
		}

		//The UI can safely display HTML, but it's probably usually unimportant formatting,
		//like <strong> or <em>. Where possible, let's strip tags to keep labels short.
		$strippedLabel = trim(wp_strip_all_tags($label));
		if ( !empty($strippedLabel) ) {
			$label = $strippedLabel;
		}

		$type = isset($block['attrs']['type']) ? $block['attrs']['type'] : $blockName;

		if ( !empty($block['attrs'][self::BLOCK_VISIBILITY_ATTRIBUTE]) ) {
			$data = $block['attrs'][self::BLOCK_VISIBILITY_ATTRIBUTE];
			if ( is_string($data) ) {
				$settings = VisibilitySettings::fromArray(json_decode($data, true));
			} else {
				$settings = VisibilitySettings::fromArray($data);
			}
		} else {
			$settings = new VisibilitySettings();
		}

		$children = [];
		if ( !empty($block['innerBlocks']) ) {
			$children = self::navigationBlockListToItemList($block['innerBlocks'], $path);
		}

		//Let's store some block properties so that we can verify that the path still
		//points to the same block when it comes time to save the visibility settings.
		$passThroughProps = [];
		foreach (self::IDENTIFYING_BLOCK_PROPERTIES as $propertyPath) {
			$value = \ameMultiDictionary::get($block, $propertyPath, null);
			if ( $value !== null ) {
				\ameMultiDictionary::set($passThroughProps, $propertyPath, $value);
			}
		}
		$passThroughProps['path'] = $path;

		return new NavigationMenuItem(
			$label,
			$type,
			$settings,
			$children,
			$passThroughProps
		);
	}

	public function getMenuType() {
		return 'block';
	}

	public function installHooks() {
		add_filter('block_core_navigation_render_inner_blocks', [$this, 'filterNavigationMenuBlocks'], 199);

		//Register the custom attribute in the block editor.
		add_action('enqueue_block_editor_assets', [$this, 'enqueueAttributeScript']);

		//Also register the custom attribute for the REST API. If we don't, it can cause REST API
		//errors when rendering block previews. The API complains that "ameNavMenuVisibility is not
		//a valid property of Object".
		add_filter('register_block_type_args', [$this, 'addAttributeToBlockType']);

		//Remove the custom attribute just before rendering the block in case it interferes
		//with any plugins or themes.
		add_filter('render_block_data', [$this, 'removeAttributeBeforeRendering']);
	}

	public function filterNavigationMenuBlocks($innerBlocks) {
		if ( is_admin() ) {
			return $innerBlocks;
		}

		return $this->filterBlockCollection($innerBlocks);
	}

	/**
	 * @param array|\WP_Block_List $blocks
	 * @return array|\WP_Block_List
	 */
	private function filterBlockCollection($blocks) {
		if ( !is_array($blocks) && !($blocks instanceof \Traversable) ) {
			return $blocks;
		}

		$keysToRemove = [];
		foreach ($blocks as $index => $block) {
			//We expect the block to be a WP_Block instance. For forward-compatibility,
			//we only check properties that we actually use, not the class name.
			$isSupportedBlock = isset($block->parsed_block, $block->inner_blocks);
			if ( !$isSupportedBlock ) {
				continue;
			}

			if ( $this->isBlockVisible($block) ) {
				//Filter inner blocks, if any.
				if ( !empty($block->inner_blocks) ) {
					$block->inner_blocks = $this->filterBlockCollection($block->inner_blocks);
				}
			} else {
				$keysToRemove[$index] = $index;
			}
		}
		unset($index);

		if ( !empty($keysToRemove) ) {
			/*
			Removing the blocks would be complicated because:
			 1. Some code in WordPress and other plugins expects sequential indexes.
			    For example, WP_Block::render() does `++$index` in a loop.
			 2. Reindexing the array is hard because WP_Block_List doesn't allow direct
			    access to the "blocks" property.
			 3. The "inner_content" property of a block must stay in sync with the
			    "inner_blocks" property, so that's another array to reindex.
			 4. "inner_content" can have additional string values that don't correspond to
			     inner blocks, making index updates even more complicated.

			Instead, let's replace hidden blocks with empty placeholders.
			*/
			foreach ($keysToRemove as $index) {
				$blocks[$index] = new \WP_Block([
					//Dummy block name that won't match any real block.
					'blockName'    => 'admin-menu-editor/empty-placeholder',
					'attrs'        => [],
					'innerBlocks'  => [],
					'innerHTML'    => '',
					'innerContent' => [],
				]);
			}
		}

		return $blocks;
	}

	/**
	 * @param \WP_Block $block
	 * @return boolean
	 */
	private function isBlockVisible($block) {
		$serializedSettings = \ameUtils::get($block->parsed_block, ['attrs', self::BLOCK_VISIBILITY_ATTRIBUTE], '');
		if ( empty($serializedSettings) || !is_string($serializedSettings) ) {
			return true;
		}

		$asArray = json_decode($serializedSettings, true);
		if ( empty($asArray) || !is_array($asArray) ) {
			return true;
		}

		$settings = VisibilitySettings::fromArray($asArray);
		return $this->isItemVisible($settings);
	}


	/**
	 * @param array $blocks
	 * @param array<int> $path
	 * @return array|null
	 */
	private static function getBlockByPath($blocks, $path) {
		if ( empty($path) ) {
			return null;
		}

		$result = null;
		$currentList = $blocks;
		foreach ($path as $index) {
			if ( !isset($currentList[$index]) ) {
				return null;
			}
			$result = $currentList[$index];
			$currentList = isset($result['innerBlocks']) ? $result['innerBlocks'] : [];
		}
		return $result;
	}

	private static function replaceBlockByPath(&$blocks, $path, $newBlock) {
		if ( empty($path) ) {
			return false;
		}

		$currentList = &$blocks;
		$lastIndex = array_pop($path);
		foreach ($path as $index) {
			if ( !isset($currentList[$index]) ) {
				return false;
			}
			$currentList = &$currentList[$index]['innerBlocks'];
		}

		$currentList[$lastIndex] = $newBlock;
		return true;
	}

	public function enqueueAttributeScript() {
		wp_enqueue_auto_versioned_script(
			'ame-nv-block-attributes',
			plugins_url('custom-block-attributes.js', __FILE__),
			['wp-blocks', 'wp-dom-ready']
		);
	}

	public function addAttributeToBlockType($args) {
		if ( !isset($args['attributes']) ) {
			$args['attributes'] = [];
		}

		$args['attributes'][self::BLOCK_VISIBILITY_ATTRIBUTE] = [
			'type'    => 'string',
			'default' => '',
		];

		return $args;
	}

	public function removeAttributeBeforeRendering($blockData) {
		if ( isset($blockData['attrs'][BlockMenuAdapter::BLOCK_VISIBILITY_ATTRIBUTE]) ) {
			unset($blockData['attrs'][BlockMenuAdapter::BLOCK_VISIBILITY_ATTRIBUTE]);
		}
		return $blockData;
	}
}

class ClassicNavigationMenuAdapter extends MenuAdapter {
	const SETTINGS_META_KEY = '_ame_nav_menu_visibility';

	public function getNavigationMenus() {
		$classicNavMenus = wp_get_nav_menus([
			'hide_empty' => true,
		]);

		$results = [];
		foreach ($classicNavMenus as $classicNavMenu) {
			$navMenu = wp_get_nav_menu_object($classicNavMenu->term_id);
			$navMenuItems = wp_get_nav_menu_items($navMenu->term_id);
			if ( empty($navMenuItems) ) {
				continue;
			}

			//Generate wrappers.
			$itemById = [];
			/** @var NavigationMenuItem[] $itemById */
			foreach ($navMenuItems as $navMenuItem) {
				$type = 'nav-menu-item';
				if ( !empty($navMenuItem->type) ) {
					$type = $navMenuItem->type;
				} else if ( !empty($navMenuItem->object) ) {
					$type = $navMenuItem->object;
				}

				$settings = $this->loadItemSettings($navMenuItem->ID);
				if ( $settings === null ) {
					$settings = new VisibilitySettings();
				}

				$child = new NavigationMenuItem(
					$navMenuItem->title,
					$type,
					$settings,
					[],
					[
						'id'     => $navMenuItem->ID,
						'parent' => isset($navMenuItem->menu_item_parent) ? $navMenuItem->menu_item_parent : 0,
					]
				);
				$itemById[(string)$navMenuItem->ID] = $child;
			}

			//Reconstruct the menu hierarchy.
			$children = [];
			foreach ($itemById as $item) {
				$parentId = $item->getProp('parent', 0);
				if ( isset($itemById[$parentId]) ) {
					$itemById[$parentId]->addChild($item);
				} else {
					$children[] = $item;
				}
			}

			$results[] = new NavigationMenu(
				$classicNavMenu->term_id,
				$classicNavMenu->name,
				$this->getMenuType(),
				$children
			);
		}

		return $results;
	}

	public function updateNavigationMenuVisibility($menuId, $newItemData) {
		$navMenuItems = wp_get_nav_menu_items($menuId);
		if ( $navMenuItems === false ) {
			throw new \RuntimeException(sprintf('Error: Could not retrieve navigation menu %d', $menuId));
		}

		//Index by ID.
		$navMenuItemsById = [];
		foreach ($navMenuItems as $navMenuItem) {
			if ( isset($navMenuItem->ID) ) {
				$navMenuItemsById[$navMenuItem->ID] = $navMenuItem;
			}
		}

		//Find the corresponding nav menu items and update their settings.
		foreach ($newItemData as $itemData) {
			$id = $itemData->getProp('id');
			if ( !isset($navMenuItemsById[$id]) ) {
				continue; //Silently skip non-existent items.
			}

			$navMenuItem = $navMenuItemsById[$id];
			$settings = $itemData->getSettings();
			$settings->prune($this->getAccessCleaner());

			if ( $settings->isDefault() ) {
				delete_post_meta($navMenuItem->ID, self::SETTINGS_META_KEY);
			} else {
				update_post_meta($navMenuItem->ID, self::SETTINGS_META_KEY, wp_json_encode($settings));
			}
		}
	}

	public function getMenuType() {
		return 'classic';
	}

	public function installHooks() {
		//While "wp_nav_menu_objects" would normally be the most appropriate filter to use,
		//"wp_get_nav_menu_items" is more reliable because it should work even with themes
		//that don't use wp_nav_menu() to render their menus.
		add_filter('wp_get_nav_menu_items', [$this, 'filterNavigationMenuItems'], 199);
	}

	public function filterNavigationMenuItems($items) {
		//Don't change anything on normal admin pages. This is only for the front end.
		if ( is_admin() ) {
			return $items;
		}

		//Filter out items that are not visible.
		$filteredItems = array_filter($items, function ($item) {
			$settings = $this->loadItemSettings($item->ID);
			if ( $settings === null ) {
				return true;
			}
			return $this->isItemVisible($settings);
		});

		//Just to be safe, let's only replace the original items if we actually removed some.
		if ( count($filteredItems) < count($items) ) {
			//Not sure if array indexes matter here; WordPress itself doesn't seem to care.
			$items = array_values($filteredItems);
		}

		return $items;
	}

	/**
	 * Load visibility settings for a navigation menu item.
	 *
	 * Returns NULL if the item has no settings or the settings are invalid.
	 *
	 * @param int $menuItemId
	 * @return VisibilitySettings|null
	 */
	private function loadItemSettings($menuItemId) {
		$serializedSettings = get_post_meta($menuItemId, self::SETTINGS_META_KEY, true);
		if ( empty($serializedSettings) ) {
			return null;
		}

		$asArray = json_decode($serializedSettings, true);
		if ( empty($asArray) || !is_array($asArray) ) {
			return null;
		}

		return VisibilitySettings::fromArray($asArray);
	}
}