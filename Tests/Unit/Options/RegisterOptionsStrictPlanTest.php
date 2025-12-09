<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class RegisterOptionsStrictPlanTest extends PluginLibTestCase {
	/**
	 * Lightweight test double that avoids WP calls; overrides _read_main_option() to empty array.
	 */
	private function makeOptions(string $key = 'strict_plan_opts'): RegisterOptions {
		$logger = $this->logger_mock;
		return new class($key, $logger) extends RegisterOptions {
			public function __construct(string $k, Logger $logger) {
				parent::__construct($k, null, true, $logger);
			}
			protected function _read_main_option(): array {
				return array();
			}
			// Always allow writes in tests so validation paths run and avoid external gates
			protected function _apply_write_gate(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
				return true;
			}
			// Pretend persistence succeeded to keep test focused on validation behavior
			protected function _save_all_options(bool $merge = true): bool {
				return true;
			}
		};
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_set_option_rejects_unknown_key_without_schema(): void {
		$opts = self::makeOptions();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/No schema defined for option 'unknown'/");

		$opts->stage_option('unknown', 'x')->commit_merge();
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_stage_option_rejects_unknown_key_without_schema(): void {
		$opts = self::makeOptions();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/No schema defined for option 'k1'/");

		$opts->stage_option('k1', 123);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_stage_options_rejects_unknown_keys_without_schema(): void {
		$opts = self::makeOptions();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/No schema defined for option 'a'/");

		$opts->stage_options(array('a' => 1, 'b' => 2));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_stage_option_succeeds_with_explicit_schema_and_no_inference(): void {
		$opts = self::makeOptions();

		// Register an explicit schema with strict validator; no type inference should be needed/used
		$opts->register_schema(array(
		    'port' => array(
		        'default'  => 80,
		        'sanitize' => null,
		        'validate' => Validate::number()->between(1, 65535),
		    ),
		));

		// stage_option for a known key passes through validation
		$this->assertTrue($opts->stage_option('port', 8080)->commit_merge());

		// Unknown key should still be rejected
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/No schema defined for option 'host'/");
		$opts->stage_option('host', 'example.com')->commit_merge();
	}
}
