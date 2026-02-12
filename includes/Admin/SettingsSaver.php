<?php
/**
 * Settings Saver class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin;

use LightweightPlugins\Translate\Options;

/**
 * Handles saving settings form data.
 */
final class SettingsSaver {

	/**
	 * Handle form submission.
	 *
	 * @return void
	 */
	public static function maybe_save(): void {
		if ( ! isset( $_POST['lw_translate_save'] ) ) {
			return;
		}

		if (
			! isset( $_POST['_lw_translate_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['_lw_translate_nonce'] ), 'lw_translate_save' )
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::save_options();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used for URL hash.
		$active_tab = isset( $_POST['lw_translate_active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['lw_translate_active_tab'] ) ) : '';

		$redirect = add_query_arg(
			[
				'page'    => SettingsPage::SLUG,
				'updated' => '1',
			],
			admin_url( 'admin.php' )
		);

		if ( '' !== $active_tab ) {
			$redirect .= '#' . $active_tab;
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Save translation options.
	 *
	 * @return void
	 */
	private static function save_options(): void {
		$valid_tones = [ 'formal', 'informal' ];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in maybe_save().
		$raw = isset( $_POST[ Options::OPTION_NAME ] ) ? wp_unslash( (array) $_POST[ Options::OPTION_NAME ] ) : [];

		$tone      = isset( $raw['tone'] ) ? sanitize_text_field( (string) $raw['tone'] ) : 'formal';
		$locale    = isset( $raw['locale'] ) ? sanitize_text_field( (string) $raw['locale'] ) : 'hu_HU';
		$cache_ttl = isset( $raw['cache_ttl'] ) ? absint( $raw['cache_ttl'] ) : 43200;

		$options = [
			'tone'      => in_array( $tone, $valid_tones, true ) ? $tone : 'formal',
			'locale'    => $locale,
			'cache_ttl' => $cache_ttl,
		];

		Options::save( $options );
	}
}
