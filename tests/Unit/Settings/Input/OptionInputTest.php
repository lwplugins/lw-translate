<?php
/**
 * Tests for OptionInput and its parsers.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Settings\Input;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Settings\Input\OptionInput;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Settings\Input\OptionInput
 * @covers \LightweightPlugins\Translate\Settings\Input\EnumParser
 * @covers \LightweightPlugins\Translate\Settings\Input\IntParser
 * @covers \LightweightPlugins\Translate\Settings\Input\LocaleParser
 * @covers \LightweightPlugins\Translate\Settings\Input\InputReport
 * @covers \LightweightPlugins\Translate\Settings\Input\ParseResult
 */
final class OptionInputTest extends MonkeyTestCase {

	private const OFFERED = [ 'hu_HU' ];

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	public function test_valid_values_are_parsed_and_typed(): void {
		$report = OptionInput::parse(
			[
				'tone'      => ' Informal ',
				'locale'    => 'hu_HU',
				'cache_ttl' => '86400',
			],
			self::OFFERED
		);

		$this->assertFalse( $report->has_errors() );
		$this->assertSame(
			[
				'tone'      => 'informal',
				'locale'    => 'hu_HU',
				'cache_ttl' => 86400,
			],
			$report->values()
		);
	}

	public function test_only_submitted_keys_are_returned(): void {
		$this->assertSame( [ 'tone' => 'formal' ], OptionInput::parse( [ 'tone' => 'formal' ], self::OFFERED )->values() );
	}

	/**
	 * @dataProvider provide_invalid_values
	 */
	public function test_invalid_values_are_refused( string $key, mixed $value ): void {
		$report = OptionInput::parse( [ $key => $value ], self::OFFERED );

		$this->assertTrue( $report->has_errors() );
		$this->assertArrayHasKey( $key, $report->errors() );
		$this->assertSame( [], $report->values() );
	}

	/**
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function provide_invalid_values(): array {
		return [
			'unknown tone'            => [ 'tone', 'casual' ],
			'tone as bool'            => [ 'tone', true ],
			'tone as array'           => [ 'tone', [ 'formal' ] ],
			'malformed locale'        => [ 'locale', 'hu_HU/../x' ],
			'locale not offered'      => [ 'locale', 'de_DE' ],
			'locale as int'           => [ 'locale', 5 ],
			'ttl below one hour'      => [ 'cache_ttl', 3599 ],
			'ttl above one week'      => [ 'cache_ttl', 604801 ],
			'blank ttl'               => [ 'cache_ttl', '' ],
			'ttl with a fraction'     => [ 'cache_ttl', 3600.5 ],
			'ttl as words'            => [ 'cache_ttl', 'one day' ],
		];
	}

	public function test_range_edges_are_accepted(): void {
		$this->assertSame( [ 'cache_ttl' => 3600 ], OptionInput::parse( [ 'cache_ttl' => 3600 ], self::OFFERED )->values() );
		$this->assertSame( [ 'cache_ttl' => 604800 ], OptionInput::parse( [ 'cache_ttl' => 604800.0 ], self::OFFERED )->values() );
	}

	public function test_unknown_keys_are_reported_but_not_errors(): void {
		$report = OptionInput::parse( [ 'foo' => 1 ], self::OFFERED );

		$this->assertFalse( $report->has_errors() );
		$this->assertSame( [ 'foo' ], $report->unknown() );
	}

	public function test_a_locked_key_cannot_be_written(): void {
		$report = OptionInput::parse( [ 'tone' => 'formal' ], self::OFFERED, [ 'tone' => 'LW_TRANSLATE_TONE' ] );

		$this->assertStringContainsString( 'LW_TRANSLATE_TONE', $report->errors()['tone'][0] );
	}

	public function test_the_locale_error_names_the_offered_locales(): void {
		$report = OptionInput::parse( [ 'locale' => 'de_DE' ], [ 'hu_HU', 'sk_SK' ] );

		$this->assertStringContainsString( 'hu_HU, sk_SK', $report->errors()['locale'][0] );
	}
}
