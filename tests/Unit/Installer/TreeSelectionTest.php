<?php
/**
 * Tests for TreeSelection (which tree entries get installed for an item).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use LightweightPlugins\Translate\Installer\TreeSelection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\TreeSelection
 */
final class TreeSelectionTest extends TestCase {

	private const PREFIX = 'formal/plugins/hu_HU/woocommerce/';

	/**
	 * @param array<int, string> $names File names under the item folder.
	 * @return array<int, array<string, string>>
	 */
	private function tree_with( array $names ): array {
		$tree = [];

		foreach ( $names as $name ) {
			$tree[] = [
				'type' => 'blob',
				'path' => self::PREFIX . $name,
				'sha'  => 'sha-' . $name,
			];
		}

		return $tree;
	}

	public function test_selects_the_item_files_with_their_blob_sha(): void {
		$tree = $this->tree_with( [ 'woocommerce-hu_HU.mo', 'woocommerce-hu_HU.po' ] );

		$selection = TreeSelection::select( $tree, 'woocommerce', 'plugin', 'formal', 'hu_HU' );

		$this->assertSame(
			[
				'woocommerce-hu_HU.mo' => [
					'path' => self::PREFIX . 'woocommerce-hu_HU.mo',
					'sha'  => 'sha-woocommerce-hu_HU.mo',
				],
				'woocommerce-hu_HU.po' => [
					'path' => self::PREFIX . 'woocommerce-hu_HU.po',
					'sha'  => 'sha-woocommerce-hu_HU.po',
				],
			],
			$selection['files']
		);
	}

	/**
	 * @dataProvider provide_never_installed_names
	 */
	public function test_never_selects_php_or_foreign_names_and_reports_them( string $name ): void {
		$tree = $this->tree_with( [ 'woocommerce-hu_HU.mo', $name ] );

		$selection = TreeSelection::select( $tree, 'woocommerce', 'plugin', 'formal', 'hu_HU' );

		$this->assertSame( [ 'woocommerce-hu_HU.mo' ], array_keys( $selection['files'] ) );
		$this->assertSame( [ $name ], $selection['skipped'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_never_installed_names(): array {
		return [
			'arbitrary l10n php'    => [ 'evil.l10n.php' ],
			'l10n php of the item'  => [ 'woocommerce-hu_HU.l10n.php' ],
			'another plugin mo'     => [ 'akismet-hu_HU.mo' ],
			'file in a subfolder'   => [ 'sub/woocommerce-hu_HU.mo' ],
		];
	}

	public function test_ignores_entries_outside_the_item_folder(): void {
		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/akismet/akismet-hu_HU.mo', 'sha' => 'x' ],
			[ 'type' => 'tree', 'path' => 'formal/plugins/hu_HU/woocommerce', 'sha' => 'y' ],
		];

		$selection = TreeSelection::select( $tree, 'woocommerce', 'plugin', 'formal', 'hu_HU' );

		$this->assertSame( [ 'files' => [], 'skipped' => [] ], $selection );
	}

	public function test_uses_the_themes_folder_for_a_theme(): void {
		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/themes/hu_HU/astra/astra-hu_HU.mo', 'sha' => 'x' ],
		];

		$selection = TreeSelection::select( $tree, 'astra', 'theme', 'formal', 'hu_HU' );

		$this->assertSame( [ 'astra-hu_HU.mo' ], array_keys( $selection['files'] ) );
	}

	public function test_selects_nothing_for_an_invalid_slug(): void {
		$tree = [
			[ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/../..-hu_HU.mo', 'sha' => 'x' ],
		];

		$selection = TreeSelection::select( $tree, '..', 'plugin', 'formal', 'hu_HU' );

		$this->assertSame( [], $selection['files'] );
	}

	public function test_skips_malformed_entries_without_failing(): void {
		$tree = [
			[ 'type' => 'blob', 'path' => 12345 ],
			[ 'type' => 'blob', 'path' => self::PREFIX . "woocommerce-hu_HU.mo\0.php" ],
			[ 'type' => 'blob' ],
		];

		$selection = TreeSelection::select( $tree, 'woocommerce', 'plugin', 'formal', 'hu_HU' );

		$this->assertSame( [], $selection['files'] );
	}
}
