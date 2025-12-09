<?php
/**
 * Individual Block Configuration Class
 *
 * This class represents a single block configuration that can be manipulated
 * independently before being registered via BlockFactory. Provides a more
 * object-oriented approach to block configuration.
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
 * Class Block
 *
 * Represents a single block configuration with fluent interface methods
 * for configuring individual aspects of the block. Can be used independently
 * or in conjunction with BlockFactory.
 */
class Block {
	/**
	 * Block name (e.g., 'my-plugin/hero').
	 *
	 * @var string
	 */
	private string $block_name;

	/**
	 * Block configuration array.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Reference to the BlockFactory that owns this block.
	 *
	 * @var BlockFactory
	 */
	private BlockFactory $manager;

	/**
	 * Create a new Block instance.
	 *
	 * A BlockFactory is required for blocks to function properly (registration,
	 * status tracking, etc.). If no manager is provided, the shared BlockFactory
	 * instance will be used. Block configuration is managed by the BlockFactory
	 * to maintain single source of truth.
	 *
	 * @param string            $block_name Block name.
	 * @param BlockFactory|null $manager    Manager reference. If null, uses shared instance.
	 * @throws \RuntimeException If no manager provided and no shared instance exists.
	 */
	public function __construct(string $block_name, ?BlockFactory $manager = null) {
		$this->block_name = $block_name;
		$this->manager    = $manager ?? BlockFactory::get_shared();

		// Initialize config from manager (single source of truth)
		$this->_sync_from_manager();
	}

	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->block_name;
	}

	/**
	 * Get the full block configuration array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Set a configuration value.
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return self
	 */
	public function set(string $key, $value): self {
		$this->config[$key] = $value;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Get a configuration value.
	 *
	 * @param string $key     Configuration key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed
	 */
	public function get(string $key, $default = null) {
		return $this->config[$key] ?? $default;
	}

	/**
	 * Set block condition for conditional registration.
	 *
	 * @param string $condition Condition callback name or function.
	 * @return self
	 */
	public function condition(string $condition): self {
		$this->config['condition'] = $condition;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block hook for deferred registration.
	 *
	 * @param string $hook     WordPress hook name.
	 * @param int    $priority Hook priority.
	 * @return self
	 */
	public function hook(string $hook, int $priority = 10): self {
		$this->config['hook']     = $hook;
		$this->config['priority'] = $priority;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Configure block assets.
	 *
	 * @param array<string, mixed> $assets_config Asset configuration array.
	 * @return self
	 */
	public function assets(array $assets_config): self {
		$this->config['assets'] = $assets_config;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block preload configuration.
	 *
	 * @param bool|callable|string $preload_config Preload configuration.
	 * @return self
	 */
	public function preload($preload_config): self {
		$this->config['preload'] = $preload_config;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Add a single script asset.
	 *
	 * @param array<string, mixed> $script_config Script configuration.
	 * @return self
	 */
	public function add_script(array $script_config): self {
		$assets                 = $this->config['assets'] ?? array();
		$scripts                = $assets['scripts']      ?? array();
		$scripts[]              = $script_config;
		$assets['scripts']      = $scripts;
		$this->config['assets'] = $assets;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Add a single style asset.
	 *
	 * @param array<string, mixed> $style_config Style configuration.
	 * @return self
	 */
	public function add_style(array $style_config): self {
		$assets                 = $this->config['assets'] ?? array();
		$styles                 = $assets['styles']       ?? array();
		$styles[]               = $style_config;
		$assets['styles']       = $styles;
		$this->config['assets'] = $assets;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set render callback.
	 *
	 * @param callable|string $callback Render callback function.
	 * @return self
	 */
	public function render_callback($callback): self {
		$this->config['render_callback'] = $callback;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes schema.
	 * @return self
	 */
	public function attributes(array $attributes): self {
		$this->config['attributes'] = $attributes;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block category.
	 *
	 * @param string $category Block category.
	 * @return self
	 */
	public function category(string $category): self {
		$this->config['category'] = $category;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block icon.
	 *
	 * @param string|array<string, mixed> $icon Block icon.
	 * @return self
	 */
	public function icon($icon): self {
		$this->config['icon'] = $icon;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block keywords.
	 *
	 * @param array<string> $keywords Block keywords for search.
	 * @return self
	 */
	public function keywords(array $keywords): self {
		$this->config['keywords'] = $keywords;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block description.
	 *
	 * @param string $description Block description.
	 * @return self
	 */
	public function description(string $description): self {
		$this->config['description'] = $description;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block title.
	 *
	 * @param string $title Block title.
	 * @return self
	 */
	public function title(string $title): self {
		$this->config['title'] = $title;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Set block supports.
	 *
	 * @param array<string, mixed> $supports Block supports configuration.
	 * @return self
	 */
	public function supports(array $supports): self {
		$this->config['supports'] = $supports;
		$this->_sync_to_manager();
		return $this;
	}

	/**
	 * Register this block.
	 *
	 * On successful registration, returns the WP_Block_Type object.
	 * On failure or pending state, returns comprehensive status information
	 * for debugging and error handling.
	 *
	 * @return \WP_Block_Type|array WP_Block_Type on success,
	 *                              status array on failure/pending.
	 */
	public function register() {
		// Attempt registration (manager is guaranteed to exist)
		$results             = $this->manager->register();
		$registration_result = $results[$this->block_name] ?? false;

		// On success, return the WP_Block_Type object
		if ($registration_result !== false) {
			return $registration_result;
		}

		// On failure/pending, return comprehensive status for debugging
		$status = $this->get_status();
		// Note: $status cannot be null since manager is guaranteed to exist
		$status['registration_result']     = $registration_result;
		$status['registration_successful'] = false;

		return $status;
	}

	/**
	 * Get the current status of this block.
	 *
	 * Provides detailed information about the block's registration state,
	 * including whether it's pending, registered, or failed, along with
	 * metadata about hooks, assets, and configuration.
	 *
	 * @return array|null Block status array, or null if block not found in manager.
	 *                    Status array contains: status, hook, priority, message/error,
	 *                    config, has_assets, has_condition, preload_enabled, etc.
	 */
	public function get_status(): ?array {
		// Get all block statuses from manager (guaranteed to exist)
		$all_statuses = $this->manager->get_block_status();

		// Return status for this specific block
		return $all_statuses[$this->block_name] ?? null;
	}

	/**
	 * Sync this block's configuration from the manager.
	 *
	 * @return void
	 */
	private function _sync_from_manager(): void {
		// Get block config from manager (single source of truth)
		$block_config = $this->manager->has_block($this->block_name)
			? $this->manager->get_block($this->block_name)
			: array();

		// Ensure block_name is always set
		$this->config = array_merge(array('block_name' => $this->block_name), $block_config);
	}

	/**
	 * Sync this block's configuration back to the manager.
	 *
	 * @return void
	 */
	private function _sync_to_manager(): void {
		$this->manager->update_block($this->block_name, $this->config);
	}
}
