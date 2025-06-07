<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAbstract;
use WP_Mock;
use Mockery;
use Mockery\MockInterface;

/**
 * Concrete implementation of EnqueueAbstract for testing.
 */
class ConcreteEnqueueForScriptTesting extends EnqueueAbstract {
	public function load(): void {
		// Concrete implementation for testing purposes.
	}
}

/**
 * Class EnqueueAbstractScriptsTest
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract
 */
class EnqueueAbstractScriptsTest extends \RanTestCase {
	/** @var ConfigInterface&MockInterface */
	protected MockInterface $config_instance_mock;
	/**
	 * @var \Ran\PluginLib\Util\Logger&\Mockery\LegacyMockInterface
	 * @method \Mockery\Expectation shouldReceive(string $methodName) // Simplified
	 */
	protected MockInterface $logger_mock;
	/**
	 * @var ConcreteEnqueueForScriptTesting&\Mockery\LegacyMockInterface
	 * @method \Mockery\Expectation shouldReceive(string $methodName) // Simplified
	 */
	protected $instance; // Native type hint removed

	protected $capturedCallback; // For storing callback in tests

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->config_instance_mock = Mockery::mock(ConfigInterface::class);
		$this->logger_mock          = Mockery::mock(\Ran\PluginLib\Util\Logger::class);
		// Default behavior for logger: is_active() returns true.
		$expectation_is_active              = $this->logger_mock->shouldReceive('is_active')->byDefault();
		$expectation_after_is_active_return = $expectation_is_active->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();

		// Create a partial mock using Mockery, passing constructor arguments
		// @intelephense-ignore-next-line P1006
		$this->instance = Mockery::mock(
			ConcreteEnqueueForScriptTesting::class,
			array($this->config_instance_mock) // Ensure constructor arguments are passed
		)->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();

		// Set up the expectation for get_logger using Mockery syntax
		$this->instance->shouldReceive('get_logger')
		    ->zeroOrMoreTimes() // Allow it to be called any number of times, or as needed
		    ->andReturn($this->logger_mock);

		// Add a default mock for wp_script_is that can be overridden
		WP_Mock::userFunction('wp_script_is')
		    ->with(Mockery::any(), Mockery::any())
		    ->andReturn(false) // Default to false
		    ->byDefault();     // Allow overriding
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test adding scripts.
	 */
	public function test_add_scripts():void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
        ->withAnyArgs()
        ->zeroOrMoreTimes();


		$scripts_to_add = array(
			array(
				'handle'    => 'my-direct-script',
				'src'       => 'path/to/my-direct-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.2.3',
				'in_footer' => true,
				'condition' => static fn() => true,
			),
		);

		$this->instance->add_scripts($scripts_to_add);

		// Check that scripts are stored correctly
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('my-direct-script', $scripts['general'][0]['handle']);
		$this->assertEquals('path/to/my-direct-script.js', $scripts['general'][0]['src']);
		$this->assertEquals(array('jquery'), $scripts['general'][0]['deps']);
		$this->assertEquals('1.2.3', $scripts['general'][0]['version']);
		$this->assertTrue($scripts['general'][0]['in_footer']);
		$this->assertTrue($scripts['general'][0]['condition']());
	}

	/**
	 * Test enqueuing scripts.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_scripts
	 */
	public function test_enqueue_scripts(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
        ->withAnyArgs()
        ->zeroOrMoreTimes();

		WP_Mock::userFunction('wp_enqueue_script')
	    ->once()
	    ->with('my-enqueued-script');

		WP_Mock::userFunction('wp_register_script')
	    ->once()
	    ->with('my-enqueued-script', 'path/to/my-enqueued-script.js', array('jquery'), '1.2.3', true);

		WP_Mock::userFunction('wp_json_encode')
	    ->with(Mockery::type('array'))
	    ->andReturn('{}');

		$scripts_to_enqueue = array(
			array(
				'handle'    => 'my-enqueued-script',
				'src'       => 'path/to/my-enqueued-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.2.3',
				'in_footer' => true,
				'condition' => static fn() => true,
			),
		);

		// First add the scripts to the instance
		$this->instance->add_scripts($scripts_to_enqueue);
		// Then call enqueue_scripts
		$this->instance->enqueue_scripts();

		// Scripts should still be in the internal array after enqueueing
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('my-enqueued-script', $scripts['general'][0]['handle']);
	}

	/**
	 * Test enqueuing scripts with a condition that fails, ensuring they are skipped.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 */
	public function test_enqueue_scripts_with_failing_condition(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// This function should not be called because condition will fail
		WP_Mock::userFunction('wp_register_script')
			->never();

		WP_Mock::userFunction('wp_enqueue_script')
			->never();

		WP_Mock::userFunction('wp_json_encode')
			->with(Mockery::type('array'))
			->andReturn('{}');

		$scripts_to_enqueue = array(
			array(
				'handle'    => 'conditional-script',
				'src'       => 'path/to/conditional-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.2.3',
				'in_footer' => true,
				'condition' => static fn() => false, // This condition will fail
			),
		);

		// First add the scripts to the instance
		$this->instance->add_scripts($scripts_to_enqueue);

		// Then call enqueue_scripts without parameters to use the instance's scripts
		$this->instance->enqueue_scripts();

		// With failing condition, the script should be skipped for enqueueing but still stored
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('conditional-script', $scripts['general'][0]['handle']);
	}

	/**
	 * Test deferring scripts to a specific WordPress hook.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 */
	public function test_defer_scripts_to_hook(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// Mock add_action to capture the callback - using zeroOrMoreTimes to avoid conflicts
		WP_Mock::userFunction('add_action')
			->zeroOrMoreTimes()
			->andReturnUsing(function($hook, $callback, $priority = 10) {
				if ($hook === 'admin_enqueue_scripts' && $priority === 10) {
					// Store the callback for later verification
					// @intelephense-ignore-next-line P1014
					$this->capturedCallback = $callback;
				}
				return true;
			});

		WP_Mock::userFunction('wp_json_encode')
			->with(Mockery::type('array'))
			->andReturn('{}');

		WP_Mock::userFunction('is_admin')
			->andReturn(false); // Not in admin, so did_action check will be skipped

		$scripts_to_enqueue = array(
			array(
				'handle'    => 'deferred-script',
				'src'       => 'path/to/deferred-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
				'hook'      => 'admin_enqueue_scripts', // Defer to this hook
			),
		);

		$this->instance->enqueue_scripts($scripts_to_enqueue);

		// Check that the script was added to the deferred scripts array
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('general', $scripts);
		$this->assertArrayHasKey('deferred', $scripts);
		$this->assertArrayHasKey('admin_enqueue_scripts', $scripts['deferred']);
		$this->assertCount(1, $scripts['deferred']['admin_enqueue_scripts']);
		$this->assertEquals('deferred-script', $scripts['deferred']['admin_enqueue_scripts'][0]['handle']);
	}

	/**
	 * Test handling of scripts when the hook has already fired in admin context.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_scripts_with_fired_hook(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// Mock add_action - should not be called because the hook already fired
		WP_Mock::userFunction('add_action')
			->never();

		WP_Mock::userFunction('wp_json_encode')
			->with(Mockery::type('array'))
			->andReturn('{}');

		WP_Mock::userFunction('is_admin')
			->andReturn(true); // In admin context

		WP_Mock::userFunction('did_action')
			->with('admin_enqueue_scripts')
			->andReturn(true); // Hook has already fired

		// We need to mock the enqueue_deferred_scripts method to verify it's called directly
		/** @var ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $instance */
		$instance = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_instance_mock))
			->onlyMethods(array('get_logger', 'enqueue_deferred_scripts'))
			->getMock();

		$instance->expects($this->any())
			->method('get_logger')
			->willReturn($this->logger_mock);

		// Expect enqueue_deferred_scripts to be called directly with the hook name
		$instance->expects($this->once())
			->method('enqueue_deferred_scripts')
			->with('admin_enqueue_scripts');

		$scripts_to_enqueue = array(
			array(
				'handle'    => 'hook-already-fired-script',
				'src'       => 'path/to/hook-already-fired-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
				'hook'      => 'admin_enqueue_scripts', // Hook that has already fired
			),
		);
		// @intelephense-ignore-next-line P1013
		$instance->enqueue_scripts($scripts_to_enqueue);
	}

	/**
	 * Test registering scripts with wp_data and attributes.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 */
	public function test_enqueue_scripts_with_wp_data_and_attributes(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with('script-with-data', 'path/to/script-with-data.js', array('jquery'), '1.0.0', true);

		WP_Mock::userFunction('wp_enqueue_script')
			->once()
			->with('script-with-data');

		WP_Mock::userFunction('wp_script_add_data')
			->once()
			->with('script-with-data', 'strategy', 'defer');

		// Mock add_filter for the script_loader_tag filter with zeroOrMoreTimes to avoid conflicts
		WP_Mock::userFunction('add_filter')
			->zeroOrMoreTimes()
			->andReturn(true);

		WP_Mock::userFunction('wp_json_encode')
			->with(Mockery::type('array'))
			->andReturn('{}');

		$scripts_to_enqueue = array(
			array(
				'handle'    => 'script-with-data',
				'src'       => 'path/to/script-with-data.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
				'wp_data'   => array(
					'strategy' => 'defer',
				),
				'attributes' => array(
					'data-test' => 'value',
					'async'     => true,
				),
			),
		);

		// First add the scripts to the instance
		$this->instance->add_scripts($scripts_to_enqueue);

		// Then call enqueue_scripts without parameters to use the instance's scripts
		$this->instance->enqueue_scripts();

		// Verify the script is stored in the internal array after being processed
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('script-with-data', $scripts['general'][0]['handle']);
	}

	/**
	 * Test register_scripts method with valid scripts.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 */
	public function test_register_scripts(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		WP_Mock::userFunction('wp_register_script')
			->zeroOrMoreTimes()
			->andReturn(true);

		WP_Mock::userFunction('wp_script_add_data')
			->once()
			->with('script-with-data', 'strategy', 'defer');

		WP_Mock::userFunction('add_filter')
			->zeroOrMoreTimes()
			->andReturn(true);

		$scripts_to_register = array(
			array(
				'handle'    => 'basic-script',
				'src'       => 'path/to/basic-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
			),
			array(
				'handle'    => 'script-with-data',
				'src'       => 'path/to/script-with-data.js',
				'deps'      => array(),
				'version'   => '2.0.0',
				'in_footer' => false,
				'wp_data'   => array(
					'strategy' => 'defer',
				),
				'attributes' => array(
					'data-test' => 'value',
				),
			),
		);

		// First add the scripts to the instance
		$this->instance->add_scripts($scripts_to_register);

		// Then call register_scripts without parameters to use the instance's scripts
		$result = $this->instance->register_scripts();

		// Verify method chaining works
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test register_scripts with empty array and with scripts from instance property.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 */
	public function test_register_scripts_from_instance_property(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Add scripts to the instance property first
		$scripts_to_add = array(
			array(
				'handle'    => 'from-property-script',
				'src'       => 'path/to/from-property-script.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
			),
		);
		$this->instance->add_scripts($scripts_to_add);

		WP_Mock::userFunction('wp_register_script')
			->zeroOrMoreTimes()
			->andReturn(true);

		// Call register_scripts with empty array, which should use the instance property
		$result = $this->instance->register_scripts();

		// Verify method chaining works
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test register_scripts with a script with failing condition.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 */
	public function test_register_scripts_with_failing_condition(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Mock wp_register_script even for failing condition for consistency
		WP_Mock::userFunction('wp_register_script')
			->zeroOrMoreTimes()
			->andReturn(true);

		$scripts_to_register = array(
			array(
				'handle'    => 'conditional-script',
				'src'       => 'path/to/conditional-script.js',
				'deps'      => array(),
				'version'   => '1.0.0',
				'in_footer' => true,
				'condition' => static fn() => false, // This condition will fail
			),
		);

		// First add the scripts to the instance
		$this->instance->add_scripts($scripts_to_register);

		// Then call register_scripts without parameters to use the instance's scripts
		$result = $this->instance->register_scripts();

		// Verify method chaining works
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test adding inline scripts with various configurations.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_scripts
	 */
	public function test_add_inline_scripts(): void {
		// Test data - basic inline script
		$inline_scripts = array(
			array(
				'handle'    => 'test-script',
				'content'   => 'console.log("Hello World");',
				'position'  => 'after',
				'condition' => static fn() => true,
			),
			array(
				'handle'      => 'deferred-script',
				'content'     => 'console.log("Deferred inline");',
				'position'    => 'before',
				'parent_hook' => 'admin_enqueue_scripts',
			),
		);

		// Set up test script data to match against parent_handle check
		$scripts_to_add = array(
			array(
				'handle' => 'auto-deferred-script',
				'src'    => 'path/to/script.js',
				'hook'   => 'wp_footer',
			),
		);

		// Allow any debug logs
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// Add script to the instance so it can be found as a parent script
		$this->instance->add_scripts($scripts_to_add);

		// Add inline script that should inherit parent_hook from script with same handle
		$inline_script_with_auto_hook = array(
			array(
				'handle'   => 'auto-deferred-script',
				'content'  => 'console.log("Should inherit parent hook");',
				'position' => 'after',
			),
		);

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Add the first set of inline scripts
		$result = $this->instance->add_inline_scripts($inline_scripts);

		// Verify method chaining works
		$this->assertSame($this->instance, $result);

		// Add the auto-hook script
		$this->instance->add_inline_scripts($inline_script_with_auto_hook);

		// Verify all inline scripts are stored correctly
		// $inline_scripts_array = $this->get_protected_property_value($this->instance, 'inline_scripts');

		$scripts_array = $this->instance->get_scripts();

		$inline_scripts_array = $scripts_array['inline'];

		// Should have 3 inline scripts total
		$this->assertCount(3, $inline_scripts_array);

		// Check handles of stored inline scripts
		$handles = array_column($inline_scripts_array, 'handle');
		$this->assertContains('test-script', $handles);
		$this->assertContains('deferred-script', $handles);
		$this->assertContains('auto-deferred-script', $handles);

		// Verify the auto-hook script has the correct parent_hook
		$auto_hook_script = array_filter($inline_scripts_array, function($script) {
			return $script['handle'] === 'auto-deferred-script';
		});
		$auto_hook_script = reset($auto_hook_script);
		$this->assertEquals('wp_footer', $auto_hook_script['parent_hook']);
	}

	/**
	 * Test enqueueing inline scripts.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_scripts
	 */
	public function test_enqueue_inline_scripts(): void {
		// Create test inline scripts
		$inline_scripts = array(
			array(
				'handle'   => 'registered-script',
				'content'  => 'console.log("Registered script");',
				'position' => 'after',
			),
			array(
				'handle'   => 'unregistered-script',
				'content'  => 'console.log("Should be skipped");',
				'position' => 'before',
			),
			array(
				'handle'    => 'conditional-script',
				'content'   => 'console.log("Conditional");',
				'position'  => 'after',
				'condition' => static fn() => false, // This condition will fail
			),
			array(
				'handle'      => 'deferred-script',
				'content'     => 'console.log("Deferred");',
				'parent_hook' => 'admin_footer',
			),
			array(
				// Missing handle, should be skipped
				'content' => 'console.log("No handle");',
			),
			array(
				'handle' => 'empty-content',
				// Missing content, should be skipped
			),
		);

		// Set up WordPress function mocks

		// wp_script_is - simulate a registered script
		WP_Mock::userFunction('wp_script_is')
			->with('registered-script', 'registered')
			->andReturn(true);

		// wp_script_is - simulate a registered script
		WP_Mock::userFunction('wp_script_is')
			->with('registered-script', 'enqueued')
			->andReturn(false);

		// wp_script_is - simulate unregistered script
		WP_Mock::userFunction('wp_script_is')
			->with('unregistered-script', 'registered')
			->andReturn(false);

		WP_Mock::userFunction('wp_script_is')
			->with('unregistered-script', 'enqueued')
			->andReturn(false);

		// wp_script_is - we shouldn't even check conditional script as condition fails
		WP_Mock::userFunction('wp_script_is')
			->with('conditional-script', 'registered')
			->never();

		// wp_script_is - we shouldn't check deferred script in this method
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script', 'registered')
			->never();

		// wp_add_inline_script - should be called for registered-script only
		WP_Mock::userFunction('wp_add_inline_script')
			->with('registered-script', 'console.log("Registered script");') // Expecting 2 arguments
			->once();

		// Should never be called for other scripts
		WP_Mock::userFunction('wp_add_inline_script')
			->with('unregistered-script', Mockery::any(), Mockery::any())
			->never();

		WP_Mock::userFunction('wp_add_inline_script')
			->with('conditional-script', Mockery::any(), Mockery::any())
			->never();

		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script', Mockery::any(), Mockery::any())
			->never();

		WP_Mock::userFunction('esc_html')
			->zeroOrMoreTimes()
			->andReturnUsing(function($value) {
				return $value;
			});

		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->withAnyArgs()
			->andReturn(true);

		$this->logger_mock->shouldReceive('error')
			->withAnyArgs()
			->zeroOrMoreTimes();


		// Add scripts to the instance
		$this->instance->add_inline_scripts($inline_scripts);

		// Call the method
		$result = $this->instance->enqueue_inline_scripts();

		// Verify method chaining works
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test adding empty inline scripts array.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_scripts
	 */
	public function test_add_inline_scripts_empty_array(): void {
		// Allow any debug logs
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Add an empty array of inline scripts
		// The inline_scripts property is already an empty array by default.
		$result = $this->instance->add_inline_scripts(array());

		// Verify method chaining works
		$this->assertSame($this->instance, $result);

		// Verify no scripts were added using the public getter
		$all_scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('inline', $all_scripts, "The 'inline' key should exist in the array returned by get_scripts().");
		$this->assertEmpty($all_scripts['inline'], "The 'inline' scripts array should be empty after adding an empty array.");
	}

	/**
	 * Test adding inline scripts with invalid data.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_scripts
	 */
	public function test_add_inline_scripts_invalid_data(): void {
		// Allow any debug logs
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Test data with missing required fields
		$invalid_inline_scripts_data = array(
			array(
				// Missing handle - should be processed but will be skipped later when enqueueing
				'content'  => 'console.log("Missing handle");',
				'position' => 'after',
			),
			array(
				'handle' => 'missing-content',
				// Missing content - should be processed but will be skipped later
				'position' => 'before',
			),
			array(
				// Invalid structure, neither handle nor content
				'invalid' => 'field',
			),
		);

		// Get the initial count of inline scripts using the public getter
		$initial_scripts_array = $this->instance->get_scripts();
		$this->assertArrayHasKey('inline', $initial_scripts_array, "Pre-condition: 'inline' key should exist.");
		$initial_count = count($initial_scripts_array['inline']);

		// Add the invalid inline scripts
		$result = $this->instance->add_inline_scripts($invalid_inline_scripts_data);

		// Verify method chaining works
		$this->assertSame($this->instance, $result);

		// Verify all scripts were added despite being invalid
		// (validation happens at enqueue time, not add time)
		$final_scripts_array = $this->instance->get_scripts();
		$this->assertArrayHasKey('inline', $final_scripts_array, "Post-condition: 'inline' key should exist.");
		$this->assertCount(
			$initial_count + count($invalid_inline_scripts_data),
			$final_scripts_array['inline'],
			'The count of inline scripts should increase by the number of items added.'
		);
	}

	/**
	 * Test enqueueing inline scripts when logger is not active.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_scripts
	 */
	public function test_enqueue_inline_scripts_no_logger(): void {
		// Create test inline script
		$inline_scripts = array(
			array(
				'handle'   => 'registered-script',
				'content'  => 'console.log("Registered script");',
				'position' => 'after',
			),
		);

		// Set up WordPress function mocks
		WP_Mock::userFunction('wp_script_is')
			->with('registered-script', 'registered')
			->andReturn(true);

		WP_Mock::userFunction('wp_script_is')
			->with('registered-script', 'enqueued')
			->andReturn(false);

		WP_Mock::userFunction('wp_add_inline_script')
			->with('registered-script', 'console.log("Registered script");')
			->once();

		// Set logger inactive
		$this->logger_mock->shouldReceive('is_active')
			->andReturn(false);

		// Even though logger is inactive, we should allow debug calls
		// as the EnqueueAbstract may call debug() without checking is_active()
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// Add script to the instance
		$this->instance->add_inline_scripts($inline_scripts);

		// Call the method
		$result = $this->instance->enqueue_inline_scripts();

		// Verify method chaining works
		$this->assertSame($this->instance, $result);
	}

	/**
	 * Test enqueueing deferred scripts for a specific hook.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Test hook
		$hook = 'admin_footer';

		// Mock WordPress functions needed by enqueue_scripts()
		WP_Mock::userFunction('is_admin')
			->zeroOrMoreTimes() // It's called within the loop in enqueue_scripts
			->andReturn(true);

		WP_Mock::userFunction('did_action')
			->with($hook) // $hook is 'admin_footer'
			->zeroOrMoreTimes() // Also called within the loop
			->andReturn(false); // Ensures deferred scripts are not processed prematurely

		// Mock for wp_json_encode (only one instance now)
		WP_Mock::userFunction('wp_json_encode')
			->zeroOrMoreTimes()
			->andReturnUsing(function($data) {
				return json_encode($data);
			});

		// Test deferred scripts
		$deferred_scripts_data = array(
			array(
				'handle'    => 'deferred-script-1',
				'src'       => 'path/to/deferred-script-1.js',
				'deps'      => array('jquery'),
				'version'   => '1.0.0',
				'in_footer' => true,
				'hook'      => $hook,
			),
			array(
				'handle'    => 'deferred-script-2',
				'src'       => 'path/to/deferred-script-2.js',
				'deps'      => array(),
				'version'   => '2.0.0',
				'in_footer' => false,
				'hook'      => $hook,
				'condition' => static fn() => false, // This will cause script 2 to be skipped
			),
		);

		// Add deferred scripts to instance
		$this->instance->add_scripts($deferred_scripts_data);

		// Add an inline script that should be associated with a deferred script
		$inline_scripts_data = array(
			array(
				'handle'      => 'deferred-script-1',
				'content'     => 'console.log("Deferred inline script");',
				'position'    => 'after',
				'parent_hook' => $hook,
			),
			array(
				'handle'      => 'deferred-script-2', // This one should be skipped as parent script is skipped
				'content'     => 'console.log("Should be skipped");',
				'position'    => 'before',
				'parent_hook' => $hook,
			),
			array(
				'handle'      => 'deferred-script-1',
				'content'     => '',  // Empty content, should be skipped
				'parent_hook' => $hook,
			),
			array(
				'handle'      => 'deferred-script-1',
				'content'     => 'console.log("Conditional inline");',
				'position'    => 'after',
				'parent_hook' => $hook,
				'condition'   => static fn() => false, // Should be skipped due to condition
			),
		);

		$this->instance->add_inline_scripts($inline_scripts_data);

		// Enqueue the scripts first to populate deferred_scripts
		$this->instance->enqueue_scripts();

		// Ensure deferred_scripts is set properly
		$all_scripts = $this->instance->get_scripts();

		// 1. Assert that the 'deferred' key exists in the main scripts array
		$this->assertArrayHasKey('deferred', $all_scripts, "The 'deferred' key should exist in the array returned by get_scripts().");

		// 2. Assert that your specific hook ('admin_footer') exists as a key within the 'deferred' scripts
		$this->assertArrayHasKey($hook, $all_scripts['deferred'], "The hook '{$hook}' should exist as a key in the 'deferred' scripts array.");

		// 3. Assert that there are scripts for this hook
		$this->assertCount(
			count($deferred_scripts_data),
			$all_scripts['deferred'][$hook],
			'Initially, there should be ' . count($deferred_scripts_data) . " scripts registered for the hook '{$hook}' before deferred processing."
		);

		// Mock WordPress functions

		// process_single_script will call wp_register_script for script 1
		WP_Mock::userFunction('wp_register_script')
			->with('deferred-script-1', 'path/to/deferred-script-1.js', array('jquery'), '1.0.0', true)
			->once();

		// Script 2 should be skipped due to condition
		WP_Mock::userFunction('wp_register_script')
			->with('deferred-script-2', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
			->never();

		// wp_enqueue_script should be called for script 1
		WP_Mock::userFunction('wp_enqueue_script')
			->with('deferred-script-1')
			->once();

		// wp_add_inline_script should be called for the valid inline script
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', 'console.log("Deferred inline script");', 'after')
			->once();

		// But not for the ones that should be skipped
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', '', Mockery::any())
			->never();

		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', 'console.log("Conditional inline");', Mockery::any())
			->never();

		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-2', Mockery::any(), Mockery::any())
			->never();

		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		$this->logger_mock->shouldReceive('warning')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('error')
			->withAnyArgs()
			->zeroOrMoreTimes();

		// Call the method under test
		$this->instance->enqueue_deferred_scripts($hook);

		// Verify deferred_scripts for this hook is now empty
		// The EnqueueAbstract class unsets $this->deferred_scripts[$hook] after processing.
		$final_all_scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('deferred', $final_all_scripts, "The 'deferred' key should still exist after processing.");
		$this->assertArrayNotHasKey(
			$hook,
			$final_all_scripts['deferred'],
			"The hook '{$hook}' should no longer be a key in 'deferred' scripts after successful processing."
		);
	}

	/**
	 * Test enqueueing deferred scripts for a hook that has no scripts registered.
	 *
	 * This tests the scenario where the hook key is not present in the deferred_scripts array.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_for_unregistered_hook(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
		    ->withAnyArgs()
		    ->zeroOrMoreTimes(); // Allow any number of debug calls

		$this->logger_mock->shouldReceive('is_active')
		    ->andReturn(true); // Logger is active

		$test_hook = 'unregistered_hook';

		// Pre-condition: Ensure $this->instance->deferred_scripts does not contain $test_hook.
		// This is the default state if add_scripts was never called for this hook.
		$initial_scripts_state = $this->instance->get_scripts();
		$this->assertArrayNotHasKey(
			$test_hook,
			$initial_scripts_state['deferred'],
			"Pre-condition failed: The hook '{$test_hook}' should not be initially present in deferred scripts."
		);

	    // Call the method under test
		$this->instance->enqueue_deferred_scripts($test_hook); // Method is void, no return value to capture


		// Verify post-condition: The hook should still not be present in deferred_scripts.
		$final_scripts_state = $this->instance->get_scripts();
		$this->assertArrayHasKey(
			'deferred',
			$final_scripts_state,
			"The 'deferred' key should always exist in the scripts array structure."
		);
		$this->assertArrayNotHasKey(
			$test_hook,
			$final_scripts_state['deferred'],
			"The hook '{$test_hook}' should remain absent from 'deferred' scripts after processing an unregistered hook."
		);
	}


	/**
	 * Test enqueueing deferred scripts with an empty array.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_empty_array(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true);

		// Set deferred_scripts property to an empty array for the test hook
		$test_hook = 'empty_hook';
		// Reflect EnqueueAbstract directly for its private property 'deferred_scripts'
		// as $this->instance is a mock and private properties are not reflected from the mock itself.
		$property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'deferred_scripts');
		$property->setAccessible(true);
		$property->setValue($this->instance, array($test_hook => array()));

		// Call the method with an empty deferred scripts array
		$result = $this->instance->enqueue_deferred_scripts($test_hook);

		// Verify the hook was removed from deferred_scripts
		$deferred_scripts = $this->get_protected_property_value($this->instance, 'deferred_scripts');
		$this->assertArrayNotHasKey($test_hook, $deferred_scripts);
	}

	/**
	 * Test enqueueing deferred scripts when no hook is provided.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_nonexistent_hook(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true)
			->zeroOrMoreTimes();

		// Initialize deferred_scripts property to ensure it exists
		$property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'deferred_scripts');
		$property->setAccessible(true);
		$property->setValue($this->instance, array());

		// Call the method with a hook that doesn't exist in deferred_scripts
		$result = $this->instance->enqueue_deferred_scripts('nonexistent_hook');

		// Verify the hook was removed from deferred_scripts
		$deferred_scripts = $this->get_protected_property_value($this->instance, 'deferred_scripts');
		$this->assertArrayNotHasKey('nonexistent_hook', $deferred_scripts);
	}

	/**
	 * Test enqueueing deferred scripts with already enqueued parent script.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_already_enqueued(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true)
			->zeroOrMoreTimes();

		// Mock WordPress functions
		WP_Mock::userFunction('wp_json_encode')
			->zeroOrMoreTimes()
			->andReturnUsing(function($data) {
				return json_encode($data);
			});

		// Test hook
		$hook = 'admin_enqueue_scripts';

		// Test script that is already enqueued
		$deferred_scripts = array(
			array(
				'handle'    => 'already-enqueued-script',
				'src'       => 'path/to/already-enqueued-script.js',
				'deps'      => array(),
				'version'   => '1.0.0',
				'in_footer' => true,
				'hook'      => $hook,
			),
		);

		// Explicitly mock wp_script_is for 'registered' status to return false
		WP_Mock::userFunction('wp_script_is')
			->with('already-enqueued-script', 'registered')
			->andReturn(false) // Ensure this path returns false
			->once();         // Expect this to be called

		// Mock wp_script_is to return true (already enqueued) and expect it to be called once
		WP_Mock::userFunction('wp_script_is')
			->with('already-enqueued-script', 'enqueued')
			->andReturn(true)
			->once(); // Ensure this specific mock is hit and returns true

		// wp_register_script should never be called since script is already enqueued
		WP_Mock::userFunction('wp_register_script')
			->with('already-enqueued-script', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
			->never();

		// wp_enqueue_script should never be called
		WP_Mock::userFunction('wp_enqueue_script')
			->with('already-enqueued-script')
			->never();

		// wp_add_inline_script should never be called if no inline_code is provided
		WP_Mock::userFunction('wp_add_inline_script')
			->with('already-enqueued-script', Mockery::any(), Mockery::any())
			->never();

		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true)
			->zeroOrMoreTimes();


		// Initialize deferred_scripts property directly for this test
		$property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'deferred_scripts');
		$property->setAccessible(true);
		$property->setValue($this->instance, array($hook => $deferred_scripts));
		// Call the method under test
		$this->instance->enqueue_deferred_scripts($hook);

		// Verify deferred_scripts for this hook is now empty
		$updated_deferred_scripts = $this->get_protected_property_value($this->instance, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook, $updated_deferred_scripts);
	}

	/**
	 * Test enqueueing deferred scripts with an already enqueued parent script AND an associated inline script.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_scripts
	 */
	public function test_enqueue_deferred_scripts_already_enqueued_with_inline_script(): void {
		// Set up logger mocks
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		$this->logger_mock->shouldReceive('is_active')
			->andReturn(true)
			->zeroOrMoreTimes();

		// Mock WordPress functions
		WP_Mock::userFunction('wp_json_encode')
			->zeroOrMoreTimes()
			->andReturnUsing(function($data) {
				return json_encode($data);
			});

		// Test hook
		$hook           = 'test-hook-for-inline';
		$script_handle  = 'already-enqueued-with-inline';
		$inline_content = 'console.log("Inline script for already enqueued parent!");';

		// Test script that is already enqueued
		$deferred_script_definition = array(
			array(
				'handle'    => $script_handle,
				'src'       => 'path/to/already-enqueued-with-inline.js',
				'deps'      => array(),
				'version'   => '1.0.0',
				'in_footer' => true,
				'hook'      => $hook, // This 'hook' in script definition is for add_scripts, not directly used by enqueue_deferred_scripts's $hook param
			),
		);

		// Add the inline script to be processed
		$this->instance->add_inline_scripts(array(
			array(
				'handle'      => $script_handle,
				'content'     => $inline_content,
				'position'    => 'after',
				'parent_hook' => $hook // This ensures it's associated with the correct hook processing
			)
		));

		// Explicitly mock wp_script_is for 'registered' status to return false
		WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'registered')
			->andReturn(false)
			->once();

		// Mock wp_script_is to return true (already enqueued)
		WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'enqueued')
			->andReturn(true)
			->once();

		// wp_register_script should never be called
		WP_Mock::userFunction('wp_register_script')
			->with($script_handle, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
			->never();

		// wp_enqueue_script should never be called
		WP_Mock::userFunction('wp_enqueue_script')
			->with($script_handle)
			->never();

		// wp_add_inline_script SHOULD be called for the inline script
		WP_Mock::userFunction('wp_add_inline_script')
			->with($script_handle, $inline_content, 'after')
			->once();

		// Initialize deferred_scripts property directly for this test
		$property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'deferred_scripts');
		$property->setAccessible(true);
		// The script definition for the main script (even if already enqueued) must be in deferred_scripts for the hook
		$property->setValue($this->instance, array($hook => $deferred_script_definition));

		// Call the method under test
		$this->instance->enqueue_deferred_scripts($hook);

		// Verify deferred_scripts for this hook is now empty
		$updated_deferred_scripts = $this->get_protected_property_value($this->instance, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook, $updated_deferred_scripts);

		// Verify the inline script was removed from the internal collection after processing
		$remaining_inline_scripts = $this->get_protected_property_value($this->instance, 'inline_scripts');
		$found_remaining          = false;
		foreach ($remaining_inline_scripts as $remaining_inline) {
			if (($remaining_inline['handle'] ?? null) === $script_handle && ($remaining_inline['parent_hook'] ?? null) === $hook) {
				$found_remaining = true;
				break;
			}
		}
		$this->assertFalse($found_remaining, "The processed inline script should have been removed from the instance's inline_scripts array.");
	}

	/**
	 * Test register_scripts when scripts are passed directly, and with different logger states.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::process_single_script
	 */
	public function test_register_scripts_passed_directly_with_logger_states(): void {
		$direct_scripts = array(
			array(
				'handle' => 'direct-script-1',
				'src'    => 'path/to/direct-script-1.js',
			),
		);

		// Mock wp_register_script as it will be called by process_single_script
		// This needs to be defined before the first call to register_scripts
		WP_Mock::userFunction('wp_register_script')
			->with('direct-script-1', 'path/to/direct-script-1.js', array(), false, false)
			->once() // Expect it to be called for Scenario 1
			->andReturn(true);

		// Scenario 1: Logger is active
		$this->logger_mock->shouldReceive('is_active')->times(3)->andReturn(true);
		// 1. From register_scripts (initial log)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.')
			->once();

		// Logs from add_scripts:
		// 2. LOG A from add_scripts
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).')
			->once();
		// 3. LOG B from add_scripts (for the one script)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Adding script. Key: 0, Handle: direct-script-1, Src: path/to/direct-script-1.js')
			->once();
		// 4. LOG C from add_scripts
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Exiting. New total script count: 1')
			->once();
		// 5. LOG D from add_scripts
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - All current script handles after add: direct-script-1')
			->once();

		// 6. From register_scripts (final log before loop)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::register_scripts - Registering 1 script(s).')
			->once();

		// Call the method under test for Scenario 1
		$result = $this->instance->register_scripts($direct_scripts);
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// --- Reset for Scenario 2 ---
		// We need to reset the call counts on mocks and re-establish their states.
		// A robust way is to tear down and set up again.
		Mockery::close();       // Close current Mockery mocks
		WP_Mock::tearDown();    // Tear down WP_Mock state
		parent::tearDown();     // Call parent tearDown
		parent::setUp();        // Call parent setUp to reset its state

		// Re-initialize mocks specific to this test class as done in the main setUp()
		// This ensures $this->logger_mock and $this->instance are fresh.
		$this->config_instance_mock = Mockery::mock(ConfigInterface::class);
		$this->logger_mock          = Mockery::mock(\Ran\PluginLib\Util\Logger::class);
		// @phpstan-ignore-next-line P1006
		$this->instance = Mockery::mock(
			ConcreteEnqueueForScriptTesting::class,
			array($this->config_instance_mock)
		)->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();
		$this->instance->shouldReceive('get_logger')
		    ->zeroOrMoreTimes()
		    ->andReturn($this->logger_mock);
		WP_Mock::userFunction('wp_script_is') // Default wp_script_is mock
		    ->with(Mockery::any(), Mockery::any())
		    ->andReturn(false)
		    ->byDefault();

		// Re-establish wp_register_script mock for Scenario 2
		WP_Mock::userFunction('wp_register_script')
			->with('direct-script-1', 'path/to/direct-script-1.js', array(), false, false)
			->once() // Expect it to be called for Scenario 2
			->andReturn(true);

		// Scenario 2: Logger is inactive
		$this->logger_mock->shouldReceive('is_active')->times(3)->andReturn(false); // Logger inactive
		$this->logger_mock->shouldReceive('debug') // For "Scripts directly passed"
			->with('EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.')
			->once();
		// The following debug calls should NOT happen if the logger is inactive (because they are guarded by is_active() checks in SUT)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).')
			->never();
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Adding script. Key: 0, Handle: direct-script-1, Src: path/to/direct-script-1.js')
			->never();
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - Exiting. New total script count: 1')
			->never();
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_scripts - All current script handles after add: direct-script-1')
			->never();
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::register_scripts - Registering 1 script(s).')
			->never();

		// Call the method under test for Scenario 2
		$result2 = $this->instance->register_scripts($direct_scripts);
		$this->assertSame($this->instance, $result2, 'Method should be chainable in second scenario.');
	}
}
