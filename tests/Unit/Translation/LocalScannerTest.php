<?php
/**
 * Tests for LocalScanner: text domains from the headers and the local
 * PO-Revision-Date lookup.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Translation\LocalScanner;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\LocalScanner
 */
final class LocalScannerTest extends MonkeyTestCase {

	private const DIR = WP_LANG_DIR . '/plugins';

	/**
	 * Files this test wrote.
	 *
	 * @var array<int, string>
	 */
	private array $written = [];

	protected function tearDown(): void {
		foreach ( $this->written as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}

		parent::tearDown();
	}

	private function write_po( string $name, string $date ): void {
		if ( ! is_dir( self::DIR ) ) {
			mkdir( self::DIR, 0777, true );
		}

		$path = self::DIR . '/' . $name;
		file_put_contents( $path, "msgid \"\"\nmsgstr \"\"\n\"PO-Revision-Date: {$date}\\n\"\n" );
		$this->written[] = $path;
	}

	public function test_get_local_date_reads_the_po_named_after_the_text_domain(): void {
		$this->write_po( 'complianz-gdpr-hu_HU.po', '2026-10-01 10:00+0000' );

		$this->assertSame( '2026-10-01 10:00+0000', LocalScanner::get_local_date( 'complianz-gdpr-premium', 'plugin', 'hu_HU', 'complianz-gdpr' ) );
		$this->assertSame( '', LocalScanner::get_local_date( 'complianz-gdpr-premium', 'plugin', 'hu_HU' ) );
	}

	public function test_get_local_date_prefers_the_text_domain_po_and_falls_back_to_the_slug(): void {
		$this->write_po( 'admin-menu-editor-pro-hu_HU.po', '2026-01-01 10:00+0000' );

		$this->assertSame( '2026-01-01 10:00+0000', LocalScanner::get_local_date( 'admin-menu-editor-pro', 'plugin', 'hu_HU', 'admin-menu-editor' ) );

		$this->write_po( 'admin-menu-editor-hu_HU.po', '2026-02-02 10:00+0000' );

		$this->assertSame( '2026-02-02 10:00+0000', LocalScanner::get_local_date( 'admin-menu-editor-pro', 'plugin', 'hu_HU', 'admin-menu-editor' ) );
	}

	public function test_get_local_date_ignores_an_invalid_text_domain(): void {
		$this->assertSame( '', LocalScanner::get_local_date( 'akismet', 'plugin', 'hu_HU', '../../evil' ) );
	}

	public function test_get_text_domains_reads_the_plugin_headers_by_folder(): void {
		Functions\when( 'get_plugins' )->justReturn(
			[
				'complianz-gdpr-premium/complianz-gpdr-premium.php' => [
					'Name'       => 'Complianz Premium',
					'TextDomain' => 'complianz-gdpr',
				],
				'hello.php'                                         => [
					'Name'       => 'Hello Dolly',
					'TextDomain' => '',
				],
			]
		);

		$this->assertSame( [ 'complianz-gdpr-premium' => 'complianz-gdpr' ], LocalScanner::get_text_domains( 'plugin' ) );
		$this->assertSame( 'complianz-gdpr', LocalScanner::get_text_domain( 'plugin', 'complianz-gdpr-premium' ) );
		$this->assertSame( '', LocalScanner::get_text_domain( 'plugin', 'hello' ) );
	}

	public function test_get_text_domains_reads_the_theme_headers(): void {
		$theme = new class() {
			/**
			 * Header value.
			 *
			 * @param string $header Header name.
			 * @return string
			 */
			public function get( string $header ): string {
				return 'TextDomain' === $header ? 'astra-child-domain' : '';
			}
		};

		Functions\when( 'wp_get_themes' )->justReturn( [ 'astra-child' => $theme ] );

		$this->assertSame( [ 'astra-child' => 'astra-child-domain' ], LocalScanner::get_text_domains( 'theme' ) );
	}
}
