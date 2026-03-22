<?php
/**
 * LW Site Manager Integration.
 *
 * Registers Translate abilities when LW Site Manager is active.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\SiteManager;

/**
 * Hooks into LW Site Manager to register Translate abilities.
 */
final class Integration {

	/**
	 * Initialize hooks. Safe to call even if Site Manager is not active.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'lw_site_manager_register_categories', [ self::class, 'register_category' ] );
		add_action( 'lw_site_manager_register_abilities', [ self::class, 'register_abilities' ] );
	}

	/**
	 * Register the Translate ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'translate',
			[
				'label'       => __( 'Translate', 'lw-translate' ),
				'description' => __( 'Translation management abilities', 'lw-translate' ),
			]
		);
	}

	/**
	 * Register Translate abilities.
	 *
	 * @param object $permissions Permission manager from Site Manager.
	 * @return void
	 */
	public static function register_abilities( object $permissions ): void {
		TranslateAbilities::register( $permissions );
	}
}
