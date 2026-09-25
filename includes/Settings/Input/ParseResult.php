<?php
/**
 * Outcome of parsing one raw setting value.
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Settings\Input;

/**
 * Either a clean option value, or the reasons it was refused.
 */
final class ParseResult {

	/**
	 * Parsed value (null when invalid).
	 *
	 * @var mixed
	 */
	private mixed $value;

	/**
	 * Messages explaining why the value was refused.
	 *
	 * @var array<int, string>
	 */
	private array $errors;

	/**
	 * Constructor.
	 *
	 * @param mixed              $value  Parsed value.
	 * @param array<int, string> $errors Messages.
	 */
	private function __construct( mixed $value, array $errors ) {
		$this->value  = $value;
		$this->errors = $errors;
	}

	/**
	 * A valid value.
	 *
	 * @param mixed $value Parsed value.
	 * @return self
	 */
	public static function ok( mixed $value ): self {
		return new self( $value, [] );
	}

	/**
	 * A refused value.
	 *
	 * @param array<int, string> $errors Messages (at least one).
	 * @return self
	 */
	public static function fail( array $errors ): self {
		return new self( null, array_values( $errors ) );
	}

	/**
	 * The parsed value, or null when invalid.
	 *
	 * @return mixed
	 */
	public function value(): mixed {
		return $this->value;
	}

	/**
	 * Messages; empty when valid.
	 *
	 * @return array<int, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Whether the value was accepted.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return [] === $this->errors;
	}
}
