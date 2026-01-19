<?php

class ameDisableRemotePatternsTweak extends ameBaseTweak {
	public function apply($settings = null) {
		add_filter('should_load_remote_block_patterns', '__return_false');
	}
}