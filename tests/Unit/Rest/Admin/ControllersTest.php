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
use LightweightPlugins\Translate\Tests\Unit\Fakes\InMemoryFilesystem;
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
		Functions\when( 'get_user_locale' )->justReturn( 'en_US' );

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

		$data = ( new TranslationsController() )->refresh( new WP_REST_Request() )->get_data();

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

	public function test_install_delete_and_refresh_refuse_an_oversized_body_with_413(): void {
		$controller = new TranslationsController();
		$request    = new WP_REST_Request( [], str_repeat( 'x', 20000 ) );

		foreach ( [ 'install', 'delete', 'refresh' ] as $method ) {
			$result = $controller->$method( $request );

			$this->assertInstanceOf( WP_Error::class, $result, $method );
			$this->assertSame( 413, $result->get_error_data()['status'], $method );
		}
	}

	/**
	 * GitHub is reachable (cached tree) but the item has nothing installed:
	 * the per-item result is an honest failure and nothing else changes.
	 */
	public function test_delete_answers_per_item_results_plus_the_fresh_list(): void {
		$this->filesystem();

		$data = ( new TranslationsController() )
			->delete( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'akismet' ] ] ] ) )
			->get_data();

		$this->assertSame(
			[
				'type'    => 'plugin',
				'slug'    => 'akismet',
				'ok'      => false,
				'message' => 'No installed translation files were found for this item.',
				'deleted' => [],
			],
			$data['results'][0]
		);
		$this->assertArrayHasKey( 'rows', $data );
	}

	public function test_install_succeeds_with_a_verified_download(): void {
		$content = 'mo-bytes';
		$sha     = sha1( 'blob ' . strlen( $content ) . "\0" . $content );
		$fs      = $this->filesystem();

		// PathGuard resolves the target folder with realpath(); the file
		// itself is only written to the in-memory filesystem.
		if ( ! is_dir( WP_LANG_DIR . '/plugins' ) ) {
			mkdir( WP_LANG_DIR . '/plugins', 0777, true );
		}

		Functions\when( 'get_plugins' )->justReturn( [ 'hello-dolly/hello.php' => [ 'Name' => 'Hello Dolly' ] ] );
		$this->transients['lw_translate_tree_cache']['tree'][] = [
			'type' => 'blob',
			'path' => 'informal/plugins/hu_HU/hello-dolly/hello-dolly-hu_HU.mo',
			'sha'  => $sha,
		];
		Functions\when( 'wp_remote_get' )->justReturn( [ 'body' => $content ] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $content );

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'hello-dolly' ] ] ] ) )
			->get_data();

		$this->assertTrue( $data['results'][0]['ok'], $data['results'][0]['message'] );
		$this->assertSame( 'Translation installed successfully.', $data['results'][0]['message'] );
		$this->assertSame( $content, $fs->files[ WP_LANG_DIR . '/plugins/hello-dolly-hu_HU.mo' ] );
	}

	/**
	 * The folder is complianz-gdpr-premium, the text domain complianz-gdpr:
	 * the domain-named .mo (the one WordPress loads) is installed.
	 */
	public function test_install_takes_the_file_named_after_the_text_domain(): void {
		$content = 'mo-bytes';
		$sha     = sha1( 'blob ' . strlen( $content ) . "\0" . $content );
		$fs      = $this->filesystem();

		if ( ! is_dir( WP_LANG_DIR . '/plugins' ) ) {
			mkdir( WP_LANG_DIR . '/plugins', 0777, true );
		}

		Functions\when( 'get_plugins' )->justReturn(
			[
				'complianz-gdpr-premium/complianz-gpdr-premium.php' => [
					'Name'       => 'Complianz Premium',
					'TextDomain' => 'complianz-gdpr',
				],
			]
		);
		$this->transients['lw_translate_tree_cache']['tree'][] = [
			'type' => 'blob',
			'path' => 'informal/plugins/hu_HU/complianz-gdpr-premium/complianz-gdpr-hu_HU.mo',
			'sha'  => $sha,
		];
		Functions\when( 'wp_remote_get' )->justReturn( [ 'body' => $content ] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $content );

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'complianz-gdpr-premium' ] ] ] ) )
			->get_data();

		$this->assertTrue( $data['results'][0]['ok'], $data['results'][0]['message'] );
		$this->assertSame( [], $data['results'][0]['skipped'] );
		$this->assertSame( $content, $fs->files[ WP_LANG_DIR . '/plugins/complianz-gdpr-hu_HU.mo' ] );
	}

	/**
	 * Admin Menu Editor Pro has no Text Domain header: the single prefix of
	 * its repository folder (admin-menu-editor) is the domain.
	 */
	public function test_install_without_a_header_takes_the_single_prefix_of_the_folder(): void {
		$fs = $this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.po', 'readme.txt' ] );

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'admin-menu-editor-pro' ] ] ] ) )
			->get_data();

		$this->assertTrue( $data['results'][0]['ok'], $data['results'][0]['message'] );
		$this->assertSame( [ 'readme.txt' ], $data['results'][0]['skipped'] );
		$this->assertSame( 'mo-bytes', $fs->files[ WP_LANG_DIR . '/plugins/admin-menu-editor-hu_HU.mo' ] );
		$this->assertSame( 'mo-bytes', $fs->files[ WP_LANG_DIR . '/plugins/admin-menu-editor-hu_HU.po' ] );
	}

	/**
	 * The list uses the same domain: the row is there, with the
	 * domain-named files counted, and a refused add-on folder is not.
	 */
	public function test_the_list_offers_the_headerless_item_with_its_resolved_domain_files(): void {
		$this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.po', 'readme.txt' ] );
		$this->transients['lw_translate_tree_cache']['tree'][] = [
			'type' => 'blob',
			'path' => 'informal/plugins/hu_HU/woo-addon/akismet-hu_HU.mo',
			'sha'  => 'y',
		];
		Functions\when( 'get_plugins' )->justReturn(
			[
				'admin-menu-editor-pro/menu-editor.php' => [ 'Name' => 'Admin Menu Editor Pro' ],
				'woo-addon/woo-addon.php'               => [ 'Name' => 'Woo Addon' ],
				'akismet/akismet.php'                   => [ 'Name' => 'Akismet' ],
			]
		);
		unset( $this->transients['lw_translate_compare_hu_HU_informal'] );

		$rows = ( new TranslationsController() )->get_list()->get_data()['rows'];
		$ids  = array_column( $rows, 'id' );

		$this->assertContains( 'plugin:admin-menu-editor-pro', $ids );
		$this->assertNotContains( 'plugin:woo-addon', $ids );
		$this->assertSame( 2, $rows[ array_search( 'plugin:admin-menu-editor-pro', $ids, true ) ]['files'] );
		$this->assertSame( 'not_installed', $rows[ array_search( 'plugin:admin-menu-editor-pro', $ids, true ) ]['status'] );
	}

	public function test_install_without_a_header_takes_nothing_from_a_folder_with_two_prefixes(): void {
		$fs = $this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo', 'ame-hu_HU.mo' ] );

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'admin-menu-editor-pro' ] ] ] ) )
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertSame( 'No translation files found for this item.', $data['results'][0]['message'] );
		$this->assertSame( [], $fs->files );
	}

	public function test_with_two_prefixes_only_the_folder_named_file_is_installed(): void {
		$fs = $this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo', 'ame-hu_HU.mo', 'admin-menu-editor-pro-hu_HU.mo' ] );

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'admin-menu-editor-pro' ] ] ] ) )
			->get_data();

		$this->assertTrue( $data['results'][0]['ok'], $data['results'][0]['message'] );
		$this->assertSame( [ 'admin-menu-editor-hu_HU.mo', 'ame-hu_HU.mo' ], $data['results'][0]['skipped'] );
		$this->assertArrayHasKey( WP_LANG_DIR . '/plugins/admin-menu-editor-pro-hu_HU.mo', $fs->files );
		$this->assertArrayNotHasKey( WP_LANG_DIR . '/plugins/admin-menu-editor-hu_HU.mo', $fs->files );
		$this->assertArrayNotHasKey( WP_LANG_DIR . '/plugins/ame-hu_HU.mo', $fs->files );
	}

	/**
	 * An add-on folder must not overwrite another installed plugin's file.
	 */
	public function test_a_prefix_that_is_another_installed_plugin_is_never_installed(): void {
		$fs = $this->headerless_plugin_with_files( [ 'woocommerce-hu_HU.mo' ], 'woo-addon' );
		Functions\when( 'get_plugins' )->justReturn(
			[
				'woo-addon/woo-addon.php'     => [ 'Name' => 'Woo Addon' ],
				'woocommerce/woocommerce.php' => [ 'Name' => 'WooCommerce' ],
			]
		);

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'woo-addon' ] ] ] ) )
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertSame( [], $fs->files );
	}

	public function test_a_prefix_that_is_another_installed_plugins_text_domain_is_never_installed(): void {
		$fs = $this->headerless_plugin_with_files( [ 'wc-hu_HU.mo' ], 'woo-addon' );
		Functions\when( 'get_plugins' )->justReturn(
			[
				'woo-addon/woo-addon.php'     => [ 'Name' => 'Woo Addon' ],
				'woocommerce/woocommerce.php' => [
					'Name'       => 'WooCommerce',
					'TextDomain' => 'wc',
				],
			]
		);

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'woo-addon' ] ] ] ) )
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertSame( [], $fs->files );
	}

	/**
	 * Two header-less plugins whose folders both offer shared-hu_HU.mo:
	 * the prefix belongs to neither, so neither installs it.
	 */
	public function test_a_prefix_two_installed_plugins_would_take_is_installed_for_neither(): void {
		$fs = $this->headerless_plugin_with_files( [ 'shared-hu_HU.mo' ], 'shared-pro' );
		$this->headerless_plugin_with_files( [ 'shared-hu_HU.mo' ], 'shared-lite' );
		Functions\when( 'get_plugins' )->justReturn(
			[
				'shared-pro/shared-pro.php'   => [ 'Name' => 'Shared Pro' ],
				'shared-lite/shared-lite.php' => [ 'Name' => 'Shared Lite' ],
			]
		);

		$data = ( new TranslationsController() )
			->install(
				new WP_REST_Request(
					[
						'items' => [
							[ 'type' => 'plugin', 'slug' => 'shared-pro' ],
							[ 'type' => 'plugin', 'slug' => 'shared-lite' ],
						],
					]
				)
			)
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertFalse( $data['results'][1]['ok'] );
		$this->assertSame( [], $fs->files );
	}

	/**
	 * The add-on's WooCommerce-hu_HU.mo is woocommerce-hu_HU.mo on a
	 * case-insensitive filesystem.
	 */
	public function test_a_prefix_differing_from_another_plugin_only_in_case_is_never_installed(): void {
		$fs = $this->headerless_plugin_with_files( [ 'WooCommerce-hu_HU.mo' ], 'woo-addon' );
		Functions\when( 'get_plugins' )->justReturn(
			[
				'woo-addon/woo-addon.php'     => [ 'Name' => 'Woo Addon' ],
				'woocommerce/woocommerce.php' => [ 'Name' => 'WooCommerce' ],
			]
		);

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'woo-addon' ] ] ] ) )
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertSame( [], $fs->files );
	}

	/**
	 * With a header domain the repository prefix does not count.
	 */
	public function test_with_a_header_domain_the_repository_prefix_is_ignored(): void {
		$fs = $this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo' ] );
		Functions\when( 'get_plugins' )->justReturn(
			[
				'admin-menu-editor-pro/menu-editor.php' => [
					'Name'       => 'Admin Menu Editor Pro',
					'TextDomain' => 'ame',
				],
			]
		);

		$data = ( new TranslationsController() )
			->install( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'admin-menu-editor-pro' ] ] ] ) )
			->get_data();

		$this->assertFalse( $data['results'][0]['ok'] );
		$this->assertSame( [], $fs->files );
	}

	/**
	 * Delete takes the listed, allowed names of the resolved domain and the
	 * .l10n.php generated from the .mo; nothing else.
	 */
	public function test_delete_without_a_header_removes_the_listed_domain_files_and_the_l10n_php(): void {
		$fs  = $this->headerless_plugin_with_files( [ 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.po' ] );
		$dir = WP_LANG_DIR . '/plugins/';

		foreach ( [ 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.l10n.php', 'admin-menu-editor-hu_HU.po', 'admin-menu-editor-hu_HU-ffffffffffffffffffffffffffffffff.json', 'woocommerce-hu_HU.mo' ] as $name ) {
			$fs->files[ $dir . $name ] = 'x';
		}

		$data = ( new TranslationsController() )
			->delete( new WP_REST_Request( [ 'items' => [ [ 'type' => 'plugin', 'slug' => 'admin-menu-editor-pro' ] ] ] ) )
			->get_data();

		$this->assertTrue( $data['results'][0]['ok'], $data['results'][0]['message'] );
		$this->assertSame(
			[ 'admin-menu-editor-hu_HU.l10n.php', 'admin-menu-editor-hu_HU.mo', 'admin-menu-editor-hu_HU.po' ],
			$data['results'][0]['deleted']
		);
		$this->assertSame(
			[ $dir . 'admin-menu-editor-hu_HU-ffffffffffffffffffffffffffffffff.json', $dir . 'woocommerce-hu_HU.mo' ],
			array_keys( $fs->files )
		);
	}

	/**
	 * An installed plugin without a TextDomain header, whose repository
	 * folder holds the given files (each with the content "mo-bytes").
	 *
	 * @param array<int, string> $names File names in the repository folder.
	 * @param string             $slug  Plugin folder.
	 * @return InMemoryFilesystem
	 */
	private function headerless_plugin_with_files( array $names, string $slug = 'admin-menu-editor-pro' ): InMemoryFilesystem {
		$content = 'mo-bytes';
		$sha     = sha1( 'blob ' . strlen( $content ) . "\0" . $content );
		$fs      = $this->filesystem();

		if ( ! is_dir( WP_LANG_DIR . '/plugins' ) ) {
			mkdir( WP_LANG_DIR . '/plugins', 0777, true );
		}

		Functions\when( 'get_plugins' )->justReturn( [ $slug . '/' . $slug . '.php' => [ 'Name' => $slug ] ] );

		foreach ( $names as $name ) {
			$this->transients['lw_translate_tree_cache']['tree'][] = [
				'type' => 'blob',
				'path' => 'informal/plugins/hu_HU/' . $slug . '/' . $name,
				'sha'  => $sha,
			];
		}

		Functions\when( 'wp_remote_get' )->justReturn( [ 'body' => $content ] );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $content );

		return $fs;
	}

	/**
	 * Put an in-memory filesystem behind WP_Filesystem().
	 *
	 * @return InMemoryFilesystem
	 */
	private function filesystem(): InMemoryFilesystem {
		global $wp_filesystem;

		$wp_filesystem = new InMemoryFilesystem();
		Functions\when( 'WP_Filesystem' )->justReturn( true );

		return $wp_filesystem;
	}
}
