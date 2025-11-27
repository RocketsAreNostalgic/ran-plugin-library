<?php
/**
 * Tests for schema coercion optimization (Workstream A).
 *
 * Verifies that:
 * - _is_canonical_bucket_structure() correctly detects normalized entries
 * - Call 3 removal doesn't affect merge results
 * - Structure check short-circuits Call 2 for pre-coerced entries
 * - Queued validators are still validated by Call 4
 *
 * @package Ran\PluginLib\Tests\Unit\Options
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use ReflectionClass;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::_register_internal_schema
 * @covers \Ran\PluginLib\Options\RegisterOptions::_is_canonical_bucket_structure
 * @covers \Ran\PluginLib\Options\RegisterOptions::_coerce_schema_entry
 */
final class RegisterOptionsCoercionOptimizationTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private RegisterOptions $opts;

	public function setUp(): void {
		parent::setUp();

		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		$this->opts = new RegisterOptions(
			'test_coercion_options',
			StorageContext::forSite(),
			true,
			$this->logger_mock
		);
	}

	/**
	 * Helper to invoke private methods via reflection.
	 *
	 * @param object $object     The object instance.
	 * @param string $methodName The method name.
	 * @param array  $args       The method arguments.
	 * @return mixed
	 */
	private function invokePrivate(object $object, string $methodName, array $args = array()): mixed {
		$reflection = new ReflectionClass($object);
		$method     = $reflection->getMethod($methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $args);
	}

	/**
	 * Helper to get private property value via reflection.
	 *
	 * @param object $object       The object instance.
	 * @param string $propertyName The property name.
	 * @return mixed
	 */
	private function getPrivateProperty(object $object, string $propertyName): mixed {
		$reflection = new ReflectionClass($object);
		$property   = $reflection->getProperty($propertyName);
		$property->setAccessible(true);
		return $property->getValue($object);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// _is_canonical_bucket_structure() Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_is_canonical_bucket_structure_returns_true_for_complete_structure(): void {
		$entry = array(
			'sanitize' => array(
				'component' => array(),
				'schema'    => array(),
			),
			'validate' => array(
				'component' => array(),
				'schema'    => array(),
			),
		);

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertTrue($result, 'Complete bucket structure should be detected as canonical');
	}

	public function test_is_canonical_bucket_structure_returns_true_with_callables(): void {
		$entry = array(
			'sanitize' => array(
				'component' => array('intval'),
				'schema'    => array('trim'),
			),
			'validate' => array(
				'component' => array('is_numeric'),
				'schema'    => array(),
			),
			'default' => 'test',
		);

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertTrue($result, 'Bucket structure with callables and default should be canonical');
	}

	public function test_is_canonical_bucket_structure_returns_false_for_flat_structure(): void {
		$entry = array(
			'sanitize' => array('intval'),
			'validate' => array('is_numeric'),
		);

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertFalse($result, 'Flat callable arrays should not be detected as canonical');
	}

	public function test_is_canonical_bucket_structure_returns_false_for_partial_structure(): void {
		// Missing validate.schema
		$entry = array(
			'sanitize' => array(
				'component' => array(),
				'schema'    => array(),
			),
			'validate' => array(
				'component' => array(),
			),
		);

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertFalse($result, 'Partial bucket structure should not be detected as canonical');
	}

	public function test_is_canonical_bucket_structure_returns_false_for_empty_entry(): void {
		$entry = array();

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertFalse($result, 'Empty entry should not be detected as canonical');
	}

	public function test_is_canonical_bucket_structure_returns_false_when_buckets_are_not_arrays(): void {
		$entry = array(
			'sanitize' => array(
				'component' => 'not_an_array',
				'schema'    => array(),
			),
			'validate' => array(
				'component' => array(),
				'schema'    => array(),
			),
		);

		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($entry));

		$this->assertFalse($result, 'Non-array bucket values should not be detected as canonical');
	}

	// ─────────────────────────────────────────────────────────────────────────
	// _register_internal_schema() Optimization Tests
	// ─────────────────────────────────────────────────────────────────────────

	public function test_register_internal_schema_handles_first_registration(): void {
		$schema = array(
			'test_key' => array(
				'sanitize' => array('trim'),
				'validate' => array('is_string'),
				'default'  => 'hello',
			),
		);

		$this->opts->_register_internal_schema($schema);

		$internalSchema = $this->getPrivateProperty($this->opts, 'schema');

		$this->assertArrayHasKey('test_key', $internalSchema);
		$this->assertArrayHasKey('sanitize', $internalSchema['test_key']);
		$this->assertArrayHasKey('component', $internalSchema['test_key']['sanitize']);
		$this->assertArrayHasKey('schema', $internalSchema['test_key']['sanitize']);
	}

	public function test_register_internal_schema_merges_with_existing(): void {
		// First registration
		$schema1 = array(
			'merge_key' => array(
				'sanitize' => array('trim'),
				'validate' => array(),
				'default'  => 'first',
			),
		);
		$this->opts->_register_internal_schema($schema1);

		// Second registration should merge
		$schema2 = array(
			'merge_key' => array(
				'sanitize' => array('strtolower'),
				'validate' => array('is_string'),
				'default'  => 'second',
			),
		);
		$this->opts->_register_internal_schema($schema2);

		$internalSchema = $this->getPrivateProperty($this->opts, 'schema');

		// Default should be from second registration (incoming takes precedence)
		$this->assertSame('second', $internalSchema['merge_key']['default']);

		// Sanitizers should be merged
		$schemaSanitizers = $internalSchema['merge_key']['sanitize']['schema'];
		$this->assertCount(2, $schemaSanitizers, 'Both sanitizers should be present');
	}

	public function test_register_internal_schema_with_queued_validators(): void {
		$schema = array(
			'queued_key' => array(
				'sanitize' => array('trim'),
				'validate' => array(),
			),
		);

		$queuedValidators = array(
			'queued_key' => array('is_string', 'strlen'),
		);

		$this->opts->_register_internal_schema($schema, array(), $queuedValidators);

		$internalSchema = $this->getPrivateProperty($this->opts, 'schema');

		// Queued validators should be in component bucket
		$componentValidators = $internalSchema['queued_key']['validate']['component'];
		$this->assertContains('is_string', $componentValidators);
		$this->assertContains('strlen', $componentValidators);
	}

	public function test_register_internal_schema_preserves_bucket_structure_after_merge(): void {
		// Register with bucketed schema directly
		$bucketedSchema = array(
			'bucketed_key' => array(
				'sanitize' => array(
					'component' => array('intval'),
					'schema'    => array('abs'),
				),
				'validate' => array(
					'component' => array('is_int'),
					'schema'    => array(),
				),
			),
		);

		$this->opts->_register_internal_schema($bucketedSchema);

		$internalSchema = $this->getPrivateProperty($this->opts, 'schema');

		// Structure should remain canonical
		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($internalSchema['bucketed_key']));
		$this->assertTrue($result, 'Registered schema should be in canonical form');
	}

	public function test_multiple_registrations_produce_canonical_structure(): void {
		// Use real callable functions for each registration
		$sanitizers = array('trim', 'strtolower', 'stripslashes');

		// Simulate multiple registration calls (like what happens in Settings facades)
		foreach ($sanitizers as $sanitizer) {
			$schema = array(
				'multi_key' => array(
					'sanitize' => array($sanitizer),
					'validate' => array(),
				),
			);
			$this->opts->_register_internal_schema($schema);
		}

		$internalSchema = $this->getPrivateProperty($this->opts, 'schema');

		// Final structure should be canonical
		$result = $this->invokePrivate($this->opts, '_is_canonical_bucket_structure', array($internalSchema['multi_key']));
		$this->assertTrue($result, 'After multiple registrations, schema should be in canonical form');

		// Should have merged sanitizers
		$schemaSanitizers = $internalSchema['multi_key']['sanitize']['schema'];
		$this->assertCount(3, $schemaSanitizers, 'All three sanitizers should be merged');
	}
}
