<?php
/**
 * L10n Cleanup class.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Upgrade;

use LightweightPlugins\Translate\Installer\L10nPhpGenerator;
use LightweightPlugins\Translate\Installer\PathGuard;
use WP_Filesystem_Base;

/**
 * Replaces `.l10n.php` files that earlier versions copied from the
 * repository with ones generated from their sibling .mo.
 *
 * Works in batches: the returned state is passed back in on the next
 * request until "done" is true.
 */
final class L10nCleanup {

	/**
	 * Suffix of the files handled here.
	 */
	private const SUFFIX = '.l10n.php';

	/**
	 * Language folders to scan, in order.
	 *
	 * @var array<int, string>
	 */
	private array $dirs;

	/**
	 * Generator used to rebuild the files.
	 *
	 * @var L10nPhpGenerator
	 */
	private L10nPhpGenerator $generator;

	/**
	 * Constructor.
	 *
	 * @param array<int, string> $dirs      Language folders, e.g. WP_LANG_DIR/plugins and WP_LANG_DIR/themes.
	 * @param L10nPhpGenerator   $generator Generator.
	 */
	public function __construct( array $dirs, L10nPhpGenerator $generator ) {
		$this->dirs      = array_values( $dirs );
		$this->generator = $generator;
	}

	/**
	 * State before the first batch.
	 *
	 * @return array{dir: int, after: string, regenerated: int, deleted: int, orphans: int, done: bool}
	 */
	public static function initial_state(): array {
		return [
			'dir'         => 0,
			'after'       => '',
			'regenerated' => 0,
			'deleted'     => 0,
			'orphans'     => 0,
			'done'        => false,
		];
	}

	/**
	 * Process up to $limit `.l10n.php` files, continuing from $state.
	 *
	 * With a sibling .mo the file is regenerated from it; when that fails
	 * it is deleted (WordPress then loads the .mo). Files without a .mo
	 * are left alone and only counted as "orphans".
	 *
	 * @param WP_Filesystem_Base   $filesystem Filesystem.
	 * @param array<string, mixed> $state      State from initial_state() or a previous batch.
	 * @param int                  $limit      Maximum files to handle in this call.
	 * @return array{dir: int, after: string, regenerated: int, deleted: int, orphans: int, done: bool}
	 */
	public function run_batch( WP_Filesystem_Base $filesystem, array $state, int $limit ): array {
		$state     = self::normalize( $state );
		$processed = 0;
		$dir_count = count( $this->dirs );

		while ( $state['dir'] < $dir_count ) {
			$base = $this->dirs[ $state['dir'] ];

			foreach ( $this->pending_files( $filesystem, $base, $state['after'] ) as $name ) {
				if ( $processed >= $limit ) {
					return $state;
				}

				$counter = $this->process( $filesystem, $base, $name );
				++$state[ $counter ];
				$state['after'] = $name;
				++$processed;
			}

			++$state['dir'];
			$state['after'] = '';
		}

		$state['done'] = true;

		return $state;
	}

	/**
	 * Handle one file.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $base       Language folder.
	 * @param string             $name       File name ending in ".l10n.php".
	 * @return string Counter to increase: regenerated, deleted or orphans.
	 */
	private function process( WP_Filesystem_Base $filesystem, string $base, string $name ): string {
		$mo_path = $base . '/' . substr( $name, 0, -strlen( self::SUFFIX ) ) . '.mo';

		if ( ! PathGuard::is_contained( $base, $base . '/' . $name ) || ! $filesystem->exists( $mo_path ) ) {
			return 'orphans';
		}

		return $this->generator->generate( $mo_path, $filesystem ) ? 'regenerated' : 'deleted';
	}

	/**
	 * `.l10n.php` file names in a folder sorting after $after.
	 *
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string             $base       Language folder.
	 * @param string             $after      Last handled file name, '' for none.
	 * @return array<int, string>
	 */
	private function pending_files( WP_Filesystem_Base $filesystem, string $base, string $after ): array {
		$list  = $filesystem->dirlist( $base, false, false );
		$names = [];

		if ( ! is_array( $list ) ) {
			return $names;
		}

		foreach ( $list as $name => $entry ) {
			$name = (string) $name;

			if ( 'f' === $entry['type'] && str_ends_with( $name, self::SUFFIX ) && strcmp( $name, $after ) > 0 ) {
				$names[] = $name;
			}
		}

		sort( $names, SORT_STRING );

		return $names;
	}

	/**
	 * Bring a stored state back to the expected shape.
	 *
	 * @param array<string, mixed> $state Stored state.
	 * @return array{dir: int, after: string, regenerated: int, deleted: int, orphans: int, done: bool}
	 */
	private static function normalize( array $state ): array {
		$initial = self::initial_state();

		return [
			'dir'         => max( 0, (int) ( $state['dir'] ?? $initial['dir'] ) ),
			'after'       => (string) ( $state['after'] ?? $initial['after'] ),
			'regenerated' => (int) ( $state['regenerated'] ?? 0 ),
			'deleted'     => (int) ( $state['deleted'] ?? 0 ),
			'orphans'     => (int) ( $state['orphans'] ?? 0 ),
			'done'        => false,
		];
	}
}
