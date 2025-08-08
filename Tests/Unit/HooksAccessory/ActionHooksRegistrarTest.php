<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use InvalidArgumentException;
use Ran\PluginLib\HooksAccessory\ActionHooksInterface;
use Ran\PluginLib\HooksAccessory\ActionHooksRegistrar;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

final class ActionHooksRegistrarTest extends PluginLibTestCase {
	// Replaced dynamic provider builder with concrete anonymous providers per test for static methods correctness
	private function makeInvalidProviderMissingMethod(): object {
		return new class implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array('wp_init' => 'missing');
			}
			public static function validate_action_hooks(object $instance): array {
				return array();
			}
			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};
	}

	public function test_no_hooks_returns_false(): void {
		$provider = new class implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array();
			}
			public static function validate_action_hooks(object $instance): array {
				return array();
			}
			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};
		$registrar = new ActionHooksRegistrar($this->logger_mock);
		$this->assertFalse($registrar->init($provider));
	}

	public function test_init_registers_declared_action(): void {
		$provider = new class implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array('wp_init' => 'boot');
			}
			public static function validate_action_hooks(object $instance): array {
				return array();
			}
			public static function get_action_hooks_metadata(): array {
				return array();
			}
			public function boot(): void {
			}
		};
		// ensure provider has the method
		$this->assertTrue(method_exists($provider, 'boot'));

		WP_Mock::expectActionAdded('wp_init', array($provider, 'boot'), 10, 1);

		$registrar = new ActionHooksRegistrar($this->logger_mock);
		// register_single_action_hook returns true; but init() returns true only if hooks array is non-empty and no validation errors
		$this->assertTrue($registrar->init($provider));
	}

	public function test_init_throws_on_invalid_provider(): void {
		$registrar = new ActionHooksRegistrar($this->logger_mock);
		$this->expectException(InvalidArgumentException::class);
		$registrar->init(new \stdClass());
	}

	public function test_invalid_definition_type_returns_false(): void {
		$provider = new class implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array();
			}
			public static function validate_action_hooks(object $instance): array {
				return array();
			}
			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};
		$registrar = new ActionHooksRegistrar($this->logger_mock);
		$ref       = new \ReflectionClass($registrar);
		$m         = $ref->getMethod('register_single_action_hook');
		$m->setAccessible(true);
		$this->assertFalse($m->invoke($registrar, $provider, 'wp_init', new \stdClass()));
	}

	public function test_missing_method_returns_false(): void {
		$provider  = $this->makeInvalidProviderMissingMethod();
		$registrar = new ActionHooksRegistrar($this->logger_mock);
		$this->assertFalse($this->invokeRegisterSingle($registrar, $provider, 'wp_init', 'missing'));
	}

	public function test_validation_errors_throw(): void {
		$provider = new class implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array('wp_init' => 'boot');
			}
			public static function validate_action_hooks(object $instance): array {
				return array('bad config');
			}
			public static function get_action_hooks_metadata(): array {
				return array();
			}
			public function boot(): void {
			}
		};
		$registrar = new ActionHooksRegistrar($this->logger_mock);
		$this->expectException(InvalidArgumentException::class);
		$registrar->init($provider);
	}

	private function invokeRegisterSingle(ActionHooksRegistrar $registrar, object $provider, string $hook, $def): bool {
		$ref = new \ReflectionClass($registrar);
		$m   = $ref->getMethod('register_single_action_hook');
		$m->setAccessible(true);
		return $m->invoke($registrar, $provider, $hook, $def);
	}
}
