<?php
/**
 * Universal Form Element Processing Infrastructure
 *
 * This class provides universal form elements processing while supporting
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
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;

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
class FormElementRenderer {
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
	 * @var FormsService
	 */
	private FormsService $form_service;

	/**
	 * Component loader for view rendering.
	 *
	 * @var ComponentLoader
	 */
	private ComponentLoader $views;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Message handler used for retrieving validation state.
	 */
	private ?FormMessageHandler $message_handler = null;

	/**
	 * Creates a new FormElementRenderer instance.
	 *
	 * @param ComponentManifest   $components      Component manifest for rendering
	 * @param FormsService         $form_service    Form service for session management
	 * @param ComponentLoader     $views           Component loader for view rendering
	 * @param Logger|null         $logger          Optional logger instance
	 * @param FormMessageHandler|null $message_handler Optional message handler for context normalization
	 */
	public function __construct(
		ComponentManifest $components,
		FormsService $form_service,
		ComponentLoader $views,
		?Logger $logger = null,
		?FormMessageHandler $message_handler = null
	) {
		$this->components      = $components;
		$this->form_service    = $form_service;
		$this->views           = $views;
		$this->logger          = $logger;
		$this->message_handler = $message_handler;
	}

	/**
	 * Inject the message handler after construction.
	 */
	public function set_message_handler(FormMessageHandler $message_handler): void {
		$this->message_handler = $message_handler;
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
	 * Render a component and collect its declared assets into the provided session bucket.
	 *
	 * @param string                 $component Component alias to render.
	 * @param array<string,mixed>    $context   Rendering context passed to the component.
	 * @param FormsServiceSession|null $session Active session used for asset aggregation (required).
	 * @return string Rendered component markup.
	 */
	public function render_component_with_assets(
		string $component,
		array $context,
		?FormsServiceSession $session = null
	): string {
		if ($session === null) {
			$this->_get_logger()->error('FormElementRenderer: render_component_with_assets requires active session', array(
				'component' => $component,
			));
			throw new \InvalidArgumentException('FormElementRenderer::render_component_with_assets() requires an active FormsServiceSession instance.');
		}

		try {
			$render_result = $this->components->render($component, $context);
			$field_id      = (string) ($context['field_id'] ?? 'unknown');

			$session->ingest_component_result(
				$render_result,
				sprintf('render_component_with_assets:%s', $component),
				$field_id
			);

			// Only log per-field render in verbose mode to avoid log flooding
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->_get_logger()->debug('FormElementRenderer: Component rendered with assets', array(
					'component'   => $component,
					'field_id'    => $field_id,
					'has_assets'  => $render_result->has_assets(),
					'asset_types' => $this->_get_asset_types_summary($render_result),
				));
			}

			return $render_result->markup;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormElementRenderer: Component rendering with assets failed', array(
				'component'         => $component,
				'context_keys'      => array_keys($context),
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));

			throw new \InvalidArgumentException(
				"Failed to render component '{$component}' with assets: " . $e->getMessage(),
				0,
				$e
			);
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
	 * @param array $field   Raw field definition captured by builders
	 * @param array $values  Stored option values prior to normalization
	 * @param array $extras  Supplementary metadata (e.g. wrapper before/after markup)
	 * @return array Prepared context for component rendering
	 * @throws \InvalidArgumentException If field configuration is invalid
	 */
	public function prepare_field_context(array $field, array $values, array $extras = array()): array {
		$normalized_field = $field;
		$field_id         = '';
		if (isset($normalized_field['field_id']) && is_string($normalized_field['field_id'])) {
			$field_id = trim($normalized_field['field_id']);
		}
		if ($field_id === '' && isset($normalized_field['id'])) {
			$field_id = is_string($normalized_field['id']) ? trim($normalized_field['id']) : (string) $normalized_field['id'];
		}
		$normalized_field['field_id'] = $field_id;

		$component                     = $normalized_field['component'] ?? ($normalized_field['alias'] ?? null);
		$component                     = is_string($component) ? trim($component) : '';
		$normalized_field['component'] = $component;

		$label                     = isset($normalized_field['label']) ? (string) $normalized_field['label'] : '';
		$normalized_field['label'] = $label;

		if (!isset($normalized_field['component_context'])) {
			$normalized_field['component_context'] = array();
		}

		// Validate field configuration
		$validation_errors = $this->validate_field_config($normalized_field);
		if (!empty($validation_errors)) {
			$error_message = 'Invalid field configuration: ' . implode(', ', $validation_errors);
			$this->_get_logger()->error('FormElementRenderer: Field validation failed', array(
				'errors' => $validation_errors,
				'field'  => $normalized_field
			));
			throw new \InvalidArgumentException($error_message);
		}

		$component_context = $normalized_field['component_context'] ?? array();
		$component_context = is_array($component_context) ? $component_context : array();

		$effective_values = $values;
		$field_messages   = array('warnings' => array(), 'notices' => array());
		if ($this->message_handler instanceof FormMessageHandler) {
			$effective_values = $this->message_handler->get_effective_values($values);
			$field_messages   = $this->message_handler->get_messages_for_field($field_id);
		}

		$field_value = $effective_values[$field_id] ?? ($values[$field_id] ?? null);

		// Prepare base context with core field properties
		$context = array(
			'field_id'            => $field_id,
			'component'           => $component,
			'label'               => $label,
			'value'               => $field_value,
			'validation_warnings' => $field_messages['warnings'] ?? array(),
			'display_notices'     => $field_messages['notices']  ?? array(),
		);

		// Merge component_context at top level - this includes builder-provided
		// attributes like 'attributes', 'placeholder', 'required', 'disabled', etc.
		// Normalizers expect these at the top level, not nested.
		foreach ($component_context as $key => $ctx_value) {
			// For 'value'/'values', use context value as fallback when stored value is null/empty
			// This allows hardcoded defaults while respecting saved database values
			if ($key === 'value' && $context['value'] === null) {
				$context['value'] = $ctx_value;
			} elseif ($key === 'values' && ($context['value'] === null || $context['value'] === array())) {
				// Multi-select uses 'values' key - only use as default if no stored value
				$context['values'] = $ctx_value;
			} elseif ($key === 'values' && $context['value'] !== null) {
				// Stored value exists - don't use hardcoded 'values', let normalizer use 'value'
				continue;
			} elseif (!array_key_exists($key, $context)) {
				$context[$key] = $ctx_value;
			}
		}

		// Add any additional field properties to context
		foreach ($normalized_field as $key => $value) {
			if (!in_array($key, array('field_id', 'component', 'component_context', 'label'), true)) {
				$context[$key] = $value;
			}
		}

		if (isset($extras['before'])) {
			$context['before'] = $extras['before'];
		}
		if (isset($extras['after'])) {
			$context['after'] = $extras['after'];
		}

		// Only log per-field context preparation in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->_get_logger()->debug('FormElementRenderer: Context prepared', array(
				'field_id'     => $field_id,
				'component'    => $component,
				'has_warnings' => !empty($field_messages['warnings']),
				'has_notices'  => !empty($field_messages['notices'])
			));
		}

		return $context;
	}

	/**
	 * Render field component by delegating to ComponentManifest.
	 *
	 * Handles form session management and asset capture during rendering.
	 * Enhanced with proper asset collection error handling and FormsServiceSession integration.
	 *
	 * @param string                $component        Component name
	 * @param string                $field_id         Field identifier
	 * @param string                $label            Field label
	 * @param array                 $context          Prepared field context
	 * @param string                $wrapper_template Template for wrapper (default: 'direct-output')
	 * @param string                $template_type    Canonical template type for resolver (default: 'field-wrapper')
	 * @param FormsServiceSession|null $session       Session for asset collection (required)
	 * @return string Rendered component HTML
	 * @throws \InvalidArgumentException If component rendering fails or session missing
	 */
	public function render_field_component(
		string $component,
		string $field_id,
		string $label,
		array $context,
		string $wrapper_template = 'direct-output',
		string $template_type = 'field-wrapper',
		?FormsServiceSession $session = null
	): string {
		try {
			if ($session === null) {
				$this->_get_logger()->error('FormElementRenderer: render_field_component requires active session', array(
					'component' => $component,
					'field_id'  => $field_id,
				));
				throw new \InvalidArgumentException('FormElementRenderer::render_field_component() requires an active FormsServiceSession instance.');
			}

			$component_html = $this->render_component_with_assets(
				$component,
				$context,
				$session
			);

			if ($wrapper_template === 'direct-output') {
				return $component_html;
			}

			return $this->_apply_template_wrapper(
				$component_html,
				$wrapper_template,
				$template_type,
				$field_id,
				$label,
				$context,
				$session
			);
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormElementRenderer: Component rendering failed', array(
				'component'         => $component,
				'field_id'          => $field_id,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
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
	 * existing ComponentLoader infrastructure. Enhanced with proper asset collection.
	 *
	 * @param string                $component        Component name
	 * @param string                $field_id         Field identifier
	 * @param string                $label            Field label
	 * @param array                 $context          Prepared field context
	 * @param string                $wrapper_template Template for wrapper (default: 'layout.field.field-wrapper')
	 * @param string                $template_type    Canonical template type for resolver (default: 'field-wrapper')
	 * @param FormsServiceSession|null $session       Session for asset collection (required)
	 * @return string Rendered field HTML with wrapper
	 * @throws \InvalidArgumentException If component rendering fails or session missing
	 */
	public function render_field_with_wrapper(
		string $component,
		string $field_id,
		string $label,
		array $context,
		string $wrapper_template = 'layout.field.field-wrapper',
		string $template_type = 'field-wrapper',
		?FormsServiceSession $session = null
	): string {
		try {
			if ($session === null) {
				$this->_get_logger()->error('FormElementRenderer: render_field_with_wrapper requires active session', array(
					'component'        => $component,
					'field_id'         => $field_id,
					'wrapper_template' => $wrapper_template,
				));
				throw new \InvalidArgumentException('FormElementRenderer::render_field_with_wrapper() requires an active FormsServiceSession instance.');
			}

			$component_html = $this->render_component_with_assets(
				$component,
				$context,
				$session
			);

			if ($wrapper_template === 'direct-output') {
				return $component_html;
			}

			$wrapped_html = $this->_apply_template_wrapper(
				$component_html,
				$wrapper_template,
				$template_type,
				$field_id,
				$label,
				$context,
				$session
			);

			// Only log per-field success in verbose mode to avoid log flooding
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->_get_logger()->debug('FormElementRenderer: Field rendered with wrapper successfully', array(
					'component'        => $component,
					'field_id'         => $field_id,
					'wrapper_template' => $wrapper_template
				));
			}

			return $wrapped_html;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormElementRenderer: Field with wrapper rendering failed', array(
				'component'         => $component,
				'field_id'          => $field_id,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
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
	 * @param string $component_html Rendered inner HTML
	 * @param string $template_name  Template name
	 * @param string $field_id       Field identifier
	 * @param string $label          Field label
	 * @param array  $context        Field context
	 * @return string Wrapped HTML
	 */
	private function _apply_template_wrapper(
		string $component_html,
		string $template_name,
		string $template_type,
		string $field_id,
		string $label,
		array $context,
		?FormsServiceSession $session
	): string {
		$actual_template  = $template_name;
		$resolvedBy       = 'default';
		$template_context = array(
			'field_id'            => $field_id,
			'label'               => $label,
			'inner_html'          => $component_html,
			'validation_warnings' => $context['validation_warnings'] ?? array(),
			'display_notices'     => $context['display_notices']     ?? array(),
			'description'         => $context['description']         ?? '',
			'required'            => $context['required']            ?? false,
			'context'             => $context,
		);
		$template_context = $this->sanitize_wrapper_context($template_context);
		$component_html   = $template_context['inner_html'];
		if (isset($template_context['context']) && is_array($template_context['context'])) {
			$context = $template_context['context'];
		}
		$resolver_context                  = $context;
		$resolver_context['field_id']      = $resolver_context['field_id'] ?? $field_id;
		$resolver_context['template_type'] = $template_type;

		if ($session instanceof FormsServiceSession) {
			$element_config = $template_context;
			try {
				$resolved = $session->template_resolver()->resolve_template($template_type, $resolver_context);
				if (is_string($resolved) && $resolved !== '') {
					$actual_template                 = $resolved;
					$resolvedBy                      = 'session';
					$element_config['root_override'] = $actual_template;
				}
			} catch (\Throwable $e) {
				$this->_get_logger()->warning('FormElementRenderer: Template resolution via session failed; falling back to default', array(
					'template_name' => $template_name,
					'field_id'      => $field_id,
					'exception'     => $e->getMessage(),
				));
			}

			try {
				$wrapped_html = $session->render_element($template_type, $element_config, $resolver_context);
				// Only log per-field wrapper in verbose mode to avoid log flooding
				if (ErrorNoticeRenderer::isVerboseDebug()) {
					$this->_get_logger()->debug('FormElementRenderer: Template wrapper applied', array(
						'template_name'   => $template_name,
						'actual_template' => $actual_template,
						'field_id'        => $field_id,
						'resolved_by'     => $resolvedBy,
					));
				}
				return $wrapped_html;
			} catch (\Throwable $e) {
				$this->_get_logger()->error('FormElementRenderer: Session render_element failed; falling back to loader', array(
					'template_type'     => $template_type,
					'field_id'          => $field_id,
					'exception_class'   => get_class($e),
					'exception_code'    => $e->getCode(),
					'exception_message' => $e->getMessage(),
				));
			}
		}

		try {
			// Use existing ComponentLoader to render wrapper template
			$wrapped_result = $this->views->render($actual_template, $template_context);
			$wrapped_html   = $wrapped_result->markup;

			// Only log per-field wrapper in verbose mode to avoid log flooding
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->_get_logger()->debug('FormElementRenderer: Template wrapper applied', array(
					'template_name'   => $template_name,
					'actual_template' => $actual_template,
					'field_id'        => $field_id,
					'resolved_by'     => $resolvedBy,
				));
			}

			return $wrapped_html;
		} catch (\Throwable $e) {
			$this->_get_logger()->error('FormElementRenderer: Template wrapper failed', array(
				'template_name'     => $template_name,
				'actual_template'   => $actual_template,
				'field_id'          => $field_id,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));

			// Fallback to component HTML without wrapper on template error
			return $component_html;
		}
	}

	/**
	 * Sanitize wrapper context fragments to prevent unsafe values reaching templates.
	 *
	 * @param array<string,mixed> $template_context
	 * @return array<string,mixed>
	 */
	private function sanitize_wrapper_context(array $template_context): array {
		$fragment_keys = array('before', 'after', 'description', 'inner_html');
		foreach ($fragment_keys as $key) {
			if (array_key_exists($key, $template_context)) {
				$template_context[$key] = $this->coerce_wrapper_fragment($template_context[$key], $key);
			}
		}

		return $template_context;
	}

	/**
	 * Coerce wrapper fragments into safe string output for templates.
	 *
	 * @param mixed  $fragment
	 * @param string $fragment_key
	 * @return string
	 */
	private function coerce_wrapper_fragment(mixed $fragment, string $fragment_key): string {
		if ($fragment === null) {
			return '';
		}

		if ($fragment instanceof ComponentRenderResult) {
			return $fragment->markup;
		}

		if (is_string($fragment)) {
			return $fragment;
		}

		if (is_scalar($fragment)) {
			return (string) $fragment;
		}

		if (is_object($fragment) && method_exists($fragment, '__toString')) {
			try {
				return (string) $fragment;
			} catch (\Throwable $e) {
				$this->_get_logger()->warning('FormElementRenderer: Wrapper fragment object failed to stringify', array(
					'fragment_key'      => $fragment_key,
					'exception_class'   => get_class($e),
					'exception_message' => $e->getMessage(),
				));
				return '';
			}
		}

		if (is_callable($fragment)) {
			$this->_get_logger()->warning('FormElementRenderer: Wrapper fragment was callable; discarding', array(
				'fragment_key' => $fragment_key,
			));
			return '';
		}

		if (is_array($fragment)) {
			$this->_get_logger()->warning('FormElementRenderer: Wrapper fragment was array; discarding', array(
				'fragment_key' => $fragment_key,
			));
			return '';
		}

		$this->_get_logger()->warning('FormElementRenderer: Wrapper fragment could not be coerced', array(
			'fragment_key'  => $fragment_key,
			'fragment_type' => gettype($fragment),
		));

		return '';
	}

	/**
	 * Collect assets from ComponentRenderResult with enhanced error handling.
	 *
	 * Implements Requirements 6.1, 6.2, 10.1, 10.2 for proper asset collection
	 * and error handling during component rendering.
	 *
	 * @param ComponentRenderResult $render_result The component render result
	 * @param FormsServiceSession    $session       The form service session
	 * @param string                $component     Component name for logging
	 * @param string                $field_id      Field ID for logging
	 * @return void
	 */
	private function _collect_component_assets(
		ComponentRenderResult $render_result,
		FormsServiceSession $session,
		string $component,
		string $field_id
	): void {
		try {
			// Attempt to collect assets from ComponentRenderResult
			$session->assets()->ingest($render_result);

			// Log successful asset collection if assets were present
			if ($render_result->has_assets()) {
				$this->_get_logger()->debug('FormElementRenderer: Assets collected successfully', array(
					'component'     => $component,
					'field_id'      => $field_id,
					'has_script'    => $render_result->has_script(),
					'has_style'     => $render_result->has_style(),
					'needs_media'   => $render_result->requires_media,
					'script_handle' => $render_result->has_script() ? $render_result->script->handle : null,
					'style_handle'  => $render_result->has_style() ? $render_result->style->handle : null
				));
			}
		} catch (\Throwable $e) {
			// Log asset collection failure but continue with rendering
			$this->_get_logger()->warning('FormElementRenderer: Asset collection failed, continuing with rendering', array(
				'component'      => $component,
				'field_id'       => $field_id,
				'error'          => $e->getMessage(),
				'exception_type' => get_class($e)
			));

			// Asset collection failure should not break rendering
			// The component HTML is still valid even without assets
		}
	}

	/**
	 * Get a summary of asset types for logging purposes.
	 *
	 * @param ComponentRenderResult $render_result The component render result
	 * @return array<string, mixed> Summary of asset types
	 */
	private function _get_asset_types_summary(ComponentRenderResult $render_result): array {
		$summary = array();

		if ($render_result->has_script()) {
			$summary['script'] = $render_result->script->handle;
		}

		if ($render_result->has_style()) {
			$summary['style'] = $render_result->style->handle;
		}

		if ($render_result->requires_media) {
			$summary['media'] = true;
		}

		return $summary;
	}
}
