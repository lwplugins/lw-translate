<?php
/**
 * Tests for Comparator.
 *
 * Only the transient-cache short-circuit in compare_all() is covered here.
 * The actual up_to_date/update/not_installed decision lives in the private
 * determine_status()/compare_type() methods, reachable only through
 * compare_all(), which -- on a cache miss -- hard-instantiates GitHubClient
 * (a real wp_remote_get() call inside it, no injection seam) and calls
 * LocalScanner's statics (get_plugins()/wp_get_themes(), plus file_exists()/
 * file_get_contents() against the real filesystem). Reaching the decision
 * logic on a cache miss would need upwards of ten stubs plus a hand-rolled
 * WP_Error double (the class does not exist at all in this test runtime --
 * see the report), which is exactly the "5+ stubs = report it, don't force
 * it" case from tests.md. See the report for the full testability finding
 * and the tests that would cover it if GitHubClient/LocalScanner became
 * injectable.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Translation\Comparator;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\Comparator
 */
final class ComparatorTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_compare_all_returns_the_cached_result_without_contacting_github(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);

		$cached = [ 'woocommerce' ];
		Functions\when( 'get_transient' )->justReturn( $cached );

		// The fact that fetch_tree()'s HTTP call is never made is the
		// behaviour under test: it proves the cache genuinely short-circuits
		// the network fetch instead of merely returning a value that
		// happens to match.
		Functions\expect( 'wp_remote_get' )->never();

		$this->assertSame( $cached, Comparator::compare_all() );
	}
}
