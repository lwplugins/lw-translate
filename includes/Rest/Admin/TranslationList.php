<?php
/**
 * Builds the translations list response.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_Error;

/**
 * Rows, counts per view and warnings. A comparison error or an incomplete
 * repository listing is reported as a warning, never as an empty list.
 */
final class TranslationList {

	/**
	 * REST status per internal status.
	 */
	private const STATUS = [
		TranslationItem::STATUS_UP_TO_DATE    => 'installed',
		TranslationItem::STATUS_UPDATE        => 'update',
		TranslationItem::STATUS_NOT_INSTALLED => 'not_installed',
	];

	/**
	 * Build the list part of the response.
	 *
	 * @param array<int, TranslationItem>|WP_Error $items     Comparison result.
	 * @param bool                                 $truncated Whether GitHub cut the repository listing short.
	 * @param string                               $tree_url  Repository folder URL of the tone and locale with a "{dir}"
	 *                                                        placeholder for plugins/themes, ending in "/".
	 * @return array{rows: array<int, array<string, mixed>>, counts: array<string, int>, warnings: array<int, array{code: string, level: string, message: string}>}
	 */
	public static function build( array|WP_Error $items, bool $truncated, string $tree_url ): array {
		$warnings = [];
		$rows     = [];

		if ( $items instanceof WP_Error ) {
			$warnings[] = [
				'code'    => (string) $items->get_error_code(),
				'level'   => 'error',
				'message' => $items->get_error_message(),
			];
		} else {
			foreach ( $items as $item ) {
				$rows[] = self::row( $item, $tree_url );
			}
		}

		if ( $truncated ) {
			$warnings[] = [
				'code'    => 'tree_truncated',
				'level'   => 'warning',
				'message' => __( 'GitHub returned an incomplete repository listing, so some translations may be missing from this list.', 'lw-translate' ),
			];
		}

		return [
			'rows'     => $rows,
			'counts'   => self::counts( $rows ),
			'warnings' => $warnings,
		];
	}

	/**
	 * One table row.
	 *
	 * @param TranslationItem $item     Item.
	 * @param string          $tree_url Folder URL template (see build()).
	 * @return array<string, mixed>
	 */
	public static function row( TranslationItem $item, string $tree_url ): array {
		$dir = 'theme' === $item->type ? 'themes' : 'plugins';

		return [
			'id'         => $item->type . ':' . $item->slug,
			'slug'       => $item->slug,
			'name'       => '' !== $item->name ? $item->name : $item->slug,
			'type'       => $item->type,
			'status'     => self::STATUS[ $item->status ] ?? 'not_installed',
			'files'      => $item->file_count,
			'local_date' => $item->local_date,
			'remote'     => [
				'files' => array_map( 'strval', array_keys( $item->files ) ),
				'url'   => '' === $tree_url ? '' : str_replace( '{dir}', $dir, $tree_url ) . rawurlencode( $item->slug ),
			],
		];
	}

	/**
	 * Counts per filter view.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return array{all: int, plugin: int, theme: int, installed: int, update: int, not_installed: int}
	 */
	public static function counts( array $rows ): array {
		$counts = [
			'all'           => count( $rows ),
			'plugin'        => 0,
			'theme'         => 0,
			'installed'     => 0,
			'update'        => 0,
			'not_installed' => 0,
		];

		foreach ( $rows as $row ) {
			++$counts[ 'theme' === $row['type'] ? 'theme' : 'plugin' ];
			++$counts[ (string) $row['status'] ];
		}

		return $counts;
	}
}
