<?php
/**
 * Tests for L10nPhpGenerator (local .l10n.php built from the .mo).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use LightweightPlugins\Translate\Installer\L10nPhpGenerator;
use LightweightPlugins\Translate\Tests\Unit\Fakes\InMemoryFilesystem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Installer\L10nPhpGenerator
 */
final class L10nPhpGeneratorTest extends TestCase {

	private const MO  = '/lang/plugins/woocommerce-hu_HU.mo';
	private const PHP = '/lang/plugins/woocommerce-hu_HU.l10n.php';

	public function test_writes_the_converter_output_for_the_mo_next_to_it(): void {
		$calls     = [];
		$generator = new L10nPhpGenerator(
			static function ( string $file, string $format ) use ( &$calls ) {
				$calls[] = [ $file, $format ];
				return "<?php\nreturn ['messages' => []];";
			}
		);
		$fs        = new InMemoryFilesystem();

		$result = $generator->generate( self::MO, $fs );

		$this->assertTrue( $result );
		$this->assertSame( [ [ self::MO, 'php' ] ], $calls );
		$this->assertSame( [ self::PHP => "<?php\nreturn ['messages' => []];" ], $fs->files );
	}

	public function test_skips_generation_and_removes_a_stale_file_without_the_core_converter(): void {
		$generator                = new L10nPhpGenerator( null );
		$fs                       = new InMemoryFilesystem();
		$fs->files[ self::PHP ]   = '<?php // stale';

		$result = $generator->generate( self::MO, $fs );

		$this->assertFalse( $result );
		$this->assertSame( [], $fs->files );
	}

	public function test_removes_a_stale_file_when_the_conversion_fails(): void {
		$generator              = new L10nPhpGenerator( static fn () => false );
		$fs                     = new InMemoryFilesystem();
		$fs->files[ self::PHP ] = '<?php // stale';

		$result = $generator->generate( self::MO, $fs );

		$this->assertFalse( $result );
		$this->assertSame( [ self::PHP ], $fs->deleted );
	}

	public function test_remove_deletes_the_l10n_php_belonging_to_a_mo(): void {
		$generator              = new L10nPhpGenerator( null );
		$fs                     = new InMemoryFilesystem();
		$fs->files[ self::PHP ] = '<?php // generated';

		$generator->remove( self::MO, $fs );

		$this->assertSame( [ self::PHP ], $fs->deleted );
	}

	public function test_remove_does_nothing_when_there_is_no_l10n_php(): void {
		$generator = new L10nPhpGenerator( null );
		$fs        = new InMemoryFilesystem();

		$generator->remove( self::MO, $fs );

		$this->assertSame( [], $fs->deleted );
	}
}
