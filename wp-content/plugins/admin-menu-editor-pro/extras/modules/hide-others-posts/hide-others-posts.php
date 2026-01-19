<?php

class ameOtherUserPostHider extends ameModule {
	const TWEAK_SECTION_ID = 'hide-others-posts';
	const TWEAK_ID_PREFIX = 'ame_hide_others_posts-';

	private $postFilterAdded = false;
	private $postFilterEnabledForPostType = [];

	private $postCountHooksAdded = false;
	private $countFilterEnabled = [];

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		add_action('admin-menu-editor-register_tweaks', [$this, 'registerTweaks']);
		add_filter('admin_menu_editor-hideable_tweak_sections', [$this, 'addSectionToHideableSections']);

		add_filter('admin_menu_editor-rex_related_widget', [$this, 'addRelatedWidgetContent']);
	}

	/**
	 * @param \WP_Query $query
	 * @return void
	 */
	public function filterPostQuery($query) {
		$relevantPostTypes = $this->getRelevantPostTypes($query);
		if ( empty($relevantPostTypes) ) {
			return;
		}
		//Note: The tweak manager will only call our "enable..." callback if it determines that
		//the tweak applies to the current user, so we don't need to check that here.

		$currentUser = wp_get_current_user();
		if ( empty($currentUser) || empty($currentUser->ID) ) {
			return;
		}

		//getRelevantPostTypes() should have already checked is_main_query().
		//phpcs:ignore WordPressVIPMinimum.Hooks.PreGetPosts.PreGetPosts
		$query->set('author', $currentUser->ID);

		//Filter the number of posts by status to exclude posts that the user can't see.
		foreach ($relevantPostTypes as $postType) {
			$this->countFilterEnabled[$postType] = true;
		}

		if ( !$this->postCountHooksAdded ) {
			add_filter('wp_count_posts', [$this, 'filterPostCounts'], 10, 3);
			//Clear the count cache when post statuses change.
			add_action('transition_post_status', [$this, 'maybeClearPostCountCache'], 10, 3);

			$this->postCountHooksAdded = true;
		}
	}

	/**
	 * Filter for wp_count_posts() to restrict the counts to the current user's posts.
	 *
	 * This is largely a reimplementation of wp_count_posts() with the addition of the post_author
	 * restriction. Results are cached separately from the original function.
	 *
	 * @param \stdClass $counts
	 * @param string $type
	 * @param string $perm
	 * @return \stdClass
	 */
	public function filterPostCounts($counts, $type, $perm = '') {
		//Should we filter this post type?
		if ( empty($this->countFilterEnabled[$type]) || !is_admin() ) {
			return $counts;
		}
		global $wpdb;

		$cacheKey = $this->getPostCountCacheKey($type, $perm);
		$cache = wp_cache_get($cacheKey, 'counts');
		if ( $cache !== false ) {
			return $cache;
		}

		/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
		$params = [$type];

		if ( ($perm === 'readable') && is_user_logged_in() ) {
			$postTypeObject = get_post_type_object($type);
			if ( !current_user_can($postTypeObject->cap->read_private_posts) ) {
				$query .= " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' )) ";
				$params[] = get_current_user_id();
			}
		}

		//Important part: Include only the user's own posts.
		$query .= ' AND (post_author = %d) ';
		$params[] = get_current_user_id();

		$query .= ' GROUP BY post_status';

		$results = (array)$wpdb->get_results(
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- It is actually prepared, but PHPCS doesn't recognize it.
			$wpdb->prepare($query, $params),
			ARRAY_A
		);

		$counts = array_fill_keys(get_post_stati(), 0);
		foreach ($results as $row) {
			$counts[$row['post_status']] = $row['num_posts'];
		}
		$counts = (object)$counts;

		wp_cache_set($cacheKey, $counts, 'counts');
		return $counts;
	}

	private function getPostCountCacheKey($postType, $perm = 'all') {
		return 'ame_own_posts-' . $postType . '-' . $perm;
	}

	/**
	 * @param string $newStatus
	 * @param string $oldStatus
	 * @param \WP_Post $post
	 */
	public function maybeClearPostCountCache($newStatus, $oldStatus, $post) {
		if ( ($newStatus !== $oldStatus) && isset($post->post_type) ) {
			wp_cache_delete($this->getPostCountCacheKey($post->post_type), 'counts');
			wp_cache_delete($this->getPostCountCacheKey($post->post_type, 'readable'), 'counts');
		}
	}

	/**
	 * @param \ameTweakManager $manager
	 */
	public function registerTweaks($manager) {
		$section = $manager->addSection(self::TWEAK_SECTION_ID, 'Hide Other Users\' Posts', 60);

		$notes = [
			'Applies to post listings in the admin dashboard, like "Posts -> All Posts".',
			'Does not not prevent users from editing or deleting posts if they still have'
			. ' the required role capabilities. Does not prevent users from viewing published'
			. ' posts.',
		];
		$section->setDescription(implode("\n\n", $notes));

		$tweakCallback = [$this, 'enableForPostTypeThisTime'];

		//Register a tweak for each post type.
		$postTypes = get_post_types(['show_ui' => true], 'objects');
		foreach ($postTypes as $postType) {
			if ( empty($postType->show_in_menu) ) {
				continue;
			}

			$postTypeId = $postType->name;

			$tweak = new ameDelegatedTweak(
				self::TWEAK_ID_PREFIX . $postTypeId,
				$postType->label,
				$tweakCallback,
				[$postTypeId]
			);
			$tweak->setSectionId(self::TWEAK_SECTION_ID);

			$manager->addTweak($tweak);
		}
		//exit;
	}

	public function enableForPostTypeThisTime($postType) {
		$this->postFilterEnabledForPostType[$postType] = true;

		if ( !$this->postFilterAdded ) {
			add_action('pre_get_posts', [$this, 'filterPostQuery']);
			$this->postFilterAdded = true;
		}
	}

	private function isSupportedAdminPage() {
		global $pagenow;
		return in_array($pagenow, ['edit.php', 'upload.php'], true);
	}

	/**
	 * @param \WP_Query $query
	 * @return string[]
	 */
	private function getRelevantPostTypes($query) {
		if (
			//Sanity check: Is it even a real query?
			!($query instanceof WP_Query)
			//We only care about queries made on admin pages.
			|| !$query->is_admin
		) {
			return [];
		}

		//The post type can be a string or an array of strings.
		$postTypeQueryVar = $query->get('post_type');
		if ( empty($postTypeQueryVar) ) {
			return [];
		}
		$postTypes = is_array($postTypeQueryVar) ? $postTypeQueryVar : [$postTypeQueryVar];

		//Is the restriction enabled for all of these post types?
		//(Usually, there will be only one, but let's support multiple just in case.)
		foreach ($postTypes as $postType) {
			if (
				empty($postType) || !is_scalar($postType) //Sanity check.
				|| empty($this->postFilterEnabledForPostType[$postType])
			) {
				return [];
			}
		}

		//Special case: Retrieving media in grid mode. WordPress uses AJAX to load the media.
		if (
			($postTypeQueryVar === 'attachment')
			&& wp_doing_ajax()
			//phpcs:disable WordPress.Security.NonceVerification.Recommended -- Not taking actions, just filtering.
			&& isset($_REQUEST['action'])
			&& ($_REQUEST['action'] === 'query-attachments')
			//phpcs:enable WordPress.Security.NonceVerification.Recommended
		) {
			return $postTypes;
		}

		//Normal case: An admin page with a list of posts.
		if ( $query->is_main_query() && $this->isSupportedAdminPage() ) {
			return $postTypes;
		}
		return [];
	}

	public function addSectionToHideableSections($sections) {
		//Alt: 'Post Listings'
		$sections[self::TWEAK_SECTION_ID] = 'Posts by Other Users';
		return $sections;
	}

	public function addRelatedWidgetContent($content = '') {
		$tweaksTabUrl = $this->menuEditor->get_tab_url('tweaks');
		if ( empty($tweaksTabUrl) ) {
			return $content;
		}

		$content .= sprintf(
			'<a href="%s#twm-section_hide-others-posts" class="">Hide other users\' posts</a>',
			esc_url($tweaksTabUrl)
		);

		return $content;
	}
}