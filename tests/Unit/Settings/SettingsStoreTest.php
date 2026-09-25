<?php
/**
 * Tests for partial, atomic settings saves.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Settings\SettingsStore;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Settings\SettingsStore
 */
final class SettingsStoreTest extends MonkeyTestCase {

	/**
	 * Stored option row.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	private int $writes = 0;

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();

		$this->stored = [
			'tone'      => 'informal',
			'locale'    => 'hu_HU',
			'cache_ttl' => 7200,
			'legacy'    => 'dropped',
		];
		$this->writes = 0;

		Functions\when( 'get_option' )->alias( fn () => $this->stored );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ): bool {
				$this->stored = $value;
				++$this->writes;
				return true;
			}
		);
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_current_returns_typed_values(): void {
		$this->stored['cache_ttl'] = '7200';

		$this->assertSame(
			[
				'tone'      => 'informal',
				'locale'    => 'hu_HU',
				'cache_ttl' => 7200,
			],
			SettingsStore::current()
		);
	}

	public function test_current_clamps_an_out_of_range_ttl_saved_by_an_older_version(): void {
		$this->stored['cache_ttl'] = 0;

		$this->assertSame( 3600, SettingsStore::current()['cache_ttl'] );
	}

	public function test_a_partial_save_keeps_the_other_keys(): void {
		$errors = SettingsStore::save( [ 'cache_ttl' => 86400 ], [ 'hu_HU' ] );

		$this->assertSame( [], $errors );
		$this->assertSame(
			[
				'tone'      => 'informal',
				'locale'    => 'hu_HU',
				'cache_ttl' => 86400,
			],
			$this->stored
		);
	}

	public function test_nothing_is_saved_when_any_field_is_invalid(): void {
		$errors = SettingsStore::save(
			[
				'tone'      => 'formal',
				'cache_ttl' => 1,
			],
			[ 'hu_HU' ]
		);

		$this->assertSame( [ 'cache_ttl' ], array_keys( $errors ) );
		$this->assertSame( 0, $this->writes );
		$this->assertSame( 'informal', $this->stored['tone'] );
	}

	public function test_an_empty_body_writes_nothing(): void {
		$this->assertSame( [], SettingsStore::save( [], [ 'hu_HU' ] ) );
		$this->assertSame( 0, $this->writes );
	}

	public function test_no_key_is_locked_today(): void {
		$this->assertSame( [], SettingsStore::locked() );
	}
}
