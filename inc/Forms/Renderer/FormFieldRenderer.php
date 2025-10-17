<?php
/**
 * Universal Form Field Processing Infrastructure
 *
 * This class provides universal form field processing logic that eliminates
 * code duplication between AdminSettings and UserSettings while supporting
 * template overrides and component rendering coordination.
 *
 * @package  RanPluginLib\Forms\Renderer
 * @author   Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license  GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link     https://github.com/RocketsAreNostalgic
 * @since    0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Renderer;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\Component\ComponentManifest;

/**
 * Universal form field processing logic that eliminates duplication between
 * AdminSettings and UserSettings.
 *
 * Key responsibilities:
 * - Field configuration validation
 * - Context preparation and message merging
 * - Component rendering coordination
 * - Basic template override support
 * - Form session and asset management
 */
class FormFieldRenderer {
	use WPWrappersTrait;
	/**
	 * Component manifest for component discovery and rendering.
	 *
	 * @var ComponentManifest
	 */
	private ComponentManifest $components;

	/**
	 * Form service for session management and asset capture.
	 *
	 * @var FormService
	 */
	private FormService $form_service;

	/**
	 * Component loader for view rendering.
	 *
	 * @var ComponentLoader
	 */
	private ComponentLoader $views;

	/**
	 * Template overrides for context-specific rendering.
	 * Structure: ['template_name' => 'override_template_name']
	 *
	 * @var array<string, string>
	 */
	private array $template_overrides = array();

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Creates a new FormFieldRenderer instance.
	 *
	 * @param ComponentManifest $components    Component manifest for rendering
	 * @param FormService       $form_service  Form service for session management
	 * @param ComponentLoader   $views         Component loader for view rendering
	 * @param Logger|null       $logger        Optional logger instance
	 */
	public function __construct(
		ComponentManifest $components,
		FormService $form_service,
		ComponentLoader $views,
		?Logger $logger = null
	) {
		$this->components   = $components;
		$this->form_service = $form_service;
		$this->views        = $views;
		$this->logger       = $logger;
	}

	/**
	 * Validate field configuration structure.
	 *
	 * Checks for required properties like field_id, component, and component_context.
	 *
	 * @param array $field Field configuration array
	 * @return array Validation errors (empty if valid)
	 */
	public function validate_field_config(array $field): array {
		$errors = array();

		// Check required field_id
		if (!isset($field['field_id']) || !is_string($field['field_id']) || trim($field['field_id']) === '') {
			$errors[] = 'Field configuration must have a non-empty string field_id';
		}

		// Check required component
		if (!isset($field['component']) || !is_string($field['component']) || trim($field['component']) === '') {
			$errors[] = 'Field configuration must have a non-empty string component';
		}

		// Check component_context (can be null, but if present must be array)
		if (isset($field['component_context']) && !is_array($field['component_context'])) {
			$errors[] = 'Field component_context must be an array if provided';
		}

		// Check label (optional, but if present must be string)
		if (isset($field['label']) && !is_string($field['label'])) {
			$errors[] = 'Field label must be a string if provided';
		}

		return $errors;
	}

	/**
	 * Set template overrides for context-specific rendering.
	 *
	 * @param array<string, string> $overrides Template override map
	 * @return void
	 */
	public function set_template_overrides(array $overrides): void {
		$this->template_overrides = $overrides;
		$this->_get_logger()->debug('FormFieldRenderer: Template overrides set', array('count' => count($overrides)));
	}

	/**
	 * Get current template overrides.
	 *
	 * @return array<string, string> Template override map
	 */
	public function get_template_overrides(): array {
		return $this->template_overrides;
	}

	/**
	 * Ensure form messages CSS is enqueued for proper message styling.
	 *
	 * This method ensures the form-messages.css file is enqueued to provide
	 * consistent styling for validation warnings and display notices.
	 *
	 * @deprecated We should inestead add the messaging stylesheet to the array of css deps that a compoent requires.
	 * @return void
	 */
	private function _ensure_form_messages_css(): void {
		static $enqueued = false;

		if ($enqueued) {
			return;
		}

		$css_url  = $this->_do_plugin_dir_url(__FILE__) . '../assets/form-messages.css';
		$css_path = dirname(__FILE__) . '/../assets/form-messages.css';

		// Only enqueue if file exists
		if (file_exists($css_path)) {
			$this->_do_wp_enqueue_style(
				'ran-form-messages',
				$css_url,
				array(),
				(string) filemtime($css_path), // Use file modification time as version
				'all'
			);

			$this->_get_logger()->debug('FormFieldRenderer: Form messages CSS enqueued');
			$enqueued = true;
		} else {
			$this->_get_logger()->warning('FormFieldRenderer: Form messages CSS file not found', array(
				'expected_path' => $css_path
			));
		}
	}

	/**
	 * Get the logger instance, creating a default one if needed.
	 *
	 * @return Logger
	 */
	private function _get_logger(): Logger {
		if ($this->logger === null) {
			$this->logger = new Logger();
		}
		return $this->logger;
	}
	/**
	 * Prepare field context by merging field configuration with runtime values and messages.
	 *
	 * @param array $field    Field configuration
	 * @param array $values   Runtime values
	 * @param array $messages Field messages (structured format with warnings/notices)
	 * @return array Prepared context for component rendering
	 * @throws \InvalidArgumentException If field configuration is invalid
	 */
	public function prepare_field_context(array $field, array $values, array $messages): array {
		// Validate field configuration
		$validation_errors = $this->validate_field_config($field);
		if (!empty($validation_errors)) {
			$error_message = 'Invalid field configuration: ' . implode(', ', $validation_errors);
			$this->_get_logger()->error('FormFieldRenderer: Field validation failed', array(
				'errors' => $validation_errors,
				'field'  => $field
			));
			throw new \InvalidArgumentException($error_message);
		}

		$field_id          = $field['field_id'];
		$component         = $field['component'];
		$component_context = $field['component_context'] ?? array();
		$label             = $field['label']             ?? '';

		// Get field value
		$field_value = $values[$field_id] ?? null;

		// Get field messages (both warnings and notices)
		$field_messages = $messages[$field_id] ?? array('warnings' => array(), 'notices' => array());

		// Prepare base context
		$context = array(
			'field_id'            => $field_id,
			'component'           => $component,
			'label'               => $label,
			'value'               => $field_value,
			'validation_warnings' => $field_messages['warnings'] ?? array(),
			'display_notices'     => $field_messages['notices']  ?? array(),
			'component_context'   => $component_context,
		);

		// Add any additional field properties to context
		foreach ($field as $key => $value) {
			if (!in_array($key, array('field_id', 'component', 'component_context', 'label'), true)) {
				$context[$key] = $value;
			}
		}

		$this->_get_logger()->debug('FormFieldRenderer: Context prepared', array(
			'field_id'     => $field_id,
			'component'    => $component,
			'has_warnings' => !empty($field_messages['warnings']),
			'has_notices'  => !empty($field_messages['notices'])
		));

		return $context;
	}

	/**
	 * Render field component by delegating to ComponentManifest.
	 *
	 * Handles form session management and asset capture during rendering.
	 *
	 * @param string $component        Component name
	 * @param string $field_id         Field identifier
	 * @param string $label            Field label
	 * @param array  $context          Prepared field context
	 * @param array  $values           All form values
	 * @param string $wrapper_template Template for wrapper (default: 'direct-output')
	 * @return string Rendered component HTML
	 * @throws \InvalidArgumentException If component rendering fails
	 */
	public function render_field_component(
		string $component,
		string $field_id,
		string $label,
		array $context,
		array $values,
		string $wrapper_template = 'direct-output'
	): string {
		try {
			// Ensure form messages CSS is enqueued
			$this->_ensure_form_messages_css();

			// Start form session for asset capture
			$this->form_service->start_session();

			// Render the component
			$render_result  = $this->components->render($component, $context);
			$component_html = $render_result->markup;

			// Handle template wrapper if not direct output
			if ($wrapper_template !== 'direct-output') {
				$component_html = $this->_apply_template_wrapper(
					$component_html,
					$wrapper_template,
					$field_id,
					$label,
					$context
				);
			}

			$this->_get_logger()->debug('FormFieldRenderer: Component rendered', array(
				'component'        => $component,
				'field_id'         => $field_id,
				'wrapper_template' => $wrapper_template
			));

			return $component_html;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormFieldRenderer: Component rendering failed', array(
				'component' => $component,
				'field_id'  => $field_id,
				'exception' => $e
			));
			throw new \InvalidArgumentException(
				"Failed to render component '{$component}' for field '{$field_id}': " . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Render field with wrapper template.
	 *
	 * Combines component rendering with template wrapper application using
	 * existing ComponentLoader infrastructure.
	 *
	 * @param string $component        Component name
	 * @param string $field_id         Field identifier
	 * @param string $label            Field label
	 * @param array  $context          Prepared field context
	 * @param array  $values           All form values
	 * @param string $wrapper_template Template for wrapper (default: 'shared.field-wrapper')
	 * @return string Rendered field HTML with wrapper
	 * @throws \InvalidArgumentException If component rendering fails
	 */
	public function render_field_with_wrapper(
		string $component,
		string $field_id,
		string $label,
		array $context,
		array $values,
		string $wrapper_template = 'shared.field-wrapper'
	): string {
		try {
			// Ensure form messages CSS is enqueued
			$this->_ensure_form_messages_css();

			// Start form session for asset capture
			$this->form_service->start_session();

			// Render the component first
			$render_result  = $this->components->render($component, $context);
			$component_html = $render_result->markup;

			// Apply template wrapper using ComponentLoader
			$wrapped_html = $this->_apply_template_wrapper(
				$component_html,
				$wrapper_template,
				$field_id,
				$label,
				$context
			);

			$this->_get_logger()->debug('FormFieldRenderer: Field rendered with wrapper', array(
				'component'        => $component,
				'field_id'         => $field_id,
				'wrapper_template' => $wrapper_template
			));

			return $wrapped_html;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormFieldRenderer: Field with wrapper rendering failed', array(
				'component' => $component,
				'field_id'  => $field_id,
				'exception' => $e
			));
			throw new \InvalidArgumentException(
				"Failed to render field '{$field_id}' with component '{$component}' and wrapper '{$wrapper_template}': " . $e->getMessage(),
				0,
				$e
			);
		}
	}

	/**
	 * Apply template wrapper to component HTML.
	 *
	 * Enhanced to use ComponentLoader for template rendering with proper context.
	 *
	 * @param string $component_html Rendered component HTML
	 * @param string $template_name  Template name
	 * @param string $field_id       Field identifier
	 * @param string $label          Field label
	 * @param array  $context        Field context
	 * @return string Wrapped HTML
	 */
	private function _apply_template_wrapper(
		string $component_html,
		string $template_name,
		string $field_id,
		string $label,
		array $context
	): string {
		// Check for template override
		$actual_template = $this->template_overrides[$template_name] ?? $template_name;

		// Prepare template context with required variables
		$template_context = array(
			'field_id'            => $field_id,
			'label'               => $label,
			'component_html'      => $component_html,
			'validation_warnings' => $context['validation_warnings'] ?? array(),
			'display_notices'     => $context['display_notices']     ?? array(),
			'description'         => $context['description']         ?? '',
			'required'            => $context['required']            ?? false,
			'context'             => $context,
		);

		try {
			// Use existing ComponentLoader to render wrapper template
			$wrapped_html = $this->views->render($actual_template, $template_context);

			$this->_get_logger()->debug('FormFieldRenderer: Template wrapper applied', array(
				'template_name'   => $template_name,
				'actual_template' => $actual_template,
				'field_id'        => $field_id
			));

			return $wrapped_html;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormFieldRenderer: Template wrapper failed', array(
				'template_name'   => $template_name,
				'actual_template' => $actual_template,
				'field_id'        => $field_id,
				'exception'       => $e
			));

			// Fallback to component HTML without wrapper on template error
			return $component_html;
		}
	}
}
