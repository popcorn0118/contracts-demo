<?php

class ameGutenbergBlockManager {
	const DETECTED_BLOCK_OPTION = 'ws_ame_detected_gtb_blocks';
	const SECTION_ID = 'gutenberg-blocks';

	const SCRIPT_HANDLE = 'ame-gtb-block-detector';
	const UPDATE_BLOCKS_ACTION = 'ws_ame_update_gtb_blocks';

	const TWEAK_PREFIX = 'hide-gtb-';
	const PARENT_PREFIX = 'gtb-block-section-';

	/**
	 * @var WPMenuEditor
	 */
	private $menuEditor;
	/**
	 * @var ameLockedGlobalOption
	 */
	private $detectedBlocksOption;

	private $hiddenBlocks = array();

	public function __construct($menuEditor) {
		$this->menuEditor = $menuEditor;
		$this->detectedBlocksOption = new ameLockedGlobalOption(self::DETECTED_BLOCK_OPTION, __FILE__, null, true);

		if ( is_admin() ) {
			add_action('enqueue_block_editor_assets', array($this, 'enqueueGutenbergAssets'), 10000);
			add_action('wp_ajax_' . self::UPDATE_BLOCKS_ACTION, array($this, 'ajaxUpdateBlocks'));
		}

		add_action('admin-menu-editor-register_tweaks', array($this, 'registerBlockTweaks'), 10, 2);

		//The "allowed_block_types" filter was deprecated in WP 5.8 and a new "allowed_block_types_all"
		//filter was introduced. Note that the filters take different arguments, but we can ignore that
		//in this case because the first argument is the same and that's all we need here.
		global $wp_version;
		if ( isset($wp_version) && is_string($wp_version) && version_compare($wp_version, '5.8', '<') ) {
			$blockFilter = 'allowed_block_types'; //Deprecated since WP 5.8.0.
		} else {
			$blockFilter = 'allowed_block_types_all';
		}
		add_filter($blockFilter, array($this, 'filterAllowedBlocks'), 10000, 1);
	}

	public function enqueueGutenbergAssets() {
		//To reduce the performance impact of this feature, we only detect new Gutenberg blocks
		//for users that can access the plugin.
		if ( !$this->menuEditor->current_user_can_edit_menu() ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url('gutenberg-block-detector.js', __FILE__),
			array('jquery', 'wp-dom-ready'),
			'20240509',
			true
		);

		$detectedItems = $this->getDetectedItems();
		$scriptData = array(
			'knownBlocks'     => array_fill_keys(array_keys($detectedItems['blocks']), true),
			'knownCategories' => array_fill_keys(array_keys($detectedItems['categories']), true),
			'ajaxUrl'         => self_admin_url('admin-ajax.php'),
			'ajaxAction'      => self::UPDATE_BLOCKS_ACTION,
			'updateNonce'     => wp_create_nonce('ws_ame_update_gtb_blocks'),
		);

		//Make sure to encode associative arrays as objects (dictionaries) even when they're empty.
		if ( empty($scriptData['knownBlocks']) ) {
			$scriptData['knownBlocks'] = new stdClass();
		}
		if ( empty($scriptData['knownCategories']) ) {
			$scriptData['knownCategories'] = new stdClass();
		}

		wp_localize_script(self::SCRIPT_HANDLE, 'wsAmeGutenbergBlockData', $scriptData);
	}

	public function ajaxUpdateBlocks() {
		check_ajax_referer(self::UPDATE_BLOCKS_ACTION);

		@header('Content-Type: application/json; charset=' . get_option('blog_charset'));
		if ( !$this->menuEditor->current_user_can_edit_menu() ) {
			echo wp_json_encode(array('error' => 'Access denied'));
			exit;
		}

		//Basic validation.
		$post = $this->menuEditor->get_post_params();
		if ( !isset($post['blocks'], $post['categories']) ) {
			echo wp_json_encode(array('error' => 'The "blocks" or "categories" field is missing.'));
			exit;
		}
		$blocks = json_decode($post['blocks'], true);
		$categories = json_decode($post['categories'], true);
		if ( ($blocks === null) || ($categories === null) ) {
			echo wp_json_encode(array('error' => 'The "blocks" or "categories" field is not valid JSON.'));
			exit;
		}

		$this->saveDetectedItems($blocks, $categories);
		echo wp_json_encode(array('success' => true));
		exit;
	}

	private function saveDetectedItems($blocks, $categories) {
		$usedCategories = [];

		//Index the lists by name or slug.
		$blockIndex = array();
		foreach ($blocks as $block) {
			$name = $block['name'];
			unset($block['name']);
			$blockIndex[$name] = $block;

			//Track which categories are actually used.
			if ( !empty($block['category']) ) {
				$usedCategories[$block['category']] = true;
			}
		}
		$categoryIndex = array();
		foreach ($categories as $category) {
			$slug = $category['slug'];
			unset($category['slug']);

			//Skip categories that don't contain any blocks.
			if ( empty($usedCategories[$slug]) ) {
				continue;
			}

			$categoryIndex[$slug] = $category;
		}

		$data = array(
			'blocks'     => $blockIndex,
			'categories' => $categoryIndex,
		);

		$this->detectedBlocksOption->set($data);
	}

	private function getDetectedItems() {
		$default = array('blocks' => array(), 'categories' => array());
		$data = $this->detectedBlocksOption->get($default);
		return is_array($data) ? $data : $default;
	}

	/**
	 * @param ameTweakManager $manager
	 * @param null|array $tweakFilter
	 */
	public function registerBlockTweaks($manager, $tweakFilter = null) {
		$data = $this->getDetectedItems();
		$blocks = ameUtils::get($data, 'blocks', array());
		if ( empty($blocks) ) {
			//The user must first open the Gutenberg editor so that we can detect registered blocks.
			return;
		}

		$manager->addSection(self::SECTION_ID, 'Hide Gutenberg Blocks', 70);

		if ( $tweakFilter !== null ) {
			$filteredBlocks = array();
			foreach ($blocks as $id => $data) {
				if ( isset($tweakFilter[self::TWEAK_PREFIX . $id]) ) {
					$filteredBlocks[$id] = $data;
				}
			}
			$blocks = $filteredBlocks;
		}

		if ( $tweakFilter === null ) {
			//Create stub tweaks that represent each block category.
			$categories = ameUtils::get($data, 'categories', array());
			foreach ($categories as $catId => $category) {
				//Skip the special "reusable" category because it will always be empty.
				//Individual reusable blocks are not detected by our script and the "core/block"
				//block that would appear in this category cannot be hidden for compat. reasons.
				if ( $catId === 'reusable' ) {
					continue;
				}

				$parentTweak = new ameDelegatedTweak(
					self::PARENT_PREFIX . $catId,
					ameUtils::get($category, 'title', $catId),
					'__return_false' //This tweak is just a presentation tool. It doesn't do anything.
				);
				$parentTweak->setSectionId(self::SECTION_ID);
				$manager->addTweak($parentTweak);
			}
		}

		$theCallback = array($this, 'flagBlockAsHidden');
		foreach ($blocks as $id => $block) {
			//Skip blocks that don't appear in the block inserter.
			if ( isset($block['supportsInserter']) && !$block['supportsInserter'] ) {
				continue;
			}

			$tweak = new ameDelegatedTweak(
				self::TWEAK_PREFIX . $id,
				ameUtils::get($block, 'title', $id),
				$theCallback,
				array($id)
			);
			$tweak->setSectionId(self::SECTION_ID);
			if ( !empty($block['category']) ) {
				$tweak->setParentId(self::PARENT_PREFIX . $block['category']);
			}
			$manager->addTweak($tweak);
		}
	}

	/** @noinspection PhpUnused Actually used as a tweak callback. */
	public function flagBlockAsHidden($blockId) {
		$this->hiddenBlocks[] = $blockId;
	}

	public function filterAllowedBlocks($allowedBlocks) {
		if ( empty($this->hiddenBlocks) ) {
			return $allowedBlocks;
		}

		if ( $allowedBlocks === true ) {
			//All blocks are allowed by default. We need to turn our blacklist into a whitelist.
			//Unfortunately, we can't get all available blocks via PHP, so we rely on the cached
			//list of registered blocks that was supplied by our JS script.
			$registeredBlocks = array_keys(ameUtils::get($this->getDetectedItems(), 'blocks', array()));
			//Some blocks support core editor functionality and should be included even if not detected.
			$registeredBlocks = array_unique(array_merge($registeredBlocks, [
				'core/block', //Required for reusable blocks (a.k.a. synced patterns) to work.
				'core/pattern', //Not sure what this one does, but sounds important.
			]));

			$result = array_diff($registeredBlocks, $this->hiddenBlocks);

			//Reindex the array. array_diff() can create "holes" in the array, which means that
			//json_encode() will encode it as an object with numeric keys and not a real array.
			//As of WP 5.4-alpha, Gutenberg requires a plain array.
			return array_values($result);
		} else if ( is_array($allowedBlocks) ) {
			//Another plugin has already filtered the list of allowed block types.
			//Let's remove any blocks that are hidden by AME settings.
			return array_values(array_diff($allowedBlocks, $this->hiddenBlocks));
		}
		//Either all blocks were hidden by another plugin, or the data type of $allowedBlocks
		//is not recognized.
		return $allowedBlocks;
	}
}