<?php
/**
 * Field Renderer Trait.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Admin\Settings;

use LightweightPlugins\Translate\Options;

/**
 * Trait for rendering form fields.
 */
trait FieldRendererTrait {

	/**
	 * Render a select field.
	 *
	 * @param array{name: string, options: array<string, string>, description?: string} $args Field arguments.
	 * @return void
	 */
	protected function render_select_field( array $args ): void {
		$name    = $args['name'];
		$options = $args['options'] ?? [];
		$value   = Options::get( $name );
		$desc    = $args['description'] ?? '';

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME )
		);

		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Render a number input field.
	 *
	 * @param array{name: string, min?: int, max?: int, description?: string} $args Field arguments.
	 * @return void
	 */
	protected function render_number_field( array $args ): void {
		$name  = $args['name'];
		$value = Options::get( $name );
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? 999999;
		$desc  = $args['description'] ?? '';

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d" max="%5$d" class="small-text" />',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME ),
			esc_attr( (string) $value ),
			intval( $min ),
			intval( $max )
		);

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}
}
