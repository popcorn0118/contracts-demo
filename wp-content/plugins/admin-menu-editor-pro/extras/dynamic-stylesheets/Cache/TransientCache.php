<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache;

abstract class TransientCache {
	protected function parseRetrievedValue($transientValue, $default) {
		if ( $transientValue === false ) {
			return $default;
		} else {
			return $transientValue;
		}
	}

	protected function convertTtlForWp($ttl) {
		if ( $ttl === null ) {
			$ttl = 0;
		}
		return $ttl;
	}
}