<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache;

class DummyCache implements StyleCacheInterface {
	public function get($key, $default = null) {
		return $default;
	}

	public function set($key, $value, $ttl = null) {
		return true;
	}

	public function delete($key) {
		return true;
	}
}