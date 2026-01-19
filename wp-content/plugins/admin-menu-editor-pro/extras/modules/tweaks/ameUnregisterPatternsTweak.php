<?php

/**
 * A tweak that unregisters all[^1] block patterns.
 *
 * [^1]: The current implementation doesn't unregister user-created reusable blocks, which also
 * show up as patterns in the block editor.
 */
class ameUnregisterPatternsTweak extends ameBaseTweak {
	public function apply($settings = null) {
		if ( did_action('init') ) {
			$this->maybeRemovePatterns();
		} else {
			add_action('init', [$this, 'maybeRemovePatterns'], 999);
		}
	}

	public function maybeRemovePatterns() {
		//Let's only unregister patterns in the admin area and the block editor. Removing them
		//from the frontend can break the site (e.g. parts that use patterns would disappear).
		if ( is_admin() ) {
			$this->removePatterns();
		} else {
			//The Gutenberg editor loads patterns via the REST API, so intercept those requests
			//and unregister the patterns there.
			add_filter('rest_request_before_callbacks', [$this, 'removePatternsFromRestApi'], 1);
		}
	}

	public function removePatternsFromRestApi($response = null) {
		if ( defined('REST_REQUEST') && REST_REQUEST ) {
			$this->removePatterns();
		}
		return $response;
	}

	public function removePatterns() {
		if ( !class_exists(WP_Block_Patterns_Registry::class) ) {
			return;
		}

		$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		foreach ($patterns as $pattern) {
			if ( is_array($pattern) && isset($pattern['name']) ) {
				$visibleInInserter = ameUtils::get($pattern, 'inserter', true);
				if ( $visibleInInserter ) {
					unregister_block_pattern($pattern['name']);
				}
			}
		}
	}
}