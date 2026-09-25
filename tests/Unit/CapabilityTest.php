<?php
/**
 * Tests for Capability (who may change translation files).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Capability;

/**
 * @covers \LightweightPlugins\Translate\Capability
 */
final class CapabilityTest extends MonkeyTestCase {

	/**
	 * Core maps install_languages to "do not allow" under DISALLOW_FILE_MODS
	 * and for non-super-admins on multisite, so a plain site admin with
	 * manage_options must not pass.
	 */
	public function test_can_install_requires_install_languages_not_manage_options(): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => 'manage_options' === $cap
		);

		$this->assertFalse( Capability::can_install() );
	}

	public function test_can_install_is_true_with_install_languages(): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => 'install_languages' === $cap
		);

		$this->assertTrue( Capability::can_install() );
	}
}
