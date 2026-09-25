<?php
/**
 * PHPUnit bootstrap file.
 *
 * Unit tests run WITHOUT WordPress: only the Composer autoloader is loaded,
 * which also pulls in Brain Monkey. WordPress functions are stubbed per test
 * via Brain\Monkey — the setUp()/tearDown() lifecycle lives in
 * tests/Unit/MonkeyTestCase.php.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Never touched by unit tests (no real filesystem I/O is performed against
// it), but FileMatcher/FileInstaller/LocalScanner reference the constant
// when building path strings, so it must exist.
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', '/tmp/lw-translate-tests/languages' );
}

if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}

if ( ! defined( 'FS_CHMOD_DIR' ) ) {
	define( 'FS_CHMOD_DIR', 0755 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Minimal doubles for the WordPress core classes the plugin type-hints.
if ( ! class_exists( 'WP_Error' ) ) {
	require_once __DIR__ . '/Stubs/WP_Error.php';
}

if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
	require_once __DIR__ . '/Stubs/WP_Filesystem_Base.php';
}
