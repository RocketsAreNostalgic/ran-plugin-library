<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;

/**
 * Class EnqueuePublicTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic
 */
class EnqueuePublicTest extends PluginLibTestCase {
	/** @var EnqueuePublic */
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
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault();
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::__construct
	 */
	public function test_constructor_initializes_properly_on_public(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);

		// Act
		$this->instance = new EnqueuePublic($this->config_mock);

		// Assert
		$this->assertInstanceOf(EnqueuePublic::class, $this->instance);
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
		
		// Verify public logging occurred
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('On public page, proceeding to set up asset handlers.', $logs[0]['message']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::__construct
	 */
	public function test_constructor_bails_when_admin(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(true);

		// Act
		$this->instance = new EnqueuePublic($this->config_mock);

		// Assert - constructor should return early, handlers won't be initialized
		$this->assertInstanceOf(EnqueuePublic::class, $this->instance);
		
		// Verify debug logging occurred
		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('Not on public page, bailing.', $logs[0]['message']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::__construct
	 */
	public function test_constructor_accepts_custom_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);
		
		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$styles_mock  = Mockery::mock(StylesHandler::class);
		$media_mock   = Mockery::mock(MediaHandler::class);

		// Act
		$this->instance = new EnqueuePublic($this->config_mock, $scripts_mock, $styles_mock, $media_mock);

		// Assert
		$this->assertSame($scripts_mock, $this->instance->scripts());
		$this->assertSame($styles_mock, $this->instance->styles());
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::load
	 */
	public function test_load_hooks_stage_to_wp_enqueue_scripts(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);
		$this->instance = new EnqueuePublic($this->config_mock);

		// Expect
		WP_Mock::expectActionAdded('wp_enqueue_scripts', array($this->instance, 'stage'));

		// Act
		$this->instance->load();

		// Assert - WP_Mock will verify the expectation
		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::stage
	 */
	public function test_stage_delegates_to_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);
		
		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$scripts_mock->shouldReceive('stage')->once();
		
		$styles_mock = Mockery::mock(StylesHandler::class);
		$styles_mock->shouldReceive('stage')->once();

		$this->instance = new EnqueuePublic($this->config_mock, $scripts_mock, $styles_mock);

		// Act
		$this->instance->stage();

		// Assert - Mockery will verify the expectations
		$this->assertTrue(method_exists($this->instance, 'stage'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::styles
	 */
	public function test_accessor_methods_return_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);
		$this->instance = new EnqueuePublic($this->config_mock);

		// Act & Assert
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
	}
}
