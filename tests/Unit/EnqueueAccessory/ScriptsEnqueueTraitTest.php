<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase;
use Ran\PluginLib\EnqueueAccessory\EnqueueAssetBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use InvalidArgumentException;
use WP_Mock;
use Mockery;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForScriptsTesting extends EnqueueAssetBaseAbstract {
	public function __construct(ConfigInterface $config, Logger $logger) {
		parent::__construct($config);
		$this->config = $config;
		$this->logger = $logger;
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	public function get_logger(): Logger {
		return $this->logger;
	}

	// Mocked implementation for trait's dependency.
	protected function _get_wp_script_attributes(string $handle): array {
		return array();
	}

	// Mocked implementation for trait's dependency.
	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
	}

	// Expose protected property for testing
	public function get_internal_inline_assets_array(): array {
		return $this->get_inline_scripts();
	}

	protected function _get_asset_type(): string {
		return 'script';
	}
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class ScriptsEnqueueTraitTest extends PluginLibTestCase {
	/**
	 * @var (ConcreteEnqueueForScriptsTesting&Mockery\MockInterface)|Mockery\LegacyMockInterface
	 */
	protected $instance;

	/**
	 * @var Logger|MockInterface
	 */
	protected ?\Mockery\MockInterface $logger_mock = null;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->logger_mock = Mockery::mock(Logger::class);
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$config_mock = Mockery::mock(\Ran\PluginLib\Config\ConfigInterface::class);
		$config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock);

		$this->instance = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($config_mock, $this->logger_mock))->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();

		// Default WP_Mock function mocks for asset functions
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_add_data')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();
		// Tests that need `wp_script_is` should mock it directly.
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static fn($data) => json_encode($data),
		))->byDefault();
		WP_Mock::userFunction('esc_attr', array(
			'return' => static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'),
		))->byDefault();

		// Mock has_action to control its return value for specific tests
		WP_Mock::userFunction('has_action')
			->with(Mockery::any(), Mockery::any())
			->andReturnUsing(function ($hook, $callback) {
				// Default behavior: no action exists.
				// Tests can add more specific expectations.
				return false;
			})
			->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();

		// Reset protected properties to ensure a clean state for each test.
		$reflection = new \ReflectionObject($this->instance);

		$props_to_reset = ['assets', 'inline_assets', 'deferred_assets'];

		foreach ($props_to_reset as $prop_name) {
			if ($reflection->hasProperty($prop_name)) {
				$property = $reflection->getProperty($prop_name);
				$property->setAccessible(true);
				$property->setValue($this->instance, []);
			}
		}
	}

	// ------------------------------------------------------------------------
	// Test Methods for Script Functionalities
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 */
	public function test_add_scripts_handles_empty_input_gracefully(): void {
		// --- Test Setup ---
		// Logger expectations for add_scripts() with an empty array.
		$this->expectLog('debug', array('add_', 'Entered with empty array'));

		// Call the method under test
		$this->instance->add_scripts(array());

		// Retrieve and check stored assets
		$retrieved_assets = $this->instance->get_scripts()['general'];
		$this->assertCount(0, $retrieved_assets, 'The assets queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\Util\Logger::debug
	 */
	public function test_add_scripts_should_store_assets_correctly(): void {
		// --- Test Setup ---
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$assets_to_add = array(
			array(
				'handle'    => 'my-asset-1',
				'src'       => 'path/to/my-asset-1.js',
				'deps'      => array('jquery-ui-asset'),
				'version'   => '1.0.0',
				'media'     => 'screen',
				'condition' => static fn() => true,
			),
			array(
				'handle'  => 'my-asset-2',
				'src'     => 'path/to/my-asset-2.js',
				'deps'    => array(),
				'version' => false, // Use plugin version
				'media'   => 'all',
				// No condition, should default to true
			),
		);
		$group_name = 'add_scripts';

		// Logger expectations for EnqueueAssetTraitBase::add_assets() via ScriptsEnqueueTrait.
		$this->expectLog('debug', array('add_scripts', 'Entered', 'Current scripts count: 0', 'Adding 2 new scripts(s)'), 1, $group_name);
		$this->expectLog('debug', array('add_scripts', 'Adding scripts', 'Key: 0, Handle: my-asset-1, src: path/to/my-asset-1.js'), 1, $group_name);
		$this->expectLog('debug', array('add_scripts', 'Adding scripts', 'Key: 1, Handle: my-asset-2, src: path/to/my-asset-2.js'), 1, $group_name);
		$this->expectLog('debug', array('add_scripts', 'Adding 2 scripts definition(s)', 'Current total: 0'), 1, $group_name);
		$this->expectLog('debug', array('add_scripts', 'Exiting', 'New total scripts count: 2'), 1, $group_name);
		$this->expectLog('debug', array('add_scripts', 'All current scripts handles', 'my-asset-1, my-asset-2'), 1, $group_name);

		// Call the method under test
		$result = $this->instance->add_scripts($assets_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result,
			'add_scripts() should be chainable and return an instance of the class.'
		);

		// get the results of get_scripts() and check that it contains the assets we added
		$scripts = $this->instance->get_scripts();
		$this->assertArrayHasKey('general', $scripts);
		$this->assertArrayHasKey('deferred', $scripts);
		$this->assertArrayHasKey('inline', $scripts);
		$this->assertEquals('my-asset-1', $scripts['general'][0]['handle']);

	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::register_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_asset
	 */
	public function test_register_assets_registers_non_hooked_asset_correctly(): void {
		// --- Test Setup ---
		$asset_to_add = array(
			'handle'    => 'my-asset',
			'src'       => 'path/to/my-asset.js',
			'deps'      => array(),
			'version'   => '1.0',
			'in_footer' => false, // Correct attribute for scripts
		);
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$group_name = 'test_register_assets_registers_non_hooked_asset_correctly';

		// --- Logger Mocks for add_scripts() ---
		$this->expectLog('debug', array('add_', 'Entered', 'Current', 'count: 0', 'Adding 1 new'), 1, $group_name, true);
		$this->expectLog('debug', array('add_', 'Adding', 'Key: 0', 'Handle: my-asset', 'src: path/to/my-asset.js'), 1, $group_name);
		$this->expectLog('debug', array('add_', 'Adding 1', 'definition(s)', 'Current total: 0'), 1, $group_name);
		$this->expectLog('debug', array('add_', 'Exiting', 'count: 1'), 1, $group_name);
		$this->expectLog('debug', array('add_', 'All current scripts handles: my-asset'), 1, $group_name);

		$this->instance->add_scripts($asset_to_add);

		// --- WP_Mock and Logger Mocks for register_assets() ---
		// $this->expectLog('debug', array('register_assets', 'Entered', 'Processing 1'), 1, $group_name);
		// $this->expectLog('debug', array('Processing scripts: "my-asset"', 'original index: 0'), 1, $group_name);

		// Use the helper to mock WP functions for the asset lifecycle.
		$this->_mock_asset_lifecycle_functions(
			'script',
			'wp_register_script',
			'wp_enqueue_script',
			'wp_script_is',
			$asset_to_add
		);

		// $this->expectLog('debug', array('_process_single_asset', 'Registering', 'my-asset'), 1, $group_name);

		// Note: The call to wp_register_script is now mocked by _mock_asset_lifecycle_functions.
		// It sets up `wp_script_is` to return false then true, and `wp_register_script` to return true.

		// Mocks for inline asset processing
		// $this->expectLog('debug', array('_process_inline_', 'Checking for inline', 'my-asset'), 1, $group_name);
		// $this->expectLog('debug', array('_process_inline_', 'No inline assets found', 'my-asset'), 1, $group_name);
		// $this->expectLog('debug', array('_process_single_asset', 'Finished processing', 'my-asset'), 1, $group_name);
		// $this->expectLog('debug', array('register_', '- Exited', 'Remaining immediate', ': 1'), 1, $group_name);

		// Call the method under test
		$this->instance->register_scripts();

		// // Assert that the asset has been removed from the queue after registration.
		// $reflection = new \ReflectionClass(ConcreteEnqueueForScriptsTesting::class);
		// $assets_prop = $reflection->getProperty('assets');
		// $assets_prop->setAccessible(true);
		// $stored_assets = $assets_prop->getValue($this->instance);

		// $this->assertArrayHasKey('script', $stored_assets, 'The `scripts` key should still exist in the assets property.');
		// $this->assertCount(0, $stored_assets['script'], 'The scripts queue should be empty after registration.');

		// get the results of get_scripts() and check that it contains the assets we added
		$scripts = $this->instance->get_scripts();

		print_r($scripts);
		$this->assertArrayHasKey('general', $scripts);
		// $this->assertArrayHasKey('deferred', $scripts);
		// $this->assertArrayHasKey('inline', $scripts);
		// $this->assertEquals('my-asset', $scripts['general'][0]['handle']);
	}
}
