<?php
/**
 * Tests for FileInstaller::delete() when the repository cannot be read.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Installer\FileInstaller;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\Installer\FileInstaller
 */
final class FileInstallerDeleteTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * Without the repository listing the plugin cannot tell its own files
	 * from a language pack's, so it deletes nothing.
	 */
	public function test_github_unavailable_refuses_the_delete_without_touching_the_filesystem(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'cURL error 28' ) );
		Functions\expect( 'WP_Filesystem' )->never();

		$result = ( new FileInstaller() )->delete( 'akismet', 'plugin' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'repository_unavailable', $result->get_error_code() );
		$this->assertStringContainsString( 'cURL error 28', $result->get_error_message() );
	}
}
