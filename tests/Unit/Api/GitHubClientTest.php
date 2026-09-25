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
		Functions\expect( 'set_transient' )->once()->with( 'lw_translate_tree_cache', \Mockery::on( static fn ( $payload ): bool => $payload['tree'] === $tree ), 3600 );

		( new GitHubClient() )->fetch_tree();
	}

	/**
	 * The admin warns when GitHub cut the listing short; before 1.2.0 the
	 * flag was dropped and entries silently went missing.
	 */
	public function test_fetch_tree_keeps_the_truncated_flag_for_the_admin(): void {
		$tree   = [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/a/a-hu_HU.mo' ] ];
		$stored = null;

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'get_transient' )->alias( static function () use ( &$stored ) {
			return $stored ?? false;
		} );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( [] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( json_encode( [ 'tree' => $tree, 'truncated' => true ] ) );
		Functions\when( 'set_transient' )->alias( static function ( $key, $value ) use ( &$stored ): bool {
			$stored = $value;
			return true;
		} );

		$this->assertNull( GitHubClient::cached_info() );
		$this->assertSame( $tree, ( new GitHubClient() )->fetch_tree() );

		$info = GitHubClient::cached_info();
		$this->assertTrue( $info['truncated'] );
		$this->assertIsInt( $info['fetched_at'] );
	}

	public function test_fetch_tree_reports_an_exhausted_rate_limit(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->returnArg( 2 );
		Functions\when( 'wp_remote_get' )->justReturn( [] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 403 );
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static fn ( $response, string $name ): string => 'x-ratelimit-remaining' === $name ? '0' : (string) ( time() + 300 )
		);
		Functions\expect( 'set_transient' )->never();

		$result = ( new GitHubClient() )->fetch_tree();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'github_rate_limited', $result->get_error_code() );
	}
}
