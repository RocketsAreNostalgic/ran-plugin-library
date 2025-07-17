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
 * Unlike scripts and styles, wp_enqueue_media() is a singleton function that loads
 * the entire WordPress media framework. Each configuration represents a call to
 * wp_enqueue_media() with specific arguments and conditions.
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
	public function get(): array {
		return array(
			'general'  => $this->media_tool_configs,
			'deferred' => $this->deferred_media_tool_configs,
		);
	}

	/**
	 * Adds configurations for loading WordPress media tools to an internal queue.
	 *
	 * @param  array<int, array<string, mixed>> $media_configs Array of configurations.
	 *     Each item is an associative array:
	 *     - 'args' (array, optional): Arguments for `wp_enqueue_media()`. Default: array()
	 *     - 'condition' (callable|null, optional): Callback returning boolean. If false, not called.
	 *     - 'hook' (string|null, optional): WordPress hook to defer loading to.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function add( array $media_configs ): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;
		if ($logger->is_active()) {
			$logger->debug( "{$context} - Adding " . count( $media_configs ) . ' media tool configurations to the queue.' );
		}

		// Based on original implementation, this was an overwrite operation
		// Sticking to that to minimize behavioral change during refactor
		$this->media_tool_configs = $media_configs;

		return $this;
	}

	/**
	 * Processes media tool configurations, deferring them to WordPress action hooks.
	 *
	 * @param  array<int, array<string, mixed>> $tool_configs The array of media tool configurations.
	 * @return self Returns the instance of this class for method chaining.
	 */
	public function stage_media( array $tool_configs ): self {
		$logger  = $this->get_logger();
		$context = __TRAIT__ . '::' . __FUNCTION__;
		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered. Processing " . count( $tool_configs ) . ' media tool configuration(s).' );
		}

		foreach ( $tool_configs as $index => $config ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing media tool configuration at original index: {$index}." );
			}

			$hook = $config['hook'] ?? 'admin_enqueue_scripts'; // use wp_enqueue_scripts on front end with propper capability checks

			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Deferring media tool configuration at original index {$index} to hook: \"{$hook}\"." );
			}

			$this->deferred_media_tool_configs[ $hook ][ $index ] = $config;

			if ( ! has_action( $hook, array( $this, '_enqueue_deferred_media_tools' ) ) ) {
				add_action( $hook, array( $this, '_enqueue_deferred_media_tools' ), 10, 0 );
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Added action for '_enqueue_deferred_media_tools' on hook: \"{$hook}\"." );
				}
			}
		}

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exited." );
		}
		return $this;
	}

	/**
	 * Enqueues WordPress media tools deferred to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name that triggered this method (optional).
	 * @return void
	 */
	public function _enqueue_deferred_media_tools( string $hook_name = '' ): void {
		$logger = $this->get_logger();

		$context             = __TRAIT__ . '::' . __FUNCTION__;
		$effective_hook_name = $hook_name ?: (function_exists('current_action') ? current_action() : 'unknown_hook');

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Entered for hook: \"{$effective_hook_name}\"." );
		}

		if ( ! isset( $this->deferred_media_tool_configs[ $effective_hook_name ] ) || empty( $this->deferred_media_tool_configs[ $effective_hook_name ] ) ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - No deferred media tool configurations found for hook: \"{$effective_hook_name}\"." );
			}
			return;
		}

		$media_configs_on_this_hook = $this->deferred_media_tool_configs[ $effective_hook_name ];

		foreach ( $media_configs_on_this_hook as $index => $config ) {
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Processing deferred media tool configuration at original index {$index} for hook: \"{$effective_hook_name}\"." );
			}

			$args      = $config['args']      ?? array();
			$condition = $config['condition'] ?? null;

			// Check condition if provided
			if ( is_callable( $condition ) && ! $condition() ) {
				if ( $logger->is_active() ) {
					$logger->debug( "{$context} - Condition not met for deferred media tool configuration at original index {$index} on hook \"{$effective_hook_name}\". Skipping." );
				}
				continue;
			}

			// Call wp_enqueue_media() with the provided arguments
			if ( $logger->is_active() ) {
				$logger->debug( "{$context} - Calling wp_enqueue_media() for deferred configuration at original index {$index} on hook \"{$effective_hook_name}\". Args: " . wp_json_encode( $args ) );
			}

			wp_enqueue_media( $args );
		}

		// Clean up processed configurations
		unset( $this->deferred_media_tool_configs[ $effective_hook_name ] );

		if ( $logger->is_active() ) {
			$logger->debug( "{$context} - Exited for hook: \"{$effective_hook_name}\"." );
		}
	}
}
