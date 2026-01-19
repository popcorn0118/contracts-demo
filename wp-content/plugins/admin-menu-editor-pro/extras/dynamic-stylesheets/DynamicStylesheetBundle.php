<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets;

class DynamicStylesheetBundle extends Stylesheet {
	const CACHE_TTL_FALLBACK = 12 * 3600;

	/**
	 * @var \YahnisElsts\AdminMenuEditor\DynamicStylesheets\Stylesheet[]
	 */
	protected $stylesheets = [];

	public function __construct($handle, $cache = null, $cacheKeySuffix = '', $additionalQueryParameters = []) {
		parent::__construct(
			$handle,
			function () {
				return $this->generateCombinedCss();
			},
			null,
			$cache,
			$cacheKeySuffix,
			$additionalQueryParameters
		);

		if ( $cache !== null ) {
			$this->cache = $cache;
		}
	}

	public function addStylesheet(Stylesheet $stylesheet) {
		$this->stylesheets[] = $stylesheet;
	}

	protected function generateCombinedCss() {
		$parts = [];
		foreach ($this->stylesheets as $stylesheet) {
			$content = $stylesheet->generateCss();
			if ( !is_string($content) ) {
				continue;
			}
			$content = trim($content);
			if ( !empty($content) ) {
				$parts[] = $content;
			}
		}
		return implode("\n", $parts);
	}

	protected function getLastModifiedTimestamp() {
		//Use the most recent timestamp of all stylesheets.
		$lastModifiedTimestamp = 0;
		foreach ($this->stylesheets as $stylesheet) {
			$timestamp = $stylesheet->getLastModifiedTimestamp();
			if ( ($timestamp !== null) && ($timestamp > $lastModifiedTimestamp) ) {
				$lastModifiedTimestamp = $timestamp;
			}
		}
		return $lastModifiedTimestamp;
	}

	protected function getCacheTtl() {
		//Use the lowest non-zero TTL.
		$ttl = null;
		foreach ($this->stylesheets as $stylesheet) {
			$stylesheetTtl = $stylesheet->getCacheTtl();
			if ( ($stylesheetTtl === null) || ($stylesheetTtl <= 0) ) {
				continue;
			}
			if ( ($ttl === null) || ($stylesheetTtl < $ttl) ) {
				$ttl = $stylesheetTtl;
			}
		}

		if ( ($ttl !== null) && ($ttl > 0) ) {
			return $ttl;
		} else {
			return self::CACHE_TTL_FALLBACK;
		}
	}
}