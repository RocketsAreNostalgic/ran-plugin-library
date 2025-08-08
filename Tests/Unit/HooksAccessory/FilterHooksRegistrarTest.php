<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use InvalidArgumentException;
use Ran\PluginLib\HooksAccessory\FilterHooksInterface;
use Ran\PluginLib\HooksAccessory\FilterHooksRegistrar;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

final class FilterHooksRegistrarTest extends PluginLibTestCase {
	// Replace dynamic builder with concrete providers
	private function makeProviderSimple(): object {
		return new class implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array('the_content' => 'filter_content');
			}
			public static function validate_filter_hooks(object $instance): array {
				return array();
			}
			public static function get_filter_hooks_metadata(): array {
				return array();
			}
			public function filter_content($v) {
				return $v;
			}
		};
	}
	private function makeProviderMissing(): object {
		return new class implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array('the_content' => 'missing');
			}
			public static function validate_filter_hooks(object $instance): array {
				return array();
			}
			public static function get_filter_hooks_metadata(): array {
				return array();
			}
		};
	}
	private function makeProviderInvalid(): object {
		return new class implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array('the_content' => 'filter_content');
			}
			public static function validate_filter_hooks(object $instance): array {
				return array('bad config');
			}
			public static function get_filter_hooks_metadata(): array {
				return array();
			}
			public function filter_content($v) {
				return $v;
			}
		};
	}

	public function test_no_hooks_returns_false(): void {
		$provider = new class implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array();
			}
			public static function validate_filter_hooks(object $instance): array {
				return array();
			}
			public static function get_filter_hooks_metadata(): array {
				return array();
			}
		};
		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$this->assertFalse($registrar->init($provider));
	}

	public function test_init_registers_declared_filter(): void {
		$provider = $this->makeProviderSimple();
		$this->assertTrue(method_exists($provider, 'filter_content'));

		WP_Mock::expectFilterAdded('the_content', array($provider, 'filter_content'), 10, 1);

		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$this->assertTrue($registrar->init($provider));
	}

	public function test_init_throws_on_invalid_provider(): void {
		$provider  = new \stdClass();
		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$this->expectException(InvalidArgumentException::class);
		$registrar->init(new \stdClass());
	}

	public function test_invalid_definition_type_returns_false(): void {
		$provider = new class implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array();
			}
			public static function validate_filter_hooks(object $instance): array {
				return array();
			}
			public static function get_filter_hooks_metadata(): array {
				return array();
			}
		};
		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$ref       = new \ReflectionClass($registrar);
		$m         = $ref->getMethod('register_single_filter_hook');
		$m->setAccessible(true);
		$this->assertFalse($m->invoke($registrar, $provider, 'the_content', new \stdClass()));
	}

	public function test_missing_method_returns_false(): void {
		$provider  = $this->makeProviderMissing();
		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$this->assertFalse($this->invokeRegisterSingle($registrar, $provider, 'the_content', 'missing'));
	}

	public function test_validation_errors_throw(): void {
		$provider  = $this->makeProviderInvalid();
		$registrar = new FilterHooksRegistrar($provider, $this->logger_mock);
		$this->expectException(InvalidArgumentException::class);
		$registrar->init($provider);
	}

	private function invokeRegisterSingle(FilterHooksRegistrar $registrar, object $provider, string $hook, $def): bool {
		$ref = new \ReflectionClass($registrar);
		$m   = $ref->getMethod('register_single_filter_hook');
		$m->setAccessible(true);
		return $m->invoke($registrar, $provider, $hook, $def);
	}
}
