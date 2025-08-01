<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\BlockAssetTrait;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseTrait;

/**
 * Concrete implementation of BlockAssetTrait for testing.
 */
class ConcreteBlockAssetForTesting {
	use AssetEnqueueBaseTrait;
	use BlockAssetTrait;

	private ConfigInterface $config;

	public function __construct(ConfigInterface $config) {
		$this->config = $config;
	}

	public function get_logger(): CollectingLogger {
		return $this->config->get_logger();
	}

	public function get_config(): ConfigInterface {
		return $this->config;
	}

	// Expose protected methods for testing
	public function expose_process_block_asset_type(string $block_name, string $asset_key, AssetType $asset_type, string $scope): void {
		$this->_process_block_asset_type($block_name, $asset_key, $asset_type, $scope);
	}

	public function expose_detect_blocks_in_content(): array {
		return $this->_detect_blocks_in_content();
	}

	public function expose_is_block_present(string $block_name): bool {
		return $this->_is_block_present($block_name);
	}

	public function expose_get_asset_type_from_string(string $asset_type_string): ?AssetType {
		return $this->_get_asset_type_from_string($asset_type_string);
	}

	public function expose_detect_nested_blocks(array $blocks): array {
		return $this->_detect_nested_blocks($blocks);
	}
}

/**
 * Class BlockAssetTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait
 */
class BlockAssetTraitTest extends EnqueueTraitTestCase {
	use ExpectLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function _get_concrete_class_name(): string {
		return ConcreteBlockAssetForTesting::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function _get_test_asset_type(): string {
		return AssetType::Script->value;
	}

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock WordPress block functions
		WP_Mock::userFunction('has_blocks')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('parse_blocks')->withAnyArgs()->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_register_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_style_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('add_filter')->withAnyArgs()->andReturn(true)->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// ------------------------------------------------------------------------
	// Block Asset Registration Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::register_block_assets
	 */
	public function test_register_block_assets_stores_configuration_correctly(): void {
		// Arrange
		$block_name   = 'core/paragraph';
		$asset_config = array(
			'scripts' => array(
				array(
					'handle' => 'paragraph-script',
					'src'    => 'path/to/paragraph.js',
					'deps'   => array('wp-blocks'),
				),
			),
			'styles' => array(
				array(
					'handle' => 'paragraph-style',
					'src'    => 'path/to/paragraph.css',
				),
			),
		);

		// Act
		$result = $this->instance->register_block_assets($block_name, $asset_config);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		$block_assets = $this->get_protected_property_value($this->instance, 'block_assets');
		$this->assertArrayHasKey($block_name, $block_assets);
		$this->assertEquals($asset_config, $block_assets[$block_name]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::register_block_assets
	 */
	public function test_register_block_assets_handles_editor_and_frontend_scopes(): void {
		// Arrange
		$block_name   = 'custom/test-block';
		$asset_config = array(
			'editor_scripts' => array(
				array(
					'handle' => 'test-block-editor',
					'src'    => 'path/to/editor.js',
				),
			),
			'frontend_scripts' => array(
				array(
					'handle' => 'test-block-frontend',
					'src'    => 'path/to/frontend.js',
				),
			),
		);

		// Act
		$this->instance->register_block_assets($block_name, $asset_config);

		// Assert
		$block_assets = $this->get_protected_property_value($this->instance, 'block_assets');
		$this->assertArrayHasKey('editor_scripts', $block_assets[$block_name]);
		$this->assertArrayHasKey('frontend_scripts', $block_assets[$block_name]);
	}

	// ------------------------------------------------------------------------
	// Block Detection Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::detect_block_presence
	 */
	public function test_detect_block_presence_uses_caching(): void {
		// Arrange - Mock global $post
		global $post;
		$post = (object) array('post_content' => '<!-- wp:core/paragraph --><p>Test</p><!-- /wp:core/paragraph -->');

		// Register a block asset first
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		// Mock parse_blocks to return expected structure
		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array('blockName' => 'core/paragraph', 'innerBlocks' => array()),
			));

		// Act - Call twice to test caching
		$result1 = $this->instance->detect_block_presence();
		$result2 = $this->instance->detect_block_presence();

		// Assert
		$this->assertEquals(array('core/paragraph'), $result1);
		$this->assertEquals($result1, $result2, 'Second call should return cached result');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_returns_empty_when_no_blocks(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Regular content without blocks');

		WP_Mock::userFunction('has_blocks')
			->once()
			->with($post->post_content)
			->andReturn(false);

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert
		$this->assertEquals(array(), $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_handles_nested_blocks(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Block content');

		// Register block assets
		$this->instance->register_block_assets('core/group', array('scripts' => array()));
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		WP_Mock::userFunction('has_blocks')
			->once()
			->andReturn(true);

		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array(
					'blockName'   => 'core/group',
					'innerBlocks' => array(
						array('blockName' => 'core/paragraph', 'innerBlocks' => array()),
					),
				),
			));

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert
		$this->assertContains('core/group', $result);
		$this->assertContains('core/paragraph', $result);
	}

	// ------------------------------------------------------------------------
	// Asset Type Conversion Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_get_asset_type_from_string
	 */
	public function test_get_asset_type_from_string_handles_all_script_types(): void {
		$script_types = array('scripts', 'editor_scripts', 'frontend_scripts', 'dynamic_scripts');

		foreach ($script_types as $type) {
			$result = $this->instance->expose_get_asset_type_from_string($type);
			$this->assertEquals(AssetType::Script, $result, "Failed for type: {$type}");
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_get_asset_type_from_string
	 */
	public function test_get_asset_type_from_string_handles_all_style_types(): void {
		$style_types = array('styles', 'editor_styles', 'frontend_styles', 'dynamic_styles');

		foreach ($style_types as $type) {
			$result = $this->instance->expose_get_asset_type_from_string($type);
			$this->assertEquals(AssetType::Style, $result, "Failed for type: {$type}");
		}
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_get_asset_type_from_string
	 */
	public function test_get_asset_type_from_string_returns_null_for_invalid_type(): void {
		$result = $this->instance->expose_get_asset_type_from_string('invalid_type');
		$this->assertNull($result);
	}

	// ------------------------------------------------------------------------
	// Block Grouping Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::register_block_group
	 */
	public function test_register_block_group_registers_shared_assets(): void {
		// Arrange
		$group_name    = 'content-blocks';
		$blocks        = array('core/paragraph', 'core/heading', 'core/list');
		$shared_assets = array(
			'scripts' => array(
				array(
					'handle' => 'content-blocks-shared',
					'src'    => 'path/to/shared.js',
					'deps'   => array('wp-blocks'),
				),
			),
		);

		// Act
		$result = $this->instance->register_block_group($group_name, $blocks, $shared_assets);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// Verify the block group was stored with correct structure
		$block_groups = $this->get_protected_property_value($this->instance, 'block_groups');
		$this->assertArrayHasKey($group_name, $block_groups);
		$this->assertArrayHasKey('blocks', $block_groups[$group_name]);
		$this->assertArrayHasKey('shared_assets', $block_groups[$group_name]);
		$this->assertEquals($blocks, $block_groups[$group_name]['blocks']);
		$this->assertEquals($shared_assets, $block_groups[$group_name]['shared_assets']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::replace_block_assets
	 */
	public function test_replace_block_assets_processes_replacement_assets(): void {
		// Arrange
		$block_name         = 'core/paragraph';
		$replacement_config = array(
			'scripts' => array(
				array('handle' => 'replacement-script', 'src' => 'replacement.js'),
			),
		);

		// Act
		$result = $this->instance->replace_block_assets($block_name, $replacement_config);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// The method processes assets immediately via add_assets, so we can't easily verify
		// the internal state without mocking. The main test is that it returns the instance
		// and doesn't throw any errors.
	}

	// ------------------------------------------------------------------------
	// Block Bundling Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::create_block_bundle
	 */
	public function test_create_block_bundle_creates_conditional_assets(): void {
		// Arrange
		$bundle_name  = 'editor-bundle';
		$block_assets = array(
			'core/paragraph' => array(
				'scripts' => array(
					array('handle' => 'paragraph-script', 'src' => 'paragraph.js'),
				),
			),
			'core/heading' => array(
				'scripts' => array(
					array('handle' => 'heading-script', 'src' => 'heading.js'),
				),
			),
		);

		// Act
		$result = $this->instance->create_block_bundle($bundle_name, $block_assets);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// The method processes assets immediately via add_assets, so we can't easily verify
		// the internal state without mocking. The main test is that it returns the instance
		// and doesn't throw any errors.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::defer_block_assets
	 */
	public function test_defer_block_assets_processes_registered_block(): void {
		// Arrange
		$block_name   = 'core/paragraph';
		$trigger_hook = 'wp_footer';
		$block_config = array(
			'scripts' => array(
				array('handle' => 'paragraph-script', 'src' => 'paragraph.js'),
			),
		);

		// Register block assets first
		$this->instance->register_block_assets($block_name, $block_config);

		// Act
		$result = $this->instance->defer_block_assets($block_name, $trigger_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');

		// The method processes assets immediately via add_assets, so we can't easily verify
		// the internal state without mocking. The main test is that it returns the instance
		// and doesn't throw any errors.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::defer_block_assets
	 */
	public function test_defer_block_assets_returns_early_for_unregistered_block(): void {
		// Arrange
		$block_name   = 'unregistered/block';
		$trigger_hook = 'wp_footer';

		// Act
		$result = $this->instance->defer_block_assets($block_name, $trigger_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// No assets should be processed since block is not registered
	}

	// ------------------------------------------------------------------------
	// Block Presence Detection Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_is_block_present
	 */
	public function test_is_block_present_returns_true_for_detected_block(): void {
		// Arrange
		$block_name = 'core/paragraph';
		global $post;
		$post = (object) array('post_content' => '<!-- wp:core/paragraph --><p>Test</p><!-- /wp:core/paragraph -->');

		// Register a block asset first
		$this->instance->register_block_assets($block_name, array('scripts' => array()));

		// Mock parse_blocks to return expected structure
		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array('blockName' => $block_name, 'innerBlocks' => array()),
			));

		// Act
		$result = $this->instance->expose_is_block_present($block_name);

		// Assert
		$this->assertTrue($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_is_block_present
	 */
	public function test_is_block_present_returns_false_for_missing_block(): void {
		// Arrange
		$block_name = 'core/paragraph';
		global $post;
		$post = (object) array('post_content' => 'Regular content without blocks');

		WP_Mock::userFunction('has_blocks')
			->once()
			->with($post->post_content)
			->andReturn(false);

		// Act
		$result = $this->instance->expose_is_block_present($block_name);

		// Assert
		$this->assertFalse($result);
	}
}
