<?php

class ameMbeScreenSettings {
	const META_BOX_KEY = 'metaBoxes:';
	const CPT_FEATURE_KEY = 'postTypeFeatures:';

	const CPT_NAME_KEY = 'postType:';
	const TAXONOMY_NAME_KEY = 'taxonomy:';

	protected $screenId;
	protected $metaBoxes;
	protected $postTypeFeatures;

	protected $postType = null;
	protected $taxonomy = null;

	/**
	 * @param string $screenId
	 * @param \WP_Screen|null $screen
	 */
	public function __construct($screenId, $screen = null) {
		$this->screenId = $screenId;

		if ( $screen !== null ) {
			if ( !empty($screen->post_type) ) {
				$this->postType = $screen->post_type;
			}
			if ( !empty($screen->taxonomy) ) {
				$this->taxonomy = $screen->taxonomy;
			}
		}
	}

	/**
	 * @return ameMetaBoxCollection
	 */
	public function getMetaBoxes() {
		if ( !isset($this->metaBoxes) ) {
			$this->metaBoxes = new ameMetaBoxCollection($this->screenId);
		}
		return $this->metaBoxes;
	}

	/**
	 * @return amePostTypeFeatureCollection
	 */
	public function getPostTypeFeatures() {
		if ( !isset($this->postTypeFeatures) ) {
			$this->postTypeFeatures = new amePostTypeFeatureCollection();
		}
		return $this->postTypeFeatures;
	}

	/**
	 * @param \WP_Screen $screen
	 * @return boolean
	 */
	public function mergeScreenInfo($screen) {
		if ( !class_exists('WP_Screen', false) || !($screen instanceof WP_Screen) ) {
			return false;
		}
		$modified = false;

		$currentPostType = !empty($screen->post_type) ? $screen->post_type : null;
		if ( $currentPostType !== $this->postType ) {
			$this->postType = $currentPostType;
			$modified = true;
		}
		$currentTaxonomy = !empty($screen->taxonomy) ? $screen->taxonomy : null;
		if ( $currentTaxonomy !== $this->taxonomy ) {
			$this->taxonomy = $currentTaxonomy;
			$modified = true;
		}

		return $modified;
	}

	/**
	 * Check if the post type or taxonomy associated with this screen is missing.
	 *
	 * Note that not every screen has an associated post type or taxonomy, so getting
	 * "false" from this method does not mean that the screen has a valid post type.
	 *
	 * @return bool
	 */
	public function isContentTypeMissing() {
		if ( !empty($this->postType) && function_exists('post_type_exists') ) {
			if ( !post_type_exists($this->postType) ) {
				return true;
			}
		}
		if ( !empty($this->taxonomy) && function_exists('taxonomy_exists') ) {
			if ( !taxonomy_exists($this->taxonomy) ) {
				return true;
			}
		}
		return false;
	}

	public function toArray() {
		return array(
			self::META_BOX_KEY      => $this->getMetaBoxes()->toArray(),
			self::CPT_FEATURE_KEY   => $this->getPostTypeFeatures()->toArray(),
			self::CPT_NAME_KEY      => $this->postType,
			self::TAXONOMY_NAME_KEY => $this->taxonomy,
			'isContentTypeMissing:' => $this->isContentTypeMissing(),
		);
	}

	public static function fromArray($data, $screenId) {
		$instance = new self($screenId);
		if ( isset($data[self::META_BOX_KEY]) ) {
			$instance->metaBoxes = ameMetaBoxCollection::fromArray($data[self::META_BOX_KEY], $screenId);
		} else {
			$instance->metaBoxes = ameMetaBoxCollection::fromArray($data, $screenId);
		}
		if ( isset($data[self::CPT_FEATURE_KEY]) ) {
			$instance->postTypeFeatures = amePostTypeFeatureCollection::fromArray($data[self::CPT_FEATURE_KEY]);
		}

		if ( isset($data[self::CPT_NAME_KEY]) ) {
			$instance->postType = $data[self::CPT_NAME_KEY];
		}
		if ( isset($data[self::TAXONOMY_NAME_KEY]) ) {
			$instance->taxonomy = $data[self::TAXONOMY_NAME_KEY];
		}

		return $instance;
	}
}