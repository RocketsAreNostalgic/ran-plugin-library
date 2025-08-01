<?php
/**
 * Block Asset Management Trait
 *
 * This trait provides block-aware asset management functionality that extends
 * the existing asset enqueue system to support WordPress blocks. It enables
 * conditional loading of assets based on block presence and provides advanced
 * features like block grouping, asset replacement, and performance optimization.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Trait BlockAssetTrait
 *
 * Provides block-aware asset management functionality for WordPress blocks.
 * This trait extends the existing asset management system to support conditional
 * loading of assets based on block presence, block grouping, and advanced
 * performance optimization features.
 */
trait BlockAssetTrait {
	/**
	 * Stores block asset configurations indexed by block name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $block_assets = array();

	/**
	 * Stores detected blocks for the current request.
	 *
	 * @var array<string>
	 */
	protected array $detected_blocks = array();

	/**
	 * Stores block group configurations.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $block_groups = array();

	/**
	 * Flag to track if block detection has been performed.
	 *
	 * @var bool
	 */
	protected bool $blocks_detected = false;

	/**
	 * Register assets for a specific block.
	 *
	 * This method registers assets that should be loaded when a specific block
	 * is present on the page. It supports different asset types and scopes
	 * (frontend, editor, both) and integrates with the existing asset management
	 * system using conditional loading.
	 *
	 * @param string $block_name   The name of the block (e.g., 'my-plugin/my-block').
	 * @param array  $asset_config The asset configuration array.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_block_assets(string $block_name, array $asset_config): self {
		$this->block_assets[$block_name] = $asset_config;

		// Process each asset type with block-specific metadata
		$this->_process_block_asset_type($block_name, 'editor_scripts', AssetType::Script, 'editor');
		$this->_process_block_asset_type($block_name, 'frontend_scripts', AssetType::Script, 'frontend');
		$this->_process_block_asset_type($block_name, 'editor_styles', AssetType::Style, 'editor');
		$this->_process_block_asset_type($block_name, 'frontend_styles', AssetType::Style, 'frontend');
		$this->_process_block_asset_type($block_name, 'dynamic_scripts', AssetType::Script, 'dynamic');
		$this->_process_block_asset_type($block_name, 'dynamic_styles', AssetType::Style, 'dynamic');

		return $this;
	}

	/**
	 * Detect which registered blocks are present in the current page content.
	 *
	 * This method uses request-level caching to avoid expensive block parsing
	 * operations being performed multiple times per request.
	 *
	 * @return array<string> Array of block names that are present on the page.
	 */
	public function detect_block_presence(): array {
		return $this->_cache_for_request('block_presence', function() {
			return $this->_detect_blocks_in_content();
		});
	}

	/**
	 * Register assets for a block group (shared dependencies).
	 *
	 * This method allows registering shared assets that should be loaded when
	 * any block in the specified group is present. This is useful for reducing
	 * HTTP requests by sharing common dependencies across related blocks.
	 *
	 * @param string $group_name    The name of the block group.
	 * @param array  $blocks        Array of block names in this group.
	 * @param array  $shared_assets Array of shared asset configurations.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function register_block_group(string $group_name, array $blocks, array $shared_assets): self {
		$this->block_groups[$group_name] = array(
			'blocks'        => $blocks,
			'shared_assets' => $shared_assets
		);

		// Register shared assets with group condition
		foreach ($shared_assets as $asset_type => $assets) {
			$asset_type_enum = $this->_get_asset_type_from_string($asset_type);
			if ($asset_type_enum === null) {
				continue;
			}

			foreach ($assets as $asset) {
				$asset['condition'] = function() use ($blocks) {
					$detected = $this->detect_block_presence();
					return !empty(array_intersect($blocks, $detected));
				};
				$asset['_block_group'] = $group_name;

				$this->add_assets(array($asset), $asset_type_enum);
			}
		}

		return $this;
	}

	/**
	 * Replace existing block assets with custom versions.
	 *
	 * This method allows replacing default block assets (e.g., core WordPress
	 * block assets) with custom versions. It uses the existing replace flag
	 * functionality from the base asset system.
	 *
	 * @param string $block_name        The name of the block whose assets to replace.
	 * @param array  $replacement_assets Array of replacement asset configurations.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function replace_block_assets(string $block_name, array $replacement_assets): self {
		foreach ($replacement_assets as $asset_type => $assets) {
			$asset_type_enum = $this->_get_asset_type_from_string($asset_type);
			if ($asset_type_enum === null) {
				continue;
			}

			foreach ($assets as $asset) {
				$asset['replace']        = true; // Use existing replace functionality
				$asset['_block_context'] = $block_name;

				// Only add block presence condition for dynamic assets
				// Frontend assets use WordPress's built-in view_script/view_style conditional loading
				if (strpos($asset_type, 'dynamic') !== false) {
					$asset['condition'] = function() use ($block_name) {
						return $this->_is_block_present($block_name);
					};
				}

				$this->add_assets(array($asset), $asset_type_enum);
			}
		}

		return $this;
	}

	/**
	 * Create asset bundle for multiple blocks.
	 *
	 * This method combines multiple block assets into bundles to reduce HTTP
	 * requests and improve loading performance. The bundle is loaded when any
	 * of the specified blocks is present.
	 *
	 * @param string $bundle_name  The name of the bundle.
	 * @param array  $block_assets Array of block assets to bundle.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function create_block_bundle(string $bundle_name, array $block_assets): self {
		$bundle_condition = function() use ($block_assets) {
			$detected        = $this->detect_block_presence();
			$required_blocks = array_keys($block_assets);
			return !empty(array_intersect($required_blocks, $detected));
		};

		// Process bundled assets
		foreach ($block_assets as $block_name => $assets) {
			foreach ($assets as $asset_type => $asset_list) {
				$asset_type_enum = $this->_get_asset_type_from_string($asset_type);
				if ($asset_type_enum === null) {
					continue;
				}

				foreach ($asset_list as $asset) {
					$asset['condition']      = $bundle_condition;
					$asset['_bundle']        = $bundle_name;
					$asset['_block_context'] = $block_name;

					$this->add_assets(array($asset), $asset_type_enum);
				}
			}
		}

		return $this;
	}

	/**
	 * Defer block assets until specific trigger.
	 *
	 * This method allows deferring block assets until a specific WordPress
	 * hook fires, enabling lazy loading and performance optimization.
	 *
	 * @param string $block_name   The name of the block.
	 * @param string $trigger_hook The WordPress hook to trigger asset loading.
	 *
	 * @return self Returns the instance for method chaining.
	 */
	public function defer_block_assets(string $block_name, string $trigger_hook): self {
		if (!isset($this->block_assets[$block_name])) {
			return $this;
		}

		// Re-register assets with deferred hook
		foreach ($this->block_assets[$block_name] as $asset_type => $assets) {
			$asset_type_enum = $this->_get_asset_type_from_string($asset_type);
			if ($asset_type_enum === null) {
				continue;
			}

			foreach ($assets as $asset) {
				$asset['hook']           = $trigger_hook;
				$asset['_block_context'] = $block_name;

				// Only add block presence condition for dynamic assets
				// Frontend assets use WordPress's built-in view_script/view_style conditional loading
				if (strpos($asset_type, 'dynamic') !== false) {
					$asset['condition'] = function() use ($block_name) {
						return $this->_is_block_present($block_name);
					};
				}

				$this->add_assets(array($asset), $asset_type_enum);
			}
		}

		return $this;
	}

	/**
	 * Process a specific asset type for a block.
	 *
	 * This protected method handles the processing of different asset types
	 * (scripts, styles) for a specific block, adding block-specific metadata
	 * and conditions.
	 *
	 * @param string    $block_name The name of the block.
	 * @param string    $asset_key  The asset key in the configuration.
	 * @param AssetType $asset_type The asset type enum.
	 * @param string    $scope      The asset scope (frontend, editor, dynamic).
	 */
	protected function _process_block_asset_type(string $block_name, string $asset_key, AssetType $asset_type, string $scope): void {
		$assets = $this->block_assets[$block_name][$asset_key] ?? array();

		foreach ($assets as $asset) {
			$asset['_block_context'] = $block_name;
			$asset['_block_scope']   = $scope;

			// Add block presence condition only for dynamic assets
			// Frontend assets use WordPress's built-in view_script/view_style conditional loading
			if ($scope === 'dynamic') {
				$asset['condition'] = function() use ($block_name) {
					return $this->_is_block_present($block_name);
				};
			}

			// For dynamic assets, set appropriate hook if not already specified
			if ($scope === 'dynamic' && !isset($asset['hook'])) {
				$asset['hook'] = 'render_block';
			}

			// Add to appropriate queue using existing infrastructure
			$this->add_assets(array($asset), $asset_type);
		}
	}

	/**
	 * Detect blocks in the current page content.
	 *
	 * This method performs the actual block detection by parsing the post
	 * content and identifying which registered blocks are present.
	 *
	 * @return array<string> Array of detected block names.
	 */
	protected function _detect_blocks_in_content(): array {
		global $post;

		// Check if WordPress block functions are available
		if (!function_exists('has_blocks') || !function_exists('parse_blocks')) {
			return array();
		}

		if (!$post || !\has_blocks($post->post_content)) {
			return array();
		}

		$blocks   = \parse_blocks($post->post_content);
		$detected = array();

		foreach ($blocks as $block) {
			if (!empty($block['blockName']) && isset($this->block_assets[$block['blockName']])) {
				$detected[] = $block['blockName'];
			}

			// Handle nested blocks
			if (!empty($block['innerBlocks'])) {
				$detected = array_merge($detected, $this->_detect_nested_blocks($block['innerBlocks']));
			}
		}

		return array_unique($detected);
	}

	/**
	 * Handle nested block detection recursively.
	 *
	 * This method recursively searches through nested blocks to find all
	 * registered blocks that are present in the content.
	 *
	 * @param array $blocks Array of block data to search through.
	 *
	 * @return array<string> Array of detected block names.
	 */
	protected function _detect_nested_blocks(array $blocks): array {
		$detected = array();

		foreach ($blocks as $block) {
			if (!empty($block['blockName']) && isset($this->block_assets[$block['blockName']])) {
				$detected[] = $block['blockName'];
			}

			if (!empty($block['innerBlocks'])) {
				$detected = array_merge($detected, $this->_detect_nested_blocks($block['innerBlocks']));
			}
		}

		return $detected;
	}

	/**
	 * Check if a specific block is present on the current page.
	 *
	 * @param string $block_name The name of the block to check.
	 *
	 * @return bool True if the block is present, false otherwise.
	 */
	protected function _is_block_present(string $block_name): bool {
		return in_array($block_name, $this->detect_block_presence(), true);
	}

	/**
	 * Convert asset type string to AssetType enum.
	 *
	 * This helper method converts string asset type identifiers to the
	 * appropriate AssetType enum values.
	 *
	 * @param string $asset_type_string The asset type string.
	 *
	 * @return AssetType|null The corresponding AssetType enum or null if invalid.
	 */
	protected function _get_asset_type_from_string(string $asset_type_string): ?AssetType {
		return match ($asset_type_string) {
			'scripts', 'editor_scripts', 'frontend_scripts', 'dynamic_scripts' => AssetType::Script,
			'styles', 'editor_styles', 'frontend_styles', 'dynamic_styles' => AssetType::Style,
			default => null,
		};
	}
}
