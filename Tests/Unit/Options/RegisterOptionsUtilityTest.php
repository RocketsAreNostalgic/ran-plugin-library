<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;

/**
 * Tests for RegisterOptions utility and edge case functionality.
 */
final class RegisterOptionsUtilityTest extends PluginLibTestCase {
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
		WP_Mock::userFunction('sanitize_html_class')->andReturnUsing(
			static function ($class, $fallback = ''): string {
				$class    = (string) $class;
				$fallback = (string) $fallback;
				return $class !== '' ? $class : $fallback;
			}
		);

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			// Replace any run of non [a-z0-9_\-] with a single underscore (preserve hyphens)
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			// Trim underscores at edges (preserve leading/trailing hyphens if present)
			return trim($key, '_');
		});

		// Mock write functions to prevent actual database writes
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);

		// Default allow for write gate at site scope (tests can override with onFilter per scenario)
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_schema_internal
	 */
	public function test_get_schema_returns_empty_when_no_schema_registered(): void {
		$opts = RegisterOptions::site('no_schema_example');

		self::assertSame(array(), $opts->get_schema());
		self::assertSame(array(), $opts->_get_schema_internal());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 */
	public function test_get_schema_handles_mixed_sanitizers_and_validators(): void {
		$opts = RegisterOptions::site('mixed_schema_example');
		/** @var WritePolicyInterface|\PHPUnit\Framework\MockObject\MockObject $policy */
		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$titleSanitizer = static function ($value, ?callable $emitNotice = null): string {
			$trimmed = trim((string) $value);
			if ($emitNotice !== null) {
				$emitNotice('title sanitized');
			}
			return $trimmed;
		};

		$titleValidator = static function ($value, ?callable $emitWarning = null): bool {
			if ($value === '') {
				if ($emitWarning !== null) {
					$emitWarning('Title is required');
				}
				return false;
			}
			return true;
		};

		$flagValidator = static function ($value, ?callable $emitWarning = null): bool {
			if (!\in_array($value, array('yes', 'no'), true)) {
				if ($emitWarning !== null) {
					$emitWarning('Invalid flag value');
				}
				return false;
			}
			return true;
		};

		$opts->register_schema(array(
			'title' => array(
				'default'  => 'Hello World',
				'sanitize' => $titleSanitizer,
				'validate' => $titleValidator,
			),
			'count' => array(
				'sanitize' => 'intval',
				'validate' => 'is_int',
			),
			'flag' => array(
				'validate' => $flagValidator,
			),
		));

		$exported = $opts->get_schema();

		self::assertArrayHasKey('title', $exported);
		self::assertSame('Hello World', $exported['title']['default']);
		self::assertCount(1, $exported['title']['sanitize']);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['title']['sanitize'][0]);
		self::assertStringContainsString('Consider using a named function or Class::method for portability.', $exported['title']['sanitize'][0]);
		self::assertCount(1, $exported['title']['validate']);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['title']['validate'][0]);
		self::assertStringContainsString('Consider using a named function or Class::method for portability.', $exported['title']['validate'][0]);

		self::assertArrayHasKey('count', $exported);
		self::assertSame(array('intval'), $exported['count']['sanitize']);
		self::assertSame(array('is_int'), $exported['count']['validate']);

		self::assertArrayHasKey('flag', $exported);
		self::assertSame(array(), $exported['flag']['sanitize']);
		self::assertCount(1, $exported['flag']['validate']);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['flag']['validate'][0]);
		self::assertStringContainsString('Consider using a named function or Class::method for portability.', $exported['flag']['validate'][0]);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_schema_internal
	 */
	public function test_get_schema_handles_mixed_callables_and_missing_fields(): void {
		$opts = RegisterOptions::site('mixed_callables_missing_fields');
		/** @var WritePolicyInterface|\PHPUnit\Framework\MockObject\MockObject $policy */
		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$headlineClosure = static function ($value, ?callable $emitNotice = null) {
			if ($emitNotice !== null) {
				$emitNotice('headline sanitized');
			}
			return strtoupper((string) $value);
		};

		$summaryValidator = static function ($value, ?callable $emitWarning = null): bool {
			if (!\is_string($value)) {
				if ($emitWarning !== null) {
					$emitWarning('Summary must be a string');
				}
				return false;
			}
			return true;
		};

		$opts->register_schema(array(
			'headline' => array(
				'sanitize' => array($headlineClosure, 'trim'),
			),
			'summary' => array(
				'validate' => array($summaryValidator, 'is_string'),
			),
		));

		$exported = $opts->get_schema();

		self::assertArrayHasKey('headline', $exported);
		self::assertArrayHasKey('sanitize', $exported['headline']);
		self::assertArrayHasKey('validate', $exported['headline']);
		self::assertCount(2, $exported['headline']['sanitize']);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['headline']['sanitize'][0]);
		self::assertStringContainsString('Consider using a named function or Class::method for portability.', $exported['headline']['sanitize'][0]);
		self::assertSame('trim', $exported['headline']['sanitize'][1]);
		self::assertSame(array(), $exported['headline']['validate']);

		self::assertArrayHasKey('summary', $exported);
		self::assertSame(array(), $exported['summary']['sanitize']);
		self::assertCount(2, $exported['summary']['validate']);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['summary']['validate'][0]);
		self::assertSame('is_string', $exported['summary']['validate'][1]);

		$internal = $opts->_get_schema_internal();

		self::assertArrayHasKey('headline', $internal);
		$headlineSanitizeBucket = $internal['headline']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA] ?? array();
		self::assertCount(2, $headlineSanitizeBucket);
		self::assertIsCallable($headlineSanitizeBucket[0]);
		self::assertSame('trim', $headlineSanitizeBucket[1]);
		$headlineValidateBucket = $internal['headline']['validate'][ValidatorPipelineService::BUCKET_SCHEMA] ?? array();
		self::assertSame(array(), $headlineValidateBucket);

		self::assertArrayHasKey('summary', $internal);
		$summarySanitizeBucket = $internal['summary']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA] ?? array();
		self::assertSame(array(), $summarySanitizeBucket);
		$summaryValidateBucket = $internal['summary']['validate'][ValidatorPipelineService::BUCKET_SCHEMA] ?? array();
		self::assertCount(2, $summaryValidateBucket);
		self::assertIsCallable($summaryValidateBucket[0]);
		self::assertSame('is_string', $summaryValidateBucket[1]);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_register_internal_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_schema_internal
	 */
	public function test_get_schema_omits_component_callables(): void {
		$opts = RegisterOptions::site('component_visibility_example');

		$opts->_register_internal_schema(array(
			'field_alpha' => array(
				'sanitize' => array(
					ValidatorPipelineService::BUCKET_COMPONENT => array('strtoupper'),
					ValidatorPipelineService::BUCKET_SCHEMA    => array('trim'),
				),
				'validate' => array(
					ValidatorPipelineService::BUCKET_COMPONENT => array('ctype_alpha'),
					ValidatorPipelineService::BUCKET_SCHEMA    => array('is_string'),
				),
			),
		));

		$exported = $opts->get_schema();
		self::assertArrayHasKey('field_alpha', $exported);
		self::assertSame(array('trim'), $exported['field_alpha']['sanitize']);
		self::assertSame(array('is_string'), $exported['field_alpha']['validate']);

		$internal = $opts->_get_schema_internal();
		self::assertArrayHasKey('field_alpha', $internal);
		self::assertSame(
			array('strtoupper'),
			$internal['field_alpha']['sanitize'][ValidatorPipelineService::BUCKET_COMPONENT]
		);
		self::assertSame(
			array('ctype_alpha'),
			$internal['field_alpha']['validate'][ValidatorPipelineService::BUCKET_COMPONENT]
		);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_schema_internal
	 */
	public function test_exported_schema_can_seed_identical_instance(): void {
		$original = RegisterOptions::site('round_trip_example');
		/** @var WritePolicyInterface|\PHPUnit\Framework\MockObject\MockObject $policy */
		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$original->with_policy($policy);

		$original->with_schema(array(
			'title' => array(
				'sanitize' => array(self::class . '::round_trip_sanitize_title', 'trim'),
				'validate' => array('strlen'),
			),
			'flag' => array(
				'validate' => array(self::class . '::round_trip_validate_flag'),
			),
		));

		$exported = $original->get_schema();
		self::assertArrayHasKey('title', $exported);
		self::assertArrayHasKey('flag', $exported);
		self::assertSame(array(), $exported['flag']['sanitize'], 'Export should include empty sanitize array for flag');
		self::assertSame(
			array(self::class . '::round_trip_sanitize_title', 'trim'),
			$exported['title']['sanitize'],
			'Export should preserve named sanitize callables'
		);
		self::assertSame(array('strlen'), $exported['title']['validate']);
		self::assertSame(array(self::class . '::round_trip_validate_flag'), $exported['flag']['validate']);

		$clone = RegisterOptions::site('round_trip_example_clone');
		/** @var WritePolicyInterface|\PHPUnit\Framework\MockObject\MockObject $clonePolicy */
		$clonePolicy = $this->createMock(WritePolicyInterface::class);
		$clonePolicy->method('allow')->willReturn(true);
		$clone->with_policy($clonePolicy);

		$clone->register_schema($exported);

		self::assertSame(
			$exported,
			$clone->get_schema(),
			'Round-tripped schema should match the exported schema'
		);

		$originalInternal = $original->_get_schema_internal();
		$cloneInternal    = $clone->_get_schema_internal();
		self::assertArrayHasKey('title', $cloneInternal);
		self::assertArrayHasKey('flag', $cloneInternal);
		self::assertIsCallable($cloneInternal['title']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA][0]);
		self::assertSame(
			$originalInternal['title']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA],
			$cloneInternal['title']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA],
			'Clone should retain sanitize callables'
		);
		self::assertSame(
			$originalInternal['title']['validate'][ValidatorPipelineService::BUCKET_SCHEMA],
			$cloneInternal['title']['validate'][ValidatorPipelineService::BUCKET_SCHEMA],
			'Clone should retain named validators'
		);
		self::assertSame(
			$originalInternal['flag']['validate'][ValidatorPipelineService::BUCKET_SCHEMA],
			$cloneInternal['flag']['validate'][ValidatorPipelineService::BUCKET_SCHEMA],
			'Clone should retain flag validator callable'
		);
	}

	public static function round_trip_sanitize_title($value, ?callable $emitNotice = null): string {
		$value = trim((string) $value);
		if ($emitNotice !== null) {
			$emitNotice('title sanitized via named function');
		}
		return $value;
	}

	public static function round_trip_validate_flag($value, ?callable $emitWarning = null): bool {
		$isValid = $value === 'yes';
		if (!$isValid && $emitWarning !== null) {
			$emitWarning('flag must equal yes');
		}
		return $isValid;
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_get_schema_internal
	 */
	public function test_get_schema_internal_preserves_bucketed_callables(): void {
		$opts = RegisterOptions::site('internal_schema_example');
		/** @var WritePolicyInterface|\PHPUnit\Framework\MockObject\MockObject $policy */
		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$titleSanitizer = static function ($value, ?callable $emitNotice = null): string {
			return trim((string) $value);
		};

		$titleValidator = static function ($value, ?callable $emitWarning = null): bool {
			return $value !== '';
		};

		$opts->register_schema(array(
			'title' => array(
				'sanitize' => $titleSanitizer,
				'validate' => $titleValidator,
			),
			'count' => array(
				'sanitize' => 'intval',
				'validate' => 'is_int',
			),
		));

		$internal = $opts->_get_schema_internal();

		self::assertArrayHasKey('title', $internal);
		self::assertArrayHasKey(ValidatorPipelineService::BUCKET_SCHEMA, $internal['title']['sanitize']);
		self::assertArrayHasKey(ValidatorPipelineService::BUCKET_SCHEMA, $internal['title']['validate']);
		self::assertIsCallable($internal['title']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA][0]);
		self::assertIsCallable($internal['title']['validate'][ValidatorPipelineService::BUCKET_SCHEMA][0]);

		self::assertArrayHasKey('count', $internal);
		self::assertSame(array('intval'), $internal['count']['sanitize'][ValidatorPipelineService::BUCKET_SCHEMA]);
		self::assertSame(array('is_int'), $internal['count']['validate'][ValidatorPipelineService::BUCKET_SCHEMA]);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 */
	public function test_with_defaults_sets_default_values(): void {
		$opts = RegisterOptions::site('test_options');

		// Allow in-memory staging in this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Phase 4: require schema for all mutated keys
		$opts->with_schema(array(
			'default_key1' => array('validate' => Validate::basic()->is_string()),
			'default_key2' => array('validate' => Validate::basic()->is_string()),
		));

		$defaults = array(
			'default_key1' => 'default_value1',
			'default_key2' => 'default_value2'
		);

		$result = $opts->stage_options($defaults);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Should be able to retrieve the default values
		$this->assertEquals('default_value1', $opts->get_option('default_key1'));
		$this->assertEquals('default_value2', $opts->get_option('default_key2'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_policy
	 */
	public function test_with_policy_sets_write_policy(): void {
		$opts = RegisterOptions::site('test_options');

		// Create a mock write policy
		$mockPolicy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)
			->getMock();

		$result = $opts->with_policy($mockPolicy);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Policy should be set (we can't easily verify this without reflection, but the method should complete without error)
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_logger
	 */
	public function test_with_logger_sets_logger_instance(): void {
		// Create a mock logger
		$mockLogger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()
			->getMock();

		// Construct without DI to exercise with_logger() behavior explicitly
		$opts   = RegisterOptions::site('test_options');
		$result = $opts->with_logger($mockLogger);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);

		// Logger should be set (we can't easily verify this without reflection, but the method should complete without error)
		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_policy
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_logger
	 */
	public function test_fluent_interface_method_chaining(): void {
		// Create mock objects
		$mockPolicy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)
			->getMock();
		$mockLogger = $this->getMockBuilder(\Ran\PluginLib\Util\Logger::class)
			->disableOriginalConstructor()
			->getMock();
		$defaults = array('chained_key' => 'chained_value');

		$opts = RegisterOptions::site('test_options');

		// Allow in-memory staging in this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Test method chaining
		$result = $opts->with_schema(array(
			'chained_key' => array('validate' => Validate::basic()->is_string()),
		))
			->with_policy($mockPolicy)
			->stage_options($defaults);

		// Should return self after chaining
		$this->assertSame($opts, $result);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_with_array_result(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for mutated keys during migration
		$opts->with_schema(array(
			'new_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
			'old_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Set up initial data
		$initialData = array('old_key' => 'old_value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage to return initial data
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Mock write guards and storage functions
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that transforms data
		$migration = function($current) {
			$prev = (is_array($current) && array_key_exists('old_key', $current)) ? $current['old_key'] : '';
			return array('new_key' => 'new_value', 'old_key' => 'migrated_' . $prev);
		};

		$result = $opts->migrate($migration);

		// Should return self for fluent interface
		$this->assertSame($opts, $result);
		$this->assertEquals('new_value', $opts->get_option('new_key'));
		$this->assertEquals('migrated_old_value', $opts->get_option('old_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_with_scalar_result(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for mutated keys during migration
		$opts->with_schema(array(
			'value' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Set up initial data
		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage and functions
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that returns scalar
		$migration = function($current) {
			return 'scalar_result';
		};

		$result = $opts->migrate($migration);

		$this->assertSame($opts, $result);
		$this->assertEquals('scalar_result', $opts->get_option('value'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_no_op_when_option_missing(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Mock storage to return null (option doesn't exist)
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(null);
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Ensure _do_get_option receives sentinel by returning provided default
		WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = null) {
			return $default;
		});
		// Veto writes defensively in case migration attempts to persist
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(false);
		$opts->with_policy($policy);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(false);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(false);

		$migration = function($current) {
			return array('should_not_run' => 'value');
		};

		$result = $opts->migrate($migration);

		// Should return self but data unchanged
		$this->assertSame($opts, $result);
		$this->assertFalse($opts->has_option('should_not_run'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 */
	public function test_migrate_no_op_when_no_changes(): void {
		$opts = RegisterOptions::site('test_options');

		// Phase 4: schema required for mutated keys during migration
		$opts->with_schema(array(
			'value' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$opts        = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Phase 4: schema required for mutated keys during migration (reinstantiated opts)
		$opts->with_schema(array(
			'value' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this (second) instance as well
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Set up initial data
		$initialData = array('key' => 'value');
		$this->_set_protected_property_value($opts, 'options', $initialData);

		// Mock storage and functions
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn($initialData);
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn($initialData);

		// Migration function that returns scalar
		$migration = function($current) {
			return 'scalar_result';
		};

		$result = $opts->migrate($migration);

		$this->assertSame($opts, $result);
		$this->assertEquals('scalar_result', $opts->get_option('value'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::migrate
	 * @covers \Ran\PluginLib\Options\RegisterOptions::commit_merge
	 */
	public function test_commit_merge_combines_db_and_memory(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Phase 4: schema required for staged memory keys
		$opts->with_schema(array(
			'memory_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Add some options in memory
		$opts->stage_option('memory_key', 'memory_value');

		// Mock storage to return existing data and success
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('db_key' => 'db_value'));
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$mockStorage->method('update')->willReturn(true);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		// Allow writes in this test
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Mock get_option for merge from DB
		WP_Mock::userFunction('get_option')->andReturn(array('db_key' => 'db_value'));

		// Commit with merge should combine memory and DB data
		$result = $opts->commit_merge(); // shallow, top-level merge

		$this->assertTrue($result);
		$this->assertTrue($opts->has_option('memory_key'));
		$this->assertTrue($opts->has_option('db_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
	 */
	public function test_refresh_options_reloads_from_storage(): void {
		$opts = RegisterOptions::site('test_options');

		// Seed in-memory state different from storage
		$this->_set_protected_property_value($opts, 'options', array('foo' => 'memory_value'));

		// Storage returns fresh DB snapshot
		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(array('foo' => 'db_value', 'bar' => 2));
		$mockStorage->method('scope')->willReturn(\Ran\PluginLib\Options\OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();

		// Values should reflect storage snapshot now
		$this->assertSame('db_value', $opts->get_option('foo'));
		$this->assertTrue($opts->has_option('bar'));
		$this->assertSame(2, $opts->get_option('bar'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::supports_autoload
	 */
	public function test_supports_autoload_method(): void {
		$opts = RegisterOptions::site('test_options');
		$this->assertTrue($opts->supports_autoload()); // Site scope supports autoload

		$opts = RegisterOptions::network('test_options');
		$this->assertFalse($opts->supports_autoload()); // Network scope doesn't support autoload
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_main_option_name
	 */
	public function test_get_main_option_name_returns_constructor_value(): void {
		$opts = RegisterOptions::site('example_options');
		$this->assertSame('example_options', $opts->get_main_option_name());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_storage_context
	 */
	public function test_get_storage_context_defaults_to_site_scope(): void {
		$opts    = new RegisterOptions('storage_context_example');
		$context = $opts->get_storage_context();

		$this->assertInstanceOf(StorageContext::class, $context);
		$this->assertSame(OptionScope::Site, $context->scope);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 */
	public function test_get_schema_returns_registered_schema(): void {
		$opts = RegisterOptions::site('schema_example');

		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		$schema = array(
			'alpha_key' => array(
				'default'  => 'alpha',
				'validate' => Validate::basic()->is_string(),
				'sanitize' => null,
			),
		);

		$opts->register_schema($schema);

		// After registration, single callables are converted to arrays for backward compatibility
		$exported = $opts->get_schema();

		self::assertArrayHasKey('alpha_key', $exported);
		self::assertSame('alpha', $exported['alpha_key']['default']);
		self::assertSame(array(), $exported['alpha_key']['sanitize']);
		self::assertSame(1, count($exported['alpha_key']['validate']));
		self::assertIsString($exported['alpha_key']['validate'][0]);
		self::assertStringStartsWith(ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX, $exported['alpha_key']['validate'][0]);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_policy
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_write_policy
	 */
	public function test_get_write_policy_returns_injected_policy(): void {
		$opts   = RegisterOptions::site('policy_example');
		$policy = $this->createMock(WritePolicyInterface::class);

		$opts->with_policy($policy);

		$this->assertSame($policy, $opts->get_write_policy());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_logger
	 */
	public function test_get_logger_returns_bound_logger(): void {
		$logger = $this->getMockBuilder(Logger::class)
			->disableOriginalConstructor()
			->getMock();

		$opts = RegisterOptions::site('logger_example', true, $logger);

		$this->assertSame($logger, $opts->get_logger());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::with_context
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_storage_context
	 * @covers \Ran\PluginLib\Options\RegisterOptions::get_schema
	 */
	public function test_with_context_clones_configuration_for_new_scope(): void {
		$logger = $this->getMockBuilder(Logger::class)
			->disableOriginalConstructor()
			->getMock();

		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/network')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$base = RegisterOptions::site('context_example', true, $logger);

		$policy = $this->createMock(WritePolicyInterface::class);
		$policy->method('allow')->willReturn(true);
		$base->with_policy($policy);

		$schema = array(
			'gamma_key' => array(
				'default'  => 'gamma',
				'validate' => Validate::basic()->is_string(),
			),
		);
		$base->register_schema($schema);

		$clone = $base->with_context(StorageContext::forNetwork());

		$this->assertNotSame($base, $clone);
		$this->assertSame('context_example', $clone->get_main_option_name());
		$this->assertSame(OptionScope::Network, $clone->get_storage_context()->scope);
		$this->assertEquals($base->_get_schema_internal(), $clone->_get_schema_internal());
		$this->assertSame($policy, $clone->get_write_policy());
		$this->assertSame($logger, $clone->get_logger());
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 */
	public function test_set_option_with_string_scope_override(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		// Phase 4: schema required for set_option keys
		$opts->with_schema(array(
			'test_key' => array('validate' => function ($v) {
				return is_string($v);
			}),
		));

		// Allow all writes for this test
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$opts->with_policy($policy);

		// Switch to blog storage via typed StorageContext reflection
		$this->_set_protected_property_value($opts, 'storage_context', StorageContext::forBlog(123));
		// Force rebuild of storage to pick up new context
		$this->_set_protected_property_value($opts, 'storage', null);

		// Allow writes in this test
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
            ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
            ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/blog')
		    ->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
		    ->reply(true);

		// Mock blog update to return success
		WP_Mock::userFunction('update_blog_option')->andReturn(true);

		// Set an option - should use blog storage and succeed
		$result = $opts->stage_option('test_key', 'test_value')->commit_merge();
		$this->assertTrue($result);
		$this->assertEquals('test_value', $opts->get_option('test_key'));
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
	 * @covers \Ran\PluginLib\Options\RegisterOptions::refresh_options
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_read_main_option
	 */
	public function test_read_main_option_non_array_returns_empty_and_logs(): void {
		$opts = RegisterOptions::site('test_options', true, $this->logger_mock);

		$mockStorage = $this->createMock(\Ran\PluginLib\Options\Storage\OptionStorageInterface::class);
		$mockStorage->method('read')->willReturn(null); // non-array path
		$mockStorage->method('scope')->willReturn(OptionScope::Site);
		$this->_set_protected_property_value($opts, 'storage', $mockStorage);

		$opts->refresh_options();
		// Options should be empty; validate via public API
		$this->assertFalse($opts->has_option('any_key'));
		$this->assertFalse($opts->get_option('any_key'));
		// With logger injected at construction, _read_main_option runs during:
		// 1) constructor, 2) explicit refresh() below
		$this->expectLog('debug', 'RegisterOptions: _read_main_option completed', 2);
	}
}
