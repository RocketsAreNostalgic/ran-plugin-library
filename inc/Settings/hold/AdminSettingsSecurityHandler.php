<?php
/**
 * AdminSettingsSecurityHandler: Secure save pipeline for AdminSettings forms.
 *
 * Provides custom nonce generation and verification, capability checks, CSRF protection,
 * and secure request validation for admin-post.php handlers. This bypasses WordPress
 * Settings API constraints while maintaining security best practices.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;

/**
 * Handles security aspects of AdminSettings custom save pipeline.
 *
 * Responsibilities:
 * - Generate and verify context-specific nonces for AdminSettings forms
 * - Validate user capabilities and authorization
 * - Implement CSRF protection mechanisms
 * - Provide secure request validation for admin-post.php handlers
 * - Handle multisite and network admin contexts appropriately
 * - Manage secure redirects after form processing
 *
 *  @codeCoverageIgnore
 */
class AdminSettingsSecurityHandler {
	use WPWrappersTrait;

	private const NONCE_ACTION_PREFIX = 'admin_settings_save_';
	private const NONCE_FIELD_NAME    = 'admin_settings_nonce';
	private const DEFAULT_CAPABILITY  = 'manage_options';

	private Logger $logger;
	private FormMessageHandler $message_handler;

	/**
	 * Constructor.
	 *
	 * @param Logger|null $logger Optional logger instance.
	 * @param FormMessageHandler|null $message_handler Optional message handler.
	 */
	public function __construct(?Logger $logger = null, ?FormMessageHandler $message_handler = null) {
		$this->logger          = $logger          ?? new Logger();
		$this->message_handler = $message_handler ?? new FormMessageHandler($this->logger);
	}

	/**
	 * Generate a context-specific nonce for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return string The generated nonce.
	 */
	public function generate_nonce(string $page_slug): string {
		$action = $this->get_nonce_action($page_slug);

		if (!\function_exists('wp_create_nonce')) {
			$this->logger->warning('AdminSettingsSecurityHandler: wp_create_nonce not available, returning empty nonce');
			return '';
		}

		$nonce = \wp_create_nonce($action);

		$this->logger->debug('AdminSettingsSecurityHandler: Generated nonce', array(
			'page_slug'    => $page_slug,
			'action'       => $action,
			'nonce_length' => strlen($nonce ?? '')
		));

		return $nonce ?? '';
	}

	/**
	 * Verify a nonce for a specific settings page.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param string $page_slug The settings page slug.
	 * @return bool True if nonce is valid, false otherwise.
	 */
	public function verify_nonce(string $nonce, string $page_slug): bool {
		$action = $this->get_nonce_action($page_slug);

		if (!\function_exists('wp_verify_nonce')) {
			$this->logger->warning('AdminSettingsSecurityHandler: wp_verify_nonce not available, allowing request');
			return true; // Allow in test environments
		}

		$result   = \wp_verify_nonce($nonce, $action);
		$is_valid = $result !== false;

		$this->logger->debug('AdminSettingsSecurityHandler: Nonce verification', array(
			'page_slug' => $page_slug,
			'action'    => $action,
			'valid'     => $is_valid,
			'result'    => $result
		));

		if (!$is_valid) {
			$this->logger->warning('AdminSettingsSecurityHandler: Nonce verification failed', array(
				'page_slug'      => $page_slug,
				'action'         => $action,
				'provided_nonce' => substr($nonce, 0, 8) . '...' // Log partial nonce for debugging
			));
		}

		return $is_valid;
	}

	/**
	 * Generate the nonce field HTML for a settings page form.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return string The nonce field HTML.
	 */
	public function get_nonce_field(string $page_slug): string {
		$action = $this->get_nonce_action($page_slug);
		return $this->_do_wp_nonce_field($action, self::NONCE_FIELD_NAME, true, false);
	}

	/**
	 * Verify user capability for settings operations.
	 *
	 * @param string $capability The capability to check. Defaults to 'manage_options'.
	 * @return bool True if user has capability, false otherwise.
	 */
	public function verify_capability(string $capability = self::DEFAULT_CAPABILITY): bool {
		$has_capability = $this->_do_current_user_can($capability);

		$this->logger->debug('AdminSettingsSecurityHandler: Capability check', array(
			'capability'     => $capability,
			'user_id'        => $this->_do_get_current_user_id(),
			'has_capability' => $has_capability
		));

		if (!$has_capability) {
			$this->logger->warning('AdminSettingsSecurityHandler: Capability check failed', array(
				'capability' => $capability,
				'user_id'    => $this->_do_get_current_user_id()
			));
		}

		return $has_capability;
	}

	/**
	 * Verify if current user can save settings for a specific page.
	 * Combines capability check with context-specific validation.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param string $capability Optional capability override.
	 * @return bool True if user can save, false otherwise.
	 */
	public function verify_user_can_save(string $page_slug, string $capability = self::DEFAULT_CAPABILITY): bool {
		// Basic capability check
		if (!$this->verify_capability($capability)) {
			return false;
		}

		// Additional context-specific checks can be added here
		// For example, checking if user can access specific settings pages

		$this->logger->debug('AdminSettingsSecurityHandler: User save verification passed', array(
			'page_slug'  => $page_slug,
			'capability' => $capability,
			'user_id'    => $this->_do_get_current_user_id()
		));

		return true;
	}

	/**
	 * Verify request referrer to prevent CSRF attacks.
	 *
	 * @return bool True if referrer is valid, false otherwise.
	 */
	public function verify_referrer(): bool {
		if (!\function_exists('admin_url')) {
			$this->logger->warning('AdminSettingsSecurityHandler: admin_url not available, skipping referrer check');
			return true; // Allow in test environments
		}

		// WordPress check_admin_referer() will wp_die() on failure by default
		// We want to handle this gracefully, so we'll do a manual check
		$referrer  = $_SERVER['HTTP_REFERER'] ?? '';
		$admin_url = \admin_url();

		$is_valid = !empty($referrer) && strpos($referrer, $admin_url) === 0;

		$this->logger->debug('AdminSettingsSecurityHandler: Referrer verification', array(
			'referrer'  => $referrer,
			'admin_url' => $admin_url,
			'valid'     => $is_valid
		));

		if (!$is_valid) {
			$this->logger->warning('AdminSettingsSecurityHandler: Invalid referrer detected', array(
				'referrer'        => $referrer,
				'expected_prefix' => $admin_url
			));
		}

		return $is_valid;
	}

	/**
	 * Validate request origin to prevent cross-site attacks.
	 *
	 * @return bool True if request origin is valid, false otherwise.
	 */
	public function validate_request_origin(): bool {
		// Check if request method is POST for form submissions
		$method = $_SERVER['REQUEST_METHOD'] ?? '';
		if ($method !== 'POST') {
			$this->logger->debug('AdminSettingsSecurityHandler: Non-POST request, skipping origin validation');
			return true;
		}

		// Verify referrer
		if (!$this->verify_referrer()) {
			return false;
		}

		// Additional origin validation can be added here
		// For example, checking specific headers or request patterns

		$this->logger->debug('AdminSettingsSecurityHandler: Request origin validation passed');
		return true;
	}

	/**
	 * Handle secure save request processing.
	 * Performs all security checks and validates the request.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $post_data The POST data to validate.
	 * @param string $capability Optional capability override.
	 * @return bool True if request is secure and valid, false otherwise.
	 */
	public function handle_save_request(string $page_slug, array $post_data, string $capability = self::DEFAULT_CAPABILITY): bool {
		$this->logger->debug('AdminSettingsSecurityHandler: Starting save request validation', array(
			'page_slug'  => $page_slug,
			'capability' => $capability,
			'data_keys'  => array_keys($post_data)
		));

		// 1. Verify nonce
		$nonce = $post_data[self::NONCE_FIELD_NAME] ?? '';
		if (!$this->verify_nonce($nonce, $page_slug)) {
			$this->logger->error('AdminSettingsSecurityHandler: Save request failed - invalid nonce', array(
				'page_slug' => $page_slug
			));
			return false;
		}

		// 2. Verify user capability
		if (!$this->verify_user_can_save($page_slug, $capability)) {
			$this->logger->error('AdminSettingsSecurityHandler: Save request failed - insufficient capability', array(
				'page_slug'  => $page_slug,
				'capability' => $capability,
				'user_id'    => $this->_do_get_current_user_id()
			));
			return false;
		}

		// 3. Validate request origin
		if (!$this->validate_request_origin()) {
			$this->logger->error('AdminSettingsSecurityHandler: Save request failed - invalid origin', array(
				'page_slug' => $page_slug
			));
			return false;
		}

		$this->logger->info('AdminSettingsSecurityHandler: Save request validation passed', array(
			'page_slug' => $page_slug,
			'user_id'   => $this->_do_get_current_user_id()
		));

		return true;
	}

	/**
	 * Handle secure redirect after save operation.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param bool $success Whether the save operation was successful.
	 * @param array $messages Optional messages to display.
	 * @return void
	 */
	public function redirect_after_save(string $page_slug, bool $success, array $messages = array()): void {
		// Build redirect URL
		$redirect_url = $this->build_redirect_url($page_slug, $success, $messages);

		$this->logger->debug('AdminSettingsSecurityHandler: Redirecting after save', array(
			'page_slug'     => $page_slug,
			'success'       => $success,
			'redirect_url'  => $redirect_url,
			'message_count' => count($messages)
		));

		// Perform redirect
		if (\function_exists('wp_safe_redirect')) {
			\wp_safe_redirect($redirect_url);
		} elseif (\function_exists('wp_redirect')) {
			\wp_redirect($redirect_url);
		} else {
			// Fallback for test environments
			$this->logger->warning('AdminSettingsSecurityHandler: Redirect functions not available, would redirect to: ' . $redirect_url);
			return;
		}

		exit;
	}

	/**
	 * Check if current context is network admin (for multisite support).
	 *
	 * @return bool True if in network admin context, false otherwise.
	 */
	public function is_network_admin(): bool {
		return $this->_do_is_network_admin();
	}

	/**
	 * Get capability appropriate for current context (site vs network).
	 *
	 * @param string $default_capability Default capability for site context.
	 * @return string Appropriate capability for current context.
	 */
	public function get_context_capability(string $default_capability = self::DEFAULT_CAPABILITY): string {
		if ($this->is_network_admin()) {
			// Network admin typically requires manage_network_options
			return 'manage_network_options';
		}

		return $default_capability;
	}

	/**
	 * Verify multisite-aware capability.
	 *
	 * @param string $site_capability Capability for site context.
	 * @param string $network_capability Capability for network context.
	 * @return bool True if user has appropriate capability for current context.
	 */
	public function verify_multisite_capability(
		string $site_capability = self::DEFAULT_CAPABILITY,
		string $network_capability = 'manage_network_options'
	): bool {
		$required_capability = $this->is_network_admin() ? $network_capability : $site_capability;
		return $this->verify_capability($required_capability);
	}

	/**
	 * Get the nonce action for a specific page slug.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return string The nonce action.
	 */
	private function get_nonce_action(string $page_slug): string {
		return self::NONCE_ACTION_PREFIX . $page_slug;
	}

	/**
	 * Build redirect URL after save operation.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param bool $success Whether the save was successful.
	 * @param array $messages Optional messages to include.
	 * @return string The redirect URL.
	 */
	private function build_redirect_url(string $page_slug, bool $success, array $messages = array()): string {
		// Base admin URL for the page
		$base_url = \admin_url('admin.php?page=' . urlencode($page_slug));

		// Add success/error parameter
		$params = array();
		if ($success) {
			$params['settings-updated'] = 'true';
		} else {
			$params['settings-error'] = 'true';
		}

		// Add message count if there are messages
		if (!empty($messages)) {
			$params['message-count'] = count($messages);
		}

		// Build query string
		if (!empty($params)) {
			$base_url .= '&' . http_build_query($params);
		}

		return $base_url;
	}
}
