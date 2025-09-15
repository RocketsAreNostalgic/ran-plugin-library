<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class RegisterOptionsRegisterSchemaTest extends PluginLibTestCase {
	/**
	 * Lightweight test double to avoid WP function calls in constructor.
	 * Overrides _read_main_option() to return an empty array.
	 */
	private static function makeOptions(string $key = 'unit_test_opts'): RegisterOptions {
		return new class($key) extends RegisterOptions {
			public function __construct(string $k) {
				parent::__construct($k, true, null);
			}
			protected function _read_main_option(): array {
				return array();
			}
		};
	}
	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_requires_validate_callable(): void {
		$opts = self::makeOptions('unit_test_opts');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/requires a 'validate' callable/");

		$opts->register_schema(array(
		    'foo' => array(
		        'default' => 'x',
		        // 'validate' intentionally omitted to trigger the throw
		    ),
		));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_rejects_non_callable_sanitize(): void {
		$opts = self::makeOptions('unit_test_opts2');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches("/has non-callable 'sanitize'/");

		$opts->register_schema(array(
		    'foo' => array(
		        'default'  => 'x',
		        'validate' => static fn(mixed $v): bool => true,
		        'sanitize' => 'not_callable', // invalid sanitize, should throw
		    ),
		));
	}
}
