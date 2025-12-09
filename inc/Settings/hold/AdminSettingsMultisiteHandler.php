<?php
/**
 * NOT YET IMPLEMENTED
 * Use will require changes to AdminSettings submission pipeline.
 *
 * AdminSettingsMultisiteHandler: Multisite and network admin support for AdminSettings.
 *
 * Provides network admin context handling, site-specific vs network-wide option management,
 * multisite-aware capability checks, and network admin template variations.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 *
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Settings\AdminSettingsSecurityHandler;
use Ran\PluginLib\Settings\AdminSettingsPostHandler;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;

/**
 * Handles multisite-specific functionality for AdminSettings.
 *
 * Responsibilities:
 * - Detect and handle network admin context
 * - Manage site-specific vs network-wide option storage
 * - Provide multisite-aware capability checks
 * - Handle network admin template variations
 * - Support cross-site settings management
 *
 * skip test coverage for this class
 * @codeCoverageIgnore
 */
class AdminSettingsMultisiteHandler {
	use WPWrappersTrait;

	private Logger $logger;
	private AdminSettingsSecurityHandler $security_handler;
	private AdminSettingsPostHandler $post_handler;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsSecurityHandler|null $security_handler Optional security handler instance.
	 * @param AdminSettingsPostHandler|null $post_handler Optional post handler instance.
	 * @param Logger|null $logger Optional logger instance.
	 */
	public function __construct(
		?AdminSettingsSecurityHandler $security_handler = null,
		?AdminSettingsPostHandler $post_handler = null,
		?Logger $logger = null
	) {
		$this->security_handler = $security_handler ?? new AdminSettingsSecurityHandler();
		$this->post_handler     = $post_handler     ?? new AdminSettingsPostHandler($this->security_handler);
		$this->logger           = $logger           ?? new Logger();
	}

	/**
	 * Check if current context is network admin.
	 *
	 * @return bool True if in network admin context, false otherwise.
	 */
	public function is_network_admin(): bool {
		return $this->_do_is_network_admin();
	}

	/**
	 * Check if current WordPress installation is multisite.
	 *
	 * @return bool True if multisite, false otherwise.
	 */
	public function is_multisite(): bool {
		if (\function_exists('is_multisite')) {
			return (bool) \is_multisite();
		}
		return false;
	}

	/**
	 * Get the current blog/site ID.
	 *
	 * @return int The current blog ID, or 0 if not available.
	 */
	public function get_current_blog_id(): int {
		return $this->_do_get_current_blog_id();
	}

	/**
	 * Get appropriate RegisterOptions instance for current context.
	 *
	 * @param string $option_name The main option name.
	 * @param bool $force_network Force network scope even if not in network admin.
	 * @param int|null $blog_id Specific blog ID for blog scope (null for current).
	 * @return RegisterOptions The appropriate RegisterOptions instance.
	 */
	public function get_context_options(string $option_name, bool $force_network = false, ?int $blog_id = null): RegisterOptions {
		if ($force_network || $this->is_network_admin()) {
			// Network-wide options
			$this->logger->debug('AdminSettingsMultisiteHandler: Using network scope', array(
				'option_name'   => $option_name,
				'force_network' => $force_network
			));
			return RegisterOptions::network($option_name, $this->logger);
		}

		if ($blog_id !== null && $blog_id > 0) {
			// Specific blog options
			$this->logger->debug('AdminSettingsMultisiteHandler: Using blog scope', array(
				'option_name' => $option_name,
				'blog_id'     => $blog_id
			));
			return RegisterOptions::blog($option_name, $blog_id, null, $this->logger);
		}

		// Site-specific options (default)
		$this->logger->debug('AdminSettingsMultisiteHandler: Using site scope', array(
			'option_name' => $option_name
		));
		return RegisterOptions::site($option_name, true, $this->logger);
	}

	/**
	 * Get appropriate capability for current multisite context.
	 *
	 * @param string $site_capability Capability for site context.
	 * @param string $network_capability Capability for network context.
	 * @return string The appropriate capability.
	 */
	public function get_context_capability(
		string $site_capability = 'manage_options',
		string $network_capability = 'manage_network_options'
	): string {
		if ($this->is_network_admin()) {
			return $network_capability;
		}
		return $site_capability;
	}

	/**
	 * Verify user has appropriate capability for current multisite context.
	 *
	 * @param string $site_capability Capability for site context.
	 * @param string $network_capability Capability for network context.
	 * @return bool True if user has appropriate capability.
	 */
	public function verify_context_capability(
		string $site_capability = 'manage_options',
		string $network_capability = 'manage_network_options'
	): bool {
		return $this->security_handler->verify_multisite_capability($site_capability, $network_capability);
	}

	/**
	 * Register multisite-aware admin-post handlers.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param RegisterOptions $options The RegisterOptions instance.
	 * @param string $site_capability Site-level capability.
	 * @param string $network_capability Network-level capability.
	 * @return void
	 */
	public function register_multisite_handlers(
		string $page_slug,
		RegisterOptions $options,
		string $site_capability = 'manage_options',
		string $network_capability = 'manage_network_options'
	): void {
		$capability = $this->get_context_capability($site_capability, $network_capability);

		$this->post_handler->register_handlers($page_slug, $options, $capability);

		$this->logger->debug('AdminSettingsMultisiteHandler: Registered multisite handlers', array(
			'page_slug'        => $page_slug,
			'capability'       => $capability,
			'is_network_admin' => $this->is_network_admin(),
			'is_multisite'     => $this->is_multisite()
		));
	}

	/**
	 * Get list of sites in the network (for network admin context).
	 *
	 * @param array $args Optional arguments for get_sites().
	 * @return array Array of site objects or empty array if not multisite/network admin.
	 */
	public function get_network_sites(array $args = array()): array {
		if (!$this->is_multisite() || !$this->is_network_admin()) {
			return array();
		}

		if (!\function_exists('get_sites')) {
			$this->logger->warning('AdminSettingsMultisiteHandler: get_sites() not available');
			return array();
		}

		$default_args = array(
			'number'  => 100, // Reasonable default limit
			'orderby' => 'domain',
			'order'   => 'ASC'
		);

		$sites = \get_sites(array_merge($default_args, $args));

		$this->logger->debug('AdminSettingsMultisiteHandler: Retrieved network sites', array(
			'site_count' => count($sites),
			'args'       => array_merge($default_args, $args)
		));

		return is_array($sites) ? $sites : array();
	}

	/**
	 * Get site information for a specific blog ID.
	 *
	 * @param int $blog_id The blog ID.
	 * @return array|null Site information or null if not found.
	 */
	public function get_site_info(int $blog_id): ?array {
		if (!$this->is_multisite()) {
			return null;
		}

		if (!\function_exists('get_blog_details')) {
			$this->logger->warning('AdminSettingsMultisiteHandler: get_blog_details() not available');
			return null;
		}

		$site = \get_blog_details($blog_id);

		if (!$site) {
			$this->logger->warning('AdminSettingsMultisiteHandler: Site not found', array(
				'blog_id' => $blog_id
			));
			return null;
		}

		// Convert to array for consistent return type
		$site_info = array(
			'blog_id'  => (int) $site->blog_id,
			'domain'   => $site->domain,
			'path'     => $site->path,
			'site_url' => $site->siteurl  ?? '',
			'blogname' => $site->blogname ?? '',
			'public'   => (bool) ($site->public ?? true),
			'archived' => (bool) ($site->archived ?? false),
			'mature'   => (bool) ($site->mature ?? false),
			'spam'     => (bool) ($site->spam ?? false),
			'deleted'  => (bool) ($site->deleted ?? false)
		);

		$this->logger->debug('AdminSettingsMultisiteHandler: Retrieved site info', array(
			'blog_id'  => $blog_id,
			'domain'   => $site_info['domain'],
			'blogname' => $site_info['blogname']
		));

		return $site_info;
	}

	/**
	 * Check if user can manage settings for a specific site.
	 *
	 * @param int $blog_id The blog ID to check.
	 * @param string $capability The required capability.
	 * @return bool True if user can manage settings for the site.
	 */
	public function can_manage_site_settings(int $blog_id, string $capability = 'manage_options'): bool {
		// Network admins can manage all sites
		if ($this->is_network_admin() && $this->verify_context_capability()) {
			return true;
		}

		// Check if user has capability on the specific site
		if (\function_exists('user_can')) {
			$user_id = $this->_do_get_current_user_id();
			return \user_can($user_id, $capability) && $this->user_can_access_site($user_id, $blog_id);
		}

		// Fallback to current site capability check
		return $this->_do_current_user_can($capability);
	}

	/**
	 * Check if user can access a specific site.
	 *
	 * @param int $user_id The user ID.
	 * @param int $blog_id The blog ID.
	 * @return bool True if user can access the site.
	 */
	public function user_can_access_site(int $user_id, int $blog_id): bool {
		if (!$this->is_multisite()) {
			return true; // Single site - user can access if they have general capability
		}

		if (\function_exists('is_user_member_of_blog')) {
			return \is_user_member_of_blog($user_id, $blog_id);
		}

		// Fallback - assume access if function not available
		$this->logger->warning('AdminSettingsMultisiteHandler: is_user_member_of_blog() not available, assuming access');
		return true;
	}

	/**
	 * Get network admin URL for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $args Optional query arguments.
	 * @return string The network admin URL.
	 */
	public function get_network_admin_url(string $page_slug, array $args = array()): string {
		if (!\function_exists('network_admin_url')) {
			$this->logger->warning('AdminSettingsMultisiteHandler: network_admin_url() not available');
			return '/wp-admin/network/admin.php?page=' . urlencode($page_slug);
		}

		$base_url = 'admin.php?page=' . urlencode($page_slug);

		if (!empty($args)) {
			$base_url .= '&' . http_build_query($args);
		}

		$url = \network_admin_url($base_url);
		return $url ?? '/wp-admin/network/admin.php?page=' . urlencode($page_slug);
	}

	/**
	 * Get site admin URL for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param int|null $blog_id Optional blog ID (null for current site).
	 * @param array $args Optional query arguments.
	 * @return string The site admin URL.
	 */
	public function get_site_admin_url(string $page_slug, ?int $blog_id = null, array $args = array()): string {
		$base_url = 'admin.php?page=' . urlencode($page_slug);

		if (!empty($args)) {
			$base_url .= '&' . http_build_query($args);
		}

		if ($blog_id !== null && $blog_id > 0 && $this->is_multisite()) {
			if (\function_exists('get_admin_url')) {
				return \get_admin_url($blog_id, $base_url);
			}
		}

		// Fallback to current site admin URL
		if (\function_exists('admin_url')) {
			return \admin_url($base_url);
		}

		// Final fallback
		return '/wp-admin/' . $base_url;
	}

	/**
	 * Get context-appropriate admin URL.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $args Optional query arguments.
	 * @return string The appropriate admin URL for current context.
	 */
	public function get_context_admin_url(string $page_slug, array $args = array()): string {
		if ($this->is_network_admin()) {
			return $this->get_network_admin_url($page_slug, $args);
		}

		return $this->get_site_admin_url($page_slug, null, $args);
	}

	/**
	 * Check if user can manage network options.
	 *
	 * @return bool True if user can manage network options.
	 */
	public function can_manage_network_options(): bool {
		return $this->_do_current_user_can('manage_network_options');
	}

	/**
	 * Get template context for multisite-aware rendering.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $additional_context Additional context data.
	 * @return array Template context with multisite information.
	 */
	public function get_template_context(string $page_slug, array $additional_context = array()): array {
		$context = array(
			'is_multisite'     => $this->is_multisite(),
			'is_network_admin' => $this->is_network_admin(),
			'current_blog_id'  => $this->get_current_blog_id(),
			'page_slug'        => $page_slug,
			'admin_url'        => $this->get_context_admin_url($page_slug),
			'capability'       => $this->get_context_capability()
		);

		// Add network sites if in network admin
		if ($this->is_network_admin()) {
			$context['network_sites'] = $this->get_network_sites();
		}

		// Add current site info if multisite
		if ($this->is_multisite()) {
			$current_blog_id = $this->get_current_blog_id();
			if ($current_blog_id > 0) {
				$context['current_site'] = $this->get_site_info($current_blog_id);
			}
		}

		return array_merge($context, $additional_context);
	}
}
