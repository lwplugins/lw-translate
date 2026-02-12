<?php
/**
 * Translations Tab.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin\Settings;

use LightweightPlugins\Translate\ListTable\TranslationListTable;
use LightweightPlugins\Translate\Options;

/**
 * Handles the Translations tab with list table.
 */
final class TabTranslations implements TabInterface {

	/**
	 * Get the tab slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'translations';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Translations', 'lw-translate' );
	}

	/**
	 * Get the tab icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-translation';
	}

	/**
	 * Render the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		$list_table = new TranslationListTable();
		$list_table->prepare_items();

		?>
		<div class="lw-translate-toolbar">
			<span class="lw-translate-toolbar-info">
				<?php
				printf(
					/* translators: 1: tone label, 2: locale code */
					esc_html__( 'Source: %1$s / %2$s', 'lw-translate' ),
					esc_html( 'formal' === Options::get( 'tone' ) ? __( 'Formal', 'lw-translate' ) : __( 'Informal', 'lw-translate' ) ),
					esc_html( (string) Options::get( 'locale' ) )
				);
				?>
			</span>
			<button type="button" class="button lw-translate-refresh-cache">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh Cache', 'lw-translate' ); ?>
			</button>
		</div>

		<?php
		$list_table->views();
		$list_table->search_box( __( 'Search', 'lw-translate' ), 'lw-translate-search' );
		$list_table->display();
	}
}
