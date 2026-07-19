<?php
/**
 * Tests for FileMatcher (remote-to-local path resolution).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Installer\FileMatcher;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\FileMatcher
 */
final class FileMatcherTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * Stubs Options' backing WordPress calls so Options::get() returns the
	 * saved values verbatim (no merging surprises to reason about here).
	 *
	 * @param array<string, mixed> $saved Saved option values.
	 * @return void
	 */
	private function given_saved_options( array $saved ): void {
		Functions\when( 'get_option' )->justReturn( $saved );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
	}

	public function test_get_remote_paths_builds_one_path_per_supported_extension(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$paths = FileMatcher::get_remote_paths( 'woocommerce', 'plugin' );

		$this->assertSame(
			[
				'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo',
				'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.po',
				'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.l10n.php',
			],
			$paths
		);
	}

	public function test_get_remote_paths_uses_the_themes_directory_for_theme_type(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$paths = FileMatcher::get_remote_paths( 'twentytwentyfour', 'theme' );

		$this->assertSame( 'formal/themes/hu_HU/twentytwentyfour/twentytwentyfour-hu_HU.mo', $paths[0] );
	}

	public function test_get_remote_paths_from_tree_matches_a_blob_under_the_slug_prefix(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo' ],
		];

		$this->assertSame(
			[ 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo' ],
			FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree )
		);
	}

	public function test_get_remote_paths_from_tree_ignores_a_tree_entry(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU/woocommerce' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree ) );
	}

	public function test_get_remote_paths_from_tree_ignores_entries_missing_the_type_key(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree ) );
	}

	public function test_get_remote_paths_from_tree_ignores_entries_missing_the_path_key(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'type' => 'blob' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree ) );
	}

	/**
	 * "woo" must not match a "woocommerce" directory: the trailing "/"
	 * appended after the slug in the prefix template is what prevents a
	 * slug that is a textual prefix of another slug from cross-matching.
	 */
	public function test_get_remote_paths_from_tree_does_not_match_a_slug_that_is_a_prefix_of_another(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woo', 'plugin', $tree ) );
	}

	/**
	 * Same containment guarantee as the slug case, but for the locale
	 * segment: "de_DE" must not match a "de_DE_formal" directory.
	 */
	public function test_get_remote_paths_from_tree_does_not_match_a_locale_that_is_a_prefix_of_another(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'de_DE' ] );

		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/de_DE_formal/woocommerce/woocommerce-de_DE_formal.mo' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree ) );
	}

	/**
	 * Matching is case-sensitive by design (locale codes and slugs are
	 * expected to be canonically cased); a differently-cased directory in
	 * the tree simply will not match. Documented as expected, not a bug.
	 */
	public function test_get_remote_paths_from_tree_is_case_sensitive(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'de_DE' ] );

		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/de_de/woocommerce/woocommerce-de_de.mo' ],
		];

		$this->assertSame( [], FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree ) );
	}

	/**
	 * TODO: gyanús viselkedés -- szándékos? Unlike TreeParser::parse(), this
	 * method applies no file-extension whitelist at all: any blob under the
	 * matching prefix is returned, regardless of extension. A malicious or
	 * compromised upstream tree entry such as
	 * "formal/plugins/hu_HU/woocommerce/evil.php" passes through untouched.
	 * See the report's security-probe section for the full trace into
	 * FileInstaller::download_and_save(), which writes whatever bytes come
	 * back with no content/type validation either.
	 */
	public function test_get_remote_paths_from_tree_matches_blobs_of_any_extension(): void {
		$this->given_saved_options( [ 'tone' => 'formal', 'locale' => 'hu_HU' ] );

		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/woocommerce/evil.php' ],
		];

		$this->assertSame(
			[ 'formal/plugins/hu_HU/woocommerce/evil.php' ],
			FileMatcher::get_remote_paths_from_tree( 'woocommerce', 'plugin', $tree )
		);
	}

	public function test_remote_to_local_builds_a_path_under_wp_lang_dir(): void {
		$local = FileMatcher::remote_to_local( 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo' );

		$this->assertSame( WP_LANG_DIR . '/plugins/woocommerce-hu_HU.mo', $local );
	}

	public function test_remote_to_local_returns_empty_string_for_a_path_with_too_few_segments(): void {
		$this->assertSame( '', FileMatcher::remote_to_local( 'formal/plugins/hu_HU' ) );
	}

	/**
	 * Security probe: end($parts) after exploding on "/" means only the
	 * final path segment is ever used as the filename. Directory-traversal
	 * segments earlier in the path are discarded entirely, so the write
	 * destination stays confined to WP_LANG_DIR/{plugins|themes}/ no matter
	 * how many "../" segments a malicious tree entry's path contains.
	 */
	public function test_remote_to_local_discards_directory_traversal_segments(): void {
		$local = FileMatcher::remote_to_local( 'formal/plugins/hu_HU/woocommerce/../../../../etc/evil.mo' );

		$this->assertSame( WP_LANG_DIR . '/plugins/evil.mo', $local );
	}
}
