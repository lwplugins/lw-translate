<?php
/**
 * Settings Page class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin;

use LightweightPlugins\Translate\Admin\Settings\TabInterface;
use LightweightPlugins\Translate\Admin\Settings\TabTranslations;
use LightweightPlugins\Translate\Admin\Settings\TabGeneral;

/**
 * Handles the plugin settings page.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const SLUG = 'lw-translate';

	/**
	 * Registered tabs.
	 *
	 * @var array<TabInterface>
	 */
	private array $tabs = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tabs = [
			new TabTranslations(),
			new TabGeneral(),
		];

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ SettingsSaver::class, 'maybe_save' ] );
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
			__( 'Translate', 'lw-translate' ),
			__( 'Translate', 'lw-translate' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Enqueue admin assets on settings page.
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
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1>
				<img src="<?php echo esc_url( LW_TRANSLATE_URL . 'assets/img/title-icon.svg' ); ?>" alt="" class="lw-title-icon" />
				<?php esc_html_e( 'Lightweight Translate', 'lw-translate' ); ?>
				<span style="font-size: 13px; font-weight: 400; color: #888;">(<?php echo esc_html( LW_TRANSLATE_VERSION ); ?>)</span>
			</h1>

			<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success lw-notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'lw-translate' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'lw_translate_save', '_lw_translate_nonce' ); ?>
				<input type="hidden" name="lw_translate_active_tab" value="" />

				<div class="lw-translate-settings">
					<?php $this->render_tabs_nav(); ?>

					<div class="lw-translate-tab-content">
						<?php $this->render_tabs_content(); ?>
						<?php submit_button( __( 'Save Changes', 'lw-translate' ), 'primary', 'lw_translate_save' ); ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render tabs navigation.
	 *
	 * @return void
	 */
	private function render_tabs_nav(): void {
		?>
		<ul class="lw-translate-tabs">
			<?php foreach ( $this->tabs as $index => $tab ) : ?>
				<li>
					<a href="#<?php echo esc_attr( $tab->get_slug() ); ?>" <?php echo 0 === $index ? 'class="active"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab->get_icon() ); ?>"></span>
						<?php echo esc_html( $tab->get_label() ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render tabs content.
	 *
	 * @return void
	 */
	private function render_tabs_content(): void {
		foreach ( $this->tabs as $index => $tab ) {
			$active_class = 0 === $index ? ' active' : '';
			printf(
				'<div id="tab-%s" class="lw-translate-tab-panel%s">',
				esc_attr( $tab->get_slug() ),
				esc_attr( $active_class )
			);
			$tab->render();
			echo '</div>';
		}
	}
}
