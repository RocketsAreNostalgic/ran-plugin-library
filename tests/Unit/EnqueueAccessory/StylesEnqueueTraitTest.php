<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use InvalidArgumentException;
use WP_Mock;
use Mockery;

/**
 * Concrete implementation of StylesEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForStylesTesting extends AssetEnqueueBaseAbstract {
	use StylesEnqueueTrait;

	protected array $registered_hooks = array();

	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	public function get_asset_url(string $path): string {
		return 'https://example.com/' . $path;
	}

	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
		add_action($hook, $callback, $priority, $accepted_args);
	}
}

/**
 * Class StylesEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait
 */
class StylesEnqueueTraitTest extends PluginLibTestCase {
	/**
	 * @var (ConcreteEnqueueForStylesTesting&Mockery\MockInterface)|Mockery\LegacyMockInterface
	 */
	protected $instance;

	/**
	 * @var CollectingLogger|null
	 */
	protected ?CollectingLogger $logger_mock = null;

	public function setUp(): void {
		parent::setUp();

		// Get a fully configured and registered mock instance from the parent test case.
		// This handles logger setup and all other necessary config dependencies.
		$this->config_mock = $this->get_and_register_concrete_config_instance();

		// Instantiate the test class with the configured mock.
		$this->instance = new ConcreteEnqueueForStylesTesting($this->config_mock);

		// Default WP_Mock function mocks for asset functions
		WP_Mock::userFunction('wp_register_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_style')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_style_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault();
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();

		WP_Mock::userFunction('wp_json_encode', array(
		    'return' => static fn($data) => json_encode($data),
		))->byDefault();

		WP_Mock::userFunction('esc_attr', array(
		    'return' => static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'),
		))->byDefault();

		WP_Mock::userFunction('has_action')
		    ->with(Mockery::any(), Mockery::any())
		    ->andReturnUsing(function ($hook, $callback) {
		    	return false;
		    })
		    ->byDefault();
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();

		$reflection     = new \ReflectionObject($this->instance);
		$props_to_reset = array('assets', 'deferred_assets', 'inline_assets');
		foreach ($props_to_reset as $prop_name) {
			if ($reflection->hasProperty($prop_name)) {
				$property = $reflection->getProperty($prop_name);
				$property->setAccessible(true);
				$property->setValue($this->instance, array('style' => array(), 'script' => array()));
			}
		}
	}

	/**
	 * @test
	 */
	public function test_trait_can_be_instantiated(): void {
		$this->assertInstanceOf(ConcreteEnqueueForStylesTesting::class, $this->instance);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_styles_handles_empty_input_gracefully(): void {
		// Act
		$this->instance->add_styles(array());

		// Assert
		$styles = $this->instance->get_styles();
		$this->assertEmpty($styles['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_styles_adds_asset_correctly(): void {
		// Arrange
		$asset_to_add = array(
			'handle' => 'my-asset',
			'src'    => 'path/to/my-asset.css',
		);

		// Act
		$this->instance->add_styles($asset_to_add);

		// Assert
		$styles = $this->instance->get_styles();
		$this->assertCount(1, $styles['general']);
		$this->assertEquals('my-asset', $styles['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_add_inline_asset
	 */
	public function test_add_inline_styles_associates_with_correct_parent_handle(): void {
		// First, add the parent asset
		$parent_asset = array(
		    'handle' => 'parent-style',
		    'src'    => 'path/to/parent.css',
		);
		$this->instance->add_styles($parent_asset);

		// Now, add the inline asset
		$inline_asset = array(
		    'parent_handle' => 'parent-style',
		    'content'       => '.my-class { color: red; }',
		);
		$this->instance->add_inline_styles($inline_asset);

		// Assert that the inline data was added to the parent asset
		$styles = $this->instance->get_styles();
		$this->assertCount(1, $styles['general']);
		$this->assertArrayHasKey('inline', $styles['general'][0]);
		$this->assertCount(1, $styles['general'][0]['inline']);
		$this->assertEquals('.my-class { color: red; }', $styles['general'][0]['inline'][0]['content']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
	 */
	public function test_stage_styles_passes_media_attribute_correctly(): void {
		// Arrange
		$handle       = 'my-style-with-media';
		$asset_to_add = array(
		    'handle' => $handle,
		    'src'    => 'path/to/style.css',
		    'media'  => 'print',
		);
		$this->instance->add_styles($asset_to_add);

		// Expect wp_register_style to be called with the 'print' media type.
		$expected_url = $this->instance->get_asset_url('path/to/style.css');
		WP_Mock::userFunction('wp_register_style')
		    ->once()
		    ->with($handle, $expected_url, array(), false, 'print');

		// Act
		$this->instance->stage_styles();

		// Assert: The mock expectation handles the validation. This assertion prevents a risky test warning.
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
	 */
	public function test_stage_styles_handles_source_less_asset_correctly(): void {
		// Arrange
		$handle       = 'my-sourceless-style';
		$asset_to_add = array(
		    'handle' => $handle,
		    'src'    => false, // Explicitly no source file
		    'deps'   => array('some-dependency'),
		);
		$this->instance->add_styles($asset_to_add);

		// Expect wp_register_style to be called with an empty src ('') and the specified deps.
		WP_Mock::userFunction('wp_register_style')
		    ->once()
		    ->with($handle, '', array('some-dependency'), false, 'all');

		// Act
		$this->instance->stage_styles();

		// Assert
		$styles = $this->instance->get_styles();
		$this->assertEmpty($styles['general'], 'The general queue should be empty after registration.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_styles_throws_exception_for_missing_src(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid style definition for handle 'my-style'. Asset must have a 'src' or 'src' must be explicitly set to false.");

		// Arrange
		$invalid_asset = array('handle' => 'my-style', 'src' => '');

		// Act
		$this->instance->add_styles(array($invalid_asset));
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::add_assets
	 */
	public function test_add_styles_throws_exception_for_missing_handle(): void {
		// Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid style definition at index 0. Asset must have a 'handle'.");

		// Arrange
		$invalid_asset = array('src' => 'path/to/style.css');

		// Act
		$this->instance->add_styles(array($invalid_asset));
	}


	/**
	 * @test
	 * @dataProvider provide_style_tag_modification_cases
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_modify_style_tag_attributes
	 */
	public function test_modify_style_tag_attributes_adds_attributes_correctly(string $handle, array $attributes, string $original_tag, string $expected_tag): void {
		// Arrange
		// The test class uses a method that calls the protected method from the trait.
		// Act
		$modified_tag = $this->_invoke_protected_method(
			$this->instance,
			'_modify_style_tag_attributes',
			array(AssetType::Style, $original_tag, $handle, $handle, $attributes)
		);

		// Assert
		$this->assertEquals($expected_tag, $modified_tag);
	}

	/**
	 * Data provider for `test_modify_style_tag_attributes_adds_attributes_correctly`.
	 */
	public static function provide_style_tag_modification_cases(): array {
		$handle       = 'my-style';
		$original_tag = "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all' />";

		return array(
		    'single data attribute' => array(
		        $handle,
		        array('data-custom' => 'my-value'),
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  data-custom=\"my-value\"/>",
		    ),
		    'boolean attribute (true)' => array(
		        $handle,
		        array('async' => true),
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  async/>",
		    ),
		    'boolean attribute (false)' => array(
		        $handle,
		        array('defer' => false),
		        $original_tag,
		        $original_tag, // Expect no change
		    ),
		    'multiple attributes' => array(
		        $handle,
		        array('data-id' => '123', 'async' => true),
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  data-id=\"123\" async/>",
		    ),
		);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_stage_styles_processes_deferred_style_correctly(): void {
		// Arrange
		$handle = 'my-deferred-style';
		$src    = 'path/to/style.css';
		$hook   = 'wp_enqueue_scripts';

		// Add a deferred style to the instance's queue.
		$this->instance->add_styles( array(
			'handle' => $handle,
			'src'    => $src,
			'hook'   => $hook,
		) );

		// Mock the WordPress API calls for a deferred asset lifecycle.
		WP_Mock::userFunction( 'current_action' )->withNoArgs()->andReturn( 'wp_enqueue_scripts' );
		WP_Mock::userFunction( 'did_action' )->with( 'wp_enqueue_scripts' )->andReturn( 1 );
		WP_Mock::userFunction( 'wp_style_is' )->with( $handle, 'registered' )->andReturn( false, true );
		WP_Mock::userFunction( 'wp_style_is' )->with( $handle, 'enqueued' )->andReturn( false );

		// Expect the register and enqueue sequence.
		$expected_url = $this->instance->get_asset_url( $src );
		WP_Mock::userFunction( 'wp_register_style' )->once()->with( $handle, $expected_url, array(), false, 'all' )->andReturn(true);
		WP_Mock::userFunction( 'wp_enqueue_style' )->once()->with( $handle );

		// Act: Simulate the WordPress hook lifecycle for a deferred asset.
		$this->instance->stage_styles();
		$this->instance->_enqueue_deferred_styles( 'wp_enqueue_scripts', 10 );

		// Assert: Mockery's tearDown will verify all `once()` expectations.
		$this->assertTrue( true, 'This assertion ensures the test runs and passes if mocks are met.' );
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_immediate_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_process_single_asset
	 */
	public function test_stage_styles_enqueues_registered_style(): void {
		// Arrange
		$handle       = 'my-style-to-enqueue';
		$asset_to_add = array(
		    'handle' => $handle,
		    'src'    => 'path/to/style.css',
		);
		$this->instance->add_styles($asset_to_add);
		$this->instance->stage_styles();

		// Expect wp_enqueue_style to be called.
		WP_Mock::userFunction('wp_enqueue_style')->once()->with($handle);

		// Act
		$this->instance->stage_styles();
		$this->instance->enqueue_immediate_styles();

		// Assert
		$styles = $this->instance->get_styles();
		$this->assertEmpty($styles['general'], 'The general queue should be empty after enqueuing.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::stage_assets
	 */
	public function test_stage_styles_defers_assets_with_multiple_priorities_correctly(): void {
		// Arrange
		$hook_name     = 'my_multi_priority_hook';
		$assets_to_add = array(
			array(
				'handle'   => 'asset-prio-10',
				'src'      => 'path/to/p10.css',
				'hook'     => $hook_name,
				'priority' => 10,
			),
			array(
				'handle'   => 'asset-prio-20',
				'src'      => 'path/to/p20.css',
				'hook'     => $hook_name,
				'priority' => 20,
			),
		);
		$this->instance->add_styles($assets_to_add);

		// Assert that add_action is called for each priority with a closure.
		WP_Mock::expectActionAdded($hook_name, Mockery::type('callable'), 10, 0);
		WP_Mock::expectActionAdded($hook_name, Mockery::type('callable'), 20, 0);

		// Act
		$this->instance->stage_styles();

		// Assert: Check logs for correct deferral messages.
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::stage_styles', 'Deferring registration', 'asset-prio-10', "to hook '{$hook_name}' with priority 10"), 1);
		$this->expectLog('debug', array('AssetEnqueueBaseTrait::stage_styles', 'Deferring registration', 'asset-prio-20', "to hook '{$hook_name}' with priority 20"), 1);

		// Assert that the assets are in the correct structure in the deferred queue.
		$styles = $this->instance->get_styles();
		$this->assertArrayHasKey($hook_name, $styles['deferred'], 'Hook key should exist in deferred assets.');
		$this->assertArrayHasKey(10, $styles['deferred'][$hook_name], 'Priority 10 key should exist.');
		$this->assertArrayHasKey(20, $styles['deferred'][$hook_name], 'Priority 20 key should exist.');

		$this->assertCount(1, $styles['deferred'][$hook_name][10]);
		$this->assertCount(1, $styles['deferred'][$hook_name][20]);

		$this->assertEquals('asset-prio-10', array_values($styles['deferred'][$hook_name][10])[0]['handle']);
		$this->assertEquals('asset-prio-20', array_values($styles['deferred'][$hook_name][20])[0]['handle']);

		// Assert that the main assets queue is empty as all assets were deferred.
		$this->assertEmpty($styles['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_enqueue_deferred_assets
	 */
	public function test_enqueue_deferred_styles_processes_assets_for_correct_priority(): void {
		// Arrange
		$hook_name     = 'my_multi_priority_hook';
		$assets_to_add = array(
			array(
				'handle'   => 'asset-prio-10',
				'src'      => 'path/to/p10.css',
				'hook'     => $hook_name,
				'priority' => 10,
			),
			array(
				'handle'   => 'asset-prio-20',
				'src'      => 'path/to/p20.css',
				'hook'     => $hook_name,
				'priority' => 20,
			),
		);
		$this->instance->add_styles($assets_to_add);
		$this->instance->stage_styles(); // This populates the deferred assets array

		// Mock wp_style_is calls for proper asset processing
		WP_Mock::userFunction('wp_style_is')->with('asset-prio-10', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with('asset-prio-10', 'enqueued')->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with('asset-prio-20', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with('asset-prio-20', 'enqueued')->andReturn(false);

		// Assert that only the priority 10 asset is enqueued
		WP_Mock::userFunction('wp_enqueue_style')->once()->with('asset-prio-10');
		WP_Mock::userFunction('wp_enqueue_style')->never()->with('asset-prio-20');

		// Act: Simulate the WordPress action firing for priority 10.
		$this->instance->_enqueue_deferred_styles($hook_name, 10);

		// Assert: Check logs for correct processing messages.
		$this->expectLog('debug', array('_enqueue_deferred_', 'Entered hook: "' . $hook_name . '" with priority: 10'), 1);
		$this->expectLog('debug', array('_enqueue_deferred_', "Processing deferred asset 'asset-prio-10'"), 1);

		// Assert that the priority 10 assets are gone, but priority 20 remains.
		$styles = $this->instance->get_styles();
		$this->assertArrayHasKey($hook_name, $styles['deferred'], 'Hook key should still exist.');
		$this->assertArrayNotHasKey(10, $styles['deferred'][$hook_name], 'Priority 10 key should be removed.');
		$this->assertArrayHasKey(20, $styles['deferred'][$hook_name], 'Priority 20 key should still exist.');
		$this->assertCount(1, $styles['deferred'][$hook_name][20]);
		$this->assertEquals('asset-prio-20', array_values($styles['deferred'][$hook_name][20])[0]['handle']);
	}
}
