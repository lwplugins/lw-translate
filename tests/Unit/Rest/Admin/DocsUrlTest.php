<?php
/**
 * Tests for the docs.lwplugins.com link of the admin screen.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Rest\Admin\SettingsMeta;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * The Docs link follows the admin user's locale.
 */
final class DocsUrlTest extends MonkeyTestCase {

	/**
	 * Hungarian users get the Hungarian page.
	 *
	 * @return void
	 */
	public function test_hungarian_locale_gets_hu_page(): void {
		Functions\when( 'get_user_locale' )->justReturn( 'hu_HU' );

		$this->assertSame( 'https://docs.lwplugins.com/hu/plugins/lw-translate', SettingsMeta::docs_url() );
	}

	/**
	 * English and every other locale get the English page.
	 *
	 * @return void
	 */
	public function test_other_locales_get_en_page(): void {
		foreach ( [ 'en_US', 'de_DE', '' ] as $locale ) {
			Functions\when( 'get_user_locale' )->justReturn( $locale );

			$this->assertSame( 'https://docs.lwplugins.com/en/plugins/lw-translate', SettingsMeta::docs_url() );
		}
	}
}
