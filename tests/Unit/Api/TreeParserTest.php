<?php
/**
 * Tests for the GitHub tree parser (real-shaped and malformed input).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Api;

use LightweightPlugins\Translate\Api\TreeParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Api\TreeParser
 */
final class TreeParserTest extends TestCase {

	public function test_parse_returns_empty_array_for_empty_tree(): void {
		$this->assertSame( [], TreeParser::parse( [], 'formal', 'hu_HU' ) );
	}

	public function test_parse_groups_a_plugin_file_under_its_slug(): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo',
				'sha'  => 'abc123',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertSame( 'plugin', $result['woocommerce']['type'] );
		$this->assertSame( 'abc123', $result['woocommerce']['files']['woocommerce-hu_HU.mo'] );
	}

	public function test_parse_groups_a_theme_file_under_its_slug(): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/themes/hu_HU/twentytwentyfour/twentytwentyfour-hu_HU.po',
				'sha'  => 'def456',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertSame( 'theme', $result['twentytwentyfour']['type'] );
		$this->assertSame( 'def456', $result['twentytwentyfour']['files']['twentytwentyfour-hu_HU.po'] );
	}

	public function test_parse_defaults_sha_to_empty_string_when_missing(): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertSame( '', $result['woocommerce']['files']['woocommerce-hu_HU.mo'] );
	}

	/**
	 * @dataProvider provide_entries_that_must_be_ignored
	 */
	public function test_parse_ignores_entries_that_do_not_describe_a_translation_file( array $entry ): void {
		$result = TreeParser::parse( [ $entry ], 'formal', 'hu_HU' );

		$this->assertSame( [], $result );
	}

	/**
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function provide_entries_that_must_be_ignored(): array {
		return [
			'missing type key'                       => [ [ 'path' => 'formal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo', 'sha' => 'x' ] ],
			'missing path key'                        => [ [ 'type' => 'blob', 'sha' => 'x' ] ],
			'directory entry instead of a blob'       => [ [ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU/woocommerce', 'sha' => 'x' ] ],
			'unrelated tone'                          => [ [ 'type' => 'blob', 'path' => 'informal/plugins/hu_HU/woocommerce/woocommerce-hu_HU.mo', 'sha' => 'x' ] ],
			'unrelated locale'                        => [ [ 'type' => 'blob', 'path' => 'formal/plugins/de_DE/woocommerce/woocommerce-de_DE.mo', 'sha' => 'x' ] ],
			'unrelated type directory (not plugins or themes)' => [ [ 'type' => 'blob', 'path' => 'formal/snippets/hu_HU/woocommerce/woocommerce-hu_HU.mo', 'sha' => 'x' ] ],
			'file directly under locale, no slug segment' => [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/woocommerce-hu_HU.mo', 'sha' => 'x' ] ],
			'unsupported file extension'              => [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/woocommerce/readme.txt', 'sha' => 'x' ] ],
		];
	}

	/**
	 * @dataProvider provide_supported_extensions
	 */
	public function test_parse_accepts_every_supported_extension( string $filename ): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/plugins/hu_HU/woocommerce/' . $filename,
				'sha'  => 'x',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertArrayHasKey( $filename, $result['woocommerce']['files'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_supported_extensions(): array {
		return [
			'.mo'      => [ 'woocommerce-hu_HU.mo' ],
			'.po'      => [ 'woocommerce-hu_HU.po' ],
			'.l10n.php' => [ 'woocommerce-hu_HU.l10n.php' ],
			'.json'    => [ 'woocommerce-hu_HU-1234abcd.json' ],
		];
	}

	/**
	 * A locale that is a textual prefix of another locale must not match: the
	 * literal "/" appended after the locale in the prefix template is what
	 * keeps "hu_HU" from matching a "hu_HU_formal" directory.
	 */
	public function test_parse_does_not_match_a_locale_that_is_a_prefix_of_another(): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/plugins/hu_HU_formal/woocommerce/woocommerce-hu_HU_formal.mo',
				'sha'  => 'x',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertSame( [], $result );
	}

	/**
	 * Remote (GitHub API) data is untrusted: a hand-crafted or malformed
	 * response can carry a scalar or array in the "path" slot instead of a
	 * string. str_starts_with() requires a string argument under
	 * strict_types, so without a guard this throws an uncaught TypeError
	 * instead of simply skipping the malformed entry.
	 *
	 * @dataProvider provide_non_string_paths
	 */
	public function test_parse_skips_an_entry_whose_path_is_not_a_string( mixed $path ): void {
		$tree = [
			[ 'type' => 'blob', 'path' => $path, 'sha' => 'x' ],
		];

		$this->assertSame( [], TreeParser::parse( $tree, 'formal', 'hu_HU' ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_non_string_paths(): array {
		return [
			'integer path' => [ 12345 ],
			'boolean path' => [ true ],
			'array path'   => [ [ 'formal', 'plugins' ] ],
		];
	}

	/**
	 * Nested path segments beyond the slug are folded into the "filename" key
	 * verbatim (explode(..., 2) only splits off the first segment as the
	 * slug). Documented here as current behaviour, not asserted as desirable.
	 */
	public function test_parse_folds_extra_nested_segments_into_the_filename_key(): void {
		$tree = [
			[
				'type' => 'blob',
				'path' => 'formal/plugins/hu_HU/woocommerce/nested/dir/woocommerce-hu_HU.po',
				'sha'  => 'x',
			],
		];

		$result = TreeParser::parse( $tree, 'formal', 'hu_HU' );

		$this->assertArrayHasKey( 'nested/dir/woocommerce-hu_HU.po', $result['woocommerce']['files'] );
	}

	public function test_get_available_locales_returns_empty_array_for_empty_tree(): void {
		$this->assertSame( [], TreeParser::get_available_locales( [], 'formal' ) );
	}

	public function test_get_available_locales_extracts_locales_from_plugin_and_theme_directories(): void {
		$tree = [
			[ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU' ],
			[ 'type' => 'tree', 'path' => 'formal/themes/de_DE' ],
		];

		$result = TreeParser::get_available_locales( $tree, 'formal' );

		$this->assertSame( [ 'de_DE', 'hu_HU' ], $result );
	}

	public function test_get_available_locales_deduplicates_locales_seen_in_both_types(): void {
		$tree = [
			[ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU' ],
			[ 'type' => 'tree', 'path' => 'formal/themes/hu_HU' ],
		];

		$this->assertSame( [ 'hu_HU' ], TreeParser::get_available_locales( $tree, 'formal' ) );
	}

	/**
	 * Same untrusted-remote-data concern as parse(): get_available_locales()
	 * runs its own str_starts_with() call on "path", so a non-string value
	 * there must be skipped rather than fatal.
	 *
	 * @dataProvider provide_non_string_paths
	 */
	public function test_get_available_locales_skips_an_entry_whose_path_is_not_a_string( mixed $path ): void {
		$tree = [
			[ 'type' => 'tree', 'path' => $path ],
		];

		$this->assertSame( [], TreeParser::get_available_locales( $tree, 'formal' ) );
	}

	/**
	 * @dataProvider provide_locale_entries_that_must_be_ignored
	 */
	public function test_get_available_locales_ignores_entries_of_the_wrong_shape( array $entry ): void {
		$this->assertSame( [], TreeParser::get_available_locales( [ $entry ], 'formal' ) );
	}

	/**
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function provide_locale_entries_that_must_be_ignored(): array {
		return [
			'blob instead of a tree'         => [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU' ] ],
			'wrong tone'                     => [ [ 'type' => 'tree', 'path' => 'informal/plugins/hu_HU' ] ],
			'unrelated top-level directory'  => [ [ 'type' => 'tree', 'path' => 'formal/snippets/hu_HU' ] ],
			'too shallow (missing locale segment)' => [ [ 'type' => 'tree', 'path' => 'formal/plugins' ] ],
			'too deep (a slug directory, not a locale)' => [ [ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU/woocommerce' ] ],
			'missing type key'               => [ [ 'path' => 'formal/plugins/hu_HU' ] ],
			'missing path key'               => [ [ 'type' => 'tree' ] ],
		];
	}
}
