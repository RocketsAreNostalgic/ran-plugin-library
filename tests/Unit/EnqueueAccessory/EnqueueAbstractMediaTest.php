<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAbstract;
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
	public function enqueue_deferred_media_tools_public(string $hook_name): self {
		parent::enqueue_deferred_media_tools($hook_name);
		return $this;
	}
}

/**
 * Test suite for EnqueueAbstract media methods.
 */
final class EnqueueAbstractMediaTest extends PluginLibTestCase {
	protected MockInterface $config_instance_mock;
	protected MockInterface $logger_mock;
	protected ConcreteEnqueueForMediaTesting $enqueue_instance_mocked_logger; // Instance where get_logger() is mocked

	public function setUp(): void {
		parent::setUp();

		WP_Mock::userFunction('wp_json_encode', array(
			'return' => function($data) {
				return json_encode($data);
			},
		));

		WP_Mock::userFunction('is_admin', array('return' => false));
		WP_Mock::userFunction('did_action', array('return' => false));
		WP_Mock::userFunction('has_action', array('return' => false));

		$this->config_instance_mock = Mockery::mock(ConfigAbstract::class);
		$this->logger_mock          = Mockery::mock(Logger::class);
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Generic catch-all for log messages to prevent errors if not specifically expected
		// $this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull(); // Commented out for focused debugging
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull();
		$this->logger_mock->shouldReceive('notice')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull();
		$this->logger_mock->shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull();

		// Instantiate a real SUT with the mocked configuration.
		// The config_instance_mock is stubbed in the parent EnqueueAbstractBaseTest::setUp()
		// to return $this->logger_mock when its get_logger() method is called.
		$this->enqueue_instance_mocked_logger = new ConcreteEnqueueForMediaTesting($this->config_instance_mock);

		// Ensure the config mock is explicitly expected to provide the logger mock in this test class's context.
		$this->config_instance_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);
	}

	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_media
	 */
	public function test_add_media_stores_data(): void {
		$sut = $this->enqueue_instance_mocked_logger;

		$media_data = array(
			array('args' => array('post' => 123), 'condition' => null, 'hook' => null),
			array('args' => array(), 'condition' => null, 'hook' => 'admin_enqueue_scripts'),
		);

		// The SUT's get_logger() method will be called internally by add_media.
		// Its interaction is indirectly tested by the logger_mock->debug() expectation below.

		// Expect debug to be called at least once with any arguments.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->atLeast()->once();

		$result = $sut->add_media($media_data);
		$this->assertSame($this->enqueue_instance_mocked_logger, $result);

		$reflection = new ReflectionClass(EnqueueAbstract::class);
		$property   = $reflection->getProperty('media_tool_configs');
		$property->setAccessible(true);
		$stored_media_configs = $property->getValue($this->enqueue_instance_mocked_logger);
		$this->assertSame($media_data, $stored_media_configs);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_media_tools
	 */
	public function test_enqueue_media_defers_to_admin_enqueue_scripts_when_no_hook_provided(): void {
		$media_item_args = array('post' => 123);
		$media_configs   = array(array('args' => $media_item_args));
		$default_hook    = 'admin_enqueue_scripts';

		$sut = $this->enqueue_instance_mocked_logger;

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Entered\. Processing 1 media tool configuration\(s\)\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Processing media tool configuration at original index: 0\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - No hook specified for media tool configuration at original index 0\. Defaulting to '{$default_hook}'\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - Deferring media tool configuration at original index 0 to hook: \"{$default_hook}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - Added action for 'enqueue_deferred_media_tools' on hook: \"{$default_hook}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Exited\.$/'))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media')->never();
		WP_Mock::expectActionAdded($default_hook, array($sut, 'enqueue_deferred_media_tools'), 10, 1);

		$result = $sut->enqueue_media($media_configs);
		$this->assertSame($sut, $result);

		$deferred_media_property = new ReflectionProperty(EnqueueAbstract::class, 'deferred_media_tool_configs');
		$deferred_media_property->setAccessible(true);
		$deferred_items = $deferred_media_property->getValue($sut);

		$this->assertArrayHasKey($default_hook, $deferred_items);
		$this->assertCount(1, $deferred_items[$default_hook]);
		$this->assertSame($media_configs[0], $deferred_items[$default_hook][0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_media
	 */
	public function test_enqueue_media_skips_when_condition_is_false(): void {
		$sut           = $this->enqueue_instance_mocked_logger;
		$media_configs = array(
			array('args' => array('post' => 123), 'condition' => static fn() => false, 'hook' => null),
		);

		$default_hook = 'admin_enqueue_scripts';

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Entered\. Processing 1 media tool configuration\(s\)\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Processing media tool configuration at original index: 0\.$/'))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - No hook specified for media tool configuration at original index 0\. Defaulting to '{$default_hook}'\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - Deferring media tool configuration at original index 0 to hook: \"{$default_hook}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_media - Added action for 'enqueue_deferred_media_tools' on hook: \"{$default_hook}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^EnqueueAbstract::enqueue_media - Exited\.$/'))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media')->never();
		WP_Mock::expectActionAdded($default_hook, array($sut, 'enqueue_deferred_media_tools'), 10, 1);

		$result = $sut->enqueue_media($media_configs);
		$this->assertSame($sut, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_media_tools
	 */
	public function test_enqueue_media_defers_media_with_hook(): void {
		// Add permissive debug expectation to prevent BadMethodCallException while other tests are focused.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();
		$media_configs = array(array('args' => array(), 'hook' => 'admin_enqueue_scripts'));
		$sut           = $this->enqueue_instance_mocked_logger;

		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($sut, 'enqueue_deferred_media_tools'), 10, 1);

		$result = $sut->enqueue_media($media_configs);
		$this->assertSame($sut, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_processes_stored_media(): void {
		$sut                   = $this->enqueue_instance_mocked_logger;
		$hook_name             = 'admin_enqueue_scripts';
		$media_args            = array('post' => 789);
		$deferred_tool_configs = array(
			$hook_name => array(
				array('args' => $media_args, 'condition' => null)
			)
		);

		$reflector = new ReflectionClass(EnqueueAbstract::class);
		$property  = $reflector->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($sut, $deferred_tool_configs);

		// Restore detailed, ordered logger expectations.
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Entered for hook: \"{$hook_name}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Processing deferred media tool configuration at original index 0 for hook: \"{$hook_name}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Calling wp_enqueue_media\\(\\) for deferred configuration at original index 0 on hook \"{$hook_name}\"\. Args: .*{$media_args['post']}.*$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Exited for hook: \"{$hook_name}\"\.$/"))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media', array(
			'args'  => array($media_args),
			'times' => 1,
		));

		$sut->enqueue_deferred_media_tools_public($hook_name);

		// If get_logger() or the debug log expectation fails, the test will indicate it.

		$updated_deferred_configs = $property->getValue($sut);
		$this->assertArrayNotHasKey($hook_name, $updated_deferred_configs);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_skips_when_condition_is_false(): void {
		$sut                   = $this->enqueue_instance_mocked_logger;
		$hook_name             = 'custom_hook';
		$deferred_tool_configs = array(
			$hook_name => array(
				array('args' => array('test' => true), 'condition' => static fn() => false)
			)
		);

		$reflector = new ReflectionClass(EnqueueAbstract::class);
		$property  = $reflector->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($sut, $deferred_tool_configs);

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Entered for hook: \"{$hook_name}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Processing deferred media tool configuration at original index 0 for hook: \"{$hook_name}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Condition not met for deferred media tool configuration at original index 0 on hook \"{$hook_name}\"\. Skipping\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Exited for hook: \"{$hook_name}\"\.$/"))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media')->never();

		$result = $sut->enqueue_deferred_media_tools_public($hook_name);
		$this->assertSame($sut, $result);

		$updated_deferred_configs = $property->getValue($sut);
		$this->assertArrayNotHasKey($hook_name, $updated_deferred_configs);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_with_empty_hook_data(): void {
		$sut       = $this->enqueue_instance_mocked_logger;
		$hook_name = 'empty_hook';

		// Set deferred_media_tool_configs to an empty array for this hook or not set at all
		$reflector = new ReflectionClass(EnqueueAbstract::class);
		$property  = $reflector->getProperty('deferred_media_tool_configs');
		$property->setAccessible(true);
		$property->setValue($sut, array($hook_name => array())); // or just [] to test no key

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - Entered for hook: \"{$hook_name}\"\.$/"))->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern("/^EnqueueAbstract::enqueue_deferred_media_tools - No deferred media tool configurations found or already processed for hook: \"{$hook_name}\"\.$/"))->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_media')->never();

		$result = $sut->enqueue_deferred_media_tools_public($hook_name);
		$this->assertSame($sut, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_media_tool_configs
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_media
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_media
	 */
	public function test_get_media_tool_configs_returns_correct_structure_and_data(): void {
		$sut = $this->enqueue_instance_mocked_logger;

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
		$sut->add_media($general_configs_to_add);

		// Mock WP functions for enqueue_media
		WP_Mock::userFunction('has_action', array('return' => false)); // Assume no actions initially
		WP_Mock::expectActionAdded('custom_hook_1', array($sut, 'enqueue_deferred_media_tools'), 10, 1);
		WP_Mock::expectActionAdded('custom_hook_2', array($sut, 'enqueue_deferred_media_tools'), 10, 1);
		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($sut, 'enqueue_deferred_media_tools'), 10, 1);

		// Call enqueue_media to populate the internal $deferred_media_tool_configs
		$sut->enqueue_media($deferred_configs_source);

		// Get the configs
		$retrieved_configs = $sut->get_media_tool_configs();

		$this->assertIsArray($retrieved_configs);
		$this->assertArrayHasKey('general', $retrieved_configs);
		$this->assertArrayHasKey('deferred', $retrieved_configs);

		// Assert 'general' configs
		$this->assertEquals($general_configs_to_add, $retrieved_configs['general']);

		// Assert 'deferred' configs
		$this->assertEquals($expected_deferred_configs, $retrieved_configs['deferred']);
	}
}
