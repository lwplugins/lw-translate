<?php
/**
 * Upgrader class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Upgrade;

use LightweightPlugins\Translate\Installer\L10nPhpGenerator;
use WP_Filesystem_Base;

/**
 * Runs one-time upgrade steps on admin requests.
 *
 * 1.1.4: `.l10n.php` files downloaded by earlier versions are rebuilt
 * from their .mo (see L10nCleanup), in batches across admin page loads.
 */
final class Upgrader {

	/**
	 * Option holding the plugin version the upgrade steps last completed for.
	 */
	public const VERSION_OPTION = 'lw_translate_version';

	/**
	 * Option holding the progress of an unfinished cleanup.
	 */
	public const STATE_OPTION = 'lw_translate_upgrade_state';

	/**
	 * Sites below this version need the cleanup.
	 */
	public const TARGET_VERSION = '1.1.4';

	/**
	 * Files handled per request.
	 */
	public const BATCH_SIZE = 200;

	/**
	 * Register the hook. admin_init never fires on the front end.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'maybe_run' ] );
	}

	/**
	 * Run the next batch if the stored version is older than 1.1.4.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		$stored = (string) get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $stored, self::TARGET_VERSION, '>=' ) || wp_doing_ajax() ) {
			return;
		}

		$filesystem = self::filesystem();

		if ( null === $filesystem ) {
			return;
		}

		$cleanup = new L10nCleanup(
			[ WP_LANG_DIR . '/plugins', WP_LANG_DIR . '/themes' ],
			new L10nPhpGenerator()
		);

		self::run( $cleanup, $filesystem );
	}

	/**
	 * Run one batch and store the progress, or finish.
	 *
	 * @param L10nCleanup        $cleanup    Cleanup.
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @return void
	 */
	public static function run( L10nCleanup $cleanup, WP_Filesystem_Base $filesystem ): void {
		$state = get_option( self::STATE_OPTION );
		$state = $cleanup->run_batch( $filesystem, is_array( $state ) ? $state : L10nCleanup::initial_state(), self::BATCH_SIZE );

		if ( ! $state['done'] ) {
			update_option( self::STATE_OPTION, $state, false );
			return;
		}

		delete_option( self::STATE_OPTION );
		update_option( self::VERSION_OPTION, LW_TRANSLATE_VERSION );
	}

	/**
	 * WP_Filesystem without asking for credentials; null if unavailable.
	 *
	 * @return WP_Filesystem_Base|null
	 */
	private static function filesystem(): ?WP_Filesystem_Base {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return null;
		}

		return $wp_filesystem;
	}
}
