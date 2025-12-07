<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Covers RegisterOptions::_save_all_options lines 1109–1116:
 * - First storage->update() returns false → retry once → still false
 * - Verify DB state via get_option matches desired payload → treat as success and log warning
 */
final class RegisterOptionsSaveAllRetryVerifyTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Common WP stubs
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('current_user_can')->andReturn(true)->byDefault();
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_update_retry_then_verify_treats_as_success_when_db_matches(): void {
		$main = 'opts_retry_verify_success';

		// Anonymous subclass to inject a controlled storage and custom get_option behavior
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock) extends RegisterOptions {
			private object $storage;
			private int $getCalls        = 0;
			private array $verifyPayload = array();
			public function __construct($main, $ctx, $autoload, $logger) {
				// Initialize storage BEFORE parent constructor to avoid early _get_storage() access
				$this->storage = new class implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					private int $updates = 0;
					public function scope(): \Ran\PluginLib\Options\OptionScope {
						return \Ran\PluginLib\Options\OptionScope::Site;
					}
					public function blog_id(): ?int {
						return null;
					}
					public function supports_autoload(): bool {
						return true;
					}
					public function read(string $key, mixed $default = false): mixed {
						return $default;
					}
					public function update(string $key, mixed $value, ?bool $autoload = null): bool {
						// First and second calls return false to trigger retry + verify path
						$this->updates++;
						return false;
					}
					public function add(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function delete(string $key): bool {
						return false;
					}
				};
				parent::__construct($main, $ctx, $autoload, $logger);
			}
			protected function _get_storage(): \Ran\PluginLib\Options\Storage\OptionStorageInterface {
				return $this->storage;
			}
			public function setVerifyPayload(array $payload): void {
				$this->verifyPayload = $payload;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
					return true;
				}
				return $value;
			}
			public function _do_get_option(string $option, mixed $default = false): mixed {
				// 1st call: existence check → pretend row exists
				// 2nd call (verify): return the desired $this->options payload
				$this->getCalls++;
				if ($this->getCalls === 1) {
					return array('exists' => true);
				}
				return $this->verifyPayload;
			}
		};

		// Prepare payload to save; directly set options via reflection to avoid other side effects
		// Reflect the private base-class property from RegisterOptions to set on this instance
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('foo' => 'bar', 'n' => 1);
		$prop->setValue($opts, $toSave);
		// Also set the verify payload used by the anonymous subclass for the post-update DB check
		if (method_exists($opts, 'setVerifyPayload')) {
			$opts->setVerifyPayload($toSave);
		}
		$ref = new \ReflectionClass($opts);

		// Expect: first update false → retry → false → verify matches → treat as success (result true)

		// Invoke protected _save_all_options(false) via reflection
		$m = $ref->getMethod('_save_all_options');
		$m->setAccessible(true);
		$result = $m->invoke($opts, false);

		// Should be treated as success and local cache updated
		$this->assertTrue($result);
		$this->assertSame($toSave, $prop->getValue($opts));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_update_retry_then_verify_logs_failure_when_db_does_not_match(): void {
		$main = 'opts_retry_verify_failure';

		// Anonymous subclass: force update() to return false twice; verify returns non-matching value
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock) extends RegisterOptions {
			private object $storage;
			private int $getCalls = 0;
			public function __construct($main, $ctx, $autoload, $logger) {
				$this->storage = new class implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					public function scope(): \Ran\PluginLib\Options\OptionScope {
						return \Ran\PluginLib\Options\OptionScope::Site;
					}
					public function blog_id(): ?int {
						return null;
					}
					public function supports_autoload(): bool {
						return true;
					}
					public function read(string $key, mixed $default = false): mixed {
						return $default;
					}
					public function update(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function add(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function delete(string $key): bool {
						return false;
					}
				};
				parent::__construct($main, $ctx, $autoload, $logger);
			}
			protected function _get_storage(): \Ran\PluginLib\Options\Storage\OptionStorageInterface {
				return $this->storage;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
					return true; // allow this instance
				}
				return $value;
			}
			public function _do_get_option(string $option, mixed $default = false): mixed {
				// 1st call existence check → pretend row exists; 2nd call verify → return non-matching
				$this->getCalls++;
				if ($this->getCalls === 1) {
					return array('exists' => true);
				}
				return array('different' => 'payload');
			}
		};

		// Prepare payload to save and reflect into private property
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('foo' => 'bar', 'n' => 1);
		$prop->setValue($opts, $toSave);
		$ref = new \ReflectionClass($opts);

		// Invoke protected _save_all_options(false)
		$m = $ref->getMethod('_save_all_options');
		$m->setAccessible(true);
		$result = $m->invoke($opts, false);

		// Should be treated as failure (false), but local cache mirrors attempted save
		$this->assertFalse($result);
		$this->assertSame($toSave, $prop->getValue($opts));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_add_fallback_then_verify_treats_as_success_when_db_matches(): void {
		$main = 'opts_add_fallback_verify_success';

		// Anonymous subclass: force add() to fail, update() to fail; verify returns matching value
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock) extends RegisterOptions {
			private object $storage;
			private int $getCalls        = 0;
			private array $verifyPayload = array();
			public function __construct($main, $ctx, $autoload, $logger) {
				$this->storage = new class implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					public function scope(): \Ran\PluginLib\Options\OptionScope {
						return \Ran\PluginLib\Options\OptionScope::Site;
					}
					public function blog_id(): ?int {
						return null;
					}
					public function supports_autoload(): bool {
						return true;
					}
					public function read(string $key, mixed $default = false): mixed {
						return $default;
					}
					public function update(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function add(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function delete(string $key): bool {
						return false;
					}
				};
				parent::__construct($main, $ctx, $autoload, $logger);
			}
			protected function _get_storage(): \Ran\PluginLib\Options\Storage\OptionStorageInterface {
				return $this->storage;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
					return true; // allow for this instance
				}
				return $value;
			}
			public function setVerifyPayload(array $payload): void {
				$this->verifyPayload = $payload;
			}
			public function _do_get_option(string $option, mixed $default = false): mixed {
				// 1st call existence check → pretend row does NOT exist (return sentinel)
				// 2nd call verify → return the desired payload to simulate DB match
				$this->getCalls++;
				if ($this->getCalls === 1) {
					return $default; // sentinel: missing row
				}
				return $this->verifyPayload;
			}
		};

		// Prepare payload to save; reflect into private property and set verify payload
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('a' => 1, 'b' => 'x');
		$prop->setValue($opts, $toSave);
		if (method_exists($opts, 'setVerifyPayload')) {
			$opts->setVerifyPayload($toSave);
		}
		$ref = new \ReflectionClass($opts);

		// Invoke protected _save_all_options(false) to exercise add() → update() → verify success path
		$m = $ref->getMethod('_save_all_options');
		$m->setAccessible(true);
		$result = $m->invoke($opts, false);

		// Should be treated as success and local cache updated
		$this->assertTrue($result);
		$this->assertSame($toSave, $prop->getValue($opts));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_save_all_options
	 */
	public function test_add_fallback_then_verify_logs_failure_when_db_does_not_match(): void {
		$main = 'opts_add_fallback_verify_failure';

		// Anonymous subclass: force add() fail, update() fail, and verify returns non-matching payload
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock) extends RegisterOptions {
			private object $storage;
			private int $getCalls = 0;
			public function __construct($main, $ctx, $autoload, $logger) {
				$this->storage = new class implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					public function scope(): \Ran\PluginLib\Options\OptionScope {
						return \Ran\PluginLib\Options\OptionScope::Site;
					}
					public function blog_id(): ?int {
						return null;
					}
					public function supports_autoload(): bool {
						return true;
					}
					public function read(string $key, mixed $default = false): mixed {
						return $default;
					}
					public function update(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function add(string $key, mixed $value, ?bool $autoload = null): bool {
						return false;
					}
					public function delete(string $key): bool {
						return false;
					}
				};
				parent::__construct($main, $ctx, $autoload, $logger);
			}
			protected function _get_storage(): \Ran\PluginLib\Options\Storage\OptionStorageInterface {
				return $this->storage;
			}
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				if ($hook_name === 'ran/plugin_lib/options/allow_persist' || $hook_name === 'ran/plugin_lib/options/allow_persist/scope/site') {
					return true;
				}
				return $value;
			}
			public function _do_get_option(string $option, mixed $default = false): mixed {
				// 1st call existence check → pretend row does NOT exist (return sentinel); 2nd call verify → mismatch
				$this->getCalls++;
				if ($this->getCalls === 1) {
					return $default; // sentinel missing row
				}
				return array('mismatch' => true);
			}
		};

		// Prepare payload and invoke
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('x' => 9);
		$prop->setValue($opts, $toSave);
		$ref = new \ReflectionClass($opts);

		$m = $ref->getMethod('_save_all_options');
		$m->setAccessible(true);
		$result = $m->invoke($opts, false);

		// Expect failure warning at add-fallback mismatch branch (after invocation)
		$this->expectLog('warning', 'RegisterOptions: storage->update() also failed and DB does not match desired state.');

		$this->assertFalse($result);
		$this->assertSame($toSave, $prop->getValue($opts));
	}
}
