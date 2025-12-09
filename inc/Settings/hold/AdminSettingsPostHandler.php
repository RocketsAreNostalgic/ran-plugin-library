<?php
/**
 * AdminSettingsPostHandler: Custom form submission handlers for AdminSettings.
 *
 * Provides custom admin-post.php handlers that bypass WordPress Settings API,
 * integrate with RegisterOptions validation pipeline, and handle error/success
 * messaging and redirects.
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
use Ran\PluginLib\Settings\AdminSettingsSecurityHandler;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;

/**
 * Handles custom form submission processing for AdminSettings.
 *
 * Responsibilities:
 * - Process admin-post.php form submissions
 * - Integrate with existing RegisterOptions validation pipeline
 * - Handle validation errors and user feedback
 * - Manage success/failure redirects with proper messaging
 * - Support various AdminSettings form configurations
 *
 * @codeCoverageIgnore
 */
class AdminSettingsPostHandler {
	use WPWrappersTrait;

	private const POST_ACTION_PREFIX = 'admin_settings_save_';

	private Logger $logger;
	private AdminSettingsSecurityHandler $security_handler;
	private FormMessageHandler $message_handler;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsSecurityHandler $security_handler Security handler instance.
	 * @param Logger|null $logger Optional logger instance.
	 * @param FormMessageHandler|null $message_handler Optional message handler.
	 */
	public function __construct(
		AdminSettingsSecurityHandler $security_handler,
		?Logger $logger = null,
		?FormMessageHandler $message_handler = null
	) {
		$this->security_handler = $security_handler;
		$this->logger           = $logger          ?? new Logger();
		$this->message_handler  = $message_handler ?? new FormMessageHandler($this->logger);
	}

	/**
	 * Register admin-post handlers for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param RegisterOptions $options The RegisterOptions instance for validation.
	 * @param string $capability Optional capability override.
	 * @return void
	 */
	public function register_handlers(string $page_slug, RegisterOptions $options, string $capability = 'manage_options'): void {
		$action = $this->get_post_action($page_slug);

		// Register handler for authenticated users
		$this->_do_add_action('admin_post_' . $action, function() use ($page_slug, $options, $capability) {
			$this->handle_form_submission($page_slug, $options, $capability);
		});

		// Register handler for non-authenticated users (should not be accessible, but for completeness)
		$this->_do_add_action('admin_post_nopriv_' . $action, function() use ($page_slug) {
			$this->handle_unauthorized_submission($page_slug);
		});

		$this->logger->debug('AdminSettingsPostHandler: Registered handlers', array(
			'page_slug'  => $page_slug,
			'action'     => $action,
			'capability' => $capability
		));
	}

	/**
	 * Handle form submission for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param RegisterOptions $options The RegisterOptions instance for validation.
	 * @param string $capability The required capability.
	 * @return void
	 */
	public function handle_form_submission(string $page_slug, RegisterOptions $options, string $capability): void {
		$this->logger->info('AdminSettingsPostHandler: Processing form submission', array(
			'page_slug'      => $page_slug,
			'user_id'        => $this->_do_get_current_user_id(),
			'post_data_keys' => array_keys($_POST)
		));

		try {
			// 1. Security validation
			if (!$this->security_handler->handle_save_request($page_slug, $_POST, $capability)) {
				$this->logger->error('AdminSettingsPostHandler: Security validation failed', array(
					'page_slug' => $page_slug
				));
				$this->handle_submission_failure($page_slug, array('Security validation failed'));
				return;
			}

			// 2. Extract and sanitize form data
			$form_data = $this->extract_form_data($_POST);

			$this->logger->debug('AdminSettingsPostHandler: Extracted form data', array(
				'page_slug'  => $page_slug,
				'data_keys'  => array_keys($form_data),
				'data_count' => count($form_data)
			));

			// 3. Process data through RegisterOptions validation pipeline
			$success = $this->process_with_register_options($options, $form_data);

			// 4. Handle result
			if ($success) {
				$this->handle_submission_success($page_slug, $options);
			} else {
				$this->handle_submission_failure($page_slug, array(), $options);
			}
		} catch (\Throwable $e) {
			$this->logger->error('AdminSettingsPostHandler: Exception during form processing', array(
				'page_slug'         => $page_slug,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));

			$this->handle_submission_failure($page_slug, array('An unexpected error occurred'));
		}
	}

	/**
	 * Handle unauthorized form submission attempts.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return void
	 */
	public function handle_unauthorized_submission(string $page_slug): void {
		$this->logger->warning('AdminSettingsPostHandler: Unauthorized submission attempt', array(
			'page_slug' => $page_slug,
			'user_id'   => $this->_do_get_current_user_id(),
			'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
		));

		// Redirect to login page or show error
		if (\function_exists('wp_die')) {
			\wp_die(
				__('You must be logged in to access this page.', 'ran-plugin-lib'),
				__('Unauthorized', 'ran-plugin-lib'),
				array('response' => 401)
			);
		} else {
			// Fallback for test environments
			$this->logger->error('AdminSettingsPostHandler: Would show unauthorized error');
		}
	}

	/**
	 * Process form data through RegisterOptions validation pipeline.
	 *
	 * @param RegisterOptions $options The RegisterOptions instance.
	 * @param array $form_data The sanitized form data.
	 * @return bool True if processing succeeded, false otherwise.
	 */
	private function process_with_register_options(RegisterOptions $options, array $form_data): bool {
		try {
			// Clear any previous messages
			$this->message_handler->clear();

			// Stage the options for validation
			$options->stage_options($form_data);

			// Get validation messages
			$messages = $options->take_messages();
			$this->message_handler->set_messages($messages);

			// Check if there are validation failures
			if ($this->message_handler->has_validation_failures()) {
				$this->logger->info('AdminSettingsPostHandler: Validation failed', array(
					'warning_count' => $this->message_handler->get_warning_count(),
					'field_count'   => count($messages)
				));
				return false;
			}

			// Commit the changes if validation passed
			$commit_success = $options->commit_replace();

			if (!$commit_success) {
				$this->logger->error('AdminSettingsPostHandler: Failed to commit options to database');
				return false;
			}

			$this->logger->info('AdminSettingsPostHandler: Successfully processed and saved options', array(
				'option_count' => count($form_data)
			));

			return true;
		} catch (\Throwable $e) {
			$this->logger->error('AdminSettingsPostHandler: Exception in RegisterOptions processing', array(
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));
			return false;
		}
	}

	/**
	 * Handle successful form submission.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param RegisterOptions $options The RegisterOptions instance.
	 * @return void
	 */
	private function handle_submission_success(string $page_slug, RegisterOptions $options): void {
		$this->logger->info('AdminSettingsPostHandler: Form submission successful', array(
			'page_slug' => $page_slug,
			'user_id'   => $this->_do_get_current_user_id()
		));

		// Get any notices (non-error messages) from the validation
		$messages         = $options->take_messages();
		$success_messages = array();

		foreach ($messages as $field => $field_messages) {
			if (!empty($field_messages['notices'])) {
				$success_messages = array_merge($success_messages, $field_messages['notices']);
			}
		}

		// Add default success message if none provided
		if (empty($success_messages)) {
			$success_messages[] = __('Settings saved successfully.', 'ran-plugin-lib');
		}

		// Redirect with success
		$this->security_handler->redirect_after_save($page_slug, true, $success_messages);
	}

	/**
	 * Handle failed form submission.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $error_messages Additional error messages.
	 * @param RegisterOptions|null $options Optional RegisterOptions instance for validation messages.
	 * @return void
	 */
	private function handle_submission_failure(string $page_slug, array $error_messages = array(), ?RegisterOptions $options = null): void {
		$this->logger->warning('AdminSettingsPostHandler: Form submission failed', array(
			'page_slug'   => $page_slug,
			'user_id'     => $this->_do_get_current_user_id(),
			'error_count' => count($error_messages)
		));

		$all_messages = $error_messages;

		// Add validation messages if available
		if ($options !== null) {
			$validation_messages = $options->take_messages();
			foreach ($validation_messages as $field => $field_messages) {
				if (!empty($field_messages['warnings'])) {
					$all_messages = array_merge($all_messages, $field_messages['warnings']);
				}
			}
		}

		// Add validation messages from message handler
		if ($this->message_handler->has_validation_failures()) {
			$handler_messages = $this->message_handler->get_all_messages();
			foreach ($handler_messages as $field => $field_messages) {
				if (!empty($field_messages['warnings'])) {
					$all_messages = array_merge($all_messages, $field_messages['warnings']);
				}
			}
		}

		// Add default error message if none provided
		if (empty($all_messages)) {
			$all_messages[] = __('Settings could not be saved. Please check your input and try again.', 'ran-plugin-lib');
		}

		// Redirect with error
		$this->security_handler->redirect_after_save($page_slug, false, $all_messages);
	}

	/**
	 * Extract and sanitize form data from POST request.
	 *
	 * @param array $post_data The raw POST data.
	 * @return array The sanitized form data.
	 */
	private function extract_form_data(array $post_data): array {
		$form_data = array();

		// Remove WordPress and security fields
		$excluded_fields = array(
			'admin_settings_nonce',
			'_wp_http_referer',
			'action',
			'submit'
		);

		foreach ($post_data as $key => $value) {
			if (!in_array($key, $excluded_fields, true)) {
				// Basic sanitization - RegisterOptions will handle detailed validation
				$sanitized_key = $this->_do_sanitize_key($key);
				if (!empty($sanitized_key)) {
					$form_data[$sanitized_key] = $this->sanitize_form_value($value);
				}
			}
		}

		return $form_data;
	}

	/**
	 * Sanitize a form value recursively.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_form_value($value) {
		if (is_array($value)) {
			return array_map(array($this, 'sanitize_form_value'), $value);
		}

		if (is_string($value)) {
			// Basic sanitization - detailed validation happens in RegisterOptions
			return $this->_do_sanitize_text_field($value);
		}

		return $value;
	}

	/**
	 * Get the admin-post action for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return string The admin-post action.
	 */
	private function get_post_action(string $page_slug): string {
		return self::POST_ACTION_PREFIX . $page_slug;
	}

	/**
	 * Generate the form action URL for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @return string The form action URL.
	 */
	public function get_form_action_url(string $page_slug): string {
		$action = $this->get_post_action($page_slug);

		if (\function_exists('admin_url')) {
			$url = \admin_url('admin-post.php?action=' . $action);
			return $url ?? '/wp-admin/admin-post.php?action=' . $action;
		}

		// Fallback for test environments
		return '/wp-admin/admin-post.php?action=' . $action;
	}

	/**
	 * Generate complete form opening tag with security fields.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param array $additional_attributes Additional form attributes.
	 * @return string The form opening tag with security fields.
	 */
	public function get_form_open_tag(string $page_slug, array $additional_attributes = array()): string {
		$action_url  = $this->get_form_action_url($page_slug);
		$nonce_field = $this->security_handler->get_nonce_field($page_slug);

		// Build attributes
		$attributes = array_merge(array(
			'method' => 'post',
			'action' => $action_url
		), $additional_attributes);

		$attr_string = '';
		foreach ($attributes as $name => $value) {
			$attr_string .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
		}

		return sprintf('<form%s>%s', $attr_string, $nonce_field);
	}

	/**
	 * Get success redirect URL for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param string|null $message Optional success message.
	 * @return string The success redirect URL.
	 */
	public function get_success_redirect_url(string $page_slug, ?string $message = null): string {
		$params = array('updated' => '1');

		if ($message !== null) {
			$params['message'] = urlencode($message);
		}

		$query_string = http_build_query($params);
		$base_url     = 'admin.php?page=' . urlencode($page_slug) . '&' . $query_string;

		if (\function_exists('admin_url')) {
			return \admin_url($base_url);
		}

		return '/wp-admin/' . $base_url;
	}

	/**
	 * Get error redirect URL for a settings page.
	 *
	 * @param string $page_slug The settings page slug.
	 * @param string|null $message Optional error message.
	 * @return string The error redirect URL.
	 */
	public function get_error_redirect_url(string $page_slug, ?string $message = null): string {
		$params = array('error' => '1');

		if ($message !== null) {
			$params['message'] = urlencode($message);
		}

		$query_string = http_build_query($params);
		$base_url     = 'admin.php?page=' . urlencode($page_slug) . '&' . $query_string;

		if (\function_exists('admin_url')) {
			return \admin_url($base_url);
		}

		return '/wp-admin/' . $base_url;
	}
}
