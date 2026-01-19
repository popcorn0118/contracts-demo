<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets;

class MenuScopedStylesheetHelper {
	const SUPPORTED_CONFIG_IDS = [
		'site'          => true,
		'global'        => true,
		'network-admin' => true,
	];

	/**
	 * Not a secret, just a random string to make the hash more unique.
	 * Common words like "site" and "global" might be hashed elsewhere,
	 * making it too easy to guess the hash of a given config ID.
	 */
	const CONFIG_ID_HASH_INPUT_SUFFIX = '|ame-cid-hash_6578711';

	/**
	 * @var \WPMenuEditor
	 */
	private $menuEditor;
	private $pendingBundles = [];
	private $pendingStylesheets = [];

	private $bundles = [];

	private $initHookDone = false;
	private $enqueueHookDone = false;
	private $knownConfigId = null;

	public function __construct(\WPMenuEditor $menuEditor) {
		$this->menuEditor = $menuEditor;

		add_action('init', [$this, 'onInit'], 20);
		add_action('admin_enqueue_scripts', [$this, 'onEnqueueAdminStylesheets'], 9);
	}

	public function onInit() {
		$this->initHookDone = true;

		if ( $this->doingAjax() ) {
			$configId = $this->getConfigIdFromAjaxRequest();
			if ( empty($configId) ) {
				return;
			}
			$this->knownConfigId = $configId;
			$this->createPendingInstances($configId);
		}
	}

	private function doingAjax() {
		return (defined('DOING_AJAX') && constant('DOING_AJAX'));
	}

	public function onEnqueueAdminStylesheets() {
		$this->enqueueHookDone = true;

		$configId = $this->menuEditor->get_loaded_menu_config_id();
		if ( !is_string($configId) || !array_key_exists($configId, self::SUPPORTED_CONFIG_IDS) ) {
			return;
		}

		$this->knownConfigId = $configId;
		$this->createPendingInstances($configId);
	}

	protected function createPendingInstances($configId) {
		if ( empty($this->pendingBundles) && empty($this->pendingStylesheets) ) {
			return;
		}

		switch ($configId) {
			case 'site':
				$cache = self::getLocalCache();
				break;
			case 'global':
				if ( is_multisite() ) {
					$cache = self::getNetworkCache();
				} else {
					//Use regular (non-network) transients for single-site installations.
					//Stylesheets take advantage of transient autoloading to load cache
					//metadata without an extra DB query, and that only works with normal
					//transients, not network/site transients.
					$cache = self::getLocalCache();
				}
				break;
			case 'network-admin':
				$cache = self::getNetworkCache();
				break;
			default:
				return;
		}

		foreach ($this->pendingBundles as $bundleHandle) {
			$this->bundles[$bundleHandle] = $bundle = new DynamicStylesheetBundle(
				$bundleHandle,
				$cache,
				$configId,
				$this->generateQueryParamsWithConfigId($configId)
			);
			$bundle->addHooks();
		}
		$this->pendingBundles = [];

		foreach ($this->pendingStylesheets as $handle => $data) {
			$providerCallback = $data['providerCallback'];
			$parentBundle = $data['parentBundle'];

			list($lastModifiedCallback, $cssGenerator) = call_user_func(
				$providerCallback,
				$configId
			);
			if ( empty($cssGenerator) ) {
				continue;
			}

			$stylesheet = new Stylesheet(
				$handle,
				$cssGenerator,
				$lastModifiedCallback ?: '__return_zero',
				$cache,
				$configId,
				$this->generateQueryParamsWithConfigId($configId)
			);

			if ( $parentBundle && isset($this->bundles[$parentBundle]) ) {
				$this->bundles[$parentBundle]->addStylesheet($stylesheet);

				/*
				Even when the stylesheet is added to a bundle, it still needs
				to be able to output itself directly because whatever process
				decides whether to use a bundle for a particular admin page may
				not produce the same result during the AJAX request that actually
				loads the stylesheet (it might not even run at all).
				*/
				$stylesheet->addOutputHook();
			} else {
				$stylesheet->addHooks();
			}
		}
		$this->pendingStylesheets = [];
	}

	private function maybeCreateLateInstances() {
		$isLate = $this->doingAjax() ? $this->initHookDone : $this->enqueueHookDone;
		if ( !$isLate ) {
			return;
		}

		if ( $this->knownConfigId ) {
			$this->createPendingInstances($this->knownConfigId);
		}
	}

	private static function getLocalCache() {
		static $cache = null;
		if ( $cache === null ) {
			$cache = new Cache\LocalTransientCache();
		}
		return $cache;
	}

	private static function getNetworkCache() {
		static $cache = null;
		if ( $cache === null ) {
			$cache = new Cache\NetworkTransientCache();
		}
		return $cache;
	}

	public function addBundle($handle) {
		$this->pendingBundles[$handle] = $handle;
		$this->maybeCreateLateInstances();
	}

	public function addStylesheet($handle, $providerCallback, $parentBundle = null) {
		$this->pendingStylesheets[$handle] = [
			'providerCallback' => $providerCallback,
			'parentBundle'     => $parentBundle,
		];
		$this->maybeCreateLateInstances();
	}

	/**
	 * @param string $menuConfigId
	 * @return array
	 */
	private function generateQueryParamsWithConfigId($menuConfigId) {
		return [
			'ame_config_id' => $menuConfigId,
			'ame_cid_hash'  => wp_hash($menuConfigId . self::CONFIG_ID_HASH_INPUT_SUFFIX),
		];
	}

	/**
	 * @return string|null
	 */
	public function getConfigIdFromAjaxRequest() {
		//External callers might not check if this is an AJAX request.
		if ( !$this->doingAjax() ) {
			return null;
		}

		$queryParams = $this->menuEditor->get_query_params();
		if ( !isset($queryParams['ame_config_id']) || !isset($queryParams['ame_cid_hash']) ) {
			return null;
		}

		$configId = (string)$queryParams['ame_config_id'];
		if ( !array_key_exists($configId, self::SUPPORTED_CONFIG_IDS) ) {
			return null;
		}

		$expectedHash = wp_hash($configId . self::CONFIG_ID_HASH_INPUT_SUFFIX);
		if ( $expectedHash !== $queryParams['ame_cid_hash'] ) {
			return null;
		}

		return $configId;
	}

	public static function getInstance(\WPMenuEditor $menuEditor) {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self($menuEditor);
		}
		return $instance;
	}
}