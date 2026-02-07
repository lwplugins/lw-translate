<?php
/**
 * Settings Page class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin;

use LightweightPlugins\Translate\Admin\Settings\TabInterface;
use LightweightPlugins\Translate\Admin\Settings\TabGeneral;
use LightweightPlugins\Translate\Options;

/**
 * Handles the plugin settings page.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const SLUG = 'lw-translate-settings';

	/**
	 * Settings group.
	 */
	private const SETTINGS_GROUP = 'lw_translate_settings';

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
		$this->tabs = [ new TabGeneral() ];

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
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
			__( 'Translate Settings', 'lw-translate' ),
			__( 'Translate Settings', 'lw-translate' ),
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
		if ( ParentPage::SLUG . '_page_' . self::SLUG !== $hook ) {
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
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Options::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => Options::get_defaults(),
			]
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Input values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$valid_tones = [ 'formal', 'informal' ];

		return [
			'tone'      => in_array( $input['tone'] ?? '', $valid_tones, true ) ? $input['tone'] : 'formal',
			'locale'    => isset( $input['locale'] ) ? sanitize_text_field( $input['locale'] ) : 'hu_HU',
			'cache_ttl' => isset( $input['cache_ttl'] ) ? absint( $input['cache_ttl'] ) : 43200,
		];
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
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<div class="lw-translate-settings">
					<?php $this->render_tabs_nav(); ?>

					<div class="lw-translate-tab-content">
						<?php $this->render_tabs_content(); ?>
						<?php submit_button(); ?>
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
