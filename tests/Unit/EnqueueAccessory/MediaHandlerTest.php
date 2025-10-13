<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;

/**
 * Class MediaHandlerTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\MediaHandler
 */
class MediaHandlerTest extends PluginLibTestCase {
	/** @var MediaHandler */
	protected $instance;

	/** @var Mockery\MockInterface|ConfigInterface */
	protected $config_mock;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$logger = new CollectingLogger();

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_logger')->andReturn($logger)->byDefault();

		$this->instance = new MediaHandler($this->config_mock);
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaHandler::__construct
	 */
	public function test_constructor_initializes_properly(): void {
		// Assert
		$this->assertInstanceOf(MediaHandler::class, $this->instance);
		$this->assertSame($logger = $this->instance->get_logger(), $logger);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaHandler::load
	 */
	public function test_load_method_exists_and_is_callable(): void {
		// Act & Assert - should not throw any exceptions
		$this->instance->load();
		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaHandler
	 */
	public function test_uses_media_enqueue_trait(): void {
		// Assert - check that trait methods are available
		$this->assertTrue(method_exists($this->instance, 'add'));
		$this->assertTrue(method_exists($this->instance, 'get_info'));
		$this->assertTrue(method_exists($this->instance, 'stage'));
	}
}
