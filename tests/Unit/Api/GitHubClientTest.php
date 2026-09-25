<?php
/**
 * Tests for GitHubClient's tree cache lifetime.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Api\GitHubClient
 */
final class GitHubClientTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * An option saved as 0 by an older version must not turn the tree into
	 * a permanent autoloaded option.
	 */
	public function test_fetch_tree_never_caches_the_tree_with_a_ttl_below_one_hour(): void {
		$tree = [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/a/a-hu_HU.mo' ] ];

		Functions\when( 'get_option' )->justReturn( [ 'cache_ttl' => 0 ] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( json_encode( [ 'tree' => $tree ] ) );
		Functions\expect( 'set_transient' )->once()->with( 'lw_translate_tree_cache', $tree, 3600 );

		( new GitHubClient() )->fetch_tree();
	}
}
