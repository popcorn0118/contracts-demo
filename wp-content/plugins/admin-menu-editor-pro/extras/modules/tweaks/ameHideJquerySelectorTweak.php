<?php

class ameHideJquerySelectorTweak extends ameBaseTweak {
	/**
	 * @var array
	 */
	protected $selector;

	public function __construct($id, $label, array $advancedSelector) {
		parent::__construct($id, $label);
		$this->selector = $advancedSelector;
	}

	public function apply($settings = null) {
		$manager = ameJqueryTweakManager::getInstance();
		$manager->addSelector($this->selector);
	}
}

class ameJqueryTweakManager {
	const HOOK_PRIORITY = 100;

	private $pendingSelectors = [];
	private $hooksAdded = false;

	private function __construct() {
		//This method doesn't do anything, it just makes sure the constructor is private.
	}

	public function addSelector(array $selector) {
		$this->pendingSelectors[] = $selector;
		$this->addHooks();
	}

	private function addHooks() {
		if ( $this->hooksAdded ) {
			return;
		}

		//Default hooks.
		add_action('admin_enqueue_scripts', array($this, 'enqueueDependencies'), self::HOOK_PRIORITY);
		add_action('admin_print_scripts', array($this, 'printScripts'), self::HOOK_PRIORITY);

		//Backup hooks in case the default ones already ran by the time we get here,
		//or some tweaks get applied after the default hooks.
		add_action('admin_footer', array($this, 'enqueueDependencies'), self::HOOK_PRIORITY);
		add_action('admin_print_footer_scripts', array($this, 'printScripts'), self::HOOK_PRIORITY);

		//While the page is loading, the hidden elements will still be visible because they're
		//only hidden once the DOM is ready. To mitigate that, let's hide the .wrap container
		//until our script runs.
		add_action('admin_print_styles', array($this, 'printHideWrapCode'), self::HOOK_PRIORITY);

		$this->hooksAdded = true;
	}

	private $dependenciesEnqueued = false;

	public function enqueueDependencies() {
		if ( $this->dependenciesEnqueued ) {
			return;
		}

		//In practice, both of these should already be queued when this plugin is active,
		//but we'll add them just in case - should be safe to do so.
		wp_enqueue_script('jquery');
		wp_enqueue_script('ame-pro-admin-helpers');

		$this->dependenciesEnqueued = true;
	}

	public function printScripts() {
		if ( empty($this->pendingSelectors) ) {
			return;
		}

		$selectorData = wp_json_encode($this->pendingSelectors);
		if ( empty($selectorData) ) {
			return;
		}

		/** @noinspection JSUnnecessarySemicolon */
		$script = <<<EOT
		<script type="text/javascript" data-ame-tweak="ameHideJquerySelectorTweak">
		/* AME Pro -- Tweaks that hide jQuery selectors */
		if (jQuery) {
			jQuery(function() {
				if (AmeAdvancedSelectors) {
					const selectors = ( $selectorData );
					for ( const selector of selectors ) {
						AmeAdvancedSelectors.queryAdvancedSelector(selector).hide();
					}
				}
		
				//Remove the style that hides the .wrap element.
				jQuery('style#ws-ame-hide-wrap-for-tweaks').remove();
			});
		}
		</script>
EOT;

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated JS cannot be meaningfully escaped.
		echo $script;
		$this->pendingSelectors = [];
	}

	public function printHideWrapCode() {
		?>
		<!-- AME Pro -- Temporarily hide the .wrap container while the page is loading -->
		<!--suppress CssUnusedSymbol -->
		<style id="ws-ame-hide-wrap-for-tweaks">
			body.js .wrap {
				visibility: hidden;
			}
		</style>
		<script>
			//Backup. Remove the style tag even if the generated tweak script fails somehow.
			document.addEventListener('DOMContentLoaded', function () {
				setTimeout(function () {
					const styleEl = document.querySelector('style#ws-ame-hide-wrap-for-tweaks');
					if (styleEl) {
						styleEl.remove();
					}
				}, 15);
			});
		</script>
		<?php
	}

	public static function getInstance() {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self();
		}
		return $instance;
	}
}