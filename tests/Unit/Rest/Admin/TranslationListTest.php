<?php
/**
 * Tests for TranslationList (rows, counts, warnings).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Rest\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Rest\Admin\TranslationList;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\Translate\Translation\TranslationItem;
use WP_Error;

/**
 * @covers \LightweightPlugins\Translate\Rest\Admin\TranslationList
 */
final class TranslationListTest extends MonkeyTestCase {

	private const FOLDER = 'https://github.com/o/r/tree/main/formal/{dir}/hu_HU/';

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	private function item( string $slug, string $type, string $status, string $name = 'Name' ): TranslationItem {
		return new TranslationItem( $slug, $name, $type, $status, 2, '2026-01-01 10:00+0000', [ $slug . '-hu_HU.mo' => 'a', $slug . '-hu_HU.po' => 'b' ] );
	}

	public function test_a_row_carries_the_rest_status_and_the_repository_folder(): void {
		$row = TranslationList::row( $this->item( 'astra', 'theme', TranslationItem::STATUS_UP_TO_DATE, 'Astra' ), self::FOLDER );

		$this->assertSame(
			[
				'id'         => 'theme:astra',
				'slug'       => 'astra',
				'name'       => 'Astra',
				'type'       => 'theme',
				'status'     => 'installed',
				'files'      => 2,
				'local_date' => '2026-01-01 10:00+0000',
				'remote'     => [
					'files' => [ 'astra-hu_HU.mo', 'astra-hu_HU.po' ],
					'url'   => 'https://github.com/o/r/tree/main/formal/themes/hu_HU/astra',
				],
			],
			$row
		);
	}

	public function test_a_nameless_item_shows_its_slug(): void {
		$this->assertSame( 'akismet', TranslationList::row( $this->item( 'akismet', 'plugin', 'update', '' ), '' )['name'] );
	}

	public function test_counts_per_view(): void {
		$list = TranslationList::build(
			[
				$this->item( 'a', 'plugin', TranslationItem::STATUS_UPDATE ),
				$this->item( 'b', 'plugin', TranslationItem::STATUS_NOT_INSTALLED ),
				$this->item( 'c', 'theme', TranslationItem::STATUS_UP_TO_DATE ),
			],
			false,
			self::FOLDER
		);

		$this->assertSame(
			[
				'all'           => 3,
				'plugin'        => 2,
				'theme'         => 1,
				'installed'     => 1,
				'update'        => 1,
				'not_installed' => 1,
			],
			$list['counts']
		);
		$this->assertSame( [], $list['warnings'] );
	}

	/**
	 * Before 1.2.0 a GitHub error (rate limit, network) showed as
	 * "No items found."
	 */
	public function test_a_comparison_error_becomes_a_warning_not_an_empty_list(): void {
		$list = TranslationList::build( new WP_Error( 'github_rate_limited', 'GitHub API rate limit reached.' ), false, self::FOLDER );

		$this->assertSame( [], $list['rows'] );
		$this->assertSame(
			[
				[
					'code'    => 'github_rate_limited',
					'level'   => 'error',
					'message' => 'GitHub API rate limit reached.',
				],
			],
			$list['warnings']
		);
	}

	public function test_a_truncated_listing_is_a_warning(): void {
		$list = TranslationList::build( [], true, self::FOLDER );

		$this->assertSame( 'tree_truncated', $list['warnings'][0]['code'] );
		$this->assertSame( 'warning', $list['warnings'][0]['level'] );
	}
}
