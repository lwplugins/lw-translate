<?php
/**
 * Tests for the admin REST route permissions.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Rest\Admin\Routes;
use LightweightPlugins\Translate\Rest\Admin\SettingsController;
use LightweightPlugins\Translate\Rest\Admin\TranslationsController;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Rest\Admin\Routes
 * @covers \LightweightPlugins\Translate\Rest\Admin\TranslationsController
 * @covers \LightweightPlugins\Translate\Rest\Admin\SettingsController
 */
final class RoutesTest extends MonkeyTestCase {

	public function test_reading_needs_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );

		$this->assertFalse( Routes::can_manage() );
	}

	/**
	 * Core denies install_languages under DISALLOW_FILE_MODS and to
	 * subsite admins on multisite.
	 */
	public function test_changing_files_needs_install_languages(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'install_languages' )->andReturn( true );

		$this->assertTrue( Routes::can_install() );
	}

	public function test_every_write_route_uses_the_install_permission_and_reading_uses_manage(): void {
		$routes = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $ns, string $path, array $endpoints ) use ( &$routes ): bool {
				$routes[ $path ] = [ $ns, $endpoints[0]['methods'], $endpoints[0]['permission_callback'][1] ];
				return true;
			}
		);

		( new TranslationsController() )->register_routes();

		$this->assertSame(
			[
				'/admin/translations'         => [ 'lw-translate/v1', 'GET', 'can_manage' ],
				'/admin/translations/install' => [ 'lw-translate/v1', 'POST', 'can_install' ],
				'/admin/translations/delete'  => [ 'lw-translate/v1', 'POST', 'can_install' ],
				'/admin/translations/refresh' => [ 'lw-translate/v1', 'POST', 'can_install' ],
			],
			$routes
		);
	}

	public function test_the_settings_routes_read_and_save_with_manage_options(): void {
		$endpoints = [];
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $ns, string $path, array $list ) use ( &$endpoints ): bool {
				foreach ( $list as $endpoint ) {
					$endpoints[ $endpoint['methods'] ] = [ $ns . $path, $endpoint['permission_callback'] ];
				}
				return true;
			}
		);

		( new SettingsController() )->register_routes();

		$this->assertSame( [ 'lw-translate/v1/admin/settings', [ Routes::class, 'can_manage' ] ], $endpoints['GET'] );
		$this->assertSame( [ 'lw-translate/v1/admin/settings', [ Routes::class, 'can_manage' ] ], $endpoints['POST'] );
	}
}
