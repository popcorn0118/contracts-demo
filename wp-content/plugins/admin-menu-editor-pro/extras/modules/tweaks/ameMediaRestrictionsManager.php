<?php

class ameMediaRestrictionsManager {
	const RELEVANT_META_CAPS = [
		'edit_post'   => true,
		'delete_post' => true,
	];

	const SECTION_ID = 'media-restrictions';

	private $enabledRestrictionsByUser = [];

	private $metaCapFilterAdded = false;

	public function __construct() {
		add_action('admin-menu-editor-register_tweaks', [$this, 'registerTweaks']);
	}

	public function mapMetaCap($caps, $cap, $userId, $args = []) {
		if (
			//Do we care about this capability?
			!array_key_exists($cap, self::RELEVANT_META_CAPS)
			//Is the user ID provided? (Should always be.)
			|| !$userId
			//Any restrictions for this user?
			|| empty($this->enabledRestrictionsByUser[$userId])
			//Is this specific capability restricted?
			|| empty($this->enabledRestrictionsByUser[$userId][$cap])
		) {
			return $caps;
		}

		//Is the post an attachment (media)?
		if ( empty($args[0]) ) {
			return $caps;
		}
		$post = get_post($args[0]);
		if ( !$post || !is_object($post) || ($post->post_type !== 'attachment') ) {
			return $caps;
		}

		//Prevent the user from editing/deleting media uploaded by other users.
		//Note: As of this writing, the `post_author` doc-comment says that the value
		//is a numeric string, so we cast both values to integers to be safe.
		if ( (int)$post->post_author !== (int)$userId ) {
			$caps = ['do_not_allow'];
		}
		return $caps;
	}

	/**
	 * @param \ameTweakManager $tweakManager
	 * @return void
	 */
	public function registerTweaks($tweakManager) {
		$tweakManager->addSection(self::SECTION_ID, 'Media Library Restrictions', 50);

		//Create a parent tweak for convenience. It makes it easier to enable/disable all
		//restrictions at once.
		$parentTweakId = 'mr-all-container';
		$tweak = new ameDelegatedTweak(
			$parentTweakId,
			'Restrict access to media uploaded by other users',
			'__return_false'
		);
		$tweak->setSectionId(self::SECTION_ID);
		$tweakManager->addTweak($tweak);

		$theCallback = [$this, 'enableRestriction'];
		$options = [
			'delete_post' => 'Prevent deletion',
			'edit_post'   => 'Prevent editing',
		];
		foreach ($options as $cap => $label) {
			$tweak = new ameDelegatedTweak('mr-others-' . $cap, $label, $theCallback, [$cap]);
			$tweak->setSectionId(self::SECTION_ID)->setParentId($parentTweakId);
			$tweakManager->addTweak($tweak);
		}

		if ( class_exists(ameOtherUserPostHider::class) ) {
			//Alias for the "Media" tweak in the "Hide Other Users' Posts" module.
			$hideMediaAlias = new ameTweakAlias(
				ameOtherUserPostHider::TWEAK_ID_PREFIX . 'attachment',
				'Hide other users\' uploads'
			);
			$hideMediaAlias->setSectionId(self::SECTION_ID)->setParentId($parentTweakId);
			$tweakManager->addAlias($hideMediaAlias);
		}
	}

	public function enableRestriction($cap) {
		$userId = get_current_user_id();
		if ( !empty($userId) ) {
			if ( !isset($this->enabledRestrictionsByUser) ) {
				$this->enabledRestrictionsByUser = [];
			}
			$this->enabledRestrictionsByUser[$userId][$cap] = true;

			//Optimization: Only add the filter when there are restrictions to apply.
			if ( !$this->metaCapFilterAdded ) {
				$this->metaCapFilterAdded = true;
				add_filter('map_meta_cap', [$this, 'mapMetaCap'], 10, 4);
			}
		}
	}
}