<?php
/**
 * Translations Page class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin;

use LightweightPlugins\Translate\ListTable\TranslationListTable;
use LightweightPlugins\Translate\Options;

/**
 * Handles the translations list page.
 */
final class TranslationsPage {

	/**
	 * Page slug.
	 */
	public const SLUG = 'lw-translate';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		ParentPage::maybe_register();

		add_submenu_page(
			ParentPage::SLUG,
			__( 'Translations', 'lw-translate' ),
			__( 'Translate', 'lw-translate' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Enqueue admin assets on translations page.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$valid_hooks = [
			'toplevel_page_' . ParentPage::SLUG,
			ParentPage::SLUG . '_page_' . self::SLUG,
		];

		if ( ! in_array( $hook, $valid_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'lw-translate-admin',
			LW_TRANSLATE_URL . 'assets/css/admin.css',
			[],
			LW_TRANSLATE_VERSION
		);

		wp_enqueue_script(
			'lw-translate-admin',
			LW_TRANSLATE_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			LW_TRANSLATE_VERSION,
			true
		);

		wp_localize_script(
			'lw-translate-admin',
			'lwTranslate',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lw_translate_nonce' ),
				'i18n'    => [
					'installing'    => __( 'Installing...', 'lw-translate' ),
					'deleting'      => __( 'Deleting...', 'lw-translate' ),
					'success'       => __( 'Done!', 'lw-translate' ),
					'error'         => __( 'Error occurred.', 'lw-translate' ),
					'confirmDelete' => __( 'Delete this translation?', 'lw-translate' ),
				],
			]
		);
	}

	/**
	 * Render the translations page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list_table = new TranslationListTable();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<img src="<?php echo esc_url( LW_TRANSLATE_URL . 'assets/img/title-icon.svg' ); ?>" alt="" class="lw-title-icon" />
				<?php esc_html_e( 'Translations', 'lw-translate' ); ?>
				<span style="font-size: 13px; font-weight: 400; color: #888;">(<?php echo esc_html( LW_TRANSLATE_VERSION ); ?>)</span>
			</h1>

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

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<?php
				$list_table->views();
				$list_table->search_box( __( 'Search', 'lw-translate' ), 'lw-translate-search' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}
}
