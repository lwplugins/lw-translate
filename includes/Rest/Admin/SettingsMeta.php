<?php
/**
 * Context the settings screen builds its controls from.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Rest\Admin;

use LightweightPlugins\Translate\Api\GitHubClient;
use LightweightPlugins\Translate\Capability;
use LightweightPlugins\Translate\Options;
use LightweightPlugins\Translate\Settings\LocaleCatalog;
use LightweightPlugins\Translate\Settings\SettingsStore;

/**
 * Locked keys, tones, offered locales, ranges, defaults, the source
 * repository and links.
 */
final class SettingsMeta {

	/**
	 * Plugin documentation.
	 */
	public const DOCS_URL = 'https://github.com/lwplugins/lw-translate#readme';

	/**
	 * Build the meta block.
	 *
	 * @param array<int, string> $offered Locales the repository offers.
	 * @return array<string, mixed>
	 */
	public static function build( array $offered ): array {
		return [
			'locked'      => (object) SettingsStore::locked(),
			'tones'       => Options::TONES,
			'locales'     => LocaleCatalog::options( $offered ),
			'ranges'      => [
				'cache_ttl' => [
					'min' => Options::CACHE_TTL_MIN,
					'max' => Options::CACHE_TTL_MAX,
				],
			],
			'defaults'    => Options::get_defaults(),
			'source'      => GitHubClient::source(),
			'docs_url'    => self::DOCS_URL,
			'can_install' => Capability::can_install(),
		];
	}
}
