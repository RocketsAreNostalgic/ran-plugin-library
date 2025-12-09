<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\EnqueueAccessory\ScriptModulesHandler;

/**
 * Class EnqueuePublicTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic
 */
class EnqueuePublicTest extends PluginLibTestCase {
	use ExpectLogTrait;
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

		$this->logger      = new CollectingLogger();
		$this->logger_mock = $this->logger;

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_logger')->andReturn($this->logger)->byDefault();
		$this->config_mock->shouldReceive('get_config')->andReturn(
			array(
			    'URL'     => 'https://example.com/wp-content/plugins/ran-plugin-lib',
			    'Version' => '1.0.0',
			)
		)->byDefault();

		// Mock WordPress functions
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault();
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturnNull()->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
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
		$this->assertInstanceOf(ScriptModulesHandler::class, $this->instance->script_modules());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());

		$this->expectLog('debug', 'On public page, proceeding to set up asset handlers.');
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

		$this->expectLog('debug', 'Not on public page, bailing.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::__construct
	 */
	public function test_constructor_accepts_custom_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);

		$scripts_mock        = Mockery::mock(ScriptsHandler::class);
		$script_modules_mock = Mockery::mock(ScriptModulesHandler::class);
		$styles_mock         = Mockery::mock(StylesHandler::class);
		$media_mock          = Mockery::mock(MediaHandler::class);

		// Act
		$this->instance = new EnqueuePublic($this->config_mock, $scripts_mock, $script_modules_mock, $styles_mock, $media_mock);

		// Assert
		$this->assertSame($scripts_mock, $this->instance->scripts());
		$this->assertSame($script_modules_mock, $this->instance->script_modules());
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
	public function test_stage_delegates_and_stages_owned_media_assets(): void {
		WP_Mock::userFunction('is_admin')->andReturn(false);

		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$scripts_mock->shouldReceive('stage')->once();
		$scripts_mock->shouldReceive('add')->once()->with(Mockery::type('array'))->andReturnSelf();

		$script_modules_mock = Mockery::mock(ScriptModulesHandler::class);
		$script_modules_mock->shouldReceive('stage')->once();

		$styles_mock = Mockery::mock(StylesHandler::class);
		$styles_mock->shouldReceive('stage')->once();

		$this->instance = new EnqueuePublic($this->config_mock, $scripts_mock, $script_modules_mock, $styles_mock);

		$media_mock = Mockery::mock(MediaHandler::class);
		$media_mock->shouldReceive('get_info')->once()->andReturn(array('assets' => array(array('handle' => 'asset-one'))));
		$media_mock->shouldReceive('stage')->once()->with(array(array('handle' => 'asset-one')));

		$this->_set_protected_property_value($this->instance, 'media_handler', $media_mock);
		$this->_set_protected_property_value($this->instance, 'owns_media_handler', true);

		$this->instance->stage();

		$this->assertTrue(method_exists($this->instance, 'stage'));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::script_modules
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic::media
	 */
	public function test_accessor_methods_return_handlers(): void {
		// Arrange
		WP_Mock::userFunction('is_admin')->andReturn(false);
		$this->instance = new EnqueuePublic($this->config_mock);

		// Act & Assert
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(ScriptModulesHandler::class, $this->instance->script_modules());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
		$this->assertInstanceOf(MediaHandler::class, $this->instance->media());
	}
}
