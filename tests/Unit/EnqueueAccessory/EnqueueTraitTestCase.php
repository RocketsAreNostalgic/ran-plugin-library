<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

/**
 * Class EnqueueTraitTestCase
 *
 * Provides a common setup for testing enqueue-related traits (Scripts and Styles).
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 */
abstract class EnqueueTraitTestCase extends PluginLibTestCase {
	/** @var MockInterface|mixed */
	protected $instance;

	/** @var MockInterface|ConfigInterface */
	protected $config_mock;
	/** @var Mockery|mixed */
	protected $hooks_manager_mock;

	/**
	 * Returns the fully qualified class name of the concrete class used for testing.
	 * e.g., ConcreteEnqueueForScriptsTesting::class
	 *
	 * @return string
	 */
	abstract protected function _get_concrete_class_name(): string;

	/**
	 * Returns the asset type slug ('script' or 'style').
	 *
	 * @return string
	 */
	abstract protected function _get_test_asset_type(): string;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_is_dev_callback')->andReturn(null)->byDefault();
		$this->config_mock->shouldReceive('is_dev_environment')->andReturn(false)->byDefault();

		$this->config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		$concrete_class_name = $this->_get_concrete_class_name();
		$this->instance      = Mockery::mock($concrete_class_name, array($this->config_mock))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$this->instance->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->instance->shouldReceive('get_config')->andReturn($this->config_mock)->byDefault();
		$this->hooks_manager_mock = Mockery::mock('Ran\PluginLib\HooksAccessory\HooksManager');
		$this->hooks_manager_mock->shouldReceive('register_action')->withAnyArgs()->andReturn(true)->byDefault();
		$this->hooks_manager_mock->shouldReceive('register_filter')->withAnyArgs()->andReturn(true)->byDefault();
		$this->instance->shouldReceive('get_hooks_manager')->andReturn($this->hooks_manager_mock)->byDefault();

		$this->instance->shouldReceive('get_asset_url')
			->withAnyArgs()
			->andReturnUsing(function($src) {
				return $src;
			})
			->byDefault();

		$asset_type = $this->_get_test_asset_type();
		$this->instance->shouldReceive('stage_{' . $asset_type . '}s')->passthru();

		// Default WP_Mock function mocks for asset functions
		WP_Mock::userFunction('wp_register_' . $asset_type)->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_add_inline_' . $asset_type)->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_' . $asset_type . '_is')->withAnyArgs()->andReturn(false)->byDefault();

		// Generic mocks shared by both test classes
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault();
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();

		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static function($data) {
				return json_encode($data);
			},
		))->byDefault();

		WP_Mock::userFunction('esc_attr', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();

		WP_Mock::userFunction('has_action')
			->with(Mockery::any(), Mockery::any())
			->andReturnUsing(function ($hook, $callback) {
				return false;
			})
			->byDefault();

		WP_Mock::userFunction('esc_html', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();
		WP_Mock::userFunction('esc_html', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();
	}

	/**
		* Expect an action registration via HooksManager.
		*/
	protected function expectAction(string $hook, int $priority = 10, int $times = 1, int $acceptedArgs = 1): void {
		$this->hooks_manager_mock
			->shouldReceive('register_action')
			->with($hook, Mockery::type('callable'), $priority, $acceptedArgs, Mockery::any())
			->times($times)
			->andReturn(true);
	}

	/**
		* Expect a filter registration via HooksManager.
		*/
	protected function expectFilter(string $hook, int $priority = 10, int $times = 1, int $acceptedArgs = 1): void {
		$this->hooks_manager_mock
			->shouldReceive('register_filter')
			->with($hook, Mockery::type('callable'), $priority, $acceptedArgs, Mockery::any())
			->times($times)
			->andReturn(true);
	}
}
