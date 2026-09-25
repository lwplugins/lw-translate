<?php
/**
 * Tests for the TranslateService write guards.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\SiteManager;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\SiteManager\TranslateService;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\SiteManager\TranslateService
 */
final class TranslateServiceTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_write_calls
	 */
	public function test_write_call_is_refused_without_install_languages( string $method ): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'get_transient' )->never();

		$result = TranslateService::$method( [ 'slug' => 'woocommerce', 'type' => 'plugin' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'forbidden', $result->get_error_code() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_write_calls(): array {
		return [
			'install' => [ 'install_translation' ],
			'update'  => [ 'update_translations' ],
		];
	}
}
