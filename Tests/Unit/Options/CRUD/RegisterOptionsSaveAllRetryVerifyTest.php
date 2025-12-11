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

		// Shared state for tracking read calls and verify payload
		$sharedState                = new \stdClass();
		$sharedState->readCalls     = 0;
		$sharedState->verifyPayload = array();

		// Anonymous subclass to inject a controlled storage
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock, $sharedState) extends RegisterOptions {
			private object $storage;
			public function __construct($main, $ctx, $autoload, $logger, $sharedState) {
				// Initialize storage BEFORE parent constructor to avoid early _get_storage() access
				$this->storage = new class($sharedState) implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					private object $state;
					public function __construct($state) {
						$this->state = $state;
					}
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
						// 1st call: constructor load
						// 2nd call: existence check → return array to indicate "exists"
						// 3rd call: verification → return the verify payload
						$this->state->readCalls++;
						if ($this->state->readCalls <= 2) {
							return array('exists' => true); // row exists (constructor + existence check)
						}
						return $this->state->verifyPayload;
					}
					public function update(string $key, mixed $value, ?bool $autoload = null): bool {
						// Always return false to trigger retry + verify path
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
		};

		// Prepare payload to save; directly set options via reflection to avoid other side effects
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('foo' => 'bar', 'n' => 1);
		$prop->setValue($opts, $toSave);
		$sharedState->verifyPayload = $toSave;
		$ref                        = new \ReflectionClass($opts);

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

		// Shared state for tracking read calls
		$sharedState            = new \stdClass();
		$sharedState->readCalls = 0;

		// Anonymous subclass: force update() to return false twice; verify returns non-matching value
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock, $sharedState) extends RegisterOptions {
			private object $storage;
			public function __construct($main, $ctx, $autoload, $logger, $sharedState) {
				$this->storage = new class($sharedState) implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					private object $state;
					public function __construct($state) {
						$this->state = $state;
					}
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
						// 1st call: constructor load
						// 2nd call: existence check → return array to indicate "exists"
						// 3rd call: verification → return non-matching payload
						$this->state->readCalls++;
						if ($this->state->readCalls <= 2) {
							return array('exists' => true); // row exists (constructor + existence check)
						}
						return array('different' => 'payload'); // non-matching
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

		// Shared state for tracking read calls and verify payload
		$sharedState                = new \stdClass();
		$sharedState->readCalls     = 0;
		$sharedState->verifyPayload = array();

		// Anonymous subclass: force add() to fail, update() to fail; verify returns matching value
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock, $sharedState) extends RegisterOptions {
			private object $storage;
			public function __construct($main, $ctx, $autoload, $logger, $sharedState) {
				// Create storage with access to shared state for read call tracking
				$this->storage = new class($sharedState) implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					private object $state;
					public function __construct($state) {
						$this->state = $state;
					}
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
						// 1st call: constructor load → return false (not found)
						// 2nd call: existence check → return false (not found)
						// 3rd call: verification → return the verify payload
						$this->state->readCalls++;
						if ($this->state->readCalls <= 2) {
							return false; // not found (constructor + existence check)
						}
						return $this->state->verifyPayload;
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
		};

		// Prepare payload to save; reflect into private property and set verify payload
		$baseRef = new \ReflectionClass(\Ran\PluginLib\Options\RegisterOptions::class);
		$prop    = $baseRef->getProperty('options');
		$prop->setAccessible(true);
		$toSave = array('a' => 1, 'b' => 'x');
		$prop->setValue($opts, $toSave);
		$sharedState->verifyPayload = $toSave;
		$ref                        = new \ReflectionClass($opts);

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

		// Shared state for tracking read calls
		$sharedState            = new \stdClass();
		$sharedState->readCalls = 0;

		// Anonymous subclass: force add() fail, update() fail, and verify returns non-matching payload
		$opts = new class($main, StorageContext::forSite(), true, $this->logger_mock, $sharedState) extends RegisterOptions {
			private object $storage;
			public function __construct($main, $ctx, $autoload, $logger, $sharedState) {
				$this->storage = new class($sharedState) implements \Ran\PluginLib\Options\Storage\OptionStorageInterface {
					private object $state;
					public function __construct($state) {
						$this->state = $state;
					}
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
						// 1st call: constructor load → return false (not found)
						// 2nd call: existence check → return false (not found)
						// 3rd call: verification → return mismatched payload
						$this->state->readCalls++;
						if ($this->state->readCalls <= 2) {
							return false; // not found (constructor + existence check)
						}
						return array('mismatch' => true); // non-matching payload
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
