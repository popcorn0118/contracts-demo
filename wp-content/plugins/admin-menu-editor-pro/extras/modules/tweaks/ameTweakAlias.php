<?php

class ameTweakAlias {
	/**
	 * @var string
	 */
	protected $tweakId;

	/**
	 * @var string|null
	 */
	protected $parentId = null;
	/**
	 * @var string|null
	 */
	protected $sectionId = null;

	/**
	 * @var string
	 */
	protected $label;

	public function __construct($tweakId, $label) {
		$this->tweakId = $tweakId;
		$this->label = $label;
	}

	public function setParentId($parentId) {
		$this->parentId = $parentId;
		return $this;
	}

	public function setSectionId($sectionId) {
		$this->sectionId = $sectionId;
		return $this;
	}

	public function toArray() {
		return [
			'tweakId'   => $this->tweakId,
			'label'     => $this->label,
			'parentId'  => $this->parentId,
			'sectionId' => $this->sectionId,
		];
	}
}