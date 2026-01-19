<?php

use YahnisElsts\AdminMenuEditor\Configurable\ActorFeature;

abstract class ameBaseTweak extends ActorFeature {
	protected $parentId;
	protected $sectionId;

	/**
	 * @var string[]|null List of admin screen IDs that the tweak applies to.
	 */
	protected $screens = null;

	/**
	 * @var string
	 */
	protected $description = '';

	/**
	 * @var string|null
	 */
	protected $hideableLabel = null;

	/**
	 * @var string|null
	 */
	protected $hideableCategory = null;

	/**
	 * @param array|null $settings User settings for this tweak.
	 * @return mixed
	 */
	abstract public function apply($settings = null);

	public function getId() {
		return $this->id;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getParentId() {
		return $this->parentId;
	}

	public function setParentId($id) {
		$this->parentId = $id;
		return $this;
	}

	public function setSectionId($id) {
		$this->sectionId = $id;
		return $this;
	}

	public function getSectionId() {
		return $this->sectionId;
	}

	public function hasScreenFilter() {
		return ($this->screens !== null);
	}

	public function isEnabledForCurrentScreen() {
		if ( !$this->hasScreenFilter() ) {
			return true;
		}
		if ( !function_exists('get_current_screen') ) {
			return false;
		}
		$screen = get_current_screen();
		if ( isset($screen, $screen->id) ) {
			return $this->isEnabledForScreen($screen->id);
		}
		return false;
	}

	public function isEnabledForScreen($screenId) {
		if ( $this->screens === null ) {
			return true;
		}
		return in_array($screenId, $this->screens);
	}

	public function setScreens($screens) {
		$this->screens = $screens;
	}

	/**
	 * @param string $description
	 * @return void
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return bool
	 */
	public function wantsToRunEarly() {
		return false;
	}

	public function isIndependentlyHideable() {
		return ($this->hideableCategory !== null);
	}

	public function getHideableCategoryId() {
		return $this->hideableCategory;
	}

	public function setHideableCategoryId($categoryId) {
		$this->hideableCategory = $categoryId;
	}

	public function getHideableLabel() {
		if ( $this->hideableLabel !== null ) {
			return $this->hideableLabel;
		}
		return $this->getLabel();
	}

	public function setHideableLabel($text) {
		$this->hideableLabel = $text;
	}

	public function toArray() {
		$props = array(
			'parentId'  => $this->getParentId(),
			'sectionId' => $this->getSectionId(),
		);

		if ( !empty($this->description) ) {
			$props['description'] = $this->description;
		}

		return array_merge(parent::toArray(), $props);
	}

	public function supportsUserInput() {
		return $this->hasAnySettings();
	}

	//todo: getEditableProperties(). Or maybe we don't need it at all? Just merge the settings.
}