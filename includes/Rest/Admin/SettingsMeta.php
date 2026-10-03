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
	 * Plugin page on docs.lwplugins.com, in the admin user's language.
	 *
	 * @return string
	 */
	public static function docs_url(): string {
		$lang = str_starts_with( get_user_locale(), 'hu' ) ? 'hu' : 'en';

		return 'https://docs.lwplugins.com/' . $lang . '/plugins/lw-translate';
	}

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
			'docs_url'    => self::docs_url(),
			'can_install' => Capability::can_install(),
		];
	}
}
