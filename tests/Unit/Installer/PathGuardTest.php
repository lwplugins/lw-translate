<?php
/**
 * Tests for PathGuard (target stays inside the language folder).
 *
 * Uses the tests directory itself as the base, so realpath() resolves
 * real, existing folders without anything being written.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use LightweightPlugins\Translate\Installer\PathGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\PathGuard
 */
final class PathGuardTest extends TestCase {

	public function test_accepts_a_file_directly_inside_the_base_folder(): void {
		$this->assertTrue( PathGuard::is_contained( __DIR__, __DIR__ . '/woocommerce-hu_HU.mo' ) );
	}

	/**
	 * @dataProvider provide_escaping_paths
	 */
	public function test_rejects_a_path_that_leaves_the_base_folder( string $relative ): void {
		$this->assertFalse( PathGuard::is_contained( __DIR__, __DIR__ . '/' . $relative ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_escaping_paths(): array {
		return [
			'parent folder'        => [ '../woocommerce-hu_HU.mo' ],
			'deep traversal'       => [ '../../../../etc/evil.mo' ],
			'windows separators'   => [ '..\\..\\evil.mo' ],
			'nested sub folder'    => [ 'Fixtures/evil.mo' ],
			'dot dot name'         => [ '..' ],
			'nul byte'             => [ "evil.mo\0" ],
			'empty name'           => [ '' ],
		];
	}

	public function test_rejects_everything_when_the_base_folder_does_not_exist(): void {
		$base = __DIR__ . '/no-such-folder';

		$this->assertFalse( PathGuard::is_contained( $base, $base . '/woocommerce-hu_HU.mo' ) );
	}
}
