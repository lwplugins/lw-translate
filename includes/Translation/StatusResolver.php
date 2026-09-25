<?php
/**
 * Status Resolver class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

/**
 * Decides an item's status from the repository files and the local copies.
 *
 * Every installable file counts (.mo, .po and the script translation
 * .json files), not only the .mo.
 */
final class StatusResolver {

	/**
	 * Resolve the status.
	 *
	 * @param array<string, string> $remote Installable repository files (file name => blob SHA).
	 * @param array<string, string> $local  Local copies of those files that exist (file name => blob SHA).
	 * @return string A TranslationItem::STATUS_* value.
	 */
	public static function resolve( array $remote, array $local ): string {
		$present = array_intersect_key( $local, $remote );

		if ( [] === $present ) {
			return TranslationItem::STATUS_NOT_INSTALLED;
		}

		foreach ( $remote as $name => $sha ) {
			if ( ! isset( $local[ $name ] ) || $local[ $name ] !== $sha ) {
				return TranslationItem::STATUS_UPDATE;
			}
		}

		return TranslationItem::STATUS_UP_TO_DATE;
	}
}
