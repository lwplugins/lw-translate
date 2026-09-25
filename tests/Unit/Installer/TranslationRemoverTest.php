<?php
/**
 * Tests for TranslationRemover (what delete removes, and its honest result).
 *
 * The language folder is this test directory (so PathGuard's realpath()
 * resolves); the files themselves live only in the in-memory filesystem.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use LightweightPlugins\Translate\Installer\TranslationRemover;
use LightweightPlugins\Translate\Tests\Unit\Fakes\InMemoryFilesystem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\TranslationRemover
 */
final class TranslationRemoverTest extends TestCase {

	private const JSON = 'akismet-hu_HU-0123456789abcdef0123456789abcdef.json';

	private const CORE_JSON = 'akismet-hu_HU-ffffffffffffffffffffffffffffffff.json';

	private string $base;

	private InMemoryFilesystem $fs;

	protected function setUp(): void {
		parent::setUp();
		$this->base = (string) realpath( __DIR__ );
		$this->fs   = new InMemoryFilesystem();
	}

	private function add( string ...$names ): void {
		foreach ( $names as $name ) {
			$this->fs->files[ $this->base . '/' . $name ] = 'x';
		}
	}

	/**
	 * @param array<int, string> $remote Repository file names of the item.
	 * @return array{deleted: array<int, string>, failed: array<int, string>}
	 */
	private function remove( array $remote, string $slug = 'akismet' ): array {
		return ( new TranslationRemover() )->remove( $this->fs, $this->base, $slug, 'hu_HU', $remote );
	}

	public function test_it_removes_the_repository_files_and_the_generated_l10n_php(): void {
		$this->add( 'akismet-hu_HU.mo', 'akismet-hu_HU.po', 'akismet-hu_HU.l10n.php', self::JSON );

		$result = $this->remove( [ 'akismet-hu_HU.mo', 'akismet-hu_HU.po', self::JSON ] );

		$this->assertSame( [ self::JSON, 'akismet-hu_HU.l10n.php', 'akismet-hu_HU.mo', 'akismet-hu_HU.po' ], $result['deleted'] );
		$this->assertSame( [], $result['failed'] );
		$this->assertSame( [], $this->fs->files );
	}

	/**
	 * A translate.wordpress.org language pack puts script translations with
	 * other hashes next to ours; the plugin never installed them.
	 */
	public function test_a_file_the_repository_does_not_list_survives(): void {
		$this->add( 'akismet-hu_HU.mo', self::CORE_JSON );

		$result = $this->remove( [ 'akismet-hu_HU.mo', self::JSON ] );

		$this->assertSame( [ 'akismet-hu_HU.mo' ], $result['deleted'] );
		$this->assertArrayHasKey( $this->base . '/' . self::CORE_JSON, $this->fs->files );
	}

	public function test_the_l10n_php_stays_when_the_mo_is_not_deleted(): void {
		$this->add( 'akismet-hu_HU.po', 'akismet-hu_HU.l10n.php', 'akismet-hu_HU.mo' );

		$result = $this->remove( [ 'akismet-hu_HU.po' ] );

		$this->assertSame( [ 'akismet-hu_HU.po' ], $result['deleted'] );
		$this->assertCount( 2, $this->fs->files );
	}

	public function test_names_the_file_name_policy_rejects_are_never_deleted(): void {
		$this->add( 'other-hu_HU.mo', 'akismet-hu_HU.txt' );

		$result = $this->remove( [ 'other-hu_HU.mo', 'akismet-hu_HU.txt', '../akismet-hu_HU.mo' ] );

		$this->assertSame( [], $result['deleted'] );
		$this->assertCount( 2, $this->fs->files );
	}

	public function test_nothing_installed_reports_nothing_deleted(): void {
		$this->assertSame(
			[
				'deleted' => [],
				'failed'  => [],
			],
			$this->remove( [ 'akismet-hu_HU.mo' ] )
		);
	}

	public function test_a_file_that_survives_the_delete_is_reported_as_failed(): void {
		$this->fs->refuse_delete = true;
		$this->add( 'akismet-hu_HU.po' );

		$result = $this->remove( [ 'akismet-hu_HU.po' ] );

		$this->assertSame( [], $result['deleted'] );
		$this->assertSame( [ 'akismet-hu_HU.po' ], $result['failed'] );
	}

	public function test_an_invalid_slug_removes_nothing(): void {
		$this->add( 'akismet-hu_HU.mo' );

		$this->assertSame( [], $this->remove( [ 'akismet-hu_HU.mo' ], '../akismet' )['deleted'] );
		$this->assertCount( 1, $this->fs->files );
	}
}
