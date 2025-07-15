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

	public function get_asset_url(string $path, ?AssetType $asset_type = null): ?string {
		return 'https://example.com/' . $path;
	}

	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
		add_action($hook, $callback, $priority, $accepted_args);
	}

	// Helper to trigger the hook for testing purposes.
	public function trigger_hooks(): void {
		WP_Mock::onAction('wp_enqueue_scripts')->execute();
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

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up the config mock like in ScriptsEnqueueTraitTest
		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_is_dev_callback')->andReturn(null)->byDefault();
		$this->config_mock->shouldReceive('is_dev_environment')->andReturn(false)->byDefault();

		$this->logger_mock = new CollectingLogger();
		$this->config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		// Create a partial mock for the class under test.
		// This allows us to mock protected methods like _file_exists and _md5_file.
		$this->instance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Mock the get_asset_url method to return the source by default
		// This handles the null URL check we added in the StylesEnqueueTrait
		$this->instance->shouldReceive('get_asset_url')
			->withAnyArgs()
			->andReturnUsing(function($src, $type = null) {
				return $src;
			})
			->byDefault();

		$this->instance->shouldReceive('stage_styles')->passthru();

		// Ensure the mock instance uses our collecting logger.
		$this->instance->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->instance->shouldReceive('get_config')->andReturn($this->config_mock)->byDefault();

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

		// Tests that need `wp_json_encode` should mock it directly.
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static function($data) {
				return json_encode($data);
			},
		))->byDefault();

		// Tests that need `esc_attr` should mock it directly.
		WP_Mock::userFunction('esc_attr', array(
			'return' => static function($text) {
				return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
			},
		))->byDefault();

		// Tests that need `has_action` should mock it directly.
		WP_Mock::userFunction('has_action')
		    ->with(Mockery::any(), Mockery::any())
		    ->andReturnUsing(function ($hook, $callback) {
		    	return false;
		    })
		    ->byDefault();

		// Tests that need `esc_html` should mock it directly.
		WP_Mock::userFunction('esc_html', array(
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

	// ------------------------------------------------------------------------
	// Trait Specific Capability Tests
	// ------------------------------------------------------------------------

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


	// ------------------------------------------------------------------------
	// Foundational Capability Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait
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
		$result = $this->instance->add_styles(array());

		// Logger expectations for AssetEnqueueBaseTrait::add_assets() via ScriptsEnqueueTrait.
		$this->expectLog('debug', array('add_', 'Entered with empty array'));

		// Assert that the method returns the instance for chainability
		$this->assertSame($this->instance, $result);

		// Assert
		$styles = $this->instance->get_styles();
		$this->assertEmpty($styles['general']);
		$this->assertEmpty($styles['deferred']);
		$this->assertEmpty($styles['external_inline']);
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
	public function test_stage_styles_handles_source_less_asset_correctly(): void {
		// Arrange: Asset with 'src' => false is a valid 'meta-handle' for dependencies or inline styles.
		$handle       = 'my-sourceless-style';
		$asset_to_add = array(
		    'handle' => $handle,
		    'src'    => false, // Explicitly no source file
		    'deps'   => array('some-dependency'),
		);
		$this->instance->add_styles($asset_to_add);

		// Expect wp_register_style to be called with false for the src.
		WP_Mock::userFunction('wp_register_style')
		    ->once()
		    ->with($handle, false, array('some-dependency'), false, 'all')
		    ->andReturn(true);

		// Act
		$this->instance->stage_styles();

		// Assert: No warnings about missing src should be logged.
		foreach ($this->logger_mock->get_logs() as $log) {
			if (strtolower((string) $log['level']) === 'warning') {
				$this->assertStringNotContainsString('Invalid style definition. Missing handle or src', $log['message']);
			}
		}
		// Ensure the logger was actually called for other things, proving it was active.
		$has_debug_records = false;
		foreach ($this->logger_mock->get_logs() as $log) {
			if ($log['level'] === 'debug') {
				$has_debug_records = true;
				break;
			}
		}
		$this->assertTrue($has_debug_records, 'Logger should have debug records.');
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

	/**
	 * @dataProvider provideEnvironmentData
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_resolves_src_based_on_environment(
		bool $is_dev_environment,
		string $expected_src
	): void {
		// Mock the config to control is_dev_environment() return value
		$this->config_mock->shouldReceive('is_dev_environment')
			->andReturn($is_dev_environment);

		$asset_definition = array(
			'handle' => 'test-style',
			'src'    => array(
				'dev'  => 'http://example.com/style.css',
				'prod' => 'http://example.com/style.min.css',
			),
		);

		WP_Mock::userFunction('wp_register_style', array(
			'times'  => 1,
			'return' => true,
			'args'   => array( 'test-style', $expected_src, Mockery::any(), Mockery::any(), Mockery::any() ),
		));

		// Use the public API to add the style and trigger the processing hooks.
		$this->instance->add_styles( array( $asset_definition ) );
		$this->instance->stage_styles();

		// The assertion is implicitly handled by the mock expectation for wp_register_style.
		$this->expectLog('debug', array('_process_single_', 'Registering', 'test-style', $expected_src), 1);
	}

	public function provideEnvironmentData(): array {
		return array(
			'Development environment' => array(true, 'http://example.com/style.css'),
			'Production environment'  => array(false, 'http://example.com/style.min.css'),
		);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait::_resolve_environment_src
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_with_string_src_remains_unchanged(): void {
		$asset_definition = array(
			'handle' => 'test-style',
			'src'    => 'http://example.com/style.css',
		);

		// Ensure get_asset_url returns the original URL for this test
		$this->instance->shouldReceive('get_asset_url')
			->with('http://example.com/style.css', Mockery::type(AssetType::class))
			->andReturn('http://example.com/style.css')
			->once();

		// Mock wp_style_is calls for proper asset processing
		WP_Mock::userFunction('wp_style_is')->with('test-style', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with('test-style', 'enqueued')->andReturn(false);

		WP_Mock::userFunction('wp_register_style')
			->withArgs(array('test-style', 'http://example.com/style.css', Mockery::any(), Mockery::any(), Mockery::any()))
			->once()
			->andReturn(true);

		// Use the public API to add the style and trigger the processing hooks.
		$this->instance->add_styles( array( $asset_definition ) );
		$this->instance->stage_styles();

		// The assertion is implicitly handled by the mock expectation for wp_register_style.
		$this->assertTrue(true);
	}
}
