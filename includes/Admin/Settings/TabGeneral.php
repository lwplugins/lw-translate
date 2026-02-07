<?php
/**
 * General Settings Tab.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin\Settings;

/**
 * Handles the General settings tab.
 */
final class TabGeneral implements TabInterface {

	use FieldRendererTrait;

	/**
	 * Get the tab slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'general';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'General', 'lw-translate' );
	}

	/**
	 * Get the tab icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-admin-settings';
	}

	/**
	 * Render the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<h2><?php esc_html_e( 'Translation Settings', 'lw-translate' ); ?></h2>

		<div class="lw-translate-section-description">
			<p><?php esc_html_e( 'Configure the translation source and caching.', 'lw-translate' ); ?></p>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="tone"><?php esc_html_e( 'Tone', 'lw-translate' ); ?></label>
				</th>
				<td>
					<?php
					$this->render_select_field(
						[
							'name'        => 'tone',
							'options'     => [
								'formal'   => __( 'Formal (magázó)', 'lw-translate' ),
								'informal' => __( 'Informal (tegező)', 'lw-translate' ),
							],
							'description' => __( 'Choose between formal and informal translation tone.', 'lw-translate' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="locale"><?php esc_html_e( 'Locale', 'lw-translate' ); ?></label>
				</th>
				<td>
					<?php
					$this->render_select_field(
						[
							'name'        => 'locale',
							'options'     => [
								'hu_HU' => 'Magyar (hu_HU)',
							],
							'description' => __( 'Target language for translations.', 'lw-translate' ),
						]
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cache_ttl"><?php esc_html_e( 'Cache TTL', 'lw-translate' ); ?></label>
				</th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'cache_ttl',
							'min'         => 3600,
							'max'         => 604800,
							'description' => __( 'Cache duration in seconds. Default: 43200 (12 hours).', 'lw-translate' ),
						]
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}
}
