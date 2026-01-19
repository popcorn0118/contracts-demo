<?php

namespace YahnisElsts\AdminMenuEditor\EasyHide;

require_once __DIR__ . '/eh-preferences.php';

use AmeEhIsExplanationHidden;
use AmeEhUserPreferences;
use JsonSerializable;
use WP_Error;

class Core {
	const SCRIPT_HANDLE = 'ame-easy-hide';
	const STYLE_HANDLE = 'ame-easy-hide-style';
	const MENU_SLUG = 'ame-easy-hide';

	const SAVE_ACTION = 'ame_save_easy_hide_settings';

	/**
	 * @var \WPMenuEditor
	 */
	private $menuEditor;

	/**
	 * @var HideableItemStore|null
	 */
	private $store = null;

	/**
	 * @var string
	 */
	private $parentMenuSlug = '';

	/**
	 * @var \AmeEhIsExplanationHidden
	 */
	private $isExplanationHidden;
	/**
	 * @var \AmeEhUserPreferences
	 */
	private $userPreferences;

	public function __construct($menuEditor) {
		$this->menuEditor = $menuEditor;

		//This module uses the WP_Error::merge_from() method, which is only available in WP 5.6.0+.
		if ( !method_exists(WP_Error::class, 'merge_from') ) {
			add_action('all_admin_notices', function () {
				if ( !current_user_can('activate_plugins') ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					'The "Easy Hide" module requires WordPress 5.6.0 or newer.'
					. ' Please upgrade your WordPress installation or disable this module in Admin Menu Editor settings.'
				);
			});
			return;
		}

		//Register the EasyHide menu item after the "Menu Editor (Pro)" item.
		add_action('admin_menu_editor-editor_menu_registered', [$this, 'addAdminMenu'], 11);

		if ( is_admin() ) {
			$this->isExplanationHidden = new AmeEhIsExplanationHidden();
			$this->userPreferences = new AmeEhUserPreferences();

			$this->isExplanationHidden
				->installAjaxCallback(array($this->menuEditor, 'current_user_can_edit_menu'));
			$this->userPreferences
				->installAjaxCallback(array($this->menuEditor, 'current_user_can_edit_menu'));
		}
	}

	public function addAdminMenu() {
		if ( !$this->menuEditor->current_user_can_edit_menu() ) {
			return;
		}

		$this->parentMenuSlug = is_network_admin() ? 'settings.php' : 'options-general.php';

		$suffix = add_submenu_page(
			$this->parentMenuSlug,
			'Easy Hide',
			'Easy Hide',
			apply_filters('admin_menu_editor-capability', 'manage_options'),
			self::MENU_SLUG,
			[$this, 'displaySettingsPage']
		);

		//Technically, admin_enqueue_scripts is the officially approved way
		//to enqueue scripts on admin pages, but that hook runs on every admin
		//page. We would have to add more logic to only enqueue scripts on
		//our own page, and that logic would then run on every page.
		//admin_print_scripts-$suffix provides a simpler solution.
		add_action(
			'admin_print_scripts-' . $suffix,
			[$this, 'enqueueDependencies'],
			2000
		);

		\ameMenuItem::add_class_to_submenu_item(
			$this->parentMenuSlug,
			self::MENU_SLUG,
			'ws-ame-secondary-am-item'
		);
	}

	public function enqueueDependencies() {
		$this->menuEditor->register_base_dependencies();

		wp_register_auto_versioned_script(
			'ame-lazyload',
			plugins_url('js/lazyload.min.js', $this->menuEditor->plugin_file)
		);

		wp_register_auto_versioned_script(
			self::SCRIPT_HANDLE,
			plugins_url('easy-hide.js', __FILE__),
			[
				'ame-knockout',
				'jquery',
				'ame-ko-extensions',
				'ame-actor-selector',
				'ame-actor-manager',
				'ame-lodash',
				'ame-lazyload',
				'ame-settings-page-utils',
				$this->isExplanationHidden->getScriptHandle(),
				$this->userPreferences->getScriptHandle(),
			]
		);

		wp_enqueue_script(self::SCRIPT_HANDLE);

		$scriptData = $this->getPopulatedStore()->jsonSerialize();
		$scriptData['selectedActor'] = !empty($_GET['selectedActor']) ? ((string)$_GET['selectedActor']) : null;
		$scriptData['selectedCategory'] = !empty($_GET['selectedCategory']) ? ((string)$_GET['selectedCategory']) : null;

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			sprintf('wsEasyHideData = (%s);', wp_json_encode($scriptData)),
			'before'
		);

		wp_enqueue_auto_versioned_style(
			self::STYLE_HANDLE,
			plugins_url('easy-hide-style.css', __FILE__),
			['menu-editor-base-style']
		);
	}

	public function displaySettingsPage() {
		if ( !$this->menuEditor->current_user_can_edit_menu() ) {
			//This should never happen since we already check permissions when adding
			//the admin menu item, but let's be extra safe.
			wp_die('You do not have permission to access this AME page.');
		}

		$post = $this->menuEditor->get_post_params();
		if ( isset($post['action']) && ($post['action'] == self::SAVE_ACTION) ) {
			$this->handleSettingsForm($post);
		}

		$this->outputMainTemplate();

		//Output the "select visible users" dialog template. This doesn't happen
		//automatically outside the "Settings -> Menu Editor (Pro)" page.
		do_action('admin_menu_editor-visible_users_template');
	}

	private function outputMainTemplate() {
		$settingsPageUrl = $this->getSettingsPageUrl();
		$moduleTitle = 'Easy Hide';
		$moduleSettingsUrl = $this->menuEditor->get_settings_page_url() . '#ame-available-modules';

		$isExplanationVisible = !$this->isExplanationHidden->__invoke();

		require __DIR__ . '/' . 'easy-hide-template.php';
	}

	private function getSettingsPageUrl() {
		return add_query_arg('page', self::MENU_SLUG, self_admin_url($this->parentMenuSlug));
	}

	/**
	 * @param array $post
	 */
	private function handleSettingsForm($post) {
		check_admin_referer(self::SAVE_ACTION);

		if ( !isset($post['settings']) || !is_string($post['settings']) ) {
			wp_die('The required "settings" parameter is missing or invalid.');
		}
		$settings = json_decode($post['settings'], true);

		if ( !isset($settings['items']) || !is_array($settings['items']) ) {
			wp_die('The required "items" key is missing or has an incorrect data type.');
		}

		//This script doesn't actually change any settings, it just notifies
		//other components and plugins that new settings should be saved.
		//First, a global notification about all items.
		$errors = apply_filters('admin_menu_editor-save_hideable_items', [], $settings['items']);

		//Trigger individual actions for each component. This way a component/module
		//won't have to scan the whole item list to find the settings it cares about.
		$itemsByComponent = [];
		foreach ($settings['items'] as $id => $itemData) {
			if ( isset($itemData['component']) ) {
				if ( !isset($itemsByComponent[$itemData['component']]) ) {
					$itemsByComponent[$itemData['component']] = [];
				}
				$itemsByComponent[$itemData['component']][$id] = $itemData;
			}
		}

		foreach ($itemsByComponent as $component => $items) {
			$componentErrors = apply_filters(
				'admin_menu_editor-save_hideable_items-' . $component,
				[],
				$items
			);
			$errors = array_merge($errors, $componentErrors);
		}

		if ( empty($errors) ) {
			//Pass through the previously selected actor and so on.
			$redirectParams = ['message' => 1];
			$passThrough = ['selectedActor', 'selectedCategory'];
			foreach ($passThrough as $parameter) {
				if ( !empty($post[$parameter]) ) {
					$redirectParams[$parameter] = (string)$post[$parameter];
				}
			}

			wp_redirect(add_query_arg($redirectParams, $this->getSettingsPageUrl()));
			exit();
		} else {
			$container = new WP_Error(
				'eh_save_failed',
				'One or more errors occurred while saving settings.'
			);
			foreach ($errors as $error) {
				if ( is_wp_error($error) ) {
					$container->merge_from($error);
				} else if ( is_string($error) ) {
					$container->add('string_error', $error);
				} else {
					$container->add(
						'invalid_error',
						'Unrecognized error type. Expected a string or a WP_Error instance.'
					);
				}
			}
			wp_die($container);
		}
	}

	/**
	 * @return HideableItemStore
	 */
	private function getPopulatedStore() {
		if ( $this->store !== null ) {
			return $this->store;
		}

		$this->store = new HideableItemStore();

		//Let modules register their items.
		do_action(
			'admin_menu_editor-register_hideable_items',
			$this->store
		);

		return $this->store;
	}
}

class HideableItemStore implements JsonSerializable {
	/**
	 * @var array<string, Category>
	 */
	private $categories = [];
	/**
	 * @var array<string, HideableItem>
	 */
	private $items = [];

	/**
	 * @param string $id
	 * @param string $label
	 * @param Category[] $categories
	 * @param string|HideableItem|null $parent
	 * @param Array<string,boolean> $enabled
	 * @param string|null $component
	 * @param string|null $tooltip
	 * @param bool|null $inverted
	 * @return HideableItem
	 */
	public function addItem(
		$id,
		$label,
		$categories = [],
		$parent = null,
		$enabled = [],
		$component = null,
		$tooltip = null,
		$inverted = null
	) {
		if ( is_string($parent) ) {
			$parent = $this->items[$parent];
		}

		$item = new HideableItem(
			$id,
			$label,
			array_filter($categories),
			$parent,
			$enabled,
			$component,
			$tooltip,
			$inverted
		);
		$this->items[$id] = $item;

		return $item;
	}

	/**
	 * @param string $id
	 * @param string $label
	 * @param Category[] $categories
	 * @param string|HideableItem|null $parent
	 * @param boolean $enabledForAll
	 * @param string|null $component
	 * @param string|null $tooltip
	 * @param bool|null $inverted
	 * @return BinaryHideableItem
	 */
	public function addBinaryItem(
		$id,
		$label,
		$categories = [],
		$parent = null,
		$enabledForAll = false,
		$component = null,
		$tooltip = null,
		$inverted = null
	) {
		if ( is_string($parent) ) {
			$parent = $this->items[$parent];
		}

		$item = new BinaryHideableItem(
			$id,
			$label,
			array_filter($categories),
			$parent,
			$enabledForAll,
			$component,
			$tooltip,
			$inverted
		);
		$this->items[$id] = $item;

		return $item;
	}

	/**
	 * @param string $id
	 * @return \YahnisElsts\AdminMenuEditor\EasyHide\HideableItem|null
	 */
	public function getItemById($id) {
		if ( isset($this->items[$id]) ) {
			return $this->items[$id];
		}
		return null;
	}

	/**
	 * @param string $id
	 * @param string $label
	 * @param Category|null $parent
	 * @param bool $invertItemState
	 * @param int $defaultSortOrder
	 * @param int $itemSortOrder
	 * @return Category
	 */
	public function getOrCreateCategory(
		$id,
		$label,
		$parent = null,
		$invertItemState = false,
		$defaultSortOrder = Category::SORT_ALPHA,
		$itemSortOrder = Category::SORT_INSERTION
	) {
		if ( isset($this->categories[$id]) ) {
			return $this->categories[$id];
		}

		$cat = new Category(
			$id,
			$label,
			$parent,
			$invertItemState,
			$defaultSortOrder,
			$itemSortOrder
		);
		$this->categories[$id] = $cat;
		return $cat;
	}

	/**
	 * @param Array<string,string> $hierarchy
	 * @return Category
	 */
	public function getOrCreateCategoryTree($hierarchy) {
		$previous = null;
		$cat = null;
		foreach ($hierarchy as $id => $label) {
			$cat = $this->getOrCreateCategory($id, $label, $previous);
			$previous = $cat;
		}
		return $cat;
	}

	/**
	 * @param string $id
	 * @return Category|null
	 */
	public function getCategory($id) {
		if ( isset($this->categories[$id]) ) {
			return $this->categories[$id];
		}
		return null;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$cats = [];
		foreach ($this->categories as $category) {
			$cats[] = $category->jsonSerialize();
		}

		$items = [];
		foreach ($this->items as $item) {
			$items[] = $item->jsonSerialize();
		}

		return [
			'categories' => $cats,
			'items'      => $items,
		];
	}
}

class Category implements JsonSerializable {
	const SORT_ALPHA = 0;
	const SORT_INSERTION = 1;

	protected $id;
	protected $label;
	protected $parent;
	protected $invertItemState;
	protected $defaultSortOrder;
	protected $itemSortOrder;
	protected $priority = null;
	protected $subtitle = null;
	/**
	 * @var null|string
	 */
	protected $tooltip = null;

	protected $tableView = [];

	public function __construct(
		$id,
		$label,
		?Category $parent = null,
		$invertItemState = false,
		$defaultSortOrder = self::SORT_ALPHA,
		$itemSortOrder = self::SORT_INSERTION
	) {
		$this->id = $id;
		$this->label = $label;
		$this->parent = $parent;
		$this->invertItemState = $invertItemState;
		$this->defaultSortOrder = $defaultSortOrder;
		$this->itemSortOrder = $itemSortOrder;
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * @param Category $rowCategory
	 * @param Category $columnCategory
	 * @return $this
	 */
	public function enableTableView($rowCategory, $columnCategory) {
		$this->tableView = [
			'rowCategory'    => $rowCategory,
			'columnCategory' => $columnCategory,
		];
		return $this;
	}

	/**
	 * @param string $subtitle
	 * @return $this
	 */
	public function addSubtitle($subtitle) {
		$this->subtitle = $subtitle;
		return $this;
	}

	/**
	 * @param int|null $priority
	 * @return \YahnisElsts\AdminMenuEditor\EasyHide\Category
	 */
	public function setSortPriority($priority) {
		$this->priority = $priority;
		return $this;
	}

	/**
	 * @param string|null $tooltip
	 * @return $this
	 */
	public function setTooltip($tooltip) {
		$this->tooltip = $tooltip;
		return $this;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$result = [
			'id'    => $this->id,
			'label' => $this->label,
		];
		if ( $this->parent !== null ) {
			$result['parent'] = $this->parent->getId();
		}
		if ( $this->defaultSortOrder !== self::SORT_ALPHA ) {
			$result['sort'] = $this->defaultSortOrder;
		}
		if ( $this->itemSortOrder !== self::SORT_INSERTION ) {
			$result['itemSort'] = $this->itemSortOrder;
		}
		if ( $this->invertItemState ) {
			$result['invertItemState'] = true;
		}
		if ( !empty($this->tableView) ) {
			$result['tableView'] = [
				'rowCategory'    => $this->tableView['rowCategory']->getId(),
				'columnCategory' => $this->tableView['columnCategory']->getId(),
			];
		}
		if ( !empty($this->subtitle) ) {
			$result['subtitle'] = $this->subtitle;
		}
		if ( !empty($this->tooltip) ) {
			$result['tooltip'] = $this->tooltip;
		}
		if ( $this->priority !== null ) {
			$result['priority'] = $this->priority;
		}
		return $result;
	}

	public function isInvertingItemState() {
		return $this->invertItemState;
	}
}

class HideableItem implements JsonSerializable {
	private $id;
	private $label;

	/**
	 * @var Category[]
	 */
	private $categories;

	/**
	 * @var HideableItem|null
	 */
	private $parent;
	private $enabled;
	private $component;
	private $tooltip;
	private $subtitle;
	private $inverted;

	/**
	 * @param $id
	 * @param $label
	 * @param Category[] $categories
	 * @param HideableItem|null $parent
	 * @param array $enabled
	 * @param $component
	 * @param string|null $tooltip
	 * @param bool|null $inverted
	 */
	public function __construct(
		$id,
		$label,
		$categories = [],
		?HideableItem $parent = null,
		$enabled = [],
		$component = null,
		$tooltip = null,
		$inverted = null
	) {
		$this->id = $id;
		$this->label = $label;
		$this->categories = $categories;
		$this->parent = $parent;
		$this->enabled = $enabled;
		$this->component = $component;
		$this->tooltip = $tooltip;
		$this->inverted = $inverted;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$result = [
			'id'         => $this->id,
			'label'      => $this->label,
			'categories' => $this->getCategoryIds(),
		];
		if ( $this->parent !== null ) {
			$result['parent'] = $this->parent->getId();
		}
		if ( !empty($this->enabled) ) {
			$result['enabled'] = $this->enabled;
		}
		if ( $this->component !== null ) {
			$result['component'] = $this->component;
		}
		if ( !empty($this->tooltip) ) {
			$result['tooltip'] = $this->tooltip;
		}
		if ( !empty($this->subtitle) ) {
			$result['subtitle'] = $this->subtitle;
		}
		if ( $this->inverted !== null ) {
			$result['inverted'] = $this->inverted;
		}
		return $result;
	}

	private function getCategoryIds() {
		$ids = [];
		foreach ($this->categories as $category) {
			$ids[] = $category->getId();
		}
		return $ids;
	}

	public function getId() {
		return $this->id;
	}

	public function addSubtitle($text) {
		$this->subtitle = $text;
	}
}

class BinaryHideableItem extends HideableItem {
	/**
	 * @var bool
	 */
	private $enabledForAll = false;

	public function __construct(
		$id,
		$label,
		$categories = [],
		?HideableItem $parent = null,
		$enabledForAll = false,
		$component = null,
		$tooltip = null,
		$inverted = null
	) {
		parent::__construct($id, $label, $categories, $parent, array(), $component, $tooltip, $inverted);
		$this->enabledForAll = $enabledForAll;
	}

	public function jsonSerialize() {
		$result = parent::jsonSerialize();
		$result['binary'] = true;
		unset($result['enabled']);
		$result['enabledForAll'] = $this->enabledForAll;
		return $result;
	}
}