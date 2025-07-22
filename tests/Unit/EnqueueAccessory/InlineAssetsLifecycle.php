<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use ReflectionProperty;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Class InlineAssetsLifecycleTest
 *
 * This test class specifically tests the complete lifecycle of inline assets,
 * from addition to processing and cleanup, for both styles and scripts.
 * It tests both pathways for adding inline assets:
 * 1. Via add() with inline property in the asset definition
 * 2. Via add_inline() after the parent asset has been registered
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait
 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 */
class InlineAssetsLifecycleTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/**
	 * @var ConcreteEnqueueForStylesTesting The styles instance for testing.
	 */
	protected $stylesInstance;

	/**
	 * @var ConcreteEnqueueForScriptsTesting The scripts instance for testing.
	 */
	protected $scriptsInstance;

	/**
	 * @var ConfigInterface|Mockery\MockInterface The mocked config.
	 */
	protected $config;

	/**
	 * @var CollectingLogger The logger instance.
	 */
	protected $logger;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a collecting logger for testing
		$this->logger = new CollectingLogger();

		// Mock the config
		$this->config = Mockery::mock(ConfigInterface::class);
		$this->config->shouldReceive('get_logger')->andReturn($this->logger);

		// Create instances for testing
		$this->stylesInstance  = new ConcreteEnqueueForStylesTesting($this->config);
		$this->scriptsInstance = new ConcreteEnqueueForScriptsTesting($this->config);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_enqueue_style')->withAnyArgs()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')->withAnyArgs()->andReturnNull();
		WP_Mock::userFunction('wp_style_add_data')->withAnyArgs()->andReturnNull();

		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturnNull();
		WP_Mock::userFunction('wp_script_add_data')->withAnyArgs()->andReturnNull();

		WP_Mock::userFunction('current_action')->andReturn('wp_enqueue_scripts');
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturnNull();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * Test the complete lifecycle of inline styles added via add() method.
	 * This tests:
	 * 1. Adding inline styles via the asset definition
	 * 2. Processing the inline styles when the parent asset is processed
	 * 3. Verifying the inline styles are properly cleaned up
	 */
	public function test_inline_styles_lifecycle_via_add_method(): void {
		// 1. Add a style with inline CSS via add() method
		$handle     = 'test-style';
		$src        = 'test-style.css';
		$deps       = array();
		$ver        = '1.0.0';
		$media      = 'all';
		$inline_css = '.test { color: red; }';

		$asset_definition = array(
		    'handle' => $handle,
		    'src'    => $src,
		    'deps'   => $deps,
		    'ver'    => $ver,
		    'media'  => $media,
		    'inline' => array(
		        'code'     => $inline_css,
		        'position' => 'after'
		    )
		);

		// Add the asset
		$this->stylesInstance->add($asset_definition);

		// Get the styles to verify the asset was added correctly
		$styles = $this->stylesInstance->get();

		// Verify the asset was added with inline CSS
		$this->assertArrayHasKey('assets', $styles);
		$this->assertCount(1, $styles['assets']);
		$this->assertEquals($handle, $styles['assets'][0]['handle']);
		$this->assertArrayHasKey('inline', $styles['assets'][0]);
		$this->assertCount(1, $styles['assets'][0]['inline']);
		$this->assertEquals($inline_css, $styles['assets'][0]['inline'][0]['content']);

		// 2. Process the asset and its inline CSS
		// Mock the WordPress function to verify it's called
		WP_Mock::userFunction('wp_add_inline_style')
		    ->with($handle, $inline_css, 'after')
		    ->once();

		// Process the asset
		$this->stylesInstance->enqueue();

		// 3. Verify the inline CSS was processed and cleaned up
		$styles = $this->stylesInstance->get();

		// The asset should still exist but the inline property should be empty
		$this->assertArrayHasKey('assets', $styles);
		$this->assertCount(1, $styles['assets']);
		$this->assertEquals($handle, $styles['assets'][0]['handle']);
		$this->assertArrayNotHasKey('inline', $styles['assets'][0]);

		// Verify expected log messages
		$this->expectLog('debug', "StylesEnqueueTrait::_process_immediate_inline_assets - Processing immediate inline style for handle '{$handle}'");
		$this->expectLog('debug', "StylesEnqueueTrait::_process_immediate_inline_assets - Added inline style for handle '{$handle}' with position 'after'");
	}

	/**
	 * Test the complete lifecycle of inline styles added via add_inline() method.
	 * This tests:
	 * 1. Adding a parent style
	 * 2. Adding inline styles via add_inline()
	 * 3. Processing the inline styles when the parent asset is processed
	 * 4. Verifying the inline styles are properly cleaned up
	 */
	public function test_inline_styles_lifecycle_via_add_inline_method(): void {
		// 1. Add a parent style
		$handle = 'test-style';
		$src    = 'test-style.css';
		$deps   = array();
		$ver    = '1.0.0';
		$media  = 'all';

		$asset_definition = array(
		    'handle' => $handle,
		    'src'    => $src,
		    'deps'   => $deps,
		    'ver'    => $ver,
		    'media'  => $media
		);

		// Add the parent asset
		$this->stylesInstance->add($asset_definition);

		// 2. Add inline CSS via add_inline()
		$inline_css = '.test { color: blue; }';
		$position   = 'after';

		$this->stylesInstance->add_inline(array(
		    'parent_handle' => $handle,
		    'content'       => $inline_css,
		    'position'      => $position
		));

		// Get the styles to verify the inline CSS was added correctly
		$styles = $this->stylesInstance->get();

		// Verify the inline CSS was added to the parent asset
		$this->assertArrayHasKey('assets', $styles);
		$this->assertCount(1, $styles['assets']);
		$this->assertEquals($handle, $styles['assets'][0]['handle']);
		$this->assertArrayHasKey('inline', $styles['assets'][0]);
		$this->assertCount(1, $styles['assets'][0]['inline']);
		$this->assertEquals($inline_css, $styles['assets'][0]['inline'][0]['content']);
		$this->assertEquals($position, $styles['assets'][0]['inline'][0]['position']);

		// 3. Process the asset and its inline CSS
		// Mock the WordPress function to verify it's called
		WP_Mock::userFunction('wp_add_inline_style')
		    ->with($handle, $inline_css, $position)
		    ->once();

		// Process the asset
		$this->stylesInstance->enqueue();

		// 4. Verify the inline CSS was processed and cleaned up
		$assets = $assets_property->getValue($this->stylesInstance);

		// The asset should still exist but the inline property should be removed
		$this->assertArrayHasKey($handle, $assets);
		$this->assertArrayNotHasKey('inline', $assets[$handle]);

		// Verify expected log messages
		$this->expectLog('debug', "StylesEnqueueTrait::_process_immediate_inline_assets - Processing immediate inline style for handle '{$handle}'");
		$this->expectLog('debug', "StylesEnqueueTrait::_process_immediate_inline_assets - Added inline style for handle '{$handle}' with position '{$position}'");
	}

	/**
	 * Test the complete lifecycle of deferred inline styles.
	 * This tests:
	 * 1. Adding a deferred parent style
	 * 2. Adding inline styles to the deferred parent
	 * 3. Processing the inline styles when the parent hook is fired
	 * 4. Verifying the inline styles are properly cleaned up
	 */
	public function test_deferred_inline_styles_lifecycle(): void {
		// 1. Add a deferred parent style
		$handle   = 'test-deferred-style';
		$src      = 'test-deferred-style.css';
		$deps     = array();
		$ver      = '1.0.0';
		$media    = 'all';
		$hook     = 'wp_enqueue_scripts';
		$priority = 20;

		$asset_definition = array(
		    'handle'   => $handle,
		    'src'      => $src,
		    'deps'     => $deps,
		    'ver'      => $ver,
		    'media'    => $media,
		    'hook'     => $hook,
		    'priority' => $priority,
		    'inline'   => array(
		        'code'     => '.deferred { color: green; }',
		        'position' => 'after'
		    )
		);

		// Add the deferred asset
		$this->stylesInstance->add($asset_definition);

		// Get the styles to verify the deferred asset was added correctly
		$styles = $this->stylesInstance->get();

		// Verify the deferred asset was added with inline CSS
		$this->assertArrayHasKey('deferred', $styles);
		$this->assertArrayHasKey($hook, $styles['deferred']);
		$this->assertArrayHasKey($priority, $styles['deferred'][$hook]);
		$this->assertCount(1, $styles['deferred'][$hook][$priority]);
		$this->assertEquals($handle, $styles['deferred'][$hook][$priority][0]['handle']);
		$this->assertArrayHasKey('inline', $styles['deferred'][$hook][$priority][0]);
		$this->assertCount(1, $styles['deferred'][$hook][$priority][0]['inline']);
		$this->assertEquals('.deferred { color: green; }', $styles['deferred'][$hook][$priority][0]['inline'][0]['content']);

		// 2. Mock the WordPress function to verify it's called when the hook fires
		WP_Mock::userFunction('wp_add_inline_style')
		    ->with($handle, '.deferred { color: green; }', 'after')
		    ->once();

		// 3. Simulate the hook being fired
		// First, we need to mock the _process_single_asset method to avoid complex setup
		$this->stylesInstance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config))
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();

		// Set up the deferred assets again
		$deferred_assets_property->setValue($this->stylesInstance, $deferred_assets);

		// Mock the _process_single_asset method to return the handle
		$this->stylesInstance->shouldReceive('_process_single_asset')
		    ->with(AssetType::Style, Mockery::type('array'), Mockery::type('string'), Mockery::any(), Mockery::any(), Mockery::any())
		    ->andReturn($handle);

		// Call the _process_deferred_assets method directly to simulate the hook firing
		$this->stylesInstance->_process_deferred_assets(AssetType::Style, $hook, $priority);

		// 4. Verify the deferred asset and its inline CSS were processed and cleaned up
		$deferred_assets = $deferred_assets_property->getValue($this->stylesInstance);

		// The hook and priority should still exist but be empty
		$this->assertArrayHasKey($hook, $deferred_assets);
		$this->assertArrayHasKey($priority, $deferred_assets[$hook]);
		$this->assertEmpty($deferred_assets[$hook][$priority]);
	}

	/**
	 * Test the complete lifecycle of external inline styles.
	 * This tests:
	 * 1. Adding external inline styles for a handle registered by WordPress/other plugins
	 * 2. Processing the inline styles when the specified hook is fired
	 * 3. Verifying the inline styles are properly cleaned up
	 */
	public function test_external_inline_styles_lifecycle(): void {
		// 1. Add external inline styles
		$handle     = 'external-style';
		$hook       = 'wp_enqueue_scripts';
		$inline_css = '.external { color: purple; }';
		$position   = 'after';

		// Add the external inline style
		$this->stylesInstance->add_inline(array(
		    'parent_handle' => $handle,
		    'content'       => $inline_css,
		    'position'      => $position,
		    'parent_hook'   => $hook
		));

		// Get the external_inline_assets property to verify the style was added correctly
		$external_inline_assets_property = new ReflectionProperty($this->stylesInstance, 'external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets = $external_inline_assets_property->getValue($this->stylesInstance);

		// Verify the external inline style was added correctly
		$this->assertArrayHasKey($hook, $external_inline_assets);
		$this->assertArrayHasKey($handle, $external_inline_assets[$hook]);
		$this->assertCount(1, $external_inline_assets[$hook][$handle]);
		$this->assertEquals($inline_css, $external_inline_assets[$hook][$handle][0]['content']);
		$this->assertEquals($position, $external_inline_assets[$hook][$handle][0]['position']);

		// 2. Mock the WordPress function to verify it's called when the hook fires
		WP_Mock::userFunction('wp_add_inline_style')
		    ->with($handle, $inline_css, $position)
		    ->once();

		// 3. Simulate the hook being fired by calling _enqueue_external_inline_styles
		$this->stylesInstance->_enqueue_external_inline_styles();

		// 4. Verify the external inline style was processed and cleaned up
		$external_inline_assets = $external_inline_assets_property->getValue($this->stylesInstance);

		// The hook should still exist but the handle should be removed
		$this->assertArrayHasKey($hook, $external_inline_assets);
		$this->assertArrayNotHasKey($handle, $external_inline_assets[$hook]);

		// Verify expected log messages
		$this->expectLog('debug', "enqueue_external_inline_styles - Fired on hook 'wp_enqueue_scripts'");
		$this->expectLog('debug', "StylesEnqueueTrait::_process_external_inline_assets - Processing external inline style for handle '{$handle}'");
		$this->expectLog('debug', "StylesEnqueueTrait::_process_external_inline_assets - Added inline style for handle '{$handle}' with position '{$position}'");
	}

	/**
	 * Test the complete lifecycle of inline scripts added via add() method.
	 * This tests:
	 * 1. Adding inline scripts via the asset definition
	 * 2. Processing the inline scripts when the parent asset is processed
	 * 3. Verifying the inline scripts are properly cleaned up
	 */
	public function test_inline_scripts_lifecycle_via_add_method(): void {
		// 1. Add a script with inline JS via add() method
		$handle    = 'test-script';
		$src       = 'test-script.js';
		$deps      = array();
		$ver       = '1.0.0';
		$in_footer = true;
		$inline_js = 'console.log("Hello World!");';

		$asset_definition = array(
		    'handle'    => $handle,
		    'src'       => $src,
		    'deps'      => $deps,
		    'ver'       => $ver,
		    'in_footer' => $in_footer,
		    'inline'    => array(
		        'code'     => $inline_js,
		        'position' => 'after'
		    )
		);

		// Add the asset
		$this->scriptsInstance->add($asset_definition);

		// Get the assets property to verify the asset was added correctly
		$assets_property = new ReflectionProperty($this->scriptsInstance, 'assets');
		$assets_property->setAccessible(true);
		$assets = $assets_property->getValue($this->scriptsInstance);

		// Verify the asset was added with inline JS
		$this->assertArrayHasKey($handle, $assets);
		$this->assertArrayHasKey('inline', $assets[$handle]);
		$this->assertEquals($inline_js, $assets[$handle]['inline']['code']);

		// 2. Process the asset and its inline JS
		// Mock the WordPress function to verify it's called
		WP_Mock::userFunction('wp_add_inline_script')
		    ->with($handle, $inline_js, 'after')
		    ->once();

		// Process the asset using reflection to access protected methods
		$reflection = new \ReflectionClass($this->scriptsInstance);
		$method     = $reflection->getMethod('_process_single_asset');
		$method->setAccessible(true);

		// Call the method with the asset definition
		$result = $method->invokeArgs($this->scriptsInstance, array(
		    AssetType::Script,
		    $asset_definition,
		    'test',
		    null,
		    true,
		    true,
		    array('in_footer' => true)
		));

		// 3. Verify the inline JS was processed and cleaned up
		$scripts = $this->scriptsInstance->get();

		// The asset should still exist but the inline property should be empty
		$this->assertArrayHasKey('assets', $scripts);
		$this->assertCount(1, $scripts['assets']);
		$this->assertEquals($handle, $scripts['assets'][0]['handle']);
		$this->assertArrayNotHasKey('inline', $scripts['assets'][0]);

		// Verify expected log messages
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_immediate_inline_assets - Processing immediate inline script for handle '{$handle}'");
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_immediate_inline_assets - Added inline script for handle '{$handle}' with position 'after'");
	}

	/**
	 * Test the complete lifecycle of external inline scripts.
	 * This tests:
	 * 1. Adding external inline scripts for a handle registered by WordPress/other plugins
	 * 2. Processing the inline scripts when the specified hook is fired
	 * 3. Verifying the inline scripts are properly cleaned up
	 */
	public function test_external_inline_scripts_lifecycle(): void {
		// 1. Add external inline scripts
		$handle    = 'external-script';
		$hook      = 'wp_enqueue_scripts';
		$inline_js = 'console.log("External Script");';
		$position  = 'before';

		// Add the external inline script
		$this->scriptsInstance->add_inline(array(
		    'parent_handle' => $handle,
		    'content'       => $inline_js,
		    'position'      => $position,
		    'parent_hook'   => $hook
		));

		// Get the external_inline_assets property to verify the script was added correctly
		$external_inline_assets_property = new ReflectionProperty($this->scriptsInstance, 'external_inline_assets');
		$external_inline_assets_property->setAccessible(true);
		$external_inline_assets = $external_inline_assets_property->getValue($this->scriptsInstance);

		// Verify the external inline script was added correctly
		$this->assertArrayHasKey($hook, $external_inline_assets);
		$this->assertArrayHasKey($handle, $external_inline_assets[$hook]);
		$this->assertCount(1, $external_inline_assets[$hook][$handle]);
		$this->assertEquals($inline_js, $external_inline_assets[$hook][$handle][0]['content']);
		$this->assertEquals($position, $external_inline_assets[$hook][$handle][0]['position']);

		// 2. Mock the WordPress function to verify it's called when the hook fires
		WP_Mock::userFunction('wp_add_inline_script')
		    ->with($handle, $inline_js, $position)
		    ->once();

		// 3. Simulate the hook being fired by calling _enqueue_external_inline_scripts
		$this->scriptsInstance->_enqueue_external_inline_scripts();

		// 4. Verify the external inline script was processed and cleaned up
		$external_inline_assets = $external_inline_assets_property->getValue($this->scriptsInstance);

		// The hook should still exist but the handle should be removed
		$this->assertArrayHasKey($hook, $external_inline_assets);
		$this->assertArrayNotHasKey($handle, $external_inline_assets[$hook]);

		// Verify expected log messages
		$this->expectLog('debug', "enqueue_external_inline_scripts - Fired on hook 'wp_enqueue_scripts'");
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_external_inline_assets - Processing external inline script for handle '{$handle}'");
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_external_inline_assets - Added inline script for handle '{$handle}' with position '{$position}'");
	}
}
