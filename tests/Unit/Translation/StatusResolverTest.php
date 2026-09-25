<?php
/**
 * Tests for StatusResolver (installed / update / not installed).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Translation;

use LightweightPlugins\Translate\Translation\StatusResolver;
use LightweightPlugins\Translate\Translation\TranslationItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Translation\StatusResolver
 */
final class StatusResolverTest extends TestCase {

	private const REMOTE = [
		'akismet-hu_HU.mo'                                   => 'mo-sha',
		'akismet-hu_HU.po'                                   => 'po-sha',
		'akismet-hu_HU-0123456789abcdef0123456789abcdef.json' => 'json-sha',
	];

	public function test_nothing_on_disk_is_not_installed(): void {
		$this->assertSame( TranslationItem::STATUS_NOT_INSTALLED, StatusResolver::resolve( self::REMOTE, [] ) );
	}

	public function test_every_file_matching_is_up_to_date(): void {
		$this->assertSame( TranslationItem::STATUS_UP_TO_DATE, StatusResolver::resolve( self::REMOTE, self::REMOTE ) );
	}

	/**
	 * Before 1.2.0 only the .mo was compared, so a changed .po or script
	 * translation (.json) upstream never showed as an update.
	 */
	public function test_a_changed_json_file_is_an_update(): void {
		$local = self::REMOTE;
		$local['akismet-hu_HU-0123456789abcdef0123456789abcdef.json'] = 'old';

		$this->assertSame( TranslationItem::STATUS_UPDATE, StatusResolver::resolve( self::REMOTE, $local ) );
	}

	public function test_a_changed_mo_is_an_update(): void {
		$local                     = self::REMOTE;
		$local['akismet-hu_HU.mo'] = 'old';

		$this->assertSame( TranslationItem::STATUS_UPDATE, StatusResolver::resolve( self::REMOTE, $local ) );
	}

	public function test_a_file_missing_locally_while_others_exist_is_an_update(): void {
		$local = [ 'akismet-hu_HU.mo' => 'mo-sha' ];

		$this->assertSame( TranslationItem::STATUS_UPDATE, StatusResolver::resolve( self::REMOTE, $local ) );
	}

	/**
	 * Before 1.2.0 an item whose folder had no .mo always read "up to date"
	 * once any local .mo existed.
	 */
	public function test_a_repository_folder_without_a_mo_is_still_compared(): void {
		$remote = [ 'akismet-hu_HU.po' => 'new' ];

		$this->assertSame( TranslationItem::STATUS_UPDATE, StatusResolver::resolve( $remote, [ 'akismet-hu_HU.po' => 'old' ] ) );
	}

	public function test_no_installable_files_is_not_installed(): void {
		$this->assertSame( TranslationItem::STATUS_NOT_INSTALLED, StatusResolver::resolve( [], [ 'x' => 'y' ] ) );
	}
}
