<?php
/**
 * Translate Ability Definitions for LW Site Manager.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\SiteManager;

/**
 * Registers Translate-specific abilities with the WordPress Abilities API.
 */
final class TranslateAbilities {

	/**
	 * Register all Translate abilities.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	public static function register( object $permissions ): void {
		self::register_list_translations( $permissions );
		self::register_get_options( $permissions );
		self::register_install_translation( $permissions );
		self::register_update_translations( $permissions );
	}

	/**
	 * Register list-translations ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_list_translations( object $permissions ): void {
		wp_register_ability(
			'lw-translate/list-translations',
			[
				'label'               => __( 'List Translations', 'lw-translate' ),
				'description'         => __( 'List installed translations and their update status for all plugins and themes.', 'lw-translate' ),
				'category'            => 'translate',
				'execute_callback'    => [ TranslateService::class, 'list_translations' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'       => 'object',
					'default'    => [],
					'properties' => [
						'type'   => [
							'type'        => 'string',
							'enum'        => [ 'plugin', 'theme', 'all' ],
							'description' => __( 'Filter by type: plugin, theme, or all (default).', 'lw-translate' ),
						],
						'status' => [
							'type'        => 'string',
							'enum'        => [ 'up_to_date', 'update', 'not_installed', 'all' ],
							'description' => __( 'Filter by status: up_to_date, update, not_installed, or all (default).', 'lw-translate' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'      => [ 'type' => 'boolean' ],
						'translations' => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
						'total'        => [ 'type' => 'integer' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Register get-options ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_get_options( object $permissions ): void {
		wp_register_ability(
			'lw-translate/get-options',
			[
				'label'               => __( 'Get Translate Options', 'lw-translate' ),
				'description'         => __( 'Get LW Translate plugin settings (locale, tone, cache TTL).', 'lw-translate' ),
				'category'            => 'translate',
				'execute_callback'    => [ TranslateService::class, 'get_options' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'    => 'object',
					'default' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'options' => [ 'type' => 'object' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Register install-translation ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_install_translation( object $permissions ): void {
		wp_register_ability(
			'lw-translate/install-translation',
			[
				'label'               => __( 'Install Translation', 'lw-translate' ),
				'description'         => __( 'Install or update a translation for a specific plugin or theme.', 'lw-translate' ),
				'category'            => 'translate',
				'execute_callback'    => [ TranslateService::class, 'install_translation' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug', 'type' ],
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => __( 'Plugin or theme slug (e.g. woocommerce, storefront).', 'lw-translate' ),
						],
						'type' => [
							'type'        => 'string',
							'enum'        => [ 'plugin', 'theme' ],
							'description' => __( 'Whether the slug refers to a plugin or a theme.', 'lw-translate' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'meta'                => self::write_meta(),
			]
		);
	}

	/**
	 * Register update-translations ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_update_translations( object $permissions ): void {
		wp_register_ability(
			'lw-translate/update-translations',
			[
				'label'               => __( 'Update All Translations', 'lw-translate' ),
				'description'         => __( 'Update all translations that have a newer version available.', 'lw-translate' ),
				'category'            => 'translate',
				'execute_callback'    => [ TranslateService::class, 'update_translations' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'    => 'object',
					'default' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'updated' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'failed'  => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
						'total'   => [ 'type' => 'integer' ],
					],
				],
				'meta'                => self::write_meta(),
			]
		);
	}

	/**
	 * Read-only ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function readonly_meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}

	/**
	 * Write ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function write_meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}
}
