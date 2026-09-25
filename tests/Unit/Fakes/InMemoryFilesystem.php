<?php
/**
 * In-memory filesystem fake.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Fakes;

/**
 * Records writes and deletes in an array instead of touching disk.
 */
final class InMemoryFilesystem extends \WP_Filesystem_Base {

	/**
	 * Path => contents.
	 *
	 * @var array<string, string>
	 */
	public array $files = [];

	/**
	 * Paths passed to delete(), in call order.
	 *
	 * @var array<int, string>
	 */
	public array $deleted = [];

	public function put_contents( $file, $contents, $mode = false ) {
		$this->files[ $file ] = $contents;
		return true;
	}

	public function exists( $path ) {
		return isset( $this->files[ $path ] );
	}

	public function delete( $file, $recursive = false, $type = false ) {
		$this->deleted[] = $file;
		unset( $this->files[ $file ] );
		return true;
	}

	public function is_dir( $path ) {
		return true;
	}

	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return true;
	}
}
