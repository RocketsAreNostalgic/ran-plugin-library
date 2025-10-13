<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;
use Ran\PluginLib\EnqueueAccessory\StylesHandler;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;
use Ran\PluginLib\EnqueueAccessory\ScriptModulesHandler;

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
		$this->config_mock->shouldReceive('get_config')->andReturn(
			array(
				'URL'     => 'https://example.com/wp-content/plugins/ran-plugin-lib',
				'Version' => '1.0.0',
			)
		)->byDefault();

		// Mock WordPress functions
		WP_Mock::userFunction('is_admin')->andReturn(true)->byDefault();
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
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
		*/
	public function test_constructor_initializes_properly_in_admin(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);

		$this->instance = new EnqueueAdmin($this->config_mock);

		$this->assertInstanceOf(EnqueueAdmin::class, $this->instance);
		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(ScriptModulesHandler::class, $this->instance->script_modules());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
		$this->assertInstanceOf(MediaHandler::class, $this->instance->media());

		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('On admin page, proceeding to set up asset handlers.', $logs[0]['message']);
	}

	/**
		* @test
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
		*/
	public function test_constructor_bails_when_not_admin(): void {
		WP_Mock::userFunction('is_admin')->andReturn(false);

		$this->instance = new EnqueueAdmin($this->config_mock);

		$this->assertInstanceOf(EnqueueAdmin::class, $this->instance);

		$logs = $this->logger->get_logs();
		$this->assertNotEmpty($logs);
		$this->assertStringContainsString('Not on admin page, bailing.', $logs[0]['message']);
	}

	/**
		* @test
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::__construct
		*/
	public function test_constructor_accepts_custom_handlers(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);

		$scripts_mock        = Mockery::mock(ScriptsHandler::class);
		$script_modules_mock = Mockery::mock(ScriptModulesHandler::class);
		$styles_mock         = Mockery::mock(StylesHandler::class);
		$media_mock          = Mockery::mock(MediaHandler::class);

		$this->instance = new EnqueueAdmin($this->config_mock, $scripts_mock, $script_modules_mock, $styles_mock, $media_mock);

		$this->assertSame($scripts_mock, $this->instance->scripts());
		$this->assertSame($script_modules_mock, $this->instance->script_modules());
		$this->assertSame($styles_mock, $this->instance->styles());
		$this->assertSame($media_mock, $this->instance->media());
	}

	/**
		* @test
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::load
		*/
	public function test_load_hooks_stage_to_admin_enqueue_scripts(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);
		$this->instance = new EnqueueAdmin($this->config_mock);

		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'stage'));

		$this->instance->load();

		$this->assertTrue(method_exists($this->instance, 'load'));
	}

	/**
		* @test
		*/
	public function test_stage_delegates_to_handlers(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);

		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$scripts_mock->shouldReceive('stage')->once();

		$script_modules_mock = Mockery::mock(ScriptModulesHandler::class);
		$script_modules_mock->shouldReceive('stage')->once();

		$styles_mock = Mockery::mock(StylesHandler::class);
		$styles_mock->shouldReceive('stage')->once();

		$media_mock = Mockery::mock(MediaHandler::class);
		$media_mock->shouldReceive('stage')->never();

		$this->instance = new EnqueueAdmin($this->config_mock, $scripts_mock, $script_modules_mock, $styles_mock, $media_mock);

		$this->instance->stage();

		$this->assertTrue(method_exists($this->instance, 'stage'));
	}

	/**
		* @test
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::stage
		*/
	public function test_stage_stages_owned_media_assets(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);

		$scripts_mock = Mockery::mock(ScriptsHandler::class);
		$scripts_mock->shouldReceive('stage')->once();
		$scripts_mock->shouldReceive('add')->once()->with(Mockery::on(static function ($assets): bool {
			if (!is_array($assets) || count($assets) !== 1) {
				return false;
			}
			$asset = $assets[0] ?? array();
			return isset($asset['handle'], $asset['src'], $asset['deps'], $asset['hook'])
				&& $asset['handle'] === 'ran-forms-media-picker'
				&& $asset['hook']   === 'admin_enqueue_scripts';
		}));

		$script_modules_mock = Mockery::mock(ScriptModulesHandler::class);
		$script_modules_mock->shouldReceive('stage')->once();

		$styles_mock = Mockery::mock(StylesHandler::class);
		$styles_mock->shouldReceive('stage')->once();

		$this->instance = new EnqueueAdmin($this->config_mock, $scripts_mock, $script_modules_mock, $styles_mock);

		$media_mock = Mockery::mock(MediaHandler::class);
		$media_mock->shouldReceive('get_info')->once()->andReturn(array('assets' => array(array('handle' => 'admin-asset'))));
		$media_mock->shouldReceive('stage')->once()->with(array(array('handle' => 'admin-asset')));

		$this->_set_protected_property_value($this->instance, 'media_handler', $media_mock);
		$this->_set_protected_property_value($this->instance, 'owns_media_handler', true);

		$this->instance->stage();

		$this->assertTrue(method_exists($this->instance, 'stage'));
	}

	/**
		* @test
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::scripts
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::script_modules
		* @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin::styles
		*/
	public function test_accessor_methods_return_handlers(): void {
		WP_Mock::userFunction('is_admin')->andReturn(true);
		$this->instance = new EnqueueAdmin($this->config_mock);

		$this->assertInstanceOf(ScriptsHandler::class, $this->instance->scripts());
		$this->assertInstanceOf(ScriptModulesHandler::class, $this->instance->script_modules());
		$this->assertInstanceOf(StylesHandler::class, $this->instance->styles());
	}
}
