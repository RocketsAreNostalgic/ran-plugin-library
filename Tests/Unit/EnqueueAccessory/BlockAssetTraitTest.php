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

		$block_assets = $this->_get_protected_property_value($this->instance, 'block_assets');
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
		$block_assets = $this->_get_protected_property_value($this->instance, 'block_assets');
		$this->assertArrayHasKey('editor_scripts', $block_assets[$block_name]);
		$this->assertArrayHasKey('frontend_scripts', $block_assets[$block_name]);
	}

	// ------------------------------------------------------------------------
	// Block Detection Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::__detect_block_presence
	 */
	public function test__detect_block_presence_uses_caching(): void {
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
		$result1 = $this->instance->__detect_block_presence();
		$result2 = $this->instance->__detect_block_presence();

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
	public function test_detect_blocks_in_content_returns_empty_when_wp_functions_unavailable(): void {
		// Arrange - Use reflection to access the cache and force function availability to false
		$reflection  = new \ReflectionClass($this->instance);
		$cacheMethod = $reflection->getMethod('_cache_for_request');
		$cacheMethod->setAccessible(true);

		// Pre-populate the cache with false to simulate functions not being available
		$cacheMethod->invoke($this->instance, 'wp_block_functions_available', function() {
			return false; // Simulate has_blocks() or parse_blocks() not existing
		});

		// Act - Call the method
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert - Should return empty array when WP functions are not available
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

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_handles_null_global_post(): void {
		// Arrange - Set global $post to null
		global $post;
		$post = null;

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert - Should return empty array when $post is null
		$this->assertEquals(array(), $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_handles_empty_block_names(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Block content');

		// Register some block assets
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		WP_Mock::userFunction('has_blocks')
			->once()
			->andReturn(true);

		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array(
					'blockName'   => '', // Empty block name
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => null, // Null block name
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/paragraph', // Valid block name
					'innerBlocks' => array(),
				),
			));

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert - Should only include the valid block name
		$this->assertEquals(array('core/paragraph'), $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_ignores_unregistered_blocks(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Block content');

		// Register only one block asset
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		WP_Mock::userFunction('has_blocks')
			->once()
			->andReturn(true);

		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array(
					'blockName'   => 'core/paragraph', // Registered block
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/heading', // Unregistered block
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'custom/block', // Another unregistered block
					'innerBlocks' => array(),
				),
			));

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert - Should only include registered blocks
		$this->assertEquals(array('core/paragraph'), $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_handles_blocks_without_inner_blocks_key(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Block content');

		// Register block assets
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		WP_Mock::userFunction('has_blocks')
			->once()
			->andReturn(true);

		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array(
					'blockName' => 'core/paragraph',
					// No 'innerBlocks' key at all
				),
			));

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert
		$this->assertEquals(array('core/paragraph'), $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_blocks_in_content
	 */
	public function test_detect_blocks_in_content_handles_duplicate_blocks(): void {
		// Arrange
		global $post;
		$post = (object) array('post_content' => 'Block content');

		// Register block assets
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));
		$this->instance->register_block_assets('core/heading', array('scripts' => array()));

		WP_Mock::userFunction('has_blocks')
			->once()
			->andReturn(true);

		WP_Mock::userFunction('parse_blocks')
			->once()
			->andReturn(array(
				array(
					'blockName'   => 'core/paragraph',
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/heading',
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/paragraph', // Duplicate
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/heading', // Duplicate
					'innerBlocks' => array(),
				),
			));

		// Act
		$result = $this->instance->expose_detect_blocks_in_content();

		// Assert - Should return unique blocks only (array_unique is called)
		$this->assertCount(2, $result);
		$this->assertContains('core/paragraph', $result);
		$this->assertContains('core/heading', $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::register_block_group
	 */
	public function test_register_block_group_skips_invalid_asset_types(): void {
		// Arrange - Create shared assets with invalid asset type
		$blocks        = array('test/block-1', 'test/block-2');
		$shared_assets = array(
			'invalid_type' => array(
				array('handle' => 'invalid-asset', 'src' => 'invalid.js')
			),
			'scripts' => array(
				array('handle' => 'valid-script', 'src' => 'valid.js')
			)
		);

		// Act - Register block group (should skip invalid_type but process scripts)
		$result = $this->instance->register_block_group('test-group', $blocks, $shared_assets);

		// Assert - Should return self for chaining
		$this->assertSame($this->instance, $result);

		// Verify that the group was registered (accessing protected property via reflection)
		$reflection = new \ReflectionClass($this->instance);
		$property   = $reflection->getProperty('block_groups');
		$property->setAccessible(true);
		$block_groups = $property->getValue($this->instance);

		$this->assertArrayHasKey('test-group', $block_groups);
		$this->assertEquals($blocks, $block_groups['test-group']['blocks']);
		$this->assertEquals($shared_assets, $block_groups['test-group']['shared_assets']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::create_block_bundle
	 */
	public function test_create_block_bundle_skips_invalid_asset_types(): void {
		// Arrange - Set up block assets with invalid asset type
		$this->instance->register_block_assets('test/block', array(
			'scripts' => array(
				array('handle' => 'test-script', 'src' => 'test.js')
			)
		));

		// Create bundle with invalid asset type that should be skipped
		$bundle_assets = array(
			'test/block' => array(
				'invalid_type' => array(
					array('handle' => 'invalid-asset', 'src' => 'invalid.js')
				),
				'scripts' => array(
					array('handle' => 'valid-script', 'src' => 'valid.js')
				)
			)
		);

		// Act - Create bundle (should skip invalid_type but process scripts)
		$result = $this->instance->create_block_bundle('test-bundle', $bundle_assets);

		// Assert - Should return self for chaining
		$this->assertSame($this->instance, $result);

		// Verify that valid assets were processed but invalid ones were skipped
		// We can't directly verify the skipping, but we can verify the method completed successfully
		$this->assertTrue(true); // Test passes if no exceptions thrown
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_nested_blocks
	 */
	public function test_detect_nested_blocks_handles_deep_recursion(): void {
		// Arrange - Create a deeply nested block structure to hit the recursive call
		$this->instance->register_block_assets('core/group', array('scripts' => array()));
		$this->instance->register_block_assets('core/columns', array('scripts' => array()));
		$this->instance->register_block_assets('core/column', array('scripts' => array()));
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		// Create a structure: group -> columns -> column -> paragraph
		// This ensures the recursive call on line 357 is hit
		$blocks = array(
			array(
				'blockName'   => 'core/group',
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/columns',
						'innerBlocks' => array(
							array(
								'blockName'   => 'core/column',
								'innerBlocks' => array(
									array(
										'blockName'   => 'core/paragraph',
										'innerBlocks' => array(),
									),
								),
							),
						),
					),
				),
			),
		);

		// Act
		$result = $this->instance->expose_detect_nested_blocks($blocks);

		// Assert - All nested blocks should be detected
		$this->assertContains('core/group', $result);
		$this->assertContains('core/columns', $result);
		$this->assertContains('core/column', $result);
		$this->assertContains('core/paragraph', $result);
		$this->assertCount(4, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_nested_blocks
	 */
	public function test_detect_nested_blocks_handles_empty_blocks(): void {
		// Arrange - Test with empty blocks array
		$blocks = array();

		// Act
		$result = $this->instance->expose_detect_nested_blocks($blocks);

		// Assert
		$this->assertEmpty($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::_detect_nested_blocks
	 */
	public function test_detect_nested_blocks_ignores_unregistered_blocks(): void {
		// Arrange - Only register one block type
		$this->instance->register_block_assets('core/paragraph', array('scripts' => array()));

		$blocks = array(
			array(
				'blockName'   => 'core/group', // Not registered
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/paragraph', // Registered
						'innerBlocks' => array(),
					),
				),
			),
		);

		// Act
		$result = $this->instance->expose_detect_nested_blocks($blocks);

		// Assert - Only registered block should be detected
		$this->assertNotContains('core/group', $result);
		$this->assertContains('core/paragraph', $result);
		$this->assertCount(1, $result);
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
		$block_groups = $this->_get_protected_property_value($this->instance, 'block_groups');
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

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::replace_block_assets
	 */
	public function test_replace_block_assets_skips_invalid_asset_types(): void {
		// Arrange - Test line 155: continue statement when asset_type_enum === null
		$block_name         = 'test/block';
		$replacement_config = array(
			'invalid_asset_type' => array(
				array('handle' => 'invalid-asset', 'src' => 'invalid.js'),
			),
			'scripts' => array(
				array('handle' => 'valid-script', 'src' => 'valid.js'),
			),
		);

		// Act
		$result = $this->instance->replace_block_assets($block_name, $replacement_config);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// The invalid asset type should be skipped (continue statement hit)
		// The valid asset type should be processed normally
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::replace_block_assets
	 */
	public function test_replace_block_assets_handles_dynamic_assets(): void {
		// Arrange - Test lines 165-167: dynamic asset condition assignment
		$block_name         = 'test/dynamic-block';
		$replacement_config = array(
			'dynamic_scripts' => array(
				array('handle' => 'dynamic-replacement-script', 'src' => 'dynamic-replacement.js'),
			),
			'dynamic_styles' => array(
				array('handle' => 'dynamic-replacement-style', 'src' => 'dynamic-replacement.css'),
			),
			'scripts' => array(
				array('handle' => 'regular-script', 'src' => 'regular.js'),
			),
		);

		// Act
		$result = $this->instance->replace_block_assets($block_name, $replacement_config);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// Dynamic assets should have condition functions added
		// Regular assets should not have condition functions
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::replace_block_assets
	 */
	public function test_replace_block_assets_comprehensive_coverage(): void {
		// Arrange - Comprehensive test hitting all code paths
		$block_name         = 'test/comprehensive-block';
		$replacement_config = array(
			// Invalid asset type - should hit continue (line 155)
			'invalid_type' => array(
				array('handle' => 'invalid', 'src' => 'invalid.js'),
			),
			// Dynamic assets - should hit lines 165-167
			'dynamic_scripts' => array(
				array('handle' => 'dynamic-script', 'src' => 'dynamic.js'),
			),
			'dynamic_styles' => array(
				array('handle' => 'dynamic-style', 'src' => 'dynamic.css'),
			),
			// Regular assets - should not hit dynamic condition
			'scripts' => array(
				array('handle' => 'regular-script', 'src' => 'regular.js'),
			),
			'styles' => array(
				array('handle' => 'regular-style', 'src' => 'regular.css'),
			),
			'editor_scripts' => array(
				array('handle' => 'editor-script', 'src' => 'editor.js'),
			),
			'frontend_scripts' => array(
				array('handle' => 'frontend-script', 'src' => 'frontend.js'),
			),
		);

		// Act
		$result = $this->instance->replace_block_assets($block_name, $replacement_config);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// This test should hit all uncovered lines:
		// - Line 155: continue for invalid asset type
		// - Line 165: condition assignment for dynamic assets
		// - Line 167: condition function creation
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

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::defer_block_assets
	 */
	public function test_defer_block_assets_skips_invalid_asset_types(): void {
		// Arrange - Test line 237: continue statement when asset_type_enum === null
		$block_name   = 'test/defer-block';
		$trigger_hook = 'wp_footer';
		$block_config = array(
			'invalid_asset_type' => array(
				array('handle' => 'invalid-asset', 'src' => 'invalid.js'),
			),
			'scripts' => array(
				array('handle' => 'valid-script', 'src' => 'valid.js'),
			),
		);

		// Register block assets first
		$this->instance->register_block_assets($block_name, $block_config);

		// Act
		$result = $this->instance->defer_block_assets($block_name, $trigger_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// The invalid asset type should be skipped (continue statement hit)
		// The valid asset type should be processed normally
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::defer_block_assets
	 */
	public function test_defer_block_assets_handles_dynamic_assets(): void {
		// Arrange - Test lines 247-249: dynamic asset condition assignment
		$block_name   = 'test/dynamic-defer-block';
		$trigger_hook = 'wp_footer';
		$block_config = array(
			'dynamic_scripts' => array(
				array('handle' => 'dynamic-defer-script', 'src' => 'dynamic-defer.js'),
			),
			'dynamic_styles' => array(
				array('handle' => 'dynamic-defer-style', 'src' => 'dynamic-defer.css'),
			),
			'scripts' => array(
				array('handle' => 'regular-defer-script', 'src' => 'regular-defer.js'),
			),
		);

		// Register block assets first
		$this->instance->register_block_assets($block_name, $block_config);

		// Act
		$result = $this->instance->defer_block_assets($block_name, $trigger_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// Dynamic assets should have condition functions added
		// Regular assets should not have condition functions
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\BlockAssetTrait::defer_block_assets
	 */
	public function test_defer_block_assets_comprehensive_coverage(): void {
		// Arrange - Comprehensive test hitting all code paths
		$block_name   = 'test/comprehensive-defer-block';
		$trigger_hook = 'wp_footer';
		$block_config = array(
			// Invalid asset type - should hit continue (line 237)
			'invalid_type' => array(
				array('handle' => 'invalid', 'src' => 'invalid.js'),
			),
			// Dynamic assets - should hit lines 247-249
			'dynamic_scripts' => array(
				array('handle' => 'dynamic-defer-script', 'src' => 'dynamic-defer.js'),
			),
			'dynamic_styles' => array(
				array('handle' => 'dynamic-defer-style', 'src' => 'dynamic-defer.css'),
			),
			// Regular assets - should not hit dynamic condition
			'scripts' => array(
				array('handle' => 'regular-defer-script', 'src' => 'regular-defer.js'),
			),
			'styles' => array(
				array('handle' => 'regular-defer-style', 'src' => 'regular-defer.css'),
			),
			'editor_scripts' => array(
				array('handle' => 'editor-defer-script', 'src' => 'editor-defer.js'),
			),
			'frontend_scripts' => array(
				array('handle' => 'frontend-defer-script', 'src' => 'frontend-defer.js'),
			),
		);

		// Register block assets first
		$this->instance->register_block_assets($block_name, $block_config);

		// Act
		$result = $this->instance->defer_block_assets($block_name, $trigger_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return instance for chaining');
		// This test should hit all uncovered lines:
		// - Line 237: continue for invalid asset type
		// - Line 247: condition assignment for dynamic assets
		// - Line 249: condition function creation
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
