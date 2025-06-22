<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAbstract;
use Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigAbstract;
use WP_Mock;
use ReflectionClass;
use ReflectionProperty;

/**
 * Concrete implementation of EnqueueAbstract for testing media methods.
 */
class ConcreteEnqueueForMediaTesting extends EnqueueAbstract {
	/**
	 * {@inheritdoc}
	 */
	public function load(): void {
		// This is a concrete implementation for testing purposes.
	}

	/**
	 * Public wrapper to test the protected enqueue_deferred_media_tools method.
	 */
	public function enqueue_deferred_media_tools_public(): self {
		$this->enqueue_deferred_media_tools();
		return $this;
	}
}

/**
 * Test suite for MediaEnqueueTrait media methods.
 */
final class EnqueueAbstractMediaTest extends PluginLibTestCase {
	/** @var ConcreteEnqueueForMediaTesting&MockInterface */
	protected $instance; // Mockery will handle the type

	public function setUp(): void {
		parent::setUp(); // This sets up WP_Mock, config_mock, and logger_mock (the spy)

		// Set up default, permissive expectations on the logger spy.
		$this->logger_mock->shouldReceive('is_active')->byDefault()->andReturn(true);
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('warning')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('notice')->withAnyArgs()->andReturnNull()->byDefault();

		// Default WP_Mock function mocks for media functions
		WP_Mock::userFunction('wp_enqueue_media')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => function ($data) {
				return json_encode($data);
			},
		));
		WP_Mock::userFunction('has_action', array('return' => false))->byDefault();

		// Create a real instance of the SUT, passing the mocked config. The logger is
		// injected via the config, so we don't need a partial mock or spy.
		$this->instance = new ConcreteEnqueueForMediaTesting($this->config_mock);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::add_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::add_media
	 */
	public function test_add_media_stores_data(): void {
		$media_data = array(
			array('args' => array('post' => 123), 'condition' => null, 'hook' => null),
			array('args' => array(), 'condition' => null, 'hook' => 'admin_enqueue_scripts'),
		);

		// The SUT's get_logger() method will be called internally by add_media.
		// Its interaction is indirectly tested by the logger_mock->debug() expectation below.

		// Expect debug to be called at least once with any arguments.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->atLeast()->once();

		WP_Mock::userFunction('did_action', array('return' => false)); // Ensure deferral happens

		$result = $this->instance->add_media($media_data);
		$this->assertSame($this->instance, $result);

		$reflection = new ReflectionClass(EnqueueAbstract::class);
		$property   = $reflection->getProperty('media_tool_configs');
		$property->setAccessible(true);
		$stored_media_configs = $property->getValue($this->instance);
		$this->assertSame($media_data, $stored_media_configs);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_deferred_media_tools
	 */
	public function test_enqueue_media_defers_to_admin_enqueue_scripts_when_no_hook_provided(): void {
		$media_item_args = array('post' => 456);
		$media_configs   = array(array('args' => $media_item_args));
		$default_hook    = 'admin_enqueue_scripts';

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - Entered\. Processing 1 media tool configuration\(s\)\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - Processing media tool configuration at original index: 0\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - No hook specified for media tool configuration at original index 0\. Defaulting to \'' . $default_hook . '\'\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - Deferring media tool configuration at original index 0 to hook: \"' . $default_hook . '\"\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - Added action for \'enqueue_deferred_media_tools\' on hook: \"' . $default_hook . '\"\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_media - Exited\.$/'))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media')->never();
		WP_Mock::userFunction('did_action', array('return' => false)); // Ensure deferral happens
		WP_Mock::expectActionAdded($default_hook, array($this->instance, 'enqueue_deferred_media_tools'), 10, 0);

		$result = $this->instance->enqueue_media($media_configs);
		$this->assertSame($this->instance, $result);

		$deferred_media_property = new ReflectionProperty(EnqueueAbstract::class, 'deferred_media_tool_configs');
		$deferred_media_property->setAccessible(true);
		$deferred_items = $deferred_media_property->getValue($this->instance);

		$this->assertArrayHasKey($default_hook, $deferred_items);
		$this->assertCount(1, $deferred_items[$default_hook]);
		$this->assertSame($media_configs[0], $deferred_items[$default_hook][0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_media
	 */
	public function test_enqueue_media_skips_when_condition_is_false(): void {
		$hook_name     = 'admin_enqueue_scripts'; // The default hook
		$media_configs = array(
			// The hook is null, so it will default to admin_enqueue_scripts
			array('args' => array('post' => 123), 'condition' => static fn() => false, 'hook' => null),
		);

		// Mocks for the deferred call
		WP_Mock::userFunction('current_action', array('return' => $hook_name));
		WP_Mock::userFunction('wp_enqueue_media')->never();

		// Expect the correct log message from the deferred call
		$this->logger_mock->expects('debug')
			->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_deferred_media_tools - Condition not met for deferred media tool configuration at original index 0 on hook "' . $hook_name . '"\. Skipping\.$/'))
			->once();

		// Act
		$this->instance->enqueue_media($media_configs); // This queues the item
		$result = $this->instance->enqueue_deferred_media_tools_public(); // This processes the queue and checks the condition

		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_deferred_media_tools
	 */
	public function test_enqueue_media_defers_media_with_hook(): void {
		// Add permissive debug expectation to prevent BadMethodCallException while other tests are focused.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();
		$media_configs = array(array('args' => array(), 'hook' => 'admin_enqueue_scripts'));

		WP_Mock::userFunction('did_action', array('return' => false)); // Ensure deferral happens
		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_media_tools'), 10, 0);

		$result = $this->instance->enqueue_media($media_configs);
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_processes_stored_media(): void {
		$hook_name = 'admin_enqueue_scripts';
		WP_Mock::userFunction('current_action', array('return' => $hook_name));
		WP_Mock::userFunction('wp_enqueue_media')->once();

		$this->logger_mock->expects('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_deferred_media_tools - Processing deferred media tool configuration at original index 0 for hook: "' . $hook_name . '"\.$/'))->once();

		// Use reflection to pre-populate the deferred_media_tool_configs property
		$reflection = new \ReflectionClass(EnqueueAbstract::class);
		$property   = $reflection->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($this->instance, array($hook_name => array(array(), ), ));

		// Act
		$this->instance->enqueue_deferred_media_tools_public();

		// Assert
		$property = $reflection->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$this->assertArrayNotHasKey($hook_name, $property->getValue($this->instance));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_skips_when_condition_is_false(): void {
		$hook_name = 'admin_enqueue_scripts';
		WP_Mock::userFunction('current_action', array('return' => $hook_name));
		WP_Mock::userFunction('wp_enqueue_media')->never();

		$this->logger_mock->expects('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_deferred_media_tools - Condition not met for deferred media tool configuration at original index 0 on hook "' . $hook_name . '"\. Skipping\.$/'))->once();

		// Use reflection to pre-populate the deferred_media_tool_configs property
		$reflection = new \ReflectionClass(EnqueueAbstract::class);
		$property   = $reflection->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($this->instance, array($hook_name => array(array('condition' => fn() => false))));

		// Act
		$this->instance->enqueue_deferred_media_tools_public();

		// Assert
		$property = $reflection->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$this->assertArrayNotHasKey($hook_name, $property->getValue($this->instance));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_with_empty_hook_data(): void {
		$hook_name = 'empty_hook';
		WP_Mock::userFunction('current_action', array('return' => $hook_name));

		// Set deferred_media_tool_configs to an empty array for this hook or not set at all
		$reflector = new ReflectionClass(EnqueueAbstract::class);
		$property  = $reflector->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($this->instance, array($hook_name => array())); // or just [] to test no key

		$this->logger_mock->expects('debug')->with(Mockery::pattern('/^MediaEnqueueTrait::enqueue_deferred_media_tools - No deferred media tool configurations found or already processed for hook: "empty_hook"\.$/'))->once();

		WP_Mock::userFunction('wp_enqueue_media')->never();

		$result = $this->instance->enqueue_deferred_media_tools_public();
		$this->assertSame($this->instance, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::get_media_tool_configs
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::add_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::enqueue_media
	 */
	public function test_get_media_tool_configs_returns_correct_structure_and_data(): void {
		// Data for the internal 'media_tool_configs' property (accessed via 'general' key)
		$general_configs_to_add = array(
			array('args' => array('post' => 111), 'hook' => 'admin_init'),
			array('args' => array('post' => 222)), // No hook, will be processed by enqueue_media if passed to it
		);

		// Data to be processed by enqueue_media to populate 'deferred_media_tool_configs'
		$deferred_configs_source = array(
			array('args' => array('post' => 333), 'hook' => 'custom_hook_1'),
			array('args' => array('post' => 444), 'hook' => 'custom_hook_2', 'condition' => static fn() => true),
			array('args' => array('post' => 555)), // No hook, should default to admin_enqueue_scripts
		);

		// Expected structure for deferred_media_tool_configs after enqueue_media
		// Note: The keys of the items within each hook's array are the original indices from $deferred_configs_source.
		$expected_deferred_configs = array(
			'custom_hook_1' => array(
				0 => $deferred_configs_source[0],
			),
			'custom_hook_2' => array(
				1 => $deferred_configs_source[1],
			),
			'admin_enqueue_scripts' => array(
				2 => $deferred_configs_source[2], // Item with defaulted hook (original item doesn't get 'hook' key added)
			),
		);

		// Permissive logger expectation for methods called
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		// Call add_media to populate the internal $media_tool_configs
		$this->instance->add_media($general_configs_to_add);

		// Mock WP functions for enqueue_media
		WP_Mock::userFunction('has_action', array('return' => false)); // Assume no actions initially
		WP_Mock::expectActionAdded('custom_hook_1', array($this->instance, 'enqueue_deferred_media_tools'), 10, 0);
		WP_Mock::expectActionAdded('custom_hook_2', array($this->instance, 'enqueue_deferred_media_tools'), 10, 0);
		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_media_tools'), 10, 0);

		// Call enqueue_media to populate the internal $deferred_media_tool_configs
		$this->instance->enqueue_media($deferred_configs_source);

		// Get the configs
		$retrieved_configs = $this->instance->get_media_tool_configs();

		$this->assertIsArray($retrieved_configs);
		$this->assertArrayHasKey('general', $retrieved_configs);
		$this->assertArrayHasKey('deferred', $retrieved_configs);

		// Assert 'general' configs
		$this->assertEquals($general_configs_to_add, $retrieved_configs['general']);

		// Assert 'deferred' configs
		$this->assertEquals($expected_deferred_configs, $retrieved_configs['deferred']);
	}
}
