<?php
/**
 * Minimal WP_Filesystem_Base double for unit tests (WordPress is not
 * loaded). The in-memory fake in tests/Unit/Fakes extends it.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

// phpcs:ignoreFile -- test double mirroring a WordPress core class name.

class WP_Filesystem_Base {

	public function put_contents( $file, $contents, $mode = false ) {
		return false;
	}

	public function exists( $path ) {
		return false;
	}

	public function delete( $file, $recursive = false, $type = false ) {
		return false;
	}

	public function is_dir( $path ) {
		return false;
	}

	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return false;
	}

	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		return false;
	}
}
