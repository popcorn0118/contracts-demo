<?php

namespace YahnisElsts\AdminMenuEditor\WebpackRegistry;

use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

class WebpackAssetRegistry {
	const HANDLE_PREFIX = 'ame-';
	const HANDLE_POSTFIX = '-bundle';
	const RUNTIME_CHUNK_NAME = 'runtime';

	/**
	 * @var string
	 */
	private $manifestFullPath;
	/**
	 * @var string
	 */
	private $distDirectory;

	/**
	 * @var array<string,\YahnisElsts\WpDependencyWrapper\v1\ScriptDependency>
	 */
	private $resolvedScriptDependencies = [];

	/**
	 * @var null|array
	 */
	private $cachedManifest = null;

	public function __construct($manifestFileName, $distDirectory) {
		$this->manifestFullPath = $manifestFileName;
		$this->distDirectory = $distDirectory;
	}

	/**
	 * @param string $name
	 * @return \YahnisElsts\WpDependencyWrapper\v1\ScriptDependency
	 */
	public function getWebpackScriptChunk($name) {
		if ( isset($this->resolvedScriptDependencies[$name]) ) {
			return $this->resolvedScriptDependencies[$name];
		}
		return $this->initScriptDependency($name, false);
	}

	/**
	 * @param string $entryPointName
	 * @return \YahnisElsts\WpDependencyWrapper\v1\ScriptDependency
	 */
	public function getWebpackEntryPoint($entryPointName) {
		if ( isset($this->resolvedScriptDependencies[$entryPointName]) ) {
			return $this->resolvedScriptDependencies[$entryPointName];
		}
		return $this->initScriptDependency($entryPointName, true);
	}

	private function initScriptDependency($chunkName, $isEntryPoint) {
		$relativePath = $this->getRelativeChunkFileName($chunkName);
		$fullFilePath = $this->distDirectory . '/' . $relativePath;
		if ( !file_exists($fullFilePath) ) {
			throw new \RuntimeException("Webpack chunk file '$relativePath' not found.");
		}

		$manifest = $this->getManifest();
		if ( isset($manifest[$chunkName]) && ($chunkName !== self::RUNTIME_CHUNK_NAME) ) {
			//Only entry points are in the manifest, so this is an entry point.
			$isEntryPoint = true;
		} else if ( $isEntryPoint ) {
			throw new \RuntimeException("Webpack entry point '$chunkName' not found in the manifest.");
		}

		$script = new ScriptDependency(
			plugins_url($relativePath, $this->distDirectory . '/fictional-file'),
			$this->nameToScriptHandle($chunkName),
			$fullFilePath
		);

		if ( !$isEntryPoint ) {
			//Every chunk depends on the runtime chunk, except the runtime chunk itself.
			if ( $chunkName !== self::RUNTIME_CHUNK_NAME ) {
				$script->addDependencies($this->getWebpackScriptChunk(self::RUNTIME_CHUNK_NAME));
			}
		}

		//Add entry point dependencies.
		if ( $isEntryPoint && isset($manifest[$chunkName]) && is_array($manifest[$chunkName]) ) {
			foreach ($manifest[$chunkName] as $dependencyChunkFileName) {
				//The last chunk will usually be the entry point itself, so skip it.
				$dependencyName = $this->getChunkNameFromFileName($dependencyChunkFileName);
				if ( $dependencyName !== $chunkName ) {
					$script->addDependencies($this->getWebpackScriptChunk($dependencyName));
				}
			}
		}

		$this->resolvedScriptDependencies[$chunkName] = $script;
		return $script;
	}

	private function nameToScriptHandle($name) {
		return self::HANDLE_PREFIX . $name . self::HANDLE_POSTFIX;
	}

	private function getRelativeChunkFileName($name) {
		return $name . '.bundle.js';
	}

	private function getChunkNameFromFileName($relativeFileName) {
		$dotPos = strpos($relativeFileName, '.');
		return substr($relativeFileName, 0, $dotPos);
	}

	private function getManifest() {
		if ( isset($this->cachedManifest) ) {
			return $this->cachedManifest;
		}

		if ( !file_exists($this->manifestFullPath) ) {
			throw new \RuntimeException("Webpack manifest file '$this->manifestFullPath' not found.");
		}
		//phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local file.
		$manifest = json_decode(file_get_contents($this->manifestFullPath), true);
		if ( !is_array($manifest) ) {
			throw new \RuntimeException("Webpack manifest file '$this->manifestFullPath' is not valid JSON.");
		}

		$this->cachedManifest = $manifest;
		return $this->cachedManifest;
	}
}