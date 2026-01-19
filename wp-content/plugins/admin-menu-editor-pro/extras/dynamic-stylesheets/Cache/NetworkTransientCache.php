<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache;

class NetworkTransientCache extends TransientCache implements StyleCacheInterface {
	/**
	 * @inheritdoc
	 */
	public function get($key, $default = null) {
		$data = get_site_transient($key);
		return $this->parseRetrievedValue($data, $default);
	}

	/**
	 * @inheritdoc
	 */
	public function set($key, $value, $ttl = null) {
		return set_site_transient($key, $value, $this->convertTtlForWp($ttl));
	}

	/**
	 * @inheritdoc
	 */
	public function delete($key) {
		return delete_site_transient($key);
	}
}