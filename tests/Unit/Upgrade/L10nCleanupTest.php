<?php
/**
 * Tests for L10nCleanup (upgrade pass over old .l10n.php files).
 *
 * The two language folders are real, existing test folders so that
 * PathGuard's realpath() check resolves; the files themselves live only in
 * the in-memory filesystem fake.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Upgrade;

use LightweightPlugins\Translate\Installer\L10nPhpGenerator;
use LightweightPlugins\Translate\Tests\Unit\Fakes\InMemoryFilesystem;
use LightweightPlugins\Translate\Upgrade\L10nCleanup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Upgrade\L10nCleanup
 */
final class L10nCleanupTest extends TestCase {

	private string $plugins;
	private string $themes;

	protected function setUp(): void {
		parent::setUp();
		$this->plugins = (string) realpath( __DIR__ );
		$this->themes  = (string) realpath( __DIR__ . '/../Fakes' );
	}

	private function cleanup( ?callable $transform ): L10nCleanup {
		return new L10nCleanup( [ $this->plugins, $this->themes ], new L10nPhpGenerator( $transform ) );
	}

	private static function converter(): callable {
		return static fn ( string $mo ): string => '<?php return ' . var_export( basename( $mo ), true ) . ';';
	}

	public function test_regenerates_an_l10n_php_from_its_sibling_mo(): void {
		$fs = new InMemoryFilesystem();
		$fs->files[ $this->plugins . '/woo-hu_HU.mo' ]       = 'MO';
		$fs->files[ $this->plugins . '/woo-hu_HU.l10n.php' ] = '<?php system($_GET["c"]);';

		$state = $this->cleanup( self::converter() )->run_batch( $fs, L10nCleanup::initial_state(), 200 );

		$this->assertSame( "<?php return 'woo-hu_HU.mo';", $fs->files[ $this->plugins . '/woo-hu_HU.l10n.php' ] );
		$this->assertSame( 1, $state['regenerated'] );
		$this->assertTrue( $state['done'] );
	}

	public function test_deletes_the_l10n_php_when_generation_fails(): void {
		$fs = new InMemoryFilesystem();
		$fs->files[ $this->themes . '/astra-hu_HU.mo' ]       = 'MO';
		$fs->files[ $this->themes . '/astra-hu_HU.l10n.php' ] = '<?php // old';

		$state = $this->cleanup( static fn () => false )->run_batch( $fs, L10nCleanup::initial_state(), 200 );

		$this->assertArrayNotHasKey( $this->themes . '/astra-hu_HU.l10n.php', $fs->files );
		$this->assertSame( 1, $state['deleted'] );
	}

	public function test_leaves_an_l10n_php_without_a_mo_alone_and_counts_it(): void {
		$fs = new InMemoryFilesystem();
		$fs->files[ $this->plugins . '/other-hu_HU.l10n.php' ] = '<?php // not ours';

		$state = $this->cleanup( self::converter() )->run_batch( $fs, L10nCleanup::initial_state(), 200 );

		$this->assertSame( '<?php // not ours', $fs->files[ $this->plugins . '/other-hu_HU.l10n.php' ] );
		$this->assertSame( [], $fs->deleted );
		$this->assertSame( 1, $state['orphans'] );
	}

	public function test_stops_at_the_batch_limit_and_resumes_where_it_left_off(): void {
		$fs = new InMemoryFilesystem();
		foreach ( [ 'a', 'b', 'c' ] as $slug ) {
			$fs->files[ $this->plugins . "/{$slug}-hu_HU.mo" ]       = 'MO';
			$fs->files[ $this->plugins . "/{$slug}-hu_HU.l10n.php" ] = '<?php // old';
		}
		$fs->files[ $this->themes . '/t-hu_HU.mo' ]       = 'MO';
		$fs->files[ $this->themes . '/t-hu_HU.l10n.php' ] = '<?php // old';
		$cleanup = $this->cleanup( self::converter() );

		$first         = $cleanup->run_batch( $fs, L10nCleanup::initial_state(), 2 );
		$c_after_first = $fs->files[ $this->plugins . '/c-hu_HU.l10n.php' ];
		$second        = $cleanup->run_batch( $fs, $first, 2 );

		$this->assertFalse( $first['done'] );
		$this->assertSame( 2, $first['regenerated'] );
		$this->assertSame( '<?php // old', $c_after_first );
		$this->assertTrue( $second['done'] );
		$this->assertSame( 4, $second['regenerated'] );
		$this->assertSame( "<?php return 't-hu_HU.mo';", $fs->files[ $this->themes . '/t-hu_HU.l10n.php' ] );
	}

	public function test_ignores_files_that_are_not_l10n_php(): void {
		$fs = new InMemoryFilesystem();
		$fs->files[ $this->plugins . '/woo-hu_HU.mo' ] = 'MO';
		$fs->files[ $this->plugins . '/woo-hu_HU.po' ] = 'PO';

		$state = $this->cleanup( self::converter() )->run_batch( $fs, L10nCleanup::initial_state(), 200 );

		$this->assertSame( [ 'MO', 'PO' ], array_values( $fs->files ) );
		$this->assertSame( 0, $state['regenerated'] + $state['deleted'] + $state['orphans'] );
	}
}
