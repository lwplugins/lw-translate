<?php
/**
 * Tests for the Site Manager ability permission callbacks.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\SiteManager;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\SiteManager\TranslateAbilities;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\SiteManager\TranslateAbilities
 */
final class TranslateAbilitiesTest extends MonkeyTestCase {

	/**
	 * Registers the abilities and returns their permission callbacks.
	 *
	 * @return array<string, callable>
	 */
	private function registered_permission_callbacks(): array {
		$callbacks = [];

		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( &$callbacks ): void {
				$callbacks[ $name ] = $args['permission_callback'];
			}
		);

		$permissions = new class() {
			public function callback( string $check ): callable {
				return static fn (): string => 'site-manager:' . $check;
			}
		};

		TranslateAbilities::register( $permissions );

		return $callbacks;
	}

	/**
	 * @dataProvider provide_write_abilities
	 */
	public function test_write_ability_requires_install_languages( string $ability ): void {
		$callbacks = $this->registered_permission_callbacks();
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => 'manage_options' === $cap
		);

		$this->assertFalse( call_user_func( $callbacks[ $ability ] ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provide_write_abilities(): array {
		return [
			'install' => [ 'lw-translate/install-translation' ],
			'update'  => [ 'lw-translate/update-translations' ],
		];
	}

	public function test_read_only_abilities_keep_the_site_manager_options_check(): void {
		$callbacks = $this->registered_permission_callbacks();

		$this->assertSame( 'site-manager:can_manage_options', call_user_func( $callbacks['lw-translate/list-translations'] ) );
	}
}
