<?php

class ameJqueryTweak extends ameBaseTweak {
	const ENQUEUE_ACTION = 'admin_enqueue_scripts';
	const BACKUP_ENQUEUE_ACTION = 'admin_footer';
	const ADMIN_FOOTER_SCRIPT_OUTPUT_ACTION = 'admin_print_footer_scripts';

	protected $jsCode;

	protected static $pendingScripts = [];
	protected static $isEnqueueHookSet = false;
	protected static $isOutputHookSet = false;
	protected static $isJqueryEnqueued = false;

	public function __construct($id, $label, $jsCode) {
		parent::__construct($id, $label);
		$this->jsCode = $jsCode;
	}

	/**
	 * @inheritDoc
	 */
	public function apply($settings = null) {
		self::$pendingScripts[] = $this->jsCode;

		if ( !self::$isEnqueueHookSet ) {
			add_action(self::ENQUEUE_ACTION, [__CLASS__, 'enqueueScripts']);
			add_action(self::BACKUP_ENQUEUE_ACTION, [__CLASS__, 'enqueueScripts']);
			self::$isEnqueueHookSet = true;
		}
	}

	public static function enqueueScripts() {
		if ( self::$isJqueryEnqueued ) {
			return;
		}

		if ( wp_script_is('jquery', 'done') ) {
			//jQuery was already enqueued and printed, so any remaining scripts
			//will have to be output directly instead of being added as inline
			//scripts to the jQuery dependency.
			self::$isJqueryEnqueued = true;
			if ( !self::$isOutputHookSet ) {
				add_action(self::ADMIN_FOOTER_SCRIPT_OUTPUT_ACTION, [__CLASS__, 'outputRemainingScripts']);
				self::$isOutputHookSet = true;
			}
			return;
		}

		wp_enqueue_script('jquery');
		self::$isJqueryEnqueued = true;

		if ( !empty(self::$pendingScripts) ) {
			/** @noinspection PhpRedundantOptionalArgumentInspection -- Let's be explicit about the position. */
			wp_add_inline_script(
				'jquery',
				self::generateCombinedScript(self::$pendingScripts),
				'after'
			);
			self::$pendingScripts = [];
		}
	}

	public static function outputRemainingScripts() {
		if ( empty(self::$pendingScripts) ) {
			return;
		}

		echo '<script type="text/javascript">', "\n";
		//The individual script fragments that are combined to produce the output
		//should already be tested and safe. They can't be meaningfully escaped.
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::generateCombinedScript(self::$pendingScripts), "\n";
		echo '</script>', "\n";

		self::$pendingScripts = [];
	}

	private static function generateCombinedScript($scripts) {
		$blocks = [];
		foreach ($scripts as $fragment) {
			$fragment = rtrim($fragment);
			if ( substr($fragment, -1) !== ';' ) {
				$fragment .= ';';
			}
			$blocks[] = "\t" . $fragment;
		}

		return sprintf(
			"jQuery(function(\$) {\n\t/* AME jQuery tweaks */\n%s\n});",
			implode("\n", $blocks)
		);
	}
}