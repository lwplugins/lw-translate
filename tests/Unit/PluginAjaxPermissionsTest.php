<?php
/**
 * Tests for the capability check of the AJAX write actions.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Plugin;
use RuntimeException;

/**
 * @covers \LightweightPlugins\Translate\Plugin
 */
final class PluginAjaxPermissionsTest extends MonkeyTestCase {

	protected function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * A site admin (manage_options) without install_languages -- e.g. a
	 * subsite admin on multisite, or any admin under DISALLOW_FILE_MODS --
	 * is refused before any file work starts.
	 *
	 * @dataProvider provide_write_actions
	 */
	public function test_write_action_is_refused_without_install_languages( string $method ): void {
		$_POST = [
			'slug'  => 'woocommerce',
			'type'  => 'plugin',
			'items' => [ 'plugin:woocommerce' ],
		];

		Functions\when( 'check_ajax_referer' )->justReturn( 1 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => 'manage_options' === $cap
		);
		Functions\when( 'wp_send_json_error' )->alias(
			static function ( $data ): void {
				throw new RuntimeException( (string) $data['message'] );
			}
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Insufficient permissions.' );

		// The constructor only registers hooks; the handlers under test need none of that.
		$plugin = ( new \ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
		$plugin->$method();
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_write_actions(): array {
		return [
			'install'       => [ 'ajax_install' ],
			'bulk install'  => [ 'ajax_bulk_install' ],
			'delete'        => [ 'ajax_delete' ],
			'refresh cache' => [ 'ajax_refresh_cache' ],
		];
	}
}
