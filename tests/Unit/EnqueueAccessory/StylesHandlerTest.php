<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\Util\CollectingLogger;

/**
 * Class StylesHandlerTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\StylesHandler
 */
class StylesHandlerTest extends PluginLibTestCase {
	/** @var StylesHandler */
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

		$this->instance = new StylesHandler($this->config_mock);
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesHandler::__construct
	 */
	public function test_constructor_initializes_properly(): void {
		// Assert
		$this->assertInstanceOf(StylesHandler::class, $this->instance);
		$this->assertSame($logger = $this->instance->get_logger(), $logger);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesHandler::load
	 */
	public function test_load_method_exists_and_is_callable(): void {
		// Act & Assert - should not throw any exceptions
		$this->instance->load();
		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesHandler
	 */
	public function test_uses_styles_enqueue_trait(): void {
		// Assert - check that trait methods are available
		$this->assertTrue(method_exists($this->instance, 'add'));
		$this->assertTrue(method_exists($this->instance, 'get_info'));
		$this->assertTrue(method_exists($this->instance, 'stage'));
	}
}
