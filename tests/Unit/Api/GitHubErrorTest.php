<?php
/**
 * Tests for GitHubError (turning a failed API response into a message).
 *
 * @package LightweightPlugins\Translate
 */

declare(strict_types=1);

namespace LightweightPlugins\Translate\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\Translate\Api\GitHubError;
use LightweightPlugins\Translate\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\Translate\Api\GitHubError
 */
final class GitHubErrorTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static fn ( string $single, string $plural, int $number ): string => 1 === $number ? $single : $plural
		);
	}

	public function test_an_exhausted_rate_limit_says_when_to_try_again(): void {
		$error = GitHubError::for_status( 403, '0', '1000600', 1000000 );

		$this->assertSame( 'github_rate_limited', $error->get_error_code() );
		$this->assertSame( 'GitHub API rate limit reached. Try again in 10 minutes.', $error->get_error_message() );
		$this->assertSame( 1000600, $error->get_error_data()['reset'] );
	}

	public function test_the_wait_is_at_least_one_minute(): void {
		$error = GitHubError::for_status( 429, '0', '1000010', 1000000 );

		$this->assertSame( 'GitHub API rate limit reached. Try again in 1 minute.', $error->get_error_message() );
	}

	public function test_a_rate_limit_without_a_reset_time_still_names_the_limit(): void {
		$error = GitHubError::for_status( 403, '0', '', 1000000 );

		$this->assertSame( 'github_rate_limited', $error->get_error_code() );
		$this->assertSame( 'GitHub API rate limit reached. Try again later.', $error->get_error_message() );
	}

	public function test_a_403_with_quota_left_is_a_plain_status_error(): void {
		$error = GitHubError::for_status( 403, '12', '1000600', 1000000 );

		$this->assertSame( 'github_api_error', $error->get_error_code() );
		$this->assertSame( 'GitHub API returned status 403.', $error->get_error_message() );
	}

	public function test_any_other_status_is_a_plain_status_error(): void {
		$this->assertSame( 'GitHub API returned status 500.', GitHubError::for_status( 500, '', '', 1 )->get_error_message() );
	}
}
