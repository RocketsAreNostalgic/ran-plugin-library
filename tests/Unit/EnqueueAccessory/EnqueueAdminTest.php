<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;

/**
 * Class EnqueueAdminTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin
 */
class EnqueueAdminTest extends PluginLibTestCase {
	/** @var EnqueueAdmin */
	protected $instance;

	/** @var Mockery\MockInterface|ConfigInterface */
	protected $config_mock;

	/** @var CollectingLogger */
	protected $logger;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->logger = new CollectingLogger();

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_logger')->andReturn($this->logger)->byDefault();

		// Mock WordPress functions
		WP_Mock::userFunction('is_admin')->andReturn(true)->byDefault();
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturnNull()->byDefault();
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
	 */
	public function test_constructor_initializes_properly_in_admin(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);

		// Act
		$this->instance = new EnqueueAdmin($this->config_mock);

		// Assert
		$this->assertInstanceOf(EnqueueAdmin::class, $this->instance);
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
		
		// Verify admin logging occurred
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('On admin page, proceeding to set up asset handlers.', $logs[0]['message']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
	 */
	public function test_constructor_bails_when_not_admin(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);

		// Act
		$this->instance = new EnqueueAdmin($this->config_mock);

		// Assert - constructor should return early, handlers won't be initialized
		$this->assertInstanceOf(EnqueueAdmin::class, $this->instance);
		
		// Verify debug logging occurred
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('Not on admin page, bailing.', $logs[0]['message']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
	 */
	public function test_constructor_accepts_custom_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);
		
		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$styles_mock  = Mockery::mock(StylesHandler::class);
		$media_mock   = Mockery::mock(MediaHandler::class);

		// Act
		$this->instance = new EnqueueAdmin($this->config_mock, $scripts_mock, $styles_mock, $media_mock);

		// Assert
		$this->assertSame($scripts_mock, $this->instance->scripts());
		$this->assertSame($styles_mock, $this->instance->styles());
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::load
	 */
	public function test_load_hooks_stage_to_admin_enqueue_scripts(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);
		$this->instance = new EnqueueAdmin($this->config_mock);

		// Expect
		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'stage'));

		// Act
		$this->instance->load();

		// Assert - WP_Mock will verify the expectation
		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::stage
	 */
	public function test_stage_delegates_to_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);
		
		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$scripts_mock->shouldReceive('stage')->once();
		
		$styles_mock = Mockery::mock(StylesHandler::class);
		$styles_mock->shouldReceive('stage')->once();

		$this->instance = new EnqueueAdmin($this->config_mock, $scripts_mock, $styles_mock);

		// Act
		$this->instance->stage();

		// Assert - Mockery will verify the expectations
		$this->assertTrue(method_exists($this->instance, 'stage'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::styles
	 */
	public function test_accessor_methods_return_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);
		$this->instance = new EnqueueAdmin($this->config_mock);

		// Act & Assert
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
	}
}
