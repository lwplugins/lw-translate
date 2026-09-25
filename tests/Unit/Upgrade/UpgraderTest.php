<?php
/**
 * Tests for Upgrader (runs the 1.1.4 cleanup once).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Upgrade;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Installer\L10nPhpGenerator;
use LightweightPlugins\Translate\Tests\Unit\Fakes\InMemoryFilesystem;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Translate\Upgrade\L10nCleanup;
use LightweightPlugins\Translate\Upgrade\Upgrader;

/**
 * @covers \LightweightPlugins\Translate\Upgrade\Upgrader
 */
final class UpgraderTest extends MonkeyTestCase {

	private function cleanup(): L10nCleanup {
		return new L10nCleanup( [ (string) realpath( __DIR__ ) ], new L10nPhpGenerator( null ) );
	}

	public function test_does_nothing_once_the_stored_version_is_1_1_4_or_newer(): void {
		Functions\when( 'get_option' )->justReturn( '1.1.4' );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\expect( 'WP_Filesystem' )->never();
		Functions\expect( 'update_option' )->never();

		Upgrader::maybe_run();
	}

	public function test_does_not_run_during_ajax_requests(): void {
		Functions\when( 'get_option' )->justReturn( '1.1.3' );
		Functions\when( 'wp_doing_ajax' )->justReturn( true );
		Functions\expect( 'WP_Filesystem' )->never();

		Upgrader::maybe_run();
	}

	public function test_records_the_version_when_the_cleanup_finishes(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )->once()->with( Upgrader::VERSION_OPTION, LW_TRANSLATE_VERSION );
		Functions\expect( 'delete_option' )->once()->with( Upgrader::STATE_OPTION );

		Upgrader::run( $this->cleanup(), new InMemoryFilesystem() );
	}

	public function test_saves_progress_and_keeps_the_old_version_while_files_remain(): void {
		$dir = (string) realpath( __DIR__ );
		$fs  = new InMemoryFilesystem();
		for ( $i = 0; $i <= Upgrader::BATCH_SIZE; $i++ ) {
			$fs->files[ $dir . "/p{$i}-hu_HU.l10n.php" ] = '<?php';
		}

		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )
			->once()
			->with( Upgrader::STATE_OPTION, \Mockery::on( static fn ( $state ): bool => false === $state['done'] ), false );

		Upgrader::run( $this->cleanup(), $fs );
	}
}
