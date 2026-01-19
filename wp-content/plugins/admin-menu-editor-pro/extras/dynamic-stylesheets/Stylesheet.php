<?php

namespace YahnisElsts\AdminMenuEditor\DynamicStylesheets;

use ameFileLock;
use ameUtils;
use YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache\DummyCache;

class Stylesheet {
	const AJAX_ACTION_PREFIX = 'ame_dyn_style-';
	const EARLY_CACHE_UPDATE_THRESHOLD = 30 * 60;

	/**
	 * Unique prefix that can be used to find and delete all cached items
	 * created by this class (e.g. when the plugin is uninstalled).
	 */
	const COMMON_CACHE_PREFIX = 'wsdst25-';

	/**
	 * @var string
	 */
	protected $handle;
	/**
	 * @var string
	 */
	protected $ajaxAction;
	/**
	 * @var Cache\StyleCacheInterface
	 */
	protected $cache;

	/**
	 * @var callable():string
	 */
	private $cssGenerator;

	/**
	 * @var callable():int|null
	 */
	private $lastModifiedCallback;
	/**
	 * @var array
	 */
	private $additionalQueryParameters;
	/**
	 * @var string
	 */
	private $cacheKeySuffix;

	/**
	 * @param string $handle
	 * @param callable():string $cssGenerator
	 * @param callable():int $lastModifiedCallback
	 * @param Cache\StyleCacheInterface|null $cache
	 * @param string $cacheKeySuffix
	 * @param array<string,mixed> $additionalQueryParameters
	 */
	public function __construct(
		$handle,
		$cssGenerator,
		$lastModifiedCallback = null,
		$cache = null,
		$cacheKeySuffix = '',
		$additionalQueryParameters = []
	) {
		$this->handle = $handle;
		$this->ajaxAction = self::AJAX_ACTION_PREFIX . $handle;
		$this->cssGenerator = $cssGenerator;
		$this->lastModifiedCallback = $lastModifiedCallback;
		$this->additionalQueryParameters = $additionalQueryParameters;

		if ( $cache ) {
			$this->cache = $cache;
		} else {
			$this->cache = new DummyCache();
		}
		$this->cacheKeySuffix = $cacheKeySuffix;
	}

	/**
	 * Register the hooks that wil enqueue and output the stylesheet.
	 *
	 * @return void
	 */
	public function addHooks() {
		$this->addAdminEnqueueHook();
		$this->addOutputHook();
	}

	public function addAdminEnqueueHook() {
		add_action('admin_enqueue_scripts', [$this, 'enqueueStyle']);
	}

	public function addOutputHook() {
		add_action('wp_ajax_' . $this->ajaxAction, [$this, 'ajaxOutputCss']);
	}

	/**
	 * Get the CSS content of the stylesheet. This method bypasses the cache.
	 *
	 * @return string
	 */
	public function generateCss() {
		$css = call_user_func($this->cssGenerator);
		return (string)$css;
	}

	public function enqueueStyle() {
		$lastModified = $this->getLastModifiedTimestamp();
		if ( $this->isContentKnownEmpty($lastModified) ) {
			return;
		}

		wp_enqueue_style(
			$this->handle,
			$this->getUrl(),
			[],
			$this->getVersion($lastModified)
		);
	}

	/**
	 * Handle the AJAX request that outputs the stylesheet.
	 *
	 * Note: This will terminate the script.
	 *
	 * @access private This is only public because it's a hook callback.
	 * @return void
	 * @internal
	 */
	public function ajaxOutputCss() {
		if ( $this->requiresLogin() && !is_user_logged_in() ) {
			echo '/* You must be logged in to view this stylesheet. */';
			exit();
		}

		$this->outputHttpResponse();
		exit();
	}

	protected function outputHttpResponse() {
		$lastModified = $this->getLastModifiedTimestamp();
		$omitResponseBody = ameUtils::sendCachingHeaders($lastModified);
		if ( $omitResponseBody ) {
			return;
		}

		$cache = $this->getCache();
		$cacheKey = $this->getCacheKey();
		$ttl = $this->getCacheTtl();

		$hasValidCachedContent = false;
		$isCacheGettingStale = false;
		$wasContentRegenerated = false;

		$data = $cache->get($cacheKey);
		if ( is_array($data) ) {
			$hasValidCachedContent = isset($data['content'])
				&& isset($data['lastModified'])
				&& ($data['lastModified'] === $lastModified);

			if ( $ttl > 0 ) {
				$refreshThreshold = ceil(min($ttl / 2, self::EARLY_CACHE_UPDATE_THRESHOLD));
				$isCacheGettingStale = isset($data['expiration'])
					&& !empty($data['expiration'])
					&& ($data['expiration'] < (time() + $refreshThreshold));
			}
		}

		if ( $hasValidCachedContent ) {
			$content = $data['content'];

			$content = sprintf(
					'/* Cache hit. Last modified on %s */',
					isset($data['lastModified']) ? gmdate('Y-m-d H:i:s e', $data['lastModified']) : 'unknown'
				) . "\n" . $content;
		} else {
			$content = $this->generateCss();
			$wasContentRegenerated = true;
		}

		header('Content-Type: text/css');
		header('X-Content-Type-Options: nosniff');

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS does not need to be HTML-escaped.
		echo $content;

		//Maybe update the cache.
		if ( !$hasValidCachedContent || ($isCacheGettingStale && $this->shouldUpdateCacheEarly()) ) {
			//Let the browser close the connection if it wants to.
			ignore_user_abort(true);
			//Flush the output buffer, if any.
			$bufferLength = ob_get_length();
			if ( ($bufferLength !== false) && ($bufferLength > 0) ) {
				@ob_end_flush();
			}

			//Attempt to update the cache.
			$lock = ameFileLock::create(__FILE__);
			if ( $lock->acquire() ) {
				if ( !$wasContentRegenerated ) {
					$content = $this->generateCss();
				}

				$cache->set(
					$cacheKey,
					[
						'content'      => $content,
						'lastModified' => $lastModified,
						'expiration'   => empty($ttl) ? 0 : (time() + $ttl),
					],
					$ttl
				);

				if ( $this->isMetadataCachingEnabled() ) {
					$trimmedContent = trim($content);
					$cache->set(
						$this->getMetaCacheKey(),
						[
							'lastModified'   => $lastModified,
							'isContentEmpty' => empty($trimmedContent),
						],
						//Normal transients with 0 TTL never expire and are autoloaded. This allows
						//us to check if the stylesheet is empty without having to regenerate it,
						//and without loading the (potentially large) stylesheet content from the DB.
						//Note: Site transients are not autoloaded.
						0
					);
				}
			}
			$lock->release();
		}
	}

	/**
	 * Is the content of this stylesheet known to be empty?
	 *
	 * This will only be true if the stylesheet data has already been generated
	 * and cached, and the generated content was empty.
	 *
	 * @param int $lastModified
	 * @return bool
	 */
	protected function isContentKnownEmpty($lastModified) {
		if ( !$this->isMetadataCachingEnabled() ) {
			return false;
		}

		$cache = $this->getCache();
		$metadata = $cache->get($this->getMetaCacheKey());
		if ( empty($metadata) ) {
			return false;
		}

		if (
			isset($metadata['isContentEmpty'])
			&& isset($metadata['lastModified'])
			&& ($metadata['lastModified'] === $lastModified)
		) {
			return $metadata['isContentEmpty'];
		}
		return false;
	}

	/**
	 * Get the last time the stylesheet was modified.
	 *
	 * This timestamp is used for cache invalidation, so the method should always
	 * return the effective value, not cached data.
	 *
	 * @return int Unix timestamp. Can be 0 if the last modified time is unknown.
	 */
	protected function getLastModifiedTimestamp() {
		$timestamp = 0;
		if ( $this->lastModifiedCallback !== null ) {
			$lastModified = call_user_func($this->lastModifiedCallback);
			if ( is_int($lastModified) ) {
				$timestamp = $lastModified;
			}
		}

		//In the preview frame, we might have a mix of existing settings and settings
		//that have been modified as part of an AC changeset. Let's use the most recent
		//timestamp.
		//The regular "last modified" timestamp doesn't get updated in AC preview when
		//the user changes individual settings, so we use the changeset modification
		//time instead.
		$changeset = $this->getActiveAcChangeset();
		if ( !empty($changeset) ) {
			$changesetModified = $changeset->getLastModified();
			if ( !empty($changesetModified) && ($changesetModified > $timestamp) ) {
				$timestamp = $changesetModified;
			}
		}

		return $timestamp;
	}

	/**
	 * Get the stylesheet URL.
	 *
	 * @return string
	 */
	protected function getUrl() {
		$url = add_query_arg($this->generateQueryParameters(), admin_url('admin-ajax.php'));

		//Add AC preview parameters to the URL. It's not automated in AC itself
		//because AC doesn't necessarily know which admin URLs are safe to change.
		return apply_filters('admin_menu_editor-ac_add_preview_params', $url);
	}

	/**
	 * Generate the query parameters for the stylesheet URL.
	 *
	 * @return array<string,mixed>
	 */
	protected function generateQueryParameters() {
		return array_merge(
			$this->additionalQueryParameters,
			['action' => $this->ajaxAction]
		);
	}

	/**
	 * Get the version string for the stylesheet.
	 *
	 * @param int $lastModified
	 * @return string
	 */
	protected function getVersion($lastModified) {
		$version = (string)$lastModified;
		$versionPrefix = $this->getVersionPrefix();
		if ( !empty($versionPrefix) ) {
			$version = $versionPrefix . $version;
		}
		return $version;
	}

	/**
	 * @return string
	 */
	protected function getVersionPrefix() {
		if ( $this->isPreviewMode() ) {
			$prefix = 'preview';
			$changeset = $this->getActiveAcChangeset();
			if ( !empty($changeset) ) {
				$name = $changeset->getName();
				if ( !empty($name) ) {
					//The version prefix is also used as part of the cache key, so it should
					//be short. The changeset name is usually long string, so we'll hash it
					//and use the first N characters.
					$prefix .= '-' . substr(sha1($name), 0, 8);
				}
			}
			return $prefix;
		} else {
			return '';
		}
	}

	/**
	 * Does the user need to be logged in to view this stylesheet?
	 *
	 * @return bool
	 */
	protected function requiresLogin() {
		//Currently, only admin stylesheets are supported, so we can assume that
		//the user needs to be logged in.
		return true;
	}

	/**
	 * @return \YahnisElsts\AdminMenuEditor\DynamicStylesheets\Cache\StyleCacheInterface
	 */
	protected function getCache() {
		return $this->cache;
	}

	protected function getCacheKey($suffix = 'css') {
		$key = self::COMMON_CACHE_PREFIX . $this->handle;

		if ( !empty($this->cacheKeySuffix) ) {
			$key .= '.' . $this->cacheKeySuffix;
		}

		$versionPrefix = $this->getVersionPrefix();
		if ( !empty($versionPrefix) ) {
			$key .= '.' . $versionPrefix;
		}

		$key .= '.' . $suffix;
		return $key;
	}

	protected function getMetaCacheKey() {
		return $this->getCacheKey('meta');
	}

	protected function getCacheTtl() {
		if ( $this->isPreviewMode() ) {
			return 2 * DAY_IN_SECONDS;
		} else {
			return 30 * DAY_IN_SECONDS;
		}
	}

	protected function isMetadataCachingEnabled() {
		return !$this->isPreviewMode();
	}

	protected function shouldUpdateCacheEarly() {
		return !$this->isPreviewMode();
	}

	protected function isPreviewMode() {
		//Once enabled, preview mode cannot be disabled during the same request.
		//This means we can cache the "true" result and possibly save some performance.
		static $isPreviewMode = false;
		if ( $isPreviewMode ) {
			return true;
		}

		$currentState = apply_filters('admin_menu_editor-is_preview_frame', false);
		if ( $currentState ) {
			$isPreviewMode = true;
		}
		return $currentState;
	}

	/**
	 * @return \YahnisElsts\AdminMenuEditor\AdminCustomizer\AcChangeset|null
	 */
	protected function getActiveAcChangeset() {
		$changeset = apply_filters('admin_menu_editor-ac_preview_frame_changeset', null);
		if ( !empty($changeset) && is_object($changeset) ) {
			return $changeset;
		} else {
			return null;
		}
	}
}