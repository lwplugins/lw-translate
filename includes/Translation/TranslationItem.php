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
 *
 * Treat the properties as immutable: they are only ever set by the constructor
 * and read afterwards. They are not declared `readonly` because that is PHP 8.1
 * syntax and this plugin supports 8.0.
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
		public string $slug,
		public string $name,
		public string $type,
		public string $status,
		public int $file_count,
		public string $local_date,
		public array $files,
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
