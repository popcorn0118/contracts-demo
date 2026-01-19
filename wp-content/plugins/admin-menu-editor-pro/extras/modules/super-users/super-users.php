<?php

class ameSuperUsers extends ameModule {
	protected $tabSlug = 'hidden-users';
	protected $tabTitle = 'Users';

	private $isInsideCountFilter = false;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);

		add_filter('users_list_table_query_args', array($this, 'filterUserQueryArgs'));
		add_filter('map_meta_cap', array($this, 'restrictUserEditing'), 10, 4);
		add_filter('pre_count_users', array($this, 'filterUserCounts'), 10, 3);

		/*
		 * Why not use the "pre_get_users" filter to hide users?
		 *
		 * This filter is called in WP_User_Query and, by extension, get_users(). The problem is that
		 * WordPress uses get_users() in at least one place where hiding specific users could cause
		 * problems. In edit-form-advanced.php, WordPress calls get_users() to determine if it should
		 * check if another user is already editing the current post. It only checks locks if get_users()
		 * returns more than one user. This means that removing users from get_users() results could
		 * make WordPress ignore post locks.
		 */

		add_action('admin_menu_editor-header', array($this, 'handleFormSubmission'), 10, 2);
	}

	public function filterUserQueryArgs($args) {
		//Exclude superusers if the current user is not a superuser.
		$superUsers = $this->getSuperUserIDs();
		if ( empty($superUsers) ) {
			return $args;
		}

		if ( !$this->isCurrentUserSuper() ) {
			$args['exclude'] = array_merge(
				isset($args['exclude']) ? $args['exclude'] : array(),
				$superUsers
			);

			//Exclude hidden users even if specifically included. This can happen
			//when looking at the "None" view on the "Users" page (this view shows
			//users that have no role on the current site).
			if ( isset($args['include']) && !empty($args['include']) ) {
				$args['include'] = array_diff($args['include'], $superUsers);
				if ( empty($args['include']) ) {
					unset($args['include']);
				}
			}
		}

		return $args;
	}

	/**
	 * Prevent normal users from editing superusers.
	 *
	 * @param string[] $requiredCaps List of primitive capabilities (output).
	 * @param string $capability     The meta capability (input).
	 * @param int $thisUserId        The user that's trying to do something.
	 * @param array $args
	 * @return string[]
	 */
	public function restrictUserEditing($requiredCaps, $capability, $thisUserId, $args) {
		static $editUserCaps = array('edit_user', 'delete_user', 'promote_user', 'remove_user');
		if ( !in_array($capability, $editUserCaps) || !isset($args[0]) ) {
			return $requiredCaps;
		}

		/** @var int The user that might be edited or deleted. */
		$targetUserId = intval($args[0]);
		$thisUserId = intval($thisUserId);

		if ( $this->isSuperUser($targetUserId) && !$this->isSuperUser($thisUserId) ) {
			return array_merge($requiredCaps, array('do_not_allow'));
		}

		return $requiredCaps;
	}

	/**
	 * Filter the user counts shown in the list of roles at the top of the "Users" page.
	 *
	 * @param $result
	 * @param $strategy
	 * @param $siteId
	 * @return array|null
	 */
	public function filterUserCounts($result = null, $strategy = 'time', $siteId = null) {
		//We're going to call count_users() which will trigger the 'pre_count_users' filter
		//again, so we need to avoid infinite recursion.
		if ( $this->isInsideCountFilter ) {
			return $result;
		}

		//Perform this filtering only on the "Users" page.
		if ( !isset($GLOBALS['parent_file']) || ($GLOBALS['parent_file'] !== 'users.php') ) {
			return $result;
		}

		if ( $this->isCurrentUserSuper() ) {
			//This user can see other hidden users.
			return $result;
		}

		$superUsers = $this->getSuperUsers($siteId);
		//Note the $siteId. We want the roles that each user has on the specified site.
		//This should normally be the current site, but it doesn't have to be.

		if ( empty($superUsers) ) {
			//There are no users that need to be hidden.
			return $result;
		}

		/** @noinspection PhpFieldImmediatelyRewrittenInspection Recursive filters! */
		$this->isInsideCountFilter = true;
		$result = count_users($strategy, $siteId);

		//Adjust the total number of users.
		$result['total_users'] -= count($superUsers);

		//For each hidden user, subtract one from each of the roles that the user has.
		foreach ($superUsers as $user) {
			if ( !empty($user->roles) && is_array($user->roles) ) {
				foreach ($user->roles as $roleId) {
					if ( isset($result['avail_roles'][$roleId]) ) {
						$result['avail_roles'][$roleId]--;
						if ( $result['avail_roles'][$roleId] <= 0 ) {
							unset($result['avail_roles'][$roleId]);
						}
					}
				}
			} else if ( isset($result['avail_roles']['none']) ) {
				$result['avail_roles']['none']--;
			}
		}

		$this->isInsideCountFilter = false;
		return $result;
	}

	/**
	 * @return int[]
	 */
	private function getSuperUserIDs() {
		$result = $this->menuEditor->get_plugin_option('super_users');
		if ( $result === null ) {
			return array();
		}
		return $result;
	}

	/**
	 * @param int|null $siteId
	 * @return WP_User[]
	 */
	private function getSuperUsers($siteId = null) {
		$ids = $this->getSuperUserIDs();
		if ( empty($ids) ) {
			return array();
		}

		if ( !is_numeric($siteId) ) {
			$siteId = get_current_blog_id();
		}

		//Caution: If you pass an empty array as "include", get_users() will return *all* users from the current site.
		return get_users( array(
			'include' => $ids,
			'blog_id' => $siteId,
		) );
	}

	/**
	 * Is the current user one of the superusers?
	 *
	 * @return bool
	 */
	private function isCurrentUserSuper() {
		$user = wp_get_current_user();
		return $user && $this->isSuperUser($user->ID);
	}

	private function isSuperUser($userId) {
		return in_array($userId, $this->getSuperUserIDs());
	}

	public function enqueueTabScripts() {
		parent::enqueueTabScripts();

		wp_enqueue_auto_versioned_script(
			'ame-super-users',
			plugins_url('super-users.js', __FILE__),
			array('ame-knockout', 'jquery', 'ame-visible-users', 'ame-actor-manager', 'ame-jquery-cookie')
		);

		//Pass users to JS.
		$users = array();
		foreach($this->getSuperUsers() as $user) {
			$properties = $this->menuEditor->user_to_property_map($user);
			$properties['avatar_html'] = get_avatar($user->ID, 32);
			$users[$user->user_login] = $properties;
		}

		$currentUser = wp_get_current_user();
		wp_localize_script(
			'ame-super-users',
			'wsAmeSuperUserSettings',
			array(
				'superUsers' => $users,
				'userEditUrl' => admin_url('user-edit.php'),
				'currentUserLogin' => $currentUser->get('user_login'),
			)
		);
	}

	public function enqueueTabStyles() {
		parent::enqueueTabStyles();

		wp_enqueue_auto_versioned_style(
			'ame-super-users-css',
			plugins_url('super-users.css', __FILE__)
		);
	}

	public function handleFormSubmission($action, $post = array()) {
		//Note: We don't need to check user permissions here because plugin core already did.
		if ( $action === 'ame_save_super_users' ) {
			check_admin_referer($action);

			$userIDs = array_map('intval', explode(',', $post['settings'], 100));
			$userIDs = array_unique(array_filter($userIDs));

			//Save settings.
			$this->menuEditor->set_plugin_option('super_users', $userIDs);

			wp_redirect($this->getTabUrl(array('message' => 1)));
			exit;
		}
	}
}