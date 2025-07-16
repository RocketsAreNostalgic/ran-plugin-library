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
	 * @dataProvider provide_style_tag_modification_cases
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_modify_style_tag_attributes
	 */
	public function test_modify_style_tag_attributes_adds_attributes_correctly(string $handle, array $attributes, string $original_tag, string $expected_tag, string $tag_handle = null): void {
		// Arrange
		// The test class uses a method that calls the protected method from the trait.
		// Act
		$tag_handle   = $tag_handle ?? $handle; // Use provided tag_handle or default to handle
		$modified_tag = $this->_invoke_protected_method(
			$this->instance,
			'_modify_style_tag_attributes',
			array(AssetType::Style, $original_tag, $tag_handle, $handle, $attributes)
		);

		// Assert
		$this->assertEquals($expected_tag, $modified_tag);
	}

	/**
	 * Data provider for `test_modify_style_tag_attributes_adds_attributes_correctly`.
	 */
	public static function provide_style_tag_modification_cases(): array {
		$handle                   = 'my-style';
		$original_tag             = "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all' />";
		$non_self_closing_tag     = "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'>";
		$malformed_tag            = "<link stylesheet {$handle} path/to/style.css>";
		$completely_malformed_tag = 'just some random text without any tag structure';
		$unclosed_link_tag        = "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'";

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
		    'handle mismatch' => array(
		        $handle,
		        array('data-custom' => 'value'),
		        $original_tag,
		        $original_tag, // Should return original tag unchanged
		        'different-handle', // Different tag_handle parameter
		    ),
		    'non self-closing tag' => array(
		        $handle,
		        array('data-custom' => 'value'),
		        $non_self_closing_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all' data-custom=\"value\">",
		    ),
		    'media attribute warning' => array(
		        $handle,
		        array('media' => 'print'), // Should be ignored with warning
		        $original_tag,
		        $original_tag, // No change expected
		    ),
		    'managed attribute warning' => array(
		        $handle,
		        array('href' => 'new-path.css', 'rel' => 'alternate', 'id' => 'new-id', 'type' => 'text/plain'),
		        $original_tag,
		        $original_tag, // No change expected as these are managed attributes
		    ),
		    'null and empty values' => array(
		        $handle,
		        array('data-null' => null, 'data-empty' => '', 'data-valid' => 'value'),
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  data-valid=\"value\"/>", // Only data-valid should be added
		    ),
		    'malformed tag' => array(
		        $handle,
		        array('data-custom' => 'value'),
		        $malformed_tag,
		        "<link stylesheet {$handle} path/to/style.css data-custom=\"value\">", // The method still adds attributes even for malformed tags
		    ),
		    'boolean attributes as array keys' => array(
		        $handle,
		        array('async' => true, 'defer' => true),
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  async defer/>",
		    ),
		    'indexed array for boolean attributes' => array(
		        $handle,
		        array('crossorigin', 'integrity'), // Indexed array entries become boolean attributes
		        $original_tag,
		        "<link rel='stylesheet' id='{$handle}-css' href='path/to/style.css' type='text/css' media='all'  crossorigin integrity/>",
		    ),
		    'completely malformed tag' => array(
		        $handle,
		        array('data-custom' => 'value'),
		        $completely_malformed_tag,
		        $completely_malformed_tag, // Should return original tag unchanged for completely malformed tags
		    ),
		    'unclosed link tag' => array(
		        $handle,
		        array('data-custom' => 'value'),
		        $unclosed_link_tag,
		        $unclosed_link_tag, // Should return original tag unchanged for malformed tags
		    ),
		);
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
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_adds_data_attributes_correctly(): void {
		$handle           = 'test-style-data';
		$hook_name        = 'wp_enqueue_scripts';
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'https://example.com/style.css',
			'data'   => array(
				'rtl'         => true,
				'conditional' => 'IE 9',
				'alternate'   => false,
			),
		);

		// Set up expectations for wp_style_add_data calls
		WP_Mock::userFunction('wp_style_add_data', array(
			'times'  => 1,
			'args'   => array($handle, 'rtl', true),
			'return' => true,
		));

		WP_Mock::userFunction('wp_style_add_data', array(
			'times'  => 1,
			'args'   => array($handle, 'conditional', 'IE 9'),
			'return' => true,
		));

		WP_Mock::userFunction('wp_style_add_data', array(
			'times'  => 1,
			'args'   => array($handle, 'alternate', false),
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Verify logger messages
		$this->expectLog('debug', array('_process_style_extras', "Adding data 'rtl' to style '{$handle}' on hook '{$hook_name}'"), 1);
		$this->expectLog('debug', array('_process_style_extras', "Adding data 'conditional' to style '{$handle}' on hook '{$hook_name}'"), 1);
		$this->expectLog('debug', array('_process_style_extras', "Adding data 'alternate' to style '{$handle}' on hook '{$hook_name}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_adds_inline_styles_with_different_positions(): void {
		$handle           = 'test-style-inline';
		$hook_name        = 'wp_enqueue_scripts';
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'https://example.com/style.css',
			'inline' => array(
				// First inline style with 'after' position (default)
				array(
					'content' => 'body { color: red; }',
				),
				// Second inline style with explicit 'before' position
				array(
					'content'  => 'html { font-size: 16px; }',
					'position' => 'before',
				),
			),
		);

		// Set up expectations for wp_add_inline_style calls
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, 'body { color: red; }', array('position' => 'after')),
			'return' => true,
		));

		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, 'html { font-size: 16px; }', array('position' => 'before')),
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Verify logger messages
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: after) on hook '{$hook_name}'"), 1);
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: before) on hook '{$hook_name}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_handles_single_inline_style_definition(): void {
		$handle           = 'test-style-single-inline';
		$hook_name        = 'wp_enqueue_scripts';
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'https://example.com/style.css',
			// Single inline style definition (not in an array)
			'inline' => array(
				'content'  => 'body { margin: 0; }',
				'position' => 'after',
			),
		);

		// Set up expectations for wp_add_inline_style calls
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, 'body { margin: 0; }', array('position' => 'after')),
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Verify logger messages
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: after) on hook '{$hook_name}'"), 1);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_respects_conditional_inline_styles(): void {
		$handle    = 'test-style';
		$hook_name = 'wp_enqueue_scripts';

		// Create an asset definition with conditional inline styles
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'inline' => array(
				// Regular inline style without condition
				array(
					'content' => 'body { background: white; }',
				),
				// Inline style with condition that evaluates to false
				array(
					'content'   => 'body { background: black; }',
					'condition' => function() {
						return false;
					},
				),
			),
		);

		// Set up expectations for wp_add_inline_style calls - only the first one should be called
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, 'body { background: white; }', array('position' => 'after')),
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Set up expectation for the debug log messages
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: after) on hook '{$hook_name}'"), 1);
		$this->expectLog('debug', array('_process_style_extras', "Condition for inline style on '{$handle}' not met. Skipping."), 1);

		// Assert that WP_Mock expectations were met (wp_add_inline_style was called exactly once)
		$this->assertConditionsMet();
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_enqueue_external_inline_styles
	 */
	public function test_enqueue_external_inline_styles_calls_base_method(): void {
		// Create a partial mock to spy on _enqueue_external_inline_assets
		$instance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Set expectation that _enqueue_external_inline_assets is called with AssetType::Style
		$instance->shouldReceive('_enqueue_external_inline_assets')
			->once()
			->with(AssetType::Style);

		// Call the method under test
		$instance->_enqueue_external_inline_styles();

		// Add explicit assertion to avoid PHPUnit marking the test as risky
		$this->assertTrue(true, 'Method should call _enqueue_external_inline_assets with AssetType::Style');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_calls_base_method(): void {
		$hook_name = 'wp_footer';
		$priority  = 10;

		// Create a partial mock to spy on _enqueue_deferred_assets
		$instance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Set expectation that _enqueue_deferred_assets is called with AssetType::Style
		$instance->shouldReceive('_enqueue_deferred_assets')
			->once()
			->with(AssetType::Style, $hook_name, $priority);

		// Call the method under test
		$instance->_enqueue_deferred_styles($hook_name, $priority);

		// Add explicit assertion to avoid PHPUnit marking the test as risky
		$this->assertTrue(true, 'Method should call _enqueue_deferred_assets with AssetType::Style');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_handles_empty_data(): void {
		$handle    = 'test-style-empty-data';
		$hook_name = 'wp_enqueue_scripts';

		// Create an asset definition with empty data
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'data'   => array(), // Empty data array
		);

		// No wp_style_add_data calls should be made
		WP_Mock::userFunction('wp_style_add_data', array(
			'times' => 0,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Assert that WP_Mock expectations were met (no wp_style_add_data calls)
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_handles_style_data_attributes(): void {
		$handle    = 'test-style-data';
		$hook_name = 'wp_enqueue_scripts';

		// Create an asset definition with data attributes
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'data'   => array(
				'rtl'         => true,
				'conditional' => 'IE 9',
			),
		);

		// Set up expectations for wp_style_add_data calls
		WP_Mock::userFunction('wp_style_add_data', array(
			'times'  => 2,
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Set up expectation for the debug log messages
		$this->expectLog('debug', array('_process_style_extras', "Adding data 'rtl' to style '{$handle}' on hook '{$hook_name}'"), 1);
		$this->expectLog('debug', array('_process_style_extras', "Adding data 'conditional' to style '{$handle}' on hook '{$hook_name}'"), 1);

		// Assert that WP_Mock expectations were met
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_handles_empty_inline_content(): void {
		$handle    = 'test-style-empty-inline';
		$hook_name = 'wp_enqueue_scripts';

		// Create an asset definition with empty inline content
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'inline' => array(
				'content'  => '', // Empty content
				'position' => 'after',
			),
		);

		// wp_add_inline_style should still be called even with empty content
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, '', array('position' => 'after')),
			'return' => true,
		));


		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Set up expectation for the debug log message
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: after) on hook '{$hook_name}'"), 1);

		// Assert that WP_Mock expectations were met
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_style_extras
	 */
	public function test_process_style_extras_with_no_hook_name(): void {
		$handle    = 'test-style-no-hook';
		$hook_name = null; // No hook name

		// Create an asset definition with inline styles
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'inline' => array(
				'content'  => 'body { color: red; }',
				'position' => 'after',
			),
		);

		// Set up expectations for wp_add_inline_style calls
		WP_Mock::userFunction('wp_add_inline_style', array(
			'times'  => 1,
			'args'   => array($handle, 'body { color: red; }', array('position' => 'after')),
			'return' => true,
		));

		// Call the protected method using reflection
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_style_extras');
		$method->setAccessible(true);
		$method->invokeArgs($this->instance, array($asset_definition, $handle, $hook_name));

		// Set up expectation for the debug log message - without hook context
		$this->expectLog('debug', array('_process_style_extras', "Adding inline style to '{$handle}' (position: after)"), 1);

		// Assert that WP_Mock expectations were met
		$this->assertConditionsMet();
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_handles_media_attribute(): void {
		// Create a test asset definition with media attribute
		$handle           = 'test-style-with-media';
		$asset_definition = array(			'handle' => $handle,
			'src'                                => 'path/to/style.css',
			'media'                              => 'print',
			'data'                               => array('key' => 'value'),
			'inline'                             => array(
				array('content' => 'body { color: red; }'),
			),
		);

		// Create a partial mock to spy on the concrete process method and style extras processing
		$instance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		// Set up expectations for the concrete process method
		$instance->shouldReceive('_concrete_process_single_asset')
			->once()
			->withArgs(function($asset_type, $def, $context, $hook, $register, $enqueue, $extra_args) use ($handle) {
				// Verify the asset type is correct
				if ($asset_type !== AssetType::Style) {
					return false;
				}

				// Verify the handle is passed correctly
				if ($def['handle'] !== $handle) {
					return false;
				}

				// Verify media attribute is passed in extra_args
				if (!isset($extra_args['media']) || $extra_args['media'] !== 'print') {
					return false;
				}

				return true;
			})
			->andReturn($handle); // Return the handle to indicate success

		// Set up expectations for style extras processing
		$instance->shouldReceive('_process_style_extras')
			->once()
			->withArgs(function($def, $h, $hook) use ($asset_definition, $handle) {
				return $def === $asset_definition && $h === $handle && $hook === null;
			});

		// Set up expectations for inline style processing
		$instance->shouldReceive('_process_inline_style_assets')
			->once()
			->withArgs(function($asset_type, $h, $hook, $context) use ($handle) {
				return $asset_type === AssetType::Style && $h === $handle && $hook === null && $context === 'immediate';
			});

		// Call the method under test
		$reflection = new \ReflectionClass($instance);
		$method     = $reflection->getMethod('_process_single_style_asset');
		$method->setAccessible(true);

		$result = $method->invokeArgs($instance, array(
			AssetType::Style,
			$asset_definition,
			'test',
			null, // hook_name
			true, // do_register
			true  // do_enqueue
		));

		// Verify the result is the handle, indicating success
		$this->assertEquals($handle, $result, 'Method should return the handle on success');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_with_incorrect_asset_type(): void {
		// Create a test asset definition
		$asset_definition = array(
			'handle' => 'test-style',
			'src'    => 'path/to/style.css',
		);

		// Create a partial mock
		$instance = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))
			->makePartial();

		// Call the method under test with incorrect asset type (Script instead of Style)
		$reflection = new \ReflectionClass($instance);
		$method     = $reflection->getMethod('_process_single_style_asset');
		$method->setAccessible(true);



		$result = $method->invokeArgs($instance, array(
			AssetType::Script, // Incorrect asset type
			$asset_definition,
			'test',
			null,
			true,
			true
		));

		// Set up logger expectation for the warning message
		$this->expectLog('warning', array('_process_single_', 'Incorrect asset type provided to _process_single_', "Expected 'style', got 'script'"), 1);

		// Verify the result is false, indicating failure
		$this->assertFalse($result, 'Method should return false when incorrect asset type is provided');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_with_attributes(): void {
		// Create a test asset definition with attributes
		$handle           = 'test-style-with-attributes';
		$asset_definition = array(
			'handle'     => $handle,
			'src'        => 'path/to/style.css',
			'attributes' => array(
				'data-test' => 'value',
				'integrity' => 'sha384-hash',
			),
		);

		// Use the instance from setUp() which already has protected methods mocking enabled
		// Set up expectations for the _do_add_filter method
		$this->instance->shouldReceive('_do_add_filter')
			->once()
			->with('style_loader_tag', Mockery::type('callable'), 10, 2);

		// Call the method under test
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_single_style_asset');
		$method->setAccessible(true);

		$result = $method->invokeArgs($this->instance, array(
			AssetType::Style,
			$asset_definition,
			'test',
			null,
			true,
			true
		));

		// Set up logger expectation for the debug message
		$this->expectLog('debug', array('StylesEnqueueTrait::_process_single_style_asset', "Adding attributes to style '{$handle}'"), 1);

		// Verify the result is the handle, indicating success
		$this->assertEquals($handle, $result, 'Method should return the handle on success');
	}

	/**
	 * Test that _modify_style_tag_attributes correctly handles incorrect asset type.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_modify_style_tag_attributes
	 */
	public function test_modify_style_tag_attributes_with_incorrect_asset_type(): void {
		$handle     = 'test-style';
		$tag        = '<link rel="stylesheet" id="test-style" href="https://example.com/style.css" />';
		$attributes = array('data-test' => 'value');

		// Create a reflection to access the protected method
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_modify_style_tag_attributes');
		$method->setAccessible(true);

		// Call the method with incorrect asset type (Script instead of Style)
		$result = $method->invokeArgs($this->instance, array(
			AssetType::Script, // Incorrect asset type
			$tag,
			$handle,
			$handle,
			$attributes
		));

		// Verify the result is the original tag (unchanged)
		$this->assertEquals($tag, $result, 'Method should return the original tag when incorrect asset type is provided');

		// Verify that a warning was logged
		$this->expectLog('warning', array('Incorrect asset type provided to _modify_style_tag_attributes', "Expected 'style', got 'script'"), 1);
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style_asset
	 */
	public function test_process_single_style_asset_with_inline_styles_and_extras(): void {
		// Create a test asset definition with inline styles and extras
		$handle           = 'test-style-with-inline-and-extras';
		$asset_definition = array(
			'handle' => $handle,
			'src'    => 'path/to/style.css',
			'inline' => array(
				'content'  => '.test { color: red; }',
				'position' => 'after',
			),
			'extras' => array(
				'custom_data' => 'value',
			),
		);

		// Set up expectations for the _concrete_process_single_asset method
		$this->instance->shouldReceive('_concrete_process_single_asset')
			->once()
			->with(
				AssetType::Style,
				$asset_definition,
				'test',
				null,
				true,
				true,
				array('media' => 'all')
			)
			->andReturn($handle);

		// Set up expectations for the _process_inline_style_assets method
		$this->instance->shouldReceive('_process_inline_style_assets')
			->once()
			->with(AssetType::Style, $handle, null, 'immediate')
			->andReturn(true);

		// Set up expectations for the _process_style_extras method
		$this->instance->shouldReceive('_process_style_extras')
			->once()
			->with($asset_definition, $handle, null)
			->andReturn(true);

		// Call the method under test
		$reflection = new \ReflectionClass($this->instance);
		$method     = $reflection->getMethod('_process_single_style_asset');
		$method->setAccessible(true);

		$result = $method->invokeArgs($this->instance, array(
			AssetType::Style,
			$asset_definition,
			'test',
			null,
			true,
			true
		));

		// Verify the result is the handle, indicating success
		$this->assertEquals($handle, $result, 'Method should return the handle on success');
	}
}
