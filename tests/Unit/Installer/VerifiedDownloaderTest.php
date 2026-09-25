<?php
/**
 * Tests for VerifiedDownloader (blob SHA check before anything is written).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Installer;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Installer\VerifiedDownloader;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\Installer\VerifiedDownloader
 */
final class VerifiedDownloaderTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
	}

	private static function blob_sha( string $content ): string {
		return sha1( 'blob ' . strlen( $content ) . "\0" . $content );
	}

	/**
	 * @param array<string, string> $bodies Remote path => body served.
	 */
	private function downloader( array $bodies ): VerifiedDownloader {
		return new VerifiedDownloader( static fn ( string $path ) => $bodies[ $path ] );
	}

	public function test_returns_every_body_whose_blob_sha_matches_the_tree(): void {
		$files = [
			'a-hu_HU.mo' => [ 'path' => 'p/a-hu_HU.mo', 'sha' => self::blob_sha( 'MO' ) ],
			'a-hu_HU.po' => [ 'path' => 'p/a-hu_HU.po', 'sha' => self::blob_sha( 'PO' ) ],
		];

		$result = $this->downloader( [ 'p/a-hu_HU.mo' => 'MO', 'p/a-hu_HU.po' => 'PO' ] )->fetch_all( $files );

		$this->assertSame( [ 'a-hu_HU.mo' => 'MO', 'a-hu_HU.po' => 'PO' ], $result );
	}

	/**
	 * One tampered file fails the whole item, so the caller writes nothing.
	 */
	public function test_fails_the_whole_item_when_one_body_does_not_match(): void {
		$files = [
			'a-hu_HU.mo' => [ 'path' => 'p/a-hu_HU.mo', 'sha' => self::blob_sha( 'MO' ) ],
			'a-hu_HU.po' => [ 'path' => 'p/a-hu_HU.po', 'sha' => self::blob_sha( 'PO' ) ],
		];

		$result = $this->downloader( [ 'p/a-hu_HU.mo' => 'MO', 'p/a-hu_HU.po' => 'TAMPERED' ] )->fetch_all( $files );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'checksum_mismatch', $result->get_error_code() );
	}

	public function test_rejects_a_file_without_a_sha_in_the_tree(): void {
		$files = [ 'a-hu_HU.mo' => [ 'path' => 'p/a-hu_HU.mo', 'sha' => '' ] ];

		$result = $this->downloader( [ 'p/a-hu_HU.mo' => 'MO' ] )->fetch_all( $files );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_passes_a_download_error_through(): void {
		$error = new WP_Error( 'download_error', 'HTTP 404' );
		$files = [ 'a-hu_HU.mo' => [ 'path' => 'p/a-hu_HU.mo', 'sha' => 'x' ] ];

		$result = ( new VerifiedDownloader( static fn () => $error ) )->fetch_all( $files );

		$this->assertSame( $error, $result );
	}
}
