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

	private function remover(): TranslationRemover {
		return new TranslationRemover();
	}

	/**
	 * Before 1.2.0 delete took the file names from the current repository
	 * tree, so a file removed upstream could never be deleted.
	 */
	public function test_it_removes_the_installed_files_of_the_item_found_on_disk(): void {
		$this->add(
			'akismet-hu_HU.mo',
			'akismet-hu_HU.po',
			'akismet-hu_HU.l10n.php',
			'akismet-hu_HU-0123456789abcdef0123456789abcdef.json'
		);

		$result = $this->remover()->remove( $this->fs, $this->base, 'akismet', 'hu_HU' );

		$this->assertSame(
			[
				'akismet-hu_HU-0123456789abcdef0123456789abcdef.json',
				'akismet-hu_HU.l10n.php',
				'akismet-hu_HU.mo',
				'akismet-hu_HU.po',
			],
			$result['deleted']
		);
		$this->assertSame( [], $result['failed'] );
		$this->assertSame( [], $this->fs->files );
	}

	public function test_it_leaves_other_items_and_other_locales_alone(): void {
		$this->add( 'akismet-hu_HU.mo', 'akismet-de_DE.mo', 'akismet-extra-hu_HU.mo', 'other-hu_HU.mo', 'akismet-hu_HU.txt' );

		$result = $this->remover()->remove( $this->fs, $this->base, 'akismet', 'hu_HU' );

		$this->assertSame( [ 'akismet-hu_HU.mo' ], $result['deleted'] );
		$this->assertCount( 4, $this->fs->files );
	}

	public function test_nothing_installed_reports_nothing_deleted(): void {
		$result = $this->remover()->remove( $this->fs, $this->base, 'akismet', 'hu_HU' );

		$this->assertSame(
			[
				'deleted' => [],
				'failed'  => [],
			],
			$result
		);
	}

	public function test_a_file_that_survives_the_delete_is_reported_as_failed(): void {
		$fs                = new InMemoryFilesystem();
		$fs->refuse_delete = true;
		$fs->files[ $this->base . '/akismet-hu_HU.po' ] = 'x';

		$result = $this->remover()->remove( $fs, $this->base, 'akismet', 'hu_HU' );

		$this->assertSame( [], $result['deleted'] );
		$this->assertSame( [ 'akismet-hu_HU.po' ], $result['failed'] );
	}

	public function test_an_invalid_slug_removes_nothing(): void {
		$this->add( 'akismet-hu_HU.mo' );

		$result = $this->remover()->remove( $this->fs, $this->base, '../akismet', 'hu_HU' );

		$this->assertSame( [], $result['deleted'] );
		$this->assertCount( 1, $this->fs->files );
	}
}
