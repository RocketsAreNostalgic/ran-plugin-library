<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;

/**
 * Tests for RegisterOptions helper methods functionality.
 *
 * Tests the add_component_* and add_schema_* helper methods that allow
 * bucket-aware modification of validation and sanitization pipelines.
 */
final class RegisterOptionsFluentMethodsTest extends PluginLibTestCase {
	protected ?CollectingLogger $logger_mock = null;

	public function setUp(): void {
		parent::setUp();

		// Create logger mock using parent method
		$this->logger_mock = new CollectingLogger(array());

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('update_site_option')->andReturn(true);
		WP_Mock::userFunction('update_blog_option')->andReturn(true);
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
	}

	public function tearDown(): void {
		// Force garbage collection to clear any lingering object references
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}

		parent::tearDown();
	}

	/**
	 * Test add_component_validator() method functionality.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_add_component_validator_functionality(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register initial schema with one validator
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) {
						if (strlen($value) > 10) {
							$emitWarning('Value too long');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Component validator that checks minimum length
		$result = $options->add_component_validator('test_field', function($value, callable $emitWarning) {
			if (strlen($value) < 3) {
				$emitWarning('Value too short');
				return false;
			}
			return true;
		});

		// Should return self for fluent interface
		$this->assertSame($options, $result);

		// Test that prepended validator runs first (short value should fail on first validator)
		$options->stage_option('test_field', 'ab'); // Too short
		$commit_result = $options->commit_merge();
		$this->assertFalse($commit_result, 'Should fail on prepended validator');

		// Verify first validator's message was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value too short', $messages['test_field']['warnings']);

		// Test that both validators run (medium length should pass both)
		$options->stage_option('test_field', 'hello'); // Just right
		$commit_result = $options->commit_merge();
		$this->assertTrue($commit_result, 'Should pass both validators');

		// Test that original validator still works (long value should fail on original validator)
		$options->stage_option('test_field', 'this is too long'); // Too long
		$commit_result = $options->commit_merge();
		$this->assertFalse($commit_result, 'Should fail on original validator');

		// Verify original validator's message was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value too long', $messages['test_field']['warnings']);
	}

	/**
	 * Test add_schema_validator() method functionality.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_add_schema_validator_functionality(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register initial schema with one validator
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) {
						if (empty($value)) {
							$emitWarning('Value is required');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Schema validator that checks for specific content
		$result = $options->add_schema_validator('test_field', function($value, callable $emitWarning) {
			if (strpos($value, 'forbidden') !== false) {
				$emitWarning('Value contains forbidden content');
				return false;
			}
			return true;
		});

		// Should return self for fluent interface
		$this->assertSame($options, $result);

		// Test that both validators run in order
		$options->stage_option('test_field', ''); // Empty - should fail on first validator
		$commit_result = $options->commit_merge();
		$this->assertFalse($commit_result, 'Should fail on original validator');

		// Verify first validator's message was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value is required', $messages['test_field']['warnings']);

		$options->stage_option('test_field', 'forbidden word'); // Has content but forbidden - should fail on appended validator
		$commit_result = $options->commit_merge();
		$this->assertFalse($commit_result, 'Should fail on appended validator');

		// Verify appended validator's message was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value contains forbidden content', $messages['test_field']['warnings']);

		$options->stage_option('test_field', 'allowed content'); // Should pass both
		$commit_result = $options->commit_merge();
		$this->assertTrue($commit_result, 'Should pass both validators');
	}

	/**
	 * Test add_component_sanitizer() method functionality.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_add_component_sanitizer_functionality(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register initial schema with one sanitizer
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Value was trimmed');
						}
						return $trimmed;
					}
				),
				'validate' => array(
					function($value) {
						return !empty($value);
					}
				)
			)
		));

		// Component sanitizer that converts to lowercase
		$result = $options->add_component_sanitizer('test_field', function($value, callable $emitNotice) {
			$lowercase = strtolower($value);
			if ($lowercase !== $value) {
				$emitNotice('Value was converted to lowercase');
			}
			return $lowercase;
		});

		// Should return self for fluent interface
		$this->assertSame($options, $result);

		// Test that prepended sanitizer runs first, then original sanitizer
		$options->stage_option('test_field', '  HELLO WORLD  '); // Has uppercase and whitespace
		$commit_result = $options->commit_merge();
		$this->assertTrue($commit_result, 'Should succeed after sanitization');

		// Should be lowercase and trimmed (prepended runs first, then original)
		$this->assertEquals('hello world', $options->get_option('test_field'));

		// Verify both sanitizers' notices were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value was converted to lowercase', $messages['test_field']['notices']);
		$this->assertContains('Value was trimmed', $messages['test_field']['notices']);
	}

	/**
	 * Test add_schema_sanitizer() method functionality.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_add_schema_sanitizer_functionality(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register initial schema with one sanitizer
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Value was trimmed');
						}
						return $trimmed;
					}
				),
				'validate' => array(
					function($value) {
						return !empty($value);
					}
				)
			)
		));

		// Schema sanitizer removes special characters
		$result = $options->add_schema_sanitizer('test_field', function($value, callable $emitNotice) {
			$cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $value);
			if ($cleaned !== $value) {
				$emitNotice('Special characters were removed');
			}
			return $cleaned;
		});

		// Should return self for fluent interface
		$this->assertSame($options, $result);

		// Test that original sanitizer runs first, then appended sanitizer
		$options->stage_option('test_field', '  hello@world!  '); // Has whitespace and special chars
		$commit_result = $options->commit_merge();
		$this->assertTrue($commit_result, 'Should succeed after sanitization');

		// Should be trimmed first, then special chars removed
		$this->assertEquals('helloworld', $options->get_option('test_field'));

		// Verify both sanitizers' notices were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertContains('Value was trimmed', $messages['test_field']['notices']);
		$this->assertContains('Special characters were removed', $messages['test_field']['notices']);
	}
	/**
	 * Test fluent method chaining and complex scenarios.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 */
	public function test_fluent_method_chaining(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register basic schema
		$options->register_schema(array(
			'complex_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Value was trimmed');
						}
						return $trimmed;
					}
				),
				'validate' => array(
					function($value, callable $emitWarning) {
						if (empty($value)) {
							$emitWarning('Value is required');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Chain multiple fluent method calls
		$result = $options
			->add_component_sanitizer('complex_field', function($value, callable $emitNotice) {
				$lowercase = strtolower($value);
				if ($lowercase !== $value) {
					$emitNotice('Converted to lowercase');
				}
				return $lowercase;
			})
			->add_schema_sanitizer('complex_field', function($value, callable $emitNotice) {
				$cleaned = preg_replace('/\s+/', ' ', $value); // Normalize whitespace
				if ($cleaned !== $value) {
					$emitNotice('Whitespace normalized');
				}
				return $cleaned;
			})
			->add_component_validator('complex_field', function($value, callable $emitWarning) {
				if (strlen($value) < 2) {
					$emitWarning('Value too short');
					return false;
				}
				return true;
			})
			->add_schema_validator('complex_field', function($value, callable $emitWarning) {
				if (strlen($value) > 100) {
					$emitWarning('Value too long');
					return false;
				}
				return true;
			});

		// Should return self for fluent interface
		$this->assertSame($options, $result);

		// Test the complete pipeline
		$options->stage_option('complex_field', '  HELLO    WORLD  ');
		$commit_result = $options->commit_merge();
		$this->assertTrue($commit_result, 'Should succeed with complex pipeline');

		// Should be: lowercase -> trimmed -> whitespace normalized
		$this->assertEquals('hello world', $options->get_option('complex_field'));

		// Verify all notices were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('complex_field', $messages);
		$this->assertContains('Converted to lowercase', $messages['complex_field']['notices']);
		$this->assertContains('Value was trimmed', $messages['complex_field']['notices']);
		$this->assertContains('Whitespace normalized', $messages['complex_field']['notices']);

		// Test validation failure
		$options->stage_option('complex_field', 'A'); // Too short after processing
		$commit_result = $options->commit_merge();
		$this->assertFalse($commit_result, 'Should fail validation for short value');

		// Verify validation warning was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('complex_field', $messages);
		$this->assertContains('Value too short', $messages['complex_field']['warnings']);
	}

	/**
	 * Test error handling for invalid field keys.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 */
	public function test_error_handling_for_invalid_field_keys(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with one field
		$options->register_schema(array(
			'valid_field' => array(
				'default'  => '',
				'validate' => array(function($value) {
					return true;
				})
			)
		));

		// Test with non-existent field key
		$this->expectException(\InvalidArgumentException::class);
		$options->add_component_validator('nonexistent_field', function($value) {
			return true;
		});
	}

	/**
	 * Test error handling for invalid callables.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 */
	public function test_error_handling_for_invalid_callables(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with one field
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(function($value) {
					return true;
				})
			)
		));

		// Test with non-callable
		$this->expectException(\TypeError::class);
		$options->add_component_validator('test_field', 'not_a_callable');
	}

	/**
	 * Test validator execution order across component and schema buckets.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 */
	public function test_validator_execution_order(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		$execution_order = array();

		// Register initial schema with one validator
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) use (&$execution_order) {
						$execution_order[] = 'original';
						if ($value === 'fail_original') {
							$emitWarning('Original validator failed');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Component validator
		$options->add_component_validator('test_field', function($value, callable $emitWarning) use (&$execution_order) {
			$execution_order[] = 'prepended';
			if ($value === 'fail_prepended') {
				$emitWarning('Prepended validator failed');
				return false;
			}
			return true;
		});

		// Schema validator
		$options->add_schema_validator('test_field', function($value, callable $emitWarning) use (&$execution_order) {
			$execution_order[] = 'appended';
			if ($value === 'fail_appended') {
				$emitWarning('Appended validator failed');
				return false;
			}
			return true;
		});

		// Test successful validation - all should run
		$execution_order = array(); // Reset
		$options->stage_option('test_field', 'success');
		$options->commit_merge();

		$this->assertEquals(array('prepended', 'original', 'appended'), $execution_order);

		// Test failure on prepended validator - should stop early
		$execution_order = array(); // Reset
		$options->stage_option('test_field', 'fail_prepended');
		$options->commit_merge();

		$this->assertEquals(array('prepended'), $execution_order);

		// Test failure on original validator - should stop before appended
		$execution_order = array(); // Reset
		$options->stage_option('test_field', 'fail_original');
		$options->commit_merge();

		$this->assertEquals(array('prepended', 'original'), $execution_order);
	}

	/**
	 * Test sanitizer execution order across component and schema buckets.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 */
	public function test_sanitizer_execution_order(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		$execution_order = array();

		// Register initial schema with one sanitizer
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) use (&$execution_order) {
						$execution_order[] = 'original';
						// Make it idempotent by checking if already processed
						if (strpos($value, '_original') === false) {
							return $value . '_original';
						}
						return $value;
					}
				),
				'validate' => array(
					function($value) {
						return true; // Always pass validation
					}
				)
			)
		));

		// Component sanitizer
		$options->add_component_sanitizer('test_field', function($value, callable $emitNotice) use (&$execution_order) {
			$execution_order[] = 'prepended';
			// Make it idempotent by checking if already processed
			if (strpos($value, '_prepended') === false) {
				return $value . '_prepended';
			}
			return $value;
		});

		// Schema sanitizer
		$options->add_schema_sanitizer('test_field', function($value, callable $emitNotice) use (&$execution_order) {
			$execution_order[] = 'appended';
			// Make it idempotent by checking if already processed
			if (strpos($value, '_appended') === false) {
				return $value . '_appended';
			}
			return $value;
		});

		// Test sanitizer execution order
		$options->stage_option('test_field', 'test');
		$options->commit_merge();

		// Sanitizers run multiple times for idempotency checking
		// Just verify that all sanitizers ran and the final value is correct
		$this->assertContains('prepended', $execution_order);
		$this->assertContains('original', $execution_order);
		$this->assertContains('appended', $execution_order);

		// Value should reflect the execution order
		$this->assertEquals('test_prepended_original_appended', $options->get_option('test_field'));
	}

	/**
	 * Test multiple component and schema helper calls.
	 *
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_validator
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_component_sanitizer
	 * @covers \Ran\PluginLib\Options\RegisterOptions::add_schema_sanitizer
	 */
	public function test_multiple_prepends_and_appends(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		// Register basic schema
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						// Make it idempotent by checking if already processed
						if (strpos($value, '_original') === false) {
							return $value . '_original';
						}
						return $value;
					}
				),
				'validate' => array(
					function($value, callable $emitWarning) {
						if (strpos($value, 'fail') !== false) {
							$emitWarning('Contains fail');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Add multiple prepends and appends
		$options
			->add_component_sanitizer('test_field', function($value, callable $emitNotice) {
				// Make it idempotent by checking if already processed
				if (strpos($value, '_prepend1') === false) {
					return $value . '_prepend1';
				}
				return $value;
			})
			->add_component_sanitizer('test_field', function($value, callable $emitNotice) {
				// Make it idempotent by checking if already processed
				if (strpos($value, '_prepend2') === false) {
					return $value . '_prepend2';
				}
				return $value;
			})
			->add_schema_sanitizer('test_field', function($value, callable $emitNotice) {
				// Make it idempotent by checking if already processed
				if (strpos($value, '_append1') === false) {
					return $value . '_append1';
				}
				return $value;
			})
			->add_schema_sanitizer('test_field', function($value, callable $emitNotice) {
				// Make it idempotent by checking if already processed
				if (strpos($value, '_append2') === false) {
					return $value . '_append2';
				}
				return $value;
			});

		// Test execution order
		$options->stage_option('test_field', 'test');
		$options->commit_merge();

		// Should execute: prepend2 -> prepend1 -> original -> append1 -> append2
		$expected = 'test_prepend2_prepend1_original_append1_append2';
		$this->assertEquals($expected, $options->get_option('test_field'));
	}

	public function test_sanitizer_and_validator_logging_includes_bucket_context(): void {
		$options = new RegisterOptions('test_logging', StorageContext::forSite(), true, $this->logger_mock);

		$options->register_schema(array(
			'log_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value) {
						return trim((string) $value);
					},
				),
				'validate' => array(
					function($value) {
						return $value !== '';
					},
				),
			),
		));

		$options->add_component_sanitizer('log_field', function($value) {
			return strtolower((string) $value);
		});
		$options->add_component_validator('log_field', function($value, callable $emitWarning) {
			if ($value === '') {
				$emitWarning('component validator requires value');
				return false;
			}
			return true;
		});
		$options->add_schema_validator('log_field', function($value) {
			return strlen((string) $value) < 20;
		});

		$beforeCount = count($this->logger_mock->get_logs());
		$options->stage_option('log_field', '  Example  ');
		$logs = array_slice($this->logger_mock->get_logs(), $beforeCount);

		$sanitizerBuckets = array();
		foreach ($logs as $idx => $record) {
			if ($record['message'] !== 'RegisterOptions: running sanitizer') {
				continue;
			}
			if (!isset($record['context']['bucket'])) {
				continue;
			}
			$bucket                      = $record['context']['bucket'];
			$sanitizerBuckets[$bucket][] = $idx;
		}

		self::assertArrayHasKey('component', $sanitizerBuckets);
		self::assertArrayHasKey('schema', $sanitizerBuckets);
		self::assertNotEmpty($sanitizerBuckets['component']);
		self::assertNotEmpty($sanitizerBuckets['schema']);
		self::assertLessThan(
			min($sanitizerBuckets['schema']),
			min($sanitizerBuckets['component'])
		);

		$validatorBuckets = array();
		foreach ($logs as $idx => $record) {
			if ($record['message'] !== 'RegisterOptions: running validator') {
				continue;
			}
			if (!isset($record['context']['bucket'])) {
				continue;
			}
			$bucket                      = $record['context']['bucket'];
			$validatorBuckets[$bucket][] = $idx;
		}

		self::assertArrayHasKey('component', $validatorBuckets);
		self::assertArrayHasKey('schema', $validatorBuckets);
		self::assertNotEmpty($validatorBuckets['component']);
		self::assertNotEmpty($validatorBuckets['schema']);
		self::assertLessThan(
			min($validatorBuckets['schema']),
			min($validatorBuckets['component'])
		);
	}
}
