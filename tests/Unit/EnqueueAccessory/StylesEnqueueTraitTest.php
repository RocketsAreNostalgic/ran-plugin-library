<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\EnqueueAssetBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use InvalidArgumentException;
use WP_Mock;
use Mockery;

/**
 * Concrete implementation of StylesEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForStylesTesting extends EnqueueAssetBaseAbstract {
	use StylesEnqueueTrait;

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

	protected function _process_inline_assets(AssetType $asset_type, string $parent_handle, ?string $hook_name, string $processing_context): void {
		$this->_process_inline_style_assets($asset_type, $parent_handle, $hook_name, $processing_context);
	}

	protected function _modify_html_tag_attributes(AssetType $asset_type, string $tag, string $tag_handle, string $handle_to_match, array $attributes_to_apply): string {
		return $this->_modify_style_tag_attributes($asset_type, $tag, $tag_handle, $handle_to_match, $attributes_to_apply);
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
	 * Invokes a protected method on an object.
	 *
	 * @param object $object The object to call the method on.
	 * @param string $method_name The name of the method to call.
	 * @param array $parameters An array of parameters to pass to the method.
	 *
	 * @return mixed The return value of the method.
	 * @throws \ReflectionException If the method does not exist.
	 */
	protected function _invoke_protected_method($object, string $method_name, array $parameters = array()) {
		$reflection = new \ReflectionClass(get_class($object));
		$method     = $reflection->getMethod($method_name);
		$method->setAccessible(true);

		return $method->invokeArgs($object, $parameters);
	}

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

		// Configure the config mock to return the logger instance used by the test suite.
		$this->config_mock->method('get_logger')->willReturn($this->logger_mock);

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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_add_inline_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::add_assets
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_enqueue_deferred_assets
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_inline_assets
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
		$this->instance->enqueue_deferred_styles( 'wp_enqueue_scripts' );

		// Assert: Mockery's tearDown will verify all `once()` expectations.
		$this->assertTrue( true, 'This assertion ensures the test runs and passes if mocks are met.' );
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::stage_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_immediate_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAssetTraitBase::_process_single_asset
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
}
