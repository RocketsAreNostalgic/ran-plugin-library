<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAbstract;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
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
class EnqueueAbstractScriptsTest extends PluginLibTestCase {
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
		parent::setUp(); // Calls PluginLibTestCase::setUp()

		$this->logger_mock = Mockery::mock(\Ran\PluginLib\Util\Logger::class);
		$this->logger_mock->shouldReceive('is_active')->byDefault()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->byDefault()->andReturn(true);
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('warning')->withAnyArgs()->andReturnNull()->byDefault();

		// $this->instance is used by existing tests. It will use $this->config_mock from PluginLibTestCase.
		// @phpstan-ignore-next-line P1006
		$this->instance = Mockery::mock(
			ConcreteEnqueueForScriptTesting::class,
			array($this->config_mock) // Use $this->config_mock from PluginLibTestCase
		)->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();

		$this->instance->shouldReceive('get_logger')
		    ->zeroOrMoreTimes()
		    ->andReturn($this->logger_mock);
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
	    ->with('my-enqueued-script', 'path/to/my-enqueued-script.js', array('jquery'), '1.2.3', true)->andReturn(true);

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
		$this->instance->enqueue_scripts($scripts_to_enqueue);

		// Scripts should still be in the internal array after enqueueing
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('my-enqueued-script', $scripts['general'][0]['handle']);
	}

	/**
	 * Test enqueuing scripts with a condition that fails, ensuring they are skipped.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
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

		// Then call enqueue_scripts with the scripts that were just added
		$this->instance->enqueue_scripts($scripts_to_enqueue);

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
			->setConstructorArgs(array($this->config_mock))
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_scripts
	 */
	public function test_enqueue_scripts_with_wp_data_and_attributes(): void {
		// Setup logger expectations
		$this->logger_mock->shouldReceive('debug')
			->withAnyArgs()
			->zeroOrMoreTimes();

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with('script-with-data', 'path/to/script-with-data.js', array('jquery'), '1.0.0', true)->andReturn(true);

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

		// Then call enqueue_scripts with the scripts that were just added
		$this->instance->enqueue_scripts($scripts_to_enqueue);

		// Verify the script is stored in the internal array after being processed
		$scripts = $this->instance->get_scripts();
		$this->assertCount(1, $scripts['general']);
		$this->assertEquals('script-with-data', $scripts['general'][0]['handle']);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 */
	public function test_process_single_script_attribute_filter_skips_src_attribute(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_handle     = 'test-script-src-attr';
		$script_actual_src = 'path/to/actual-script.js';
		$script_attributes = array(
			'src' => 'path/to/ignored-src.js', // This should be ignored by the filter
			'id'  => 'minimal-script-id',      // Simplified for this test
		);

		$script_definition = array(
			'handle'     => $script_handle,
			'src'        => $script_actual_src,
			'attributes' => $script_attributes,
		);

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($script_handle, $script_actual_src, array(), false, false)
			->andReturn(true);

		// Expect add_filter with specific signature, using Mockery::type('callable') for the callback.
		WP_Mock::expectFilterAdded('script_loader_tag', Mockery::type('callable'), 10, 3);
		// $filter_callback = null; // Not attempting to capture for direct execution.

		// Create a fresh SUT instance for this specific test after setting expectations
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Call _process_single_script directly using reflection on the new SUT instance
		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$result = $method->invoke($sut, $script_definition);
		$this->assertSame($script_definition['handle'], $result);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * Tests that the script_loader_tag filter callback correctly handles malformed script tags
	 * by returning them unchanged (indirectly, by ensuring add_filter is called).
	 */
	public function test_process_single_script_filter_handles_malformed_tag(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_handle     = 'test-script-malformed-tag';
		$script_actual_src = 'path/to/actual-script-malformed.js';
		// Attributes are needed to trigger the add_filter call within _process_single_script
		$script_attributes = array('id' => 'some-id-for-malformed-test');

		$script_definition = array(
			'handle'     => $script_handle,
			'src'        => $script_actual_src,
			'attributes' => $script_attributes,
		);

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($script_handle, $script_actual_src, array(), false, false)
			->andReturn(true);

		// Expect add_filter with specific signature, using Mockery::type('callable') for the callback.
		WP_Mock::expectFilterAdded('script_loader_tag', Mockery::type('callable'), 10, 3);
		// $filter_callback = null; // Not attempting to capture for direct execution.

		// Create a fresh SUT instance for this specific test after setting expectations
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Call _process_single_script directly using reflection on the new SUT instance
		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$result = $method->invoke($sut, $script_definition);
		$this->assertSame($script_definition['handle'], $result);
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * Tests that _process_single_script skips wp_script_add_data if wp_data is not an array.
	 */
	public function test_process_single_script_wp_data_not_array_skips_add_data(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_handle     = 'test-script-wp-data-not-array';
		$script_actual_src = 'path/to/script.js';

		$script_definition = array(
			'handle'     => $script_handle,
			'src'        => $script_actual_src,
			'wp_data'    => 'this is not an array', // Key: wp_data is a string
			'attributes' => array(), // Ensure attributes don't trigger add_filter
		);

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($script_handle, $script_actual_src, array(), false, false)
			->andReturn(true);

		// Expect wp_script_add_data NOT to be called
		WP_Mock::userFunction('wp_script_add_data')->never();

		// Expect add_filter NOT to be called (since attributes are empty)
		// No explicit expectation needed for 'never' with expectFilterAdded,
		// but we ensure attributes are empty.

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$result = $method->invoke($sut, $script_definition);

		$this->assertSame($script_definition['handle'], $result);
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * Tests that _process_single_script skips add_filter if attributes is not an array.
	 */
	public function test_process_single_script_attributes_not_array_skips_add_filter(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_handle     = 'test-script-attributes-not-array';
		$script_actual_src = 'path/to/script2.js';

		$script_definition = array(
			'handle'     => $script_handle,
			'src'        => $script_actual_src,
			'wp_data'    => array(), // Ensure wp_data doesn't trigger wp_script_add_data
			'attributes' => 'this is not an array', // Key: attributes is a string
		);

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($script_handle, $script_actual_src, array(), false, false)
			->andReturn(true);

		// Expect wp_script_add_data NOT to be called (since wp_data is empty)
		WP_Mock::userFunction('wp_script_add_data')->never();

		// Expect add_filter NOT to be called
		// We don't use expectFilterAdded(...)->never() as it's not standard for WP_Mock.
		// Instead, the absence of expectFilterAdded means it's not expected.
		// If it were called, the test would fail due to an unexpected call if Mockery's global
		// configuration is strict about unexpected calls, or simply not be asserted.
		// The key is that our SUT logic should prevent it.

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$result = $method->invoke($sut, $script_definition);

		$this->assertSame($script_definition['handle'], $result);
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * Tests that _process_single_script returns null if the handle is empty.
	 */
	public function test_process_single_script_empty_handle_returns_null(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_definition_empty_handle = array(
			'handle' => '', // Key: handle is empty
			'src'    => 'path/to/script.js',
		);
		$script_definition_no_handle = array( // Also test if handle key is missing
			'src' => 'path/to/another-script.js',
		);

		WP_Mock::userFunction('wp_register_script')->never();
		WP_Mock::userFunction('wp_script_add_data')->never();
		// add_filter is implicitly not expected as WP_Mock::expectFilterAdded is not called.

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);

		// Test with empty handle string
		$result_empty_handle = $method->invoke($sut, $script_definition_empty_handle);
		$this->assertNull($result_empty_handle, 'Should return null if handle is an empty string.');

		// Test with handle key not present
		$result_no_handle = $method->invoke($sut, $script_definition_no_handle);
		$this->assertNull($result_no_handle, 'Should return null if handle key is not present.');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 * Tests that _process_single_script returns null if wp_register_script fails.
	 */
	public function test_process_single_script_registration_fails_returns_null(): void {
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$script_handle     = 'test-script-registration-fails';
		$script_actual_src = 'path/to/script.js';

		$script_definition = array(
			'handle' => $script_handle,
			'src'    => $script_actual_src,
		);

		WP_Mock::userFunction('wp_register_script')
			->once()
			->with($script_handle, $script_actual_src, array(), false, false)
			->andReturn(false); // Key: wp_register_script returns false

		WP_Mock::userFunction('wp_script_add_data')->never();
		// add_filter is implicitly not expected.

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger'))
		            ->getMock();
		$sut->method('get_logger')->willReturn($this->logger_mock);

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$result = $method->invoke($sut, $script_definition);

		$this->assertNull($result);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_modify_script_tag_for_attributes
	 */
	public function test_modify_script_tag_for_attributes_handles_mismatch(): void {
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->onlyMethods(array('get_logger')) // Mock get_logger if it's called internally, though not expected here
		            ->getMock();
		// $sut->method('get_logger')->willReturn($this->logger_mock); // Uncomment if logger is used

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_modify_script_tag_for_attributes');
		$method->setAccessible(true);

		$original_tag = "<script src='test.js'></script>";
		$attributes   = array('id' => 'test-id');

		$returned_tag = $method->invokeArgs($sut, array(
			$original_tag,          // $tag
			'another-handle',       // $filter_tag_handle (different from script_handle_to_match)
			'my-script-handle',     // $script_handle_to_match
			$attributes             // $attributes_to_apply
		));
		$this->assertSame($original_tag, $returned_tag, "Tag should not be modified if handles don't match.");
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_modify_script_tag_for_attributes
	 */
	public function test_modify_script_tag_for_attributes_handles_malformed_tag(): void {
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->getMock();

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_modify_script_tag_for_attributes');
		$method->setAccessible(true);

		$malformed_tag_input = "<script src='test.js'"; // No '>'
		$attributes_input    = array('id' => 'test-id');
		$script_handle       = 'my-script-handle';

		$returned_tag = $method->invokeArgs($sut, array(
			$malformed_tag_input,
			$script_handle,
			$script_handle,
			$attributes_input
		));
		$this->assertSame($malformed_tag_input, $returned_tag, 'Malformed tag should be returned as is.');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_modify_script_tag_for_attributes
	 */
	public function test_modify_script_tag_for_attributes_skips_src_attribute(): void {
		WP_Mock::userFunction('esc_attr')
			->andReturnUsing(function ($string) {
				return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
			});

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->getMock();

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_modify_script_tag_for_attributes');
		$method->setAccessible(true);

		$tag_input                 = "<script src='original.js'></script>";
		$attributes_input_with_src = array('src' => 'ignored.js', 'id' => 'test-id', 'defer' => true, 'data-custom' => 'value');
		$script_handle             = 'my-script-handle';

		$returned_tag = $method->invokeArgs($sut, array(
			$tag_input,
			$script_handle,
			$script_handle,
			$attributes_input_with_src
		));

		$this->assertStringNotContainsString('src="ignored.js"', $returned_tag, "Ignored 'src' attribute should not be present.");
		$this->assertStringContainsString("src='original.js'", $returned_tag, "Original 'src' attribute should be preserved.");
		$this->assertStringContainsString('id="test-id"', $returned_tag);
		$this->assertStringContainsString(' defer', $returned_tag);
		$this->assertStringContainsString('data-custom="value"', $returned_tag);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_modify_script_tag_for_attributes
	 */
	public function test_modify_script_tag_for_attributes_handles_type_module(): void {
		WP_Mock::userFunction('esc_attr')
			->andReturnUsing(function ($string) {
				return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
			});

		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
		            ->setConstructorArgs(array($this->config_mock))
		            ->getMock();

		$method = new \ReflectionMethod(ConcreteEnqueueForScriptTesting::class, '_modify_script_tag_for_attributes');
		$method->setAccessible(true);

		$tag_input        = "<script src='module.js'></script>";
		$attributes_input = array('type' => 'module', 'id' => 'module-id');
		$script_handle    = 'my-module-script';

		$returned_tag = $method->invokeArgs($sut, array(
			$tag_input,
			$script_handle,
			$script_handle,
			$attributes_input
		));

		$this->assertStringContainsString("<script type=\"module\" src='module.js'", $returned_tag, "Tag should include type='module' correctly.");
		$this->assertStringContainsString('id="module-id"', $returned_tag);
		$this->assertStringNotContainsString('type="module" type="module"', $returned_tag, 'Type module should not be duplicated.');
	}


	/**
	 * Test register_scripts method with valid scripts.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
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
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 */
	public function test_register_scripts_handles_direct_pass_and_hooks(): void {
		// Ensure logger is active for debug messages
		$this->logger_mock->shouldReceive('is_active')->atLeast()->once()->andReturn(true);

		// Scripts to pass directly to the method
		$script_with_hook = array(
		    'handle'     => 'hooked-script',
		    'src'        => 'hooked.js',
		    'hook'       => 'admin_footer',
		    'deps'       => array(),
		    'version'    => false,
		    'in_footer'  => false,
		    'condition'  => null,
		    'wp_data'    => array(),
		    'attributes' => array()
		);
		$script_without_hook = array(
		    'handle'     => 'normal-script',
		    'src'        => 'normal.js',
		    'hook'       => null,
		    'deps'       => array(),
		    'version'    => false,
		    'in_footer'  => false,
		    'condition'  => null,
		    'wp_data'    => array(),
		    'attributes' => array()
		);
		$scripts_to_pass = array($script_with_hook, $script_without_hook);

		// Expectations for logger
		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.')
		    ->once()
		    ->ordered();

		// Mock add_scripts: it should be called, and we'll make it update the internal scripts property for subsequent logic
		$this->instance->shouldReceive('add_scripts')
		    ->with($scripts_to_pass)
		    ->once()
		    ->andReturnUsing(function($scripts_arg) {
		    	$this->set_protected_property_value($this->instance, 'scripts', $scripts_arg);
		    	return $this->instance;
		    })
		    ->ordered();

		// This log comes after add_scripts has (notionally) populated $this->scripts
		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::register_scripts - Registering ' . count($scripts_to_pass) . ' script(s).')
		    ->once()
		    ->ordered();

		// _process_single_script should only be called for the script without a hook
		$this->instance->shouldReceive('_process_single_script')
		    ->with(Mockery::on(function($arg) use ($script_without_hook) {
		    	return is_array($arg) && isset($arg['handle']) && $arg['handle'] === $script_without_hook['handle'];
		    }))
		    ->once()
		    ->ordered();

		// _process_single_script should NOT be called for the script WITH a hook
		$this->instance->shouldNotReceive('_process_single_script')
		    ->with(Mockery::on(function($arg) use ($script_with_hook) {
		    	return is_array($arg) && isset($arg['handle']) && $arg['handle'] === $script_with_hook['handle'];
		    }));

		// Execute the method under test
		$result = $this->instance->register_scripts($scripts_to_pass);

		// Verify method chaining
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
			array(
				'handle'  => 'default-values-script',
				'content' => 'console.log("Default values test");',
				// position, condition, and parent_hook are intentionally omitted to test defaults
			),
			array(
				'handle'   => 'before-pos-script',
				'content'  => 'console.log("Before position test");',
				'position' => 'before',
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

		// wp_script_is - simulate registered for default-values-script
		WP_Mock::userFunction('wp_script_is')
			->with('default-values-script', 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with('default-values-script', 'enqueued')
			->andReturn(false);

		// wp_script_is - simulate registered for before-pos-script
		WP_Mock::userFunction('wp_script_is')
			->with('before-pos-script', 'registered')
			->andReturn(true);
		WP_Mock::userFunction('wp_script_is')
			->with('before-pos-script', 'enqueued')
			->andReturn(false);

		// wp_script_is - we shouldn't check deferred script in this method
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script', 'registered')
			->never();

		// wp_add_inline_script - should be called for registered-script only
		WP_Mock::userFunction('wp_add_inline_script')
			->with('registered-script', 'console.log("Registered script");') // Expecting 2 arguments for 'after'
			->once();

		// wp_add_inline_script - should be called for default-values-script (defaults to position 'after')
		WP_Mock::userFunction('wp_add_inline_script')
			->with('default-values-script', 'console.log("Default values test");') // Expecting 2 arguments for 'after'
			->once();

		// wp_add_inline_script - should be called for before-pos-script with 'before' position
		WP_Mock::userFunction('wp_add_inline_script')
			->with('before-pos-script', 'console.log("Before position test");', 'before') // Expecting 3 arguments
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

		// Expect an error for the inline script with an empty handle
		$this->logger_mock->shouldReceive('error')
			->with('EnqueueAbstract::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: ')
			->once();

		// Expect an error for the inline script with empty content (diagnostic: zeroOrMoreTimes)
		$this->logger_mock->shouldReceive('error')
			->with('EnqueueAbstract::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: no-content-inline')
			->zeroOrMoreTimes();

		// Expect an error for the inline script with handle 'empty-content' and empty content
		$this->logger_mock->shouldReceive('error')
			->with('EnqueueAbstract::enqueue_inline_scripts - Skipping (non-deferred) inline script due to missing handle or content. Handle: empty-content')
			->once();

		// Expect an error for the inline script whose parent is not registered
		$this->logger_mock->shouldReceive('error')
			->with("EnqueueAbstract::enqueue_inline_scripts - (Non-deferred) Cannot add inline script. Parent script 'unregistered-script' is not registered or enqueued.")
			->once();

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

		// Process deferred scripts to populate the internal deferred_scripts array and set up actions
		$this->instance->enqueue_scripts($deferred_scripts_data);

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

		// --- Assert state BEFORE processing deferred scripts ---
		// Get the deferred scripts directly from the property BEFORE they are processed
		$deferred_scripts_before_processing = $this->get_protected_property_value($this->instance, 'deferred_scripts');

		// 1. Assert that your specific hook ('admin_footer') exists as a key
		$this->assertArrayHasKey($hook, $deferred_scripts_before_processing, "The hook '{$hook}' should exist as a key in the 'deferred_scripts' property before processing.");

		// 2. Assert that there are scripts for this hook (expect 2 from $deferred_scripts_data, one of which has a false condition but is still in the array)
		$this->assertCount(
			count($deferred_scripts_data),
			$deferred_scripts_before_processing[$hook],
			'Initially, there should be ' . count($deferred_scripts_data) . " scripts registered for the hook '{$hook}' before deferred processing."
		);

		// --- Mocks for deferred-script-1 processing (ordered) ---
		// (All WP_Mock and logger_mock expectations for script processing will follow here, then the call to enqueue_deferred_scripts)

		// Call the method under test AFTER setting up initial state and BEFORE final state assertions
		// Note: The actual mock expectations for wp_register_script, wp_enqueue_script, etc.,
		// for 'deferred-script-1' and 'deferred-script-2' (skipped) will need to be placed here or before this call.
		// For now, this edit focuses on fixing the assertion logic around deferred_scripts state.
		// The existing mocks from line 1445 onwards should be reviewed to ensure they are correctly placed relative to this call.
		// 1. Initial check: 'deferred-script-1' is not registered.
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script-1', 'registered')
			->andReturn(false)
			->ordered()
			->once();

		// 2. Initial check: 'deferred-script-1' is not enqueued.
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script-1', 'enqueued')
			->andReturn(false)
			->ordered()
			->once();

		// 3. wp_register_script for deferred-script-1 (will be called by _process_single_script)
		WP_Mock::userFunction('wp_register_script')
			->with('deferred-script-1', 'path/to/deferred-script-1.js', array('jquery'), '1.0.0', true) // Match args from $deferred_scripts_data
			->andReturn(true)
			->ordered()
			->once();

		// 4. wp_enqueue_script for deferred-script-1 (will be called after successful registration)
		WP_Mock::userFunction('wp_enqueue_script')
			->with('deferred-script-1')
			->ordered()
			->once();

		// 5. Check for inline script: 'deferred-script-1' is now registered.
		// This is called from within the inline script processing loop.
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script-1', 'registered')
			->andReturn(true)
			->ordered()
			->once(); // Expect one successful inline script to be added for deferred-script-1

		// 6. wp_add_inline_script for deferred-script-1's inline script
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', Mockery::type('string'), 'after')
			->once()
			->andReturn(true);

		// --- Mocks for deferred-script-2 processing (ordered) ---
		// Its condition `static fn() => false` will cause _process_single_script to return null.
		// It will be checked for registered/enqueued status before its condition is evaluated.
		// 7. Initial check: 'deferred-script-2' is not registered.
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script-2', 'registered')
			->andReturn(false)
			->ordered()
			->once();

		// 8. Initial check: 'deferred-script-2' is not enqueued.
		WP_Mock::userFunction('wp_script_is')
			->with('deferred-script-2', 'enqueued')
			->andReturn(false)
			->ordered()
			->once();

		// These should NOT be called for deferred-script-2 due to its failing condition
		WP_Mock::userFunction('wp_register_script')
			->with('deferred-script-2', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
			->never();
		WP_Mock::userFunction('wp_enqueue_script') // Already mocked as never earlier in test.
			->with('deferred-script-2')
			->never();
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-2', Mockery::any(), Mockery::any())
			->never();

		// Also ensure other inline scripts for deferred-script-1 that should be skipped are not called
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', '', Mockery::any()) // Empty content
			->never();
		WP_Mock::userFunction('wp_add_inline_script')
			->with('deferred-script-1', 'console.log("Conditional inline");', Mockery::any()) // Failing condition
			->never();

		// Expect the "missing content" error for one of deferred-script-1's inline scripts (if it occurs)
		$this->logger_mock->shouldReceive('error')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Skipping inline script for deferred 'deferred-script-1' due to missing content.")
			->zeroOrMoreTimes();

		// Expect a warning for deferred-script-2 not being processed (condition false)
		$this->logger_mock->shouldReceive('warning')
			->with('EnqueueAbstract::enqueue_deferred_scripts - _process_single_script returned an unexpected handle (\'\') or empty for original handle \'deferred-script-2\' on hook "admin_footer". Skipping main script enqueue and its inline scripts.')
			->once();

		$this->instance->enqueue_deferred_scripts($hook); // This processes and clears deferred_scripts[$hook]

		// --- Assert state AFTER processing deferred scripts ---
		// Get the deferred scripts directly from the property AFTER they are processed
		$deferred_scripts_after_processing = $this->get_protected_property_value($this->instance, 'deferred_scripts');

		// 3. Assert that the hook is now CLEARED from the deferred_scripts property
		$this->assertArrayNotHasKey($hook, $deferred_scripts_after_processing, "The hook '{$hook}' should be cleared from the 'deferred_scripts' property after processing.");

		// Mock expectations moved above the call to enqueue_deferred_scripts.

		// For any other script handle, assume it's not registered or enqueued initially.
		WP_Mock::userFunction('wp_script_is')
			->with(Mockery::not(array('deferred-script-1', 'deferred-script-2')), 'registered')
			->andReturn(false)
			->zeroOrMoreTimes();
		WP_Mock::userFunction('wp_script_is')
			->with(Mockery::not(array('deferred-script-1', 'deferred-script-2')), 'enqueued')
			->andReturn(false)
			->zeroOrMoreTimes();

		// Call the method under test
		$this->instance->enqueue_deferred_scripts($hook);

		// Verify deferred_scripts for this hook is now empty
		// The EnqueueAbstract class unsets $this->deferred_scripts[$hook] after processing.
		$final_deferred_scripts = $this->get_protected_property_value($this->instance, 'deferred_scripts');
		$this->assertArrayNotHasKey(
			$hook,
			$final_deferred_scripts, // Check directly in the deferred_scripts property
			"The hook '{$hook}' should no longer be a key in 'deferred_scripts' after successful processing."
		);

		// Verify the processed inline script for deferred-script-1 was removed
		// And other inline scripts (skipped parent, empty content, false condition) remain.
		$remaining_inline_scripts         = $this->get_protected_property_value($this->instance, 'inline_scripts');
		$found_processed_inline_remaining = false;
		$found_skipped_parent_inline      = false;
		$found_empty_content_inline       = false;
		$found_conditional_false_inline   = false;

		foreach ($remaining_inline_scripts as $remaining_inline) {
			$current_handle      = $remaining_inline['handle']      ?? null;
			$current_content     = $remaining_inline['content']     ?? null;
			$current_parent_hook = $remaining_inline['parent_hook'] ?? null;

			if ($current_handle === 'deferred-script-1' && $current_content === 'console.log("Deferred inline script");' && $current_parent_hook === $hook) {
				$found_processed_inline_remaining = true;
			}
			if ($current_handle === 'deferred-script-2'                 && // For 'deferred-script-2' (skipped parent)
				$current_content   === 'console.log("Should be skipped");' && $current_parent_hook === $hook) {
				$found_skipped_parent_inline = true;
			}
			if ($current_handle === 'deferred-script-1' && // For 'deferred-script-1' (empty content)
				$current_content   === ''                  && $current_parent_hook === $hook) {
				$found_empty_content_inline = true;
			}
			if ($current_handle === 'deferred-script-1'                  && // For 'deferred-script-1' (conditional false)
				$current_content   === 'console.log("Conditional inline");' && $current_parent_hook === $hook) {
				$found_conditional_false_inline = true;
			}
		}
		$this->assertFalse($found_processed_inline_remaining, 'The successfully processed inline script for deferred-script-1 should have been removed.');
		$this->assertTrue($found_skipped_parent_inline, 'The inline script for deferred-script-2 (skipped parent) should remain.');
		$this->assertTrue($found_empty_content_inline, 'The inline script for deferred-script-1 with empty content should remain.');
		$this->assertTrue($found_conditional_false_inline, 'The inline script for deferred-script-1 with a false condition should remain.');
	}

	/**
	 * Test register_scripts when scripts are passed directly, and with different logger states.
	 *
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 */
	public function test_register_scripts_passed_directly_with_logger_states(): void {
		$direct_scripts = array(
			array(
				'handle' => 'direct-script-1',
				'src'    => 'path/to/direct-script-1.js',
			),
		);

		// Mock wp_register_script as it will be called by _process_single_script
		// This needs to be defined before the first call to register_scripts
		WP_Mock::userFunction('wp_register_script')
			->with('direct-script-1', 'path/to/direct-script-1.js', array(), false, false)
			->once() // Expect it to be called for Scenario 1
			->andReturn(true);

		// Scenario 1: Logger is active
		$this->logger_mock->shouldReceive('is_active')->times(4)->andReturn(true);
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
		$this->logger_mock = Mockery::mock(\Ran\PluginLib\Util\Logger::class);
		// @phpstan-ignore-next-line P1006
		$this->instance = Mockery::mock(
			ConcreteEnqueueForScriptTesting::class,
			array($this->config_mock)
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
		$this->logger_mock->shouldReceive('is_active')->times(4)->andReturn(false); // Logger inactive, expecting 4 calls
		$this->logger_mock->shouldReceive('debug') // For "Scripts directly passed"
			->with('EnqueueAbstract::register_scripts - Scripts directly passed. Consider using add_scripts() first for better maintainability.')
			->never(); // Should not be called if logger is inactive
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

	// region Enqueue Deferred Scripts

	/**
     * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
     */
	public function test_enqueue_deferred_scripts_hook_not_set(): void {
		$hook_name = 'my_test_hook';

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock)) // from PluginLibTestCase
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Set deferred_scripts to an empty array (hook not present)
		$this->set_protected_property_value($sut, 'deferred_scripts', array());

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook_name}\" not found in deferred scripts. Nothing to process.")
			->once()
			->ordered();

		// Ensure no processing methods are called
		$sut->expects($this->never())->method('_process_single_script');
		WP_Mock::userFunction('wp_enqueue_script')->never();
		WP_Mock::userFunction('wp_add_inline_script')->never();

		$result = $sut->enqueue_deferred_scripts($hook_name);
		// Method returns void, not chainable in this context.
	}

	/**
     * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
     */
	public function test_enqueue_deferred_scripts_hook_set_but_empty(): void {
		$hook_name = 'my_empty_hook';

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Set deferred_scripts to an array where the hook exists but is empty
		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array()));

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Hook \"{$hook_name}\" was set but had no scripts. It has now been cleared.")
			->once()
			->ordered();

		$sut->expects($this->never())->method('_process_single_script');
		WP_Mock::userFunction('wp_enqueue_script')->never();
		WP_Mock::userFunction('wp_add_inline_script')->never();

		$result = $sut->enqueue_deferred_scripts($hook_name);
		// Method returns void, not chainable in this context.
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_deferred_scripts_skips_script_missing_handle(): void {
		$hook_name             = 'hook_with_invalid_script';
		$script_without_handle = array(
			// 'handle' => 'my-script', // Missing handle
			'src' => 'path/to/script.js',
		);

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Set deferred_scripts with the invalid script
		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array($script_without_handle)));

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()
			->ordered();

		$this->logger_mock->shouldReceive('error')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Script definition missing 'handle' for hook '{$hook_name}'. Skipping this script definition.")
			->once()
			->ordered();

		$sut->expects($this->never())->method('_process_single_script');
		WP_Mock::userFunction('wp_script_is')->never(); // Should not be called if handle is missing
		WP_Mock::userFunction('wp_enqueue_script')->never();
		WP_Mock::userFunction('wp_add_inline_script')->never();

		$sut->enqueue_deferred_scripts($hook_name);

		// Verify the hook was cleared from deferred_scripts
		$remaining_deferred = $this->get_protected_property_value($sut, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook_name, $remaining_deferred, 'Hook should be cleared from deferred_scripts after processing.');
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 */
	public function test_enqueue_deferred_scripts_process_single_script_fails(): void {
		$hook_name         = 'hook_process_fail';
		$script_handle     = 'script-that-fails-processing';
		$script_definition = array(
			'handle' => $script_handle,
			'src'    => 'path/to/script.js',
		);

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);
		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array($script_definition)));

		// Mock _process_single_script to simulate failure
		$sut->expects($this->once())
			->method('_process_single_script')
			->with($script_definition)
			->willReturn(null); // Simulate failure

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()
			->ordered();

		// wp_script_is checks
		WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'registered')
			->times(1) // Or more depending on logger active status, ensure it's called
			->andReturn(true); // Assume registered for this test path
		WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'enqueued')
			->times(1)
			->andReturn(false); // Not enqueued

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Processing deferred script: \"{$script_handle}\" for hook: \"{$hook_name}\"")
			->once()
			->ordered();

		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::enqueue_deferred_scripts - _process_single_script returned an unexpected handle ('') or empty for original handle '{$script_handle}' on hook \"{$hook_name}\". Skipping main script enqueue and its inline scripts.")
			->once()
			->ordered();

		WP_Mock::userFunction('wp_enqueue_script')->never();
		WP_Mock::userFunction('wp_add_inline_script')->never();

		$sut->enqueue_deferred_scripts($hook_name);

		$remaining_deferred = $this->get_protected_property_value($sut, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook_name, $remaining_deferred);
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_script
	 */
	public function test_enqueue_deferred_scripts_success_with_inline_script(): void {
		$hook_name              = 'hook_success_inline';
		$main_script_handle     = 'main-deferred-script';
		$main_script_definition = array(
			'handle'    => $main_script_handle,
			'src'       => 'path/to/main-script.js',
			'deps'      => array(),
			'version'   => '1.0',
			'in_footer' => false,
		);
		$inline_script_content = 'console.log("Inline for main-deferred-script");';
		$inline_script_data    = array(
			'handle'      => $main_script_handle,
			'content'     => $inline_script_content,
			'position'    => 'after',
			'parent_hook' => $hook_name,
			'condition'   => null,
		);

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);
		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array($main_script_definition)));
		$this->set_protected_property_value($sut, 'inline_scripts', array($inline_script_data)); // Add the inline script

		// Mock _process_single_script to simulate success
		$sut->expects($this->once())
			->method('_process_single_script')
			->with($main_script_definition)
			->willReturn($main_script_handle); // Simulate success

		// Logger expectations in order
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()->ordered();

		WP_Mock::userFunction('wp_script_is')->with($main_script_handle, 'registered')->andReturn(true);
		WP_Mock::userFunction('wp_script_is')->with($main_script_handle, 'enqueued')->andReturn(false);

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Processing deferred script: \"{$main_script_handle}\" for hook: \"{$hook_name}\"")
			->once()->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Calling wp_enqueue_script for deferred: \"{$main_script_handle}\" on hook: \"{$hook_name}\"")
			->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_script')
			->with($main_script_handle)
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Checking for inline scripts for handle '{$main_script_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		WP_Mock::userFunction('wp_add_inline_script')
			->with($main_script_handle, $inline_script_content, 'after')
			->once();

		$sut->enqueue_deferred_scripts($hook_name);

		$remaining_deferred = $this->get_protected_property_value($sut, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook_name, $remaining_deferred);

		// Assert inline script is removed by this method after processing
		$remaining_inline = $this->get_protected_property_value($sut, 'inline_scripts');
		$this->assertCount(0, $remaining_inline, 'Inline script should be removed after processing.');
		$this->assertEmpty($remaining_inline, 'Inline scripts array should be empty.');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_deferred_scripts_already_enqueued_processes_inline(): void {
		$hook_name              = 'hook_already_enqueued';
		$main_script_handle     = 'already-enqueued-script';
		$main_script_definition = array(
			'handle' => $main_script_handle,
			'src'    => 'path/to/already-enqueued.js',
		);
		$inline_script_content = 'console.log("Inline for already-enqueued-script");';
		$inline_script_data    = array(
			'handle'      => $main_script_handle,
			'content'     => $inline_script_content,
			'position'    => 'after',
			'parent_hook' => $hook_name,
			'condition'   => null,
		);

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($this->logger_mock);
		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array($main_script_definition)));
		$this->set_protected_property_value($sut, 'inline_scripts', array($inline_script_data));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Entered for hook: \"{$hook_name}\"")
			->once()->ordered();

		// Script is already enqueued
		WP_Mock::userFunction('wp_script_is')->with($main_script_handle, 'registered')->andReturn(true);
		WP_Mock::userFunction('wp_script_is')->with($main_script_handle, 'enqueued')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Script '{$main_script_handle}' is already enqueued. Skipping its registration and main enqueue call on hook '{$hook_name}'. Inline scripts will still be processed.")
			->once()->ordered();

		// Main script processing and enqueue should be skipped
		$sut->expects($this->never())->method('_process_single_script');
		WP_Mock::userFunction('wp_enqueue_script')->with($main_script_handle)->never();

		// Inline script processing should still occur
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Checking for inline scripts for handle '{$main_script_handle}' on hook '{$hook_name}'.")
			->once()->ordered();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Adding inline script for deferred '{$main_script_handle}' (position: after) on hook '{$hook_name}'.")
			->once()->ordered();

		WP_Mock::userFunction('wp_add_inline_script')
			->with($main_script_handle, $inline_script_content, 'after')
			->once();

		$sut->enqueue_deferred_scripts($hook_name);

		$remaining_deferred = $this->get_protected_property_value($sut, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook_name, $remaining_deferred, 'Hook should be cleared.');

		$remaining_inline = $this->get_protected_property_value($sut, 'inline_scripts');
		$this->assertEmpty($remaining_inline, 'Inline script should be processed and removed.');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_deferred_scripts_with_inactive_logger(): void {
		// Test was previously skipped, now re-enabling.

		$hook_name              = 'hook_inactive_logger';
		$main_script_handle     = 'inactive-logger-script';
		$main_script_definition = array(
			'handle'    => $main_script_handle,
			'src'       => 'path/to/inactive-logger.js',
			'deps'      => array(),
			'version'   => false,
			'in_footer' => false,
			'condition' => null,
		);

		$sut_logger_mock = Mockery::mock(\Ran\PluginLib\Util\Logger::class);
		$sut_logger_mock->shouldReceive('is_active')->andReturn(false); // Logger is inactive
		// Expect the final unconditional debug log
		$sut_logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_scripts - Exited for hook: \"{$hook_name}\"")
			->never();
		// Other debug/error/warning calls should not occur if they are properly guarded by is_active()

		/** @var \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForScriptTesting&\PHPUnit\Framework\MockObject\MockObject $sut */
		$sut = $this->getMockBuilder(ConcreteEnqueueForScriptTesting::class)
			->setConstructorArgs(array($this->config_mock))
			->onlyMethods(array('get_logger', '_process_single_script'))
			->getMock();

		$sut->method('get_logger')->willReturn($sut_logger_mock);
		$sut->method('_process_single_script')
			->with($main_script_definition)
			->willReturn($main_script_handle);

		$this->set_protected_property_value($sut, 'deferred_scripts', array($hook_name => array($main_script_definition)));
		$this->set_protected_property_value($sut, 'inline_scripts', array()); // No inline scripts for this test

		// Expect wp_script_is to be called even with inactive logger
		WP_Mock::userFunction('wp_script_is')
			->with($main_script_handle, 'registered')
			->once()
			->andReturn(false); // Script not registered

		WP_Mock::userFunction('wp_script_is')
			->with($main_script_handle, 'enqueued')
			->once()
			->andReturn(false); // Script not enqueued


		// Expect main script to be enqueued
		WP_Mock::userFunction('wp_enqueue_script')
			->with($main_script_handle)
			->once();

		$sut->enqueue_deferred_scripts($hook_name);

		$remaining_deferred = $this->get_protected_property_value($sut, 'deferred_scripts');
		$this->assertArrayNotHasKey($hook_name, $remaining_deferred, 'Hook should be cleared from deferred_scripts.');
	}
}
