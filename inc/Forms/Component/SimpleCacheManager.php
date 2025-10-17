<?php
/**
 * SimpleCacheManager: unified cache management for components and templates.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;

class SimpleCacheManager {
	use WPWrappersTrait;

	private ComponentManifest $componentManifest;
	private ComponentLoader $componentLoader;
	private Logger $logger;

	public function __construct(ComponentManifest $componentManifest, ComponentLoader $componentLoader, Logger $logger) {
		$this->componentManifest = $componentManifest;
		$this->componentLoader   = $componentLoader;
		$this->logger            = $logger;
	}

	/**
	 * Clear all component and template caches.
	 */
	public function clear_all(): void {
		$this->logger->debug('SimpleCacheManager: Starting clear_all operation');
		$this->componentManifest->clear_cache();
		$this->componentLoader->clear_template_cache();
		$this->logger->info('SimpleCacheManager: Cleared all component and template caches');
	}

	/**
	 * Clear cache for a specific component.
	 *
	 * @param string $alias Component alias to clear
	 */
	public function clear_component(string $alias): void {
		$this->logger->debug('SimpleCacheManager: Starting clear_component operation', array('alias' => $alias));
		$this->componentManifest->clear_cache($alias);
		$this->logger->info('SimpleCacheManager: Cleared component cache', array('alias' => $alias));
	}

	/**
	 * Clear cache for a specific template.
	 *
	 * @param string $name Template name to clear
	 */
	public function clear_template(string $name): void {
		$this->logger->debug('SimpleCacheManager: Starting clear_template operation', array('name' => $name));
		$this->componentLoader->clear_template_cache($name);
		$this->logger->info('SimpleCacheManager: Cleared template cache', array('name' => $name));
	}

	/**
	 * Get cache statistics and configuration information.
	 *
	 * @return array<string,mixed> Cache statistics
	 */
	public function get_stats(): array {
		return array(
			'cache_ttl'            => $this->_get_cache_ttl(),
			'environment'          => $this->_do_wp_get_environment_type(),
			'object_cache_enabled' => $this->_is_object_cache_enabled(),
			'caching_enabled'      => $this->_is_caching_enabled(),
			'debug_mode'           => $this->_is_debug_mode(),
		);
	}

	/**
	 * Get debug information about the cache system.
	 *
	 * @return array<string,mixed> Debug information
	 */
	public function debug_info(): array {
		$component_transients = $this->_do_get_option('component_cache_transients', array());
		$template_transients  = $this->_do_get_option('template_cache_transients', array());

		return array(
			'cache_enabled'                => $this->_is_caching_enabled(),
			'cache_ttl'                    => $this->_get_cache_ttl(),
			'environment'                  => $this->_do_wp_get_environment_type(),
			'object_cache_enabled'         => $this->_is_object_cache_enabled(),
			'debug_mode'                   => $this->_is_debug_mode(),
			'tracked_component_transients' => count($component_transients),
			'tracked_template_transients'  => count($template_transients),
			'total_tracked_transients'     => count($component_transients) + count($template_transients),
			'component_transient_keys'     => $component_transients,
			'template_transient_keys'      => $template_transients,
		);
	}

	/**
	 * Get the current cache TTL in seconds.
	 *
	 * @return int TTL in seconds
	 */
	private function _get_cache_ttl(): int {
		// Allow override via constant
		if (\defined('KEPLER_COMPONENT_CACHE_TTL')) {
			return \max(300, (int) \KEPLER_COMPONENT_CACHE_TTL); // Minimum 5 minutes
		}

		// Environment-based defaults
		$environment = $this->_do_wp_get_environment_type();
		switch ($environment) {
			case 'development':
				return 300; // 5 minutes
			case 'staging':
				return 1800; // 30 minutes
			default:
				return 3600; // 1 hour (production)
		}
	}

	/**
	 * Check if caching is currently enabled.
	 *
	 * @return bool
	 */
	private function _is_caching_enabled(): bool {
		// Explicit disable via constant
		if (\defined('KEPLER_COMPONENT_CACHE_DISABLED') && \KEPLER_COMPONENT_CACHE_DISABLED) {
			return false;
		}

		// Disable in development mode (WP_DEBUG) for immediate feedback
		if (\defined('WP_DEBUG') && \WP_DEBUG) {
			return false;
		}

		return true;
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	private function _is_debug_mode(): bool {
		return \defined('WP_DEBUG') && \WP_DEBUG;
	}

	/**
	 * Check if WordPress is using an external object cache.
	 *
	 * @return bool
	 */
	private function _is_object_cache_enabled(): bool {
		// Use wrapper method for wp_using_ext_object_cache (WordPress 6.1+)
		if ($this->_do_wp_using_ext_object_cache()) {
			return true;
		}

		// Fallback: check for common object cache drop-ins
		if (\defined('WP_CACHE') && \WP_CACHE) {
			return true;
		}

		// Check if object-cache.php drop-in exists
		$object_cache_file = \WP_CONTENT_DIR . '/object-cache.php';
		if (\file_exists($object_cache_file)) {
			return true;
		}

		return false;
	}
}
