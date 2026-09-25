<?php
/**
 * Tests for the admin REST controllers, end to end over stubbed WordPress.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Rest\Admin\SettingsController;
use LightweightPlugins\Translate\Rest\Admin\TranslationsController;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_Error;
use WP_REST_Request;

/**
 * @covers \LightweightPlugins\Translate\Rest\Admin\TranslationsController
 * @covers \LightweightPlugins\Translate\Rest\Admin\SettingsController
 * @covers \LightweightPlugins\Translate\Rest\Admin\SettingsMeta
 */
final class ControllersTest extends MonkeyTestCase {

	/**
	 * Transients by name.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Transient names deleted during the test.
	 *
	 * @var array<int, string>
	 */
	private array $deleted = [];

	/**
	 * Stored options row.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();

		$this->stored     = [
			'tone'      => 'informal',
			'locale'    => 'hu_HU',
			'cache_ttl' => 43200,
		];
		$this->transients = [
			'lw_translate_tree_cache'              => [
				'tree'       => [
					[ 'type' => 'tree', 'path' => 'informal/plugins/hu_HU' ],
					[ 'type' => 'blob', 'path' => 'informal/plugins/hu_HU/akismet/akismet-hu_HU.mo', 'sha' => 'x' ],
				],
				'truncated'  => true,
				'fetched_at' => time() - 120,
			],
			'lw_translate_compare_hu_HU_informal' => [
				new TranslationItem( 'akismet', 'Akismet', 'plugin', TranslationItem::STATUS_UPDATE, 1, '', [ 'akismet-hu_HU.mo' => 'x' ] ),
			],
		];

		Functions\when( 'get_option' )->alias( fn () => $this->stored );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ): bool {
				$this->stored = $value;
				return true;
			}
		);
		Functions\when( 'wp_parse_args' )->alias( static fn ( $args, $defaults ): array => array_merge( (array) $defaults, (array) $args ) );
		Functions\when( 'get_transient' )->alias( fn ( $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'delete_transient' )->alias(
			function ( $key ): bool {
				$this->deleted[] = $key;
				unset( $this->transients[ $key ] );
				return true;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn( [ 'akismet/akismet.php' => [ 'Name' => 'Akismet' ] ] );
		Functions\when( 'wp_get_themes' )->justReturn( [] );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_the_list_carries_rows_counts_warnings_cache_and_source(): void {
		$data = ( new TranslationsController() )->get_list()->get_data();

		$this->assertSame( 'plugin:akismet', $data['rows'][0]['id'] );
		$this->assertSame( 'update', $data['rows'][0]['status'] );
		$this->assertSame( 'https://github.com/hellowpio/wordpress-translations/tree/main/informal/plugins/hu_HU/akismet', $data['rows'][0]['remote']['url'] );
		$this->assertSame( 1, $data['counts']['update'] );
		$this->assertSame( 'tree_truncated', $data['warnings'][0]['code'] );
		$this->assertGreaterThanOrEqual( 120, $data['cache']['age'] );
		$this->assertSame( 43200, $data['cache']['ttl'] );
		$this->assertSame( 'hellowpio/wordpress-translations', $data['source']['repo'] );
		$this->assertSame( 'informal', $data['source']['tone'] );
		$this->assertTrue( $data['can_install'] );
	}

	public function test_install_refuses_malformed_items_with_400(): void {
		$result = ( new TranslationsController() )->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'x', 'slug' => 'a' ] ] ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * The repository has no folder for "hello-dolly": the item fails with a
	 * message, and the answer still carries the (fresh) list.
	 */
	public function test_install_answers_per_item_results_plus_the_fresh_list(): void {
		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'hello-dolly' ] ] ] ) )
			->get_data();

		$this->assertSame(
			[
				'type'    => 'plugin',
				'slug'    => 'hello-dolly',
				'ok'      => false,
				'message' => 'No translation files found for this item.',
				'skipped' => [],
			],
			$data['results'][0]
		);
		$this->assertArrayHasKey( 'rows', $data );
		$this->assertContains( 'lw_translate_compare_hu_HU_informal', $this->deleted, 'The comparison cache is cleared after an action.' );
		$this->assertSame( 'not_installed', $data['rows'][0]['status'], 'The list is compared again, not served from the old cache.' );
	}

	public function test_refresh_clears_the_tree_and_comparison_caches(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'offline' ) );

		$data = ( new TranslationsController() )->refresh()->get_data();

		$this->assertContains( 'lw_translate_tree_cache', $this->deleted );
		$this->assertSame( [], $data['rows'] );
		$this->assertSame( 'http_request_failed', $data['warnings'][0]['code'] );
		$this->assertSame( 'error', $data['warnings'][0]['level'] );
	}

	public function test_settings_get_returns_options_and_meta(): void {
		$data = ( new SettingsController() )->get_settings()->get_data();

		$this->assertSame(
			[
				'tone'      => 'informal',
				'locale'    => 'hu_HU',
				'cache_ttl' => 43200,
			],
			$data['options']
		);
		$this->assertSame( [ 'formal', 'informal' ], $data['meta']['tones'] );
		$this->assertSame( 'hu_HU', $data['meta']['locales'][0]['value'] );
		$this->assertSame(
			[
				'min' => 3600,
				'max' => 604800,
			],
			$data['meta']['ranges']['cache_ttl']
		);
	}

	public function test_settings_post_saves_a_valid_partial_update(): void {
		$result = ( new SettingsController() )->save_settings( new WP_REST_Request( [ 'cache_ttl' => 86400 ] ) );

		$this->assertSame( 86400, $result->get_data()['options']['cache_ttl'] );
		$this->assertSame( 'informal', $this->stored['tone'] );
	}

	public function test_settings_post_refuses_a_locale_the_repository_does_not_offer(): void {
		$result = ( new SettingsController() )->save_settings( new WP_REST_Request( [ 'locale' => 'de_DE' ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertArrayHasKey( 'locale', $result->get_error_data()['fields'] );
		$this->assertSame( 'hu_HU', $this->stored['locale'] );
	}

	public function test_settings_post_refuses_an_oversized_body(): void {
		$result = ( new SettingsController() )->save_settings( new WP_REST_Request( [], str_repeat( 'x', 9000 ) ) );

		$this->assertSame( 413, $result->get_error_data()['status'] );
	}
}
