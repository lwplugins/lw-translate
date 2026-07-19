<?php
/**
 * Tests for ListTrait's status/type filtering and row conversion.
 *
 * list_translations() itself is not exercised here: it calls
 * Comparator::compare_all() (a hard static dependency, not injectable) and,
 * on error, WP_CLI::error() -- the WP_CLI class does not exist in this
 * runtime (php-stubs/wp-cli-stubs is a PHPStan-only stub, never loaded by
 * the test bootstrap). filter_items() and items_to_rows() are private, so
 * they are exercised here through a tiny local class that uses the trait and
 * exposes thin public wrappers -- the normal way to unit test a trait
 * without reflection.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\CLI;

use LightweightPlugins\Translate\CLI\ListTrait;
use LightweightPlugins\Translate\Translation\TranslationItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\CLI\ListTrait
 */
final class ListTraitTest extends TestCase {

	private function make_helper(): object {
		return new class() {
			use ListTrait;

			/**
			 * @param array<TranslationItem> $items
			 * @return array<TranslationItem>
			 */
			public function call_filter_items( array $items, string $status, string $type ): array {
				return $this->filter_items( $items, $status, $type );
			}

			/**
			 * @param array<TranslationItem> $items
			 * @return array<array<string, string|int>>
			 */
			public function call_items_to_rows( array $items ): array {
				return $this->items_to_rows( $items );
			}
		};
	}

	public function test_filter_items_keeps_everything_when_status_is_all_and_type_is_empty(): void {
		$items = [
			$this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin' ),
			$this->make_item( 'twentytwentyfour', TranslationItem::STATUS_UP_TO_DATE, 'theme' ),
		];

		$result = $this->make_helper()->call_filter_items( $items, 'all', '' );

		$this->assertSame( $items, $result );
	}

	/**
	 * @dataProvider provide_status_filters
	 */
	public function test_filter_items_keeps_only_the_matching_status( string $status, array $expected_slugs ): void {
		$items = [
			$this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin' ),
			$this->make_item( 'woocommerce', TranslationItem::STATUS_UP_TO_DATE, 'plugin' ),
			$this->make_item( 'jetpack', TranslationItem::STATUS_NOT_INSTALLED, 'plugin' ),
		];

		$result = $this->make_helper()->call_filter_items( $items, $status, '' );

		$this->assertSame( $expected_slugs, array_map( static fn ( TranslationItem $item ): string => $item->slug, $result ) );
	}

	/**
	 * @return array<string, array{0: string, 1: array<string>}>
	 */
	public static function provide_status_filters(): array {
		return [
			'update'        => [ TranslationItem::STATUS_UPDATE, [ 'akismet' ] ],
			'up_to_date'    => [ TranslationItem::STATUS_UP_TO_DATE, [ 'woocommerce' ] ],
			'not_installed' => [ TranslationItem::STATUS_NOT_INSTALLED, [ 'jetpack' ] ],
		];
	}

	public function test_filter_items_keeps_only_the_matching_type(): void {
		$items = [
			$this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin' ),
			$this->make_item( 'twentytwentyfour', TranslationItem::STATUS_UPDATE, 'theme' ),
		];

		$result = $this->make_helper()->call_filter_items( $items, 'all', 'theme' );

		$this->assertSame( [ 'twentytwentyfour' ], array_map( static fn ( TranslationItem $item ): string => $item->slug, $result ) );
	}

	public function test_filter_items_combines_status_and_type_filters(): void {
		$items = [
			$this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin' ),
			$this->make_item( 'twentytwentyfour', TranslationItem::STATUS_UPDATE, 'theme' ),
			$this->make_item( 'woocommerce', TranslationItem::STATUS_UP_TO_DATE, 'plugin' ),
		];

		$result = $this->make_helper()->call_filter_items( $items, TranslationItem::STATUS_UPDATE, 'plugin' );

		$this->assertSame( [ 'akismet' ], array_map( static fn ( TranslationItem $item ): string => $item->slug, $result ) );
	}

	/**
	 * array_filter() preserves original keys; list_translations() relies on
	 * array_values() inside filter_items() to hand WP_CLI\Utils::format_items()
	 * a zero-indexed list.
	 */
	public function test_filter_items_reindexes_the_result(): void {
		$items = [
			$this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin' ),
			$this->make_item( 'woocommerce', TranslationItem::STATUS_UP_TO_DATE, 'plugin' ),
			$this->make_item( 'jetpack', TranslationItem::STATUS_UPDATE, 'plugin' ),
		];

		$result = $this->make_helper()->call_filter_items( $items, TranslationItem::STATUS_UPDATE, '' );

		$this->assertSame( [ 0, 1 ], array_keys( $result ) );
	}

	public function test_items_to_rows_converts_each_item_to_a_row(): void {
		$item = $this->make_item( 'akismet', TranslationItem::STATUS_UPDATE, 'plugin', 3, '2026-01-01 10:00' );

		$rows = $this->make_helper()->call_items_to_rows( [ $item ] );

		$this->assertSame(
			[
				'slug'       => 'akismet',
				'name'       => 'Akismet',
				'type'       => 'plugin',
				'status'     => TranslationItem::STATUS_UPDATE,
				'files'      => 3,
				'local_date' => '2026-01-01 10:00',
			],
			$rows[0]
		);
	}

	public function test_items_to_rows_substitutes_a_dash_for_an_empty_local_date(): void {
		$item = $this->make_item( 'akismet', TranslationItem::STATUS_NOT_INSTALLED, 'plugin', 0, '' );

		$rows = $this->make_helper()->call_items_to_rows( [ $item ] );

		$this->assertSame( '-', $rows[0]['local_date'] );
	}

	private function make_item( string $slug, string $status, string $type, int $file_count = 1, string $local_date = '' ): TranslationItem {
		return new TranslationItem(
			slug: $slug,
			name: ucfirst( $slug ),
			type: $type,
			status: $status,
			file_count: $file_count,
			local_date: $local_date,
			files: []
		);
	}
}
