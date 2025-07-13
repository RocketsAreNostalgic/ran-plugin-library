<?php
/**
 * MediaEnqueueTrait.php
 *
 * @package Ran\PluginLib\EnqueueAccessory
 * @author  Ran Plugin Lib <support@ran.org>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://www.ran.org
 * @since   0.1.0
 */

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;

/**
 * Trait MediaEnqueueTrait
 *
 * Manages the enqueuing of WordPress media tools (uploader, library interface).
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */
trait MediaEnqueueTrait {
	/**
	 * Array of configurations for loading the WordPress media tools.
	 * Each configuration details how and when `wp_enqueue_media()` should be called.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $media_tool_configs = array();

	/**
	 * Array of media tool configurations to be loaded at specific WordPress action hooks.
	 * The outer array keys are hook names, and the inner arrays contain configurations.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected array $deferred_media_tool_configs = array();

	/**
	 * Gets the array of registered configurations for loading WordPress media tools.
	 *
	 * @return array<string, array<int, mixed>> The registered media tool configurations,
	 *                                            separated into 'general' and 'deferred'.
	 */
	public function get_media_tool_configs(): array {
		return array(
			'general'  => $this->media_tool_configs,
			'deferred' => $this->deferred_media_tool_configs,
		);
	}

	/**
	 * Adds configurations for loading WordPress media tools to an internal queue.
	 *
	 * @param  array<int, array<string, mixed>> $tool_configs Array of configurations.
	 *     Each item is an associative array:
	 *     - 'args' (array, optional): Arguments for `wp_enqueue_media()`.
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, not called.
	 *     - 'hook' (string|null, optional): WordPress hook to defer loading to.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add_media( array $media ): self {
		$logger = $this->get_logger();
		if ($logger->is_active()) {
			$logger->debug( 'MediaEnqueueTrait::add_media - Adding ' . count( $media ) . ' media tool configurations to the queue.' );
		}
		// Based on `add_media` in original EnqueueAbstract, it was an overwrite.
		// Sticking to that to minimize behavioral change during refactor.
		$this->media_tool_configs = $media;

		return $this;
	}

	/**
	 * Processes media tool configurations, deferring all to WordPress action hooks.
	 *
	 * @param  array<int, array<string, mixed>> $tool_configs The array of media tool configurations.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function enqueue_media( array $tool_configs ): self {
		$logger = $this->get_logger();
		if ( $logger->is_active() ) {
			$logger->debug( 'MediaEnqueueTrait::enqueue_media - Entered. Processing ' . count( $tool_configs ) . ' media tool configuration(s).' );
		}
		foreach ( $tool_configs as $index => $item_definition ) {
			if ( $logger->is_active() ) {
				$logger->debug( "MediaEnqueueTrait::enqueue_media - Processing media tool configuration at original index: {$index}." );
			}

			$hook = $item_definition['hook'] ?? null;

			if ( empty( $hook ) ) {
				if ( $logger->is_active() ) {
					$logger->debug( "MediaEnqueueTrait::enqueue_media - No hook specified for media tool configuration at original index {$index}. Defaulting to 'admin_enqueue_scripts'." );
				}
				$hook = 'admin_enqueue_scripts';
			}

			if ( $logger->is_active() ) {
				$logger->debug( "MediaEnqueueTrait::enqueue_media - Deferring media tool configuration at original index {$index} to hook: \"{$hook}\"." );
			}
			$this->deferred_media_tool_configs[ $hook ][ $index ] = $item_definition;

			if ( ! has_action( $hook, array( $this, '_enqueue_deferred_media_tools' ) ) ) {
				add_action( $hook, array( $this, '_enqueue_deferred_media_tools' ), 10, 0 ); // WP_Enqueue_Media typically doesn't take the hook name as an arg
				if ( $logger->is_active() ) {
					$logger->debug( "MediaEnqueueTrait::enqueue_media - Added action for '_enqueue_deferred_media_tools' on hook: \"{$hook}\"." );
				}
			}
		}
		if ( $logger->is_active() ) {
			$logger->debug( 'MediaEnqueueTrait::enqueue_media - Exited.' );
		}
		return $this;
	}

	/**
	 * Enqueues WordPress media tools deferred to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method (optional, but typically not used by wp_enqueue_media callbacks).
	 * @return void
	 */
	public function _enqueue_deferred_media_tools( string $hook_name = '' ): void { // Made $hook_name optional as it's context
		$logger = $this->get_logger();
		// If $hook_name is truly needed for logic, it should not be optional or should be derived via current_action()
		// For now, using $hook_name if provided, but logging will show if it's empty.
		$context             = __METHOD__;
		$effective_hook_name = $hook_name ?: (function_exists('current_action') ? current_action() : 'unknown_hook');

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered for hook: \"{$effective_hook_name}\"." );
		}

		if ( ! isset( $this->deferred_media_tool_configs[ $effective_hook_name ] ) || empty( $this->deferred_media_tool_configs[ $effective_hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - No deferred media tool configurations found or already processed for hook: \"{$effective_hook_name}\"." );
			}
			// Unset even if empty to clean up the key, though it might already be unset or never set.
			// This also prevents re-processing if the hook fires multiple times for some reason.
			if (isset($this->deferred_media_tool_configs[ $effective_hook_name ])) {
				unset( $this->deferred_media_tool_configs[ $effective_hook_name ] );
			}
			return;
		}

		$media_configs_on_this_hook = $this->deferred_media_tool_configs[ $effective_hook_name ];

		foreach ( $media_configs_on_this_hook as $index => $item_definition ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing deferred media tool configuration at original index {$index} for hook: \"{$effective_hook_name}\"." );
			}

			$args      = $item_definition['args']      ?? array();
			$condition = $item_definition['condition'] ?? null;

			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Condition not met for deferred media tool configuration at original index {$index} on hook \"{$effective_hook_name}\". Skipping." );
				}
				continue;
			}

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Calling wp_enqueue_media() for deferred configuration at original index {$index} on hook \"{$effective_hook_name}\". Args: " . (function_exists('wp_json_encode') ? wp_json_encode( $args ) : json_encode( $args )) );
			}
			wp_enqueue_media( $args );
		}

		unset( $this->deferred_media_tool_configs[ $effective_hook_name ] );

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exited for hook: \"{$effective_hook_name}\"." );
		}
	}

	/**
	 * Abstract method to get the logger instance.
	 *
	 * This method must be implemented by the class using this trait.
	 *
	 * @return Logger The logger instance.
	 */
	abstract protected function get_logger(): Logger;

	/**
	 * Retrieves the deferred hooks for media assets.
	 *
	 * @return array<int, string> An array of unique hook names.
	 */
	public function get_media_deferred_hooks(): array {
		$configs = $this->get_media_tool_configs();
		$hooks   = array();

		if (empty($configs['deferred'])) {
			return array();
		}

		foreach ($configs['deferred'] as $hook => $assets) {
			if (!empty($assets)) {
				$hooks[] = $hook;
			}
		}

		return array_unique($hooks);
	}
}
