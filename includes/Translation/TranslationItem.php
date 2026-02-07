<?php
/**
 * Translation Item value object.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Translation;

/**
 * Represents a single translation item (plugin or theme).
 */
final class TranslationItem {

	/**
	 * Status constants.
	 */
	public const STATUS_UP_TO_DATE    = 'up_to_date';
	public const STATUS_UPDATE        = 'update';
	public const STATUS_NOT_INSTALLED = 'not_installed';

	/**
	 * Constructor.
	 *
	 * @param string               $slug       Plugin or theme slug.
	 * @param string               $name       Display name.
	 * @param string               $type       Type: 'plugin' or 'theme'.
	 * @param string               $status     Status constant.
	 * @param int                  $file_count Number of available remote files.
	 * @param string               $local_date PO-Revision-Date from local file.
	 * @param array<string,string> $files      Remote files with SHA hashes.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $type,
		public readonly string $status,
		public readonly int $file_count,
		public readonly string $local_date,
		public readonly array $files,
	) {
	}

	/**
	 * Check if an update is available.
	 *
	 * @return bool
	 */
	public function has_update(): bool {
		return self::STATUS_UPDATE === $this->status;
	}

	/**
	 * Check if the translation is installed.
	 *
	 * @return bool
	 */
	public function is_installed(): bool {
		return self::STATUS_NOT_INSTALLED !== $this->status;
	}
}
