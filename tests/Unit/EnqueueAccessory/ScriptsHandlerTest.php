<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\EnqueueAccessory\AssetType;

/**
 * Class ScriptsHandlerTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsHandler
 */
class ScriptsHandlerTest extends PluginLibTestCase {
	/** @var ScriptsHandler */
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
		$this->config_mock->shouldReceive('is_dev_environment')->andReturn(false)->byDefault();

		$this->instance = new ScriptsHandler($this->config_mock);

		// Default WP_Mock function mocks
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('current_action')->andReturn(null)->byDefault();
		WP_Mock::userFunction('esc_attr', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsHandler::__construct
	 */
	public function test_constructor_initializes_properly(): void {
		// Assert
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance);
		$this->assertSame($logger = $this->instance->get_logger(), $logger);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsHandler::load
	 */
	public function test_load_method_exists_and_is_callable(): void {
		// Act & Assert - should not throw any exceptions
		$this->instance->load();
		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsHandler
	 */
	public function test_uses_scripts_enqueue_trait(): void {
		// Assert - check that trait methods are available
		$this->assertTrue(method_exists($this->instance, 'add'));
		$this->assertTrue(method_exists($this->instance, 'get'));
		$this->assertTrue(method_exists($this->instance, 'stage'));
	}
}
