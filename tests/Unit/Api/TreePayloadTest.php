<?php
/**
 * Tests for TreePayload (the cached tree and what is known about it).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Api;

use LightweightPlugins\Translate\Api\TreePayload;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\Translate\Api\TreePayload
 */
final class TreePayloadTest extends TestCase {

	private const TREE = [ [ 'type' => 'blob', 'path' => 'formal/plugins/hu_HU/a/a-hu_HU.mo' ] ];

	public function test_from_body_keeps_the_tree_the_truncated_flag_and_the_fetch_time(): void {
		$payload = TreePayload::from_body(
			[
				'tree'      => self::TREE,
				'truncated' => true,
			],
			1700000000
		);

		$this->assertSame(
			[
				'tree'       => self::TREE,
				'truncated'  => true,
				'fetched_at' => 1700000000,
			],
			$payload
		);
	}

	public function test_from_body_treats_a_missing_truncated_flag_as_complete(): void {
		$this->assertFalse( TreePayload::from_body( [ 'tree' => self::TREE ], 1 )['truncated'] );
	}

	/**
	 * @dataProvider provide_bodies_without_a_tree
	 */
	public function test_from_body_rejects_a_body_without_a_tree( mixed $body ): void {
		$this->assertNull( TreePayload::from_body( $body, 1 ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_bodies_without_a_tree(): array {
		return [
			'null'            => [ null ],
			'no tree key'     => [ [ 'sha' => 'x' ] ],
			'tree not a list' => [ [ 'tree' => 'x' ] ],
			'empty tree'      => [ [ 'tree' => [] ] ],
		];
	}

	public function test_read_returns_a_stored_payload(): void {
		$payload = TreePayload::from_body( [ 'tree' => self::TREE ], 5 );

		$this->assertSame( $payload, TreePayload::read( $payload ) );
	}

	/**
	 * Versions before 1.2.0 cached the bare tree list.
	 */
	public function test_read_accepts_a_bare_tree_cached_by_an_older_version(): void {
		$this->assertSame(
			[
				'tree'       => self::TREE,
				'truncated'  => false,
				'fetched_at' => null,
			],
			TreePayload::read( self::TREE )
		);
	}

	/**
	 * @dataProvider provide_cache_misses
	 */
	public function test_read_returns_null_for_a_cache_miss( mixed $cached ): void {
		$this->assertNull( TreePayload::read( $cached ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_cache_misses(): array {
		return [
			'false (no transient)' => [ false ],
			'empty array'          => [ [] ],
			'string'               => [ 'x' ],
			'payload, empty tree'  => [
				[
					'tree'       => [],
					'truncated'  => false,
					'fetched_at' => 1,
				],
			],
		];
	}
}
