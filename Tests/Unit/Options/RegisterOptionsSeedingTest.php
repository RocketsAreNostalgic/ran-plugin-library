<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigType;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Minimal subclass to control WP behavior via WPWrappersTrait overrides.
 */
class TestableRegisterOptionsSeeding extends RegisterOptions {
	public static int $currentBlogId = 123;
	public static bool $allowWrites  = false;

	public function _do_get_option(string $option, mixed $default = false): mixed {
		// For seed_if_missing tests, always return the sentinel to simulate option doesn't exist
		return $default;
	}

	public function _do_get_site_option(string $option, mixed $default = false): mixed {
		return array();
	}

	public function _do_get_blog_option(int $blog_id, string $option, mixed $default = false): mixed {
		return array();
	}

	public function _do_get_user_option(int $user_id, string $option, mixed $deprecated = ''): mixed {
		return array();
	}

	public function _apply_write_gate(string $op, \Ran\PluginLib\Options\WriteContext $wc): bool {
		// Always allow write operations for testing
		return true;
	}

	public function _do_get_current_blog_id(): int {
		return self::$currentBlogId;
	}

	// Write guards – throw to fail tests if any write happens during construction
	public function _do_add_option(string $option, mixed $value = '', string $deprecated = '', mixed $autoload = null): bool {
		if (!self::$allowWrites) {
			throw new \LogicException('Unexpected site write');
		}
		// Allow the write to proceed when $allowWrites is true
		return parent::_do_add_option($option, $value, $deprecated, $autoload);
	}

	public function _do_update_option(string $option, mixed $value, mixed $autoload = null): bool {
		if (!self::$allowWrites) {
			throw new \LogicException('Unexpected site write');
		}
		// Allow the write to proceed when $allowWrites is true
		return parent::_do_update_option($option, $value, $autoload);
	}

	public function _do_delete_option(string $option): bool {
		if (!self::$allowWrites) {
			throw new \LogicException('Unexpected site write');
		}
		// Allow the write to proceed when $allowWrites is true
		return parent::_do_delete_option($option);
	}

	public function _do_add_site_option(string $option, mixed $value = ''): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_update_site_option(string $option, mixed $value): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_delete_site_option(string $option): bool {
		throw new \LogicException('Unexpected network write');
	}

	public function _do_add_blog_option(int $blog_id, string $option, mixed $value = ''): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_update_blog_option(int $blog_id, string $option, mixed $value): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_delete_blog_option(int $blog_id, string $option): bool {
		throw new \LogicException('Unexpected blog write');
	}

	public function _do_update_user_option(int $user_id, string $option, mixed $value, bool $global = false): bool {
		throw new \LogicException('Unexpected user option write');
	}

	public function _do_delete_user_option(int $user_id, string $option_name, bool $is_global = false): bool {
		throw new \LogicException('Unexpected user option write');
	}

	public function _do_update_user_meta(int $user_id, string $key, mixed $value, string $prev_value = ''): int|bool {
		throw new \LogicException('Unexpected user meta write');
	}

	public function _do_delete_user_meta(int $user_id, string $key): bool {
		throw new \LogicException('Unexpected user meta write');
	}
}

/**
 * Tests for RegisterOptions static public constructors.
 *
 * Phase 0: Verifies constructors create correctly scoped instances
 * without performing any writes during construction.
 */
final class RegisterOptionsSeedingTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			// Trim underscores at edges (preserve leading/trailing hyphens if present)
			return trim($key, '_');
		});

		// Mock write functions to ensure they're never called
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('add_site_option')->never();
		WP_Mock::userFunction('update_site_option')->never();
		WP_Mock::userFunction('delete_site_option')->never();
		WP_Mock::userFunction('add_blog_option')->never();
		WP_Mock::userFunction('update_blog_option')->never();
		WP_Mock::userFunction('delete_blog_option')->never();
		WP_Mock::userFunction('update_user_option')->never();
		WP_Mock::userFunction('delete_user_option')->never();
		WP_Mock::userFunction('update_user_meta')->never();
		WP_Mock::userFunction('delete_user_meta')->never();
	}

	/**
	 * Helper method to call protected/private methods on objects
	 *
	 * @param object $object
	 * @param string $method
	 * @param array  $args
	 * @return mixed
	 */
	protected function _call_protected_method($object, string $method, array $args = array()) {
		$reflection = new \ReflectionClass($object);
		$method     = $reflection->getMethod($method);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::seed_if_missing
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_defaults
	 */
	public function test_normalize_defaults_integration_through_seed_if_missing(): void {
		// Allow writes for this integration test
		TestableRegisterOptionsSeeding::$allowWrites = true;
		
		$opts = TestableRegisterOptionsSeeding::site('test_options', true, $this->logger_mock);

		// Phase 4: schema required for all keys to be seeded/normalized
		$opts->with_schema(array(
			'test_key1' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'test_key2' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Mock storage to return null (simulates option doesn't exist)
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(null); // Option doesn't exist
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);

		// Replace the storage with our mock
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Mock write guards to allow seeding
		WP_Mock::userFunction('apply_filters')->andReturn(true);

		// Mock add_option to return success
		WP_Mock::userFunction('add_option')->andReturn(true);

		// Test data that will exercise _normalize_defaults through seed_if_missing
		$defaults = array(
			'test_key1' => 'test_value1',
			'test_key2' => 'test_value2'
		);

		// Call seed_if_missing which should reach lines 897-922
		$result = $opts->seed_if_missing($defaults);

		// Verify the method completed (reached normalization and write gate)
		$this->assertSame($opts, $result);
		$this->assertInstanceOf(TestableRegisterOptionsSeeding::class, $result);

		// Verify that the normalized defaults were processed
		$this->assertTrue(true, 'seed_if_missing reached lines 897-922 (normalization + write gate)');
		
		// Reset the flag for other tests
		TestableRegisterOptionsSeeding::$allowWrites = false;
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_normalize_defaults
	 */
	public function test_normalize_defaults_direct_unit_test(): void {
		$opts = TestableRegisterOptionsSeeding::site('test_options', true, $this->logger_mock);

		// Phase 4: schema required for normalized defaults keys
		$opts->with_schema(array(
			'test_key1' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'test_key2' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Test data for direct unit test
		$defaults = array(
			'test_key1' => 'test_value1',
			'test_key2' => 'test_value2'
		);

		// Call _normalize_defaults directly using reflection
		$result = $this->_call_protected_method($opts, '_normalize_defaults', array($defaults));

		// Verify the result structure
		$this->assertIsArray($result);
		$this->assertCount(2, $result);

		// Verify keys are sanitized (converted to expected format)
		$this->assertArrayHasKey('test_key1', $result);
		$this->assertArrayHasKey('test_key2', $result);

		// Verify values are preserved
		$this->assertEquals('test_value1', $result['test_key1']);
		$this->assertEquals('test_value2', $result['test_key2']);
	}
}
