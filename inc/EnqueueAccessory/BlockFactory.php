<?php
/**
 * Block Manager with Fluent Interface
 *
 * This class provides a fluent interface wrapper around BlockRegistrar for improved
 * developer experience. It uses a magic constructor pattern to enable cross-plugin
 * block overrides while maintaining natural `new` syntax.
 *
 * Features:
 * - Natural `new BlockFactory()` syntax
 * - Cross-plugin block override capability via shared instance
 * - Testing mode for isolated instances
 * - Fluent interface for incremental block configuration
 * - Full compatibility with existing BlockRegistrar features
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Class BlockFactory
 *
 * Fluent interface wrapper around BlockRegistrar that provides improved developer
 * experience while maintaining all existing functionality. Uses magic constructor
 * pattern for natural syntax with cross-plugin override capability.
 *
 * Production Mode: Shared instance enables cross-plugin block overrides
 * Testing Mode: Unique instances for test isolation
 */
class BlockFactory {
	/**
	 * Shared instance for cross-plugin coordination.
	 *
	 * @var self|null
	 */
	private static ?self $shared_instance = null;

	/**
	 * Testing mode flag - when true, constructor returns unique instances.
	 *
	 * @var bool
	 */
	private static bool $testing_mode = false;

	/**
	 * The underlying BlockRegistrar instance.
	 *
	 * @var BlockRegistrar
	 */
	private BlockRegistrar $registrar;

	/**
	 * Block configurations stored for fluent interface.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $blocks = array();

	/**
	 * Magic constructor - returns shared instance in production, unique in testing.
	 *
	 * @param ConfigInterface|null $config Configuration instance for BlockRegistrar.
	 */
	public function __construct(?ConfigInterface $config = null) {
		// Initialize new instance first
		if ($config === null) {
			throw new \InvalidArgumentException('Config instance is required for BlockFactory');
		}

		// In production mode, check for shared instance and copy state
		if (!self::$testing_mode && self::$shared_instance !== null) {
			// Copy shared state to this instance (singleton behavior)
			$this->blocks    = self::$shared_instance->blocks;
			$this->registrar = self::$shared_instance->registrar;
		} else {
			// Initialize new instance
			$this->registrar = new BlockRegistrar($config);
			$this->blocks    = array();

			// Store as shared instance in production mode
			if (!self::$testing_mode) {
				self::$shared_instance = $this;
			}
		}
	}

	// === TESTING MODE MANAGEMENT ===

	/**
	 * Enable testing mode - new instances will be unique.
	 *
	 * @return void
	 */
	public static function enableTestingMode(): void {
		self::$testing_mode    = true;
		self::$shared_instance = null;  // Clear shared instance
	}

	/**
	 * Disable testing mode - return to shared instance behavior.
	 *
	 * @return void
	 */
	public static function disableTestingMode(): void {
		self::$testing_mode    = false;
		self::$shared_instance = null;  // Clear for clean slate
	}

	/**
	 * Check if testing mode is enabled.
	 *
	 * @return bool
	 */
	public static function isTestingMode(): bool {
		return self::$testing_mode;
	}

	/**
	 * Get the shared BlockFactory instance.
	 *
	 * @return self
	 * @throws \RuntimeException If no shared instance exists.
	 */
	public static function getShared(): self {
		if (self::$shared_instance === null) {
			throw new \RuntimeException('No shared BlockFactory instance available. Create a BlockFactory instance first.');
		}
		return self::$shared_instance;
	}

	// === BLOCK MANAGEMENT ===

	/**
	 * Add a new block with basic configuration.
	 *
	 * @param string               $block_name Block name (e.g., 'my-plugin/hero').
	 * @param array<string, mixed> $config     Block configuration array.
	 * @return self
	 */
	public function add_block(string $block_name, array $config = array()): self {
		$this->blocks[$block_name] = array_merge(array('block_name' => $block_name), $config);
		return $this;
	}

	/**
	 * Get block configuration array.
	 *
	 * @param string $block_name Block name.
	 * @return array<string, mixed>
	 */
	public function get_block(string $block_name): array {
		return $this->blocks[$block_name] ?? array();
	}

	/**
	 * Check if block exists.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	public function has_block(string $block_name): bool {
		return isset($this->blocks[$block_name]);
	}

	/**
	 * Remove a block configuration.
	 *
	 * @param string $block_name Block name.
	 * @return self
	 */
	public function remove_block(string $block_name): self {
		unset($this->blocks[$block_name]);
		return $this;
	}
	// === BLOCKREGISTRAR DELEGATION ===

	/**
	 * Stage all configured blocks for registration.
	 *
	 * @return self
	 */
	public function stage(): self {
		if (!empty($this->blocks)) {
			$this->registrar->add(array_values($this->blocks));
			$this->registrar->stage();
		}
		return $this;
	}

	/**
	 * Load/register all staged blocks.
	 *
	 * @return self
	 */
	public function load(): self {
		$this->registrar->load();
		return $this;
	}

	/**
	 * Register all configured blocks (stage + load).
	 *
	 * @return array<string, \WP_Block_Type|false> Array of registration results indexed by block name.
	 *                                            WP_Block_Type on success, false on failure.
	 */
	public function register(): array {
		// Stage our blocks to the registrar first
		$this->stage();
		$this->load();

		// Get registration results from registrar
		// Note: registrar.register() calls stage() again, but that's OK
		return $this->registrar->register();
	}

	/**
	 * Get all registered WP_Block_Type objects.
	 *
	 * @return array<string, \WP_Block_Type>
	 */
	public function get_registered_block_types(): array {
		return $this->registrar->get_registered_block_types();
	}

	/**
	 * Get a specific registered WP_Block_Type object.
	 *
	 * @param string $block_name Block name.
	 * @return \WP_Block_Type|null
	 */
	public function get_registered_block_type(string $block_name): ?\WP_Block_Type {
		return $this->registrar->get_registered_block_type($block_name);
	}

	/**
	 * Get comprehensive status for all blocks.
	 *
	 * Returns detailed status information for all blocks including their
	 * registration state, hook configuration, and registration results.
	 * Provides visibility into the complete block registration lifecycle,
	 * including deferred blocks that haven't registered yet.
	 *
	 * @return array<string, array> Block status information indexed by block name.
	 *                              Each entry contains: status, hook, priority, and additional data.
	 */
	public function get_block_status(): array {
		// Ensure our blocks are staged to the registrar first
		$this->stage();
		return $this->registrar->get_block_status();
	}

	// === INTERNAL METHODS ===

	// === BLOCK OBJECT SUPPORT ===

	/**
	 * Get or create a Block object for individual block configuration.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $config     Initial config if creating new block.
	 * @return Block
	 */
	public function block(string $block_name, array $config = array()): Block {
		// Create block if it doesn't exist
		if (!$this->has_block($block_name)) {
			$this->add_block($block_name, $config);
		}

		return new Block($block_name, $this);
	}

	/**
	 * Update a block's configuration (used by Block objects).
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $config     Updated block configuration.
	 * @return self
	 */
	public function update_block(string $block_name, array $config): self {
		$this->blocks[$block_name] = $config;
		return $this;
	}
}
