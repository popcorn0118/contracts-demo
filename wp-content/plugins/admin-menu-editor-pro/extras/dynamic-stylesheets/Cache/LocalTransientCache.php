<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache;

/**
 * A basic cache that stores data in WordPress transients.
 */
class LocalTransientCache extends TransientCache implements StyleCacheInterface {
	/**
	 * @inheritdoc
	 */
	public function get($key, $default = null) {
		$data = get_transient($key);
		return $this->parseRetrievedValue($data, $default);
	}

	/**
	 * @inheritdoc
	 */
	public function set($key, $value, $ttl = null) {
		return set_transient($key, $value, $this->convertTtlForWp($ttl));
	}

	/**
	 * @inheritdoc
	 */
	public function delete($key) {
		return delete_transient($key);
	}
}