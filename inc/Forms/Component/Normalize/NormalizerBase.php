<?php
/**
 * Base normalizer providing common functionality for all component normalizers.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Normalize;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Util\Sanitize;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext as NormalizationContext;
use Ran\PluginLib\Forms\Component\ComponentLoader;

abstract class NormalizerBase implements NormalizeInterface {
	protected ComponentLoader $views;
	protected NormalizationContext $session;
	protected Logger $logger;
	protected string $componentType;
	protected ?TranslationService $translator = null;

	public function __construct(ComponentLoader $views, ?TranslationService $translator = null) {
		$this->views      = $views;
		$this->translator = $translator;
	}

	/**
	 * Normalizes component context and renders the component.
	 */
	public function render (array $context, NormalizationContext $session, string $componentAlias): array {
		$this->session = $session;
		$this->logger  = $session->get_logger();

		// Extract component info from alias (e.g., "fields.checkbox" -> type: "checkbox", template: "fields.checkbox")
		$this->componentType = $this->_extract_component_type($componentAlias);
		$templateName        = $componentAlias; // Alias IS the template name

		// Initialize session state
		$fieldId = $this->_extract_field_id($context);
		$session->resetState($this->componentType, $fieldId);

		// Normalize the context data
		$normalizedContext = $this->_normalize_context($context);

		// Run validation and add warnings to context
		$normalizedContext = $this->_add_validation_warnings($normalizedContext);

		// Render the component
		$payload = $this->views->render_payload($templateName, $normalizedContext);

		// Validate payload
		$this->_validate_payload($payload, $templateName);

		return array(
			'payload'  => $payload,
			'warnings' => $session->take_warnings()
		);
	}

	/**
	 * Create a translation service for normalizers.
	 *
	 * @param string $textDomain The WordPress text domain to use.
	 *
	 * @return TranslationService
	 */
	public static function create_translation_service(string $textDomain = 'ran-plugin-lib'): TranslationService {
		return TranslationService::for_domain('forms/normalizer', $textDomain);
	}

	protected function _get_translator(): TranslationService {
		if ($this->translator === null) {
			$this->translator = self::create_translation_service();
		}
		return $this->translator;
	}

	/**
	 * Translate a message using the translation service.
	 *
	 * @param string $message The message to translate.
	 * @param string $context Optional context for the translation.
	 *
	 * @return string The translated message.
	 */
	protected function _translate(string $message, string $context = ''): string {
		return $this->_get_translator()->translate($message, $context);
	}

	/**
	 * Normalize the context data before rendering.
	 */
	protected function _normalize_context(array $context): array {
		// Validate basic component configuration (fail-fast)
		$this->_validate_basic_component_config($context);

		// Initialize attributes
		$context = $this->_initialize_attributes($context);

		// Handle common form states
		$context = $this->_normalize_form_states($context);

		// Handle description and ARIA
		$context = $this->_normalize_description_and_aria($context);

		// Apply component-specific normalizations
		$context = $this->_normalize_component_specific($context);

		return $context;
	}

	/**
	 * Add validation warnings from POST submission to component context.
	 *
	 * This method integrates validation warnings that were generated during form submission
	 * (POST request) into the component context for display during rendering (GET request).
	 *
	 * @param array $context Component context
	 * @return array Context with validation warnings added
	 */
	protected function _add_validation_warnings(array $context): array {
		// Extract field ID for message lookup
		$field_id = $this->_extract_field_id($context);

		// Initialize structured message arrays
		if (!isset($context['warnings'])) {
			$context['warnings'] = array();
		}
		if (!isset($context['notices'])) {
			$context['notices'] = array();
		}

		if ($field_id === '') {
			// No field ID means no validation warnings to add, but still structure the context
			return $context;
		}

		// Look for validation warnings in the context
		// These would be passed from the form processing layer (AdminSettings/UserSettings)
		// via FormMessageHandler or similar mechanism
		if (isset($context['_validation_warnings']) && is_array($context['_validation_warnings'])) {
			// Merge validation warnings from POST submission
			$validation_warnings = $context['_validation_warnings'];

			foreach ($validation_warnings as $warning) {
				if (is_string($warning) && trim($warning) !== '') {
					$context['warnings'][] = trim($warning);
				}
			}

			// Log the integration for debugging
			$this->logger->debug('Validation warnings integrated into component context', array(
				'field_id'       => $field_id,
				'component_type' => $this->componentType,
				'warning_count'  => count($validation_warnings),
				'total_warnings' => count($context['warnings'])
			));

			// Remove the temporary validation warnings to avoid duplication
			unset($context['_validation_warnings']);
		}

		// Look for display notices in the context
		// These would be passed from the form processing layer for informational messages
		if (isset($context['_display_notices']) && is_array($context['_display_notices'])) {
			// Merge display notices
			$display_notices = $context['_display_notices'];

			foreach ($display_notices as $notice) {
				if (is_string($notice) && trim($notice) !== '') {
					$context['notices'][] = trim($notice);
				}
			}

			// Log the integration for debugging
			$this->logger->debug('Display notices integrated into component context', array(
				'field_id'       => $field_id,
				'component_type' => $this->componentType,
				'notice_count'   => count($display_notices),
				'total_notices'  => count($context['notices'])
			));

			// Remove the temporary display notices to avoid duplication
			unset($context['_display_notices']);
		}

		return $context;
	}

	/**
	 * Component-specific normalization logic.
	 * Override this method in child classes.
	 */
	protected function _normalize_component_specific(array $context): array {
		return $context;
	}

	/**
	 * Extract component type from alias for session state.
	 * E.g., "fields.checkbox" -> "checkbox", "elements.button" -> "button"
	 */
	protected function _extract_component_type(string $componentAlias): string {
		$parts = explode('.', $componentAlias);
		return end($parts); // Get the last part (component name)
	}

	/**
	 * Extract field ID from context.
	 */
	protected function _extract_field_id(array $context): string {
		return isset($context['_field_id']) ? (string) $context['_field_id'] : '';
	}

	/**
	 * Initialize attributes array.
	 */
	protected function _initialize_attributes(array $context): array {
		if (!isset($context['attributes']) || !is_array($context['attributes'])) {
			$context['attributes'] = array();
		}
		return $context;
	}

	/**
	 * Normalize common form states (required, disabled, etc.).
	 */
	protected function _normalize_form_states(array $context): array {
		$attributes = &$context['attributes'];

		// Handle required state
		if (!empty($context['required'])) {
			$attributes['required']      = 'required';
			$attributes['aria-required'] = 'true';
		}

		// Handle disabled state
		if (!empty($context['disabled'])) {
			$attributes['disabled'] = 'disabled';
		}

		return $context;
	}

	/**
	 * Normalize description and ARIA attributes.
	 */
	protected function _normalize_description_and_aria(array $context): array {
		$description   = isset($context['description']) ? (string) $context['description'] : '';
		$descriptionId = '';

		if ($description !== '') {
			// Generate or use existing description ID
			$descriptionId             = $this->_generate_description_id($context);
			$context['description_id'] = $descriptionId;

			// Add aria-describedby
			$this->session->appendAriaDescribedBy($context['attributes'], $descriptionId);
		}

		return $context;
	}

	/**
	 * Generate component ID and reserve it.
	 */
	protected function _generate_and_reserve_id(array $context, string $fallbackType): string {
		$attributes = $context['attributes'];
		$idSource   = $attributes['id'] ?? ($context['id'] ?? ($context['name'] ?? null));

		$componentId = $this->session->reserveId(
			is_string($idSource) ? $idSource : null,
			$fallbackType
		);

		return $componentId;
	}

	/**
	 * Generate description ID.
	 */
	protected function _generate_description_id(array $context): string {
		$baseId   = $context['attributes']['id'] ?? ($context['id'] ?? ($context['name'] ?? 'component'));
		$descBase = isset($context['description_id']) ?
			(string) $context['description_id'] :
			$baseId . '__desc';

		return $this->session->reserveId($descBase, 'desc');
	}

	/**
	 * Validate the rendered payload.
	 */
	protected function _validate_payload(mixed $payload, string $templateName): void {
		if (!is_array($payload) || !isset($payload['markup'])) {
			$error = "{$templateName} must return component payload array.";
			$this->logger->error('Template payload validation failed', array(
				'template'     => $templateName,
				'payload_type' => gettype($payload),
				'has_markup'   => is_array($payload) ? isset($payload['markup']) : false
			));
			throw new \UnexpectedValueException($error);
		}
	}

	/**
	 * Sanitize a string value.
	 *
	 * @param mixed $value Value to sanitize
	 * @param string $context Context for error messages
	 * @param callable|null $emitNotice Optional callback to emit display notices
	 * @return string Sanitized string value
	 */
	protected function _sanitize_string(mixed $value, string $context = '', ?callable $emitNotice = null): string {
		if (!is_scalar($value) && $value !== null) {
			$contextMsg = $context !== '' ? " for {$context}" : '';
			$error      = "Expected string{$contextMsg}, got " . gettype($value);

			$this->logger->error('Type validation failed in normalizer', array(
				'expected_type'  => 'string',
				'actual_type'    => gettype($value),
				'context'        => $context,
				'component_type' => $this->componentType
			));

			throw new \InvalidArgumentException($error);
		}

		$original = (string) $value;

		// Use Sanitize utility to ensure clean string
		$sanitized = Sanitize::string()->trim()($original);

		// Emit notice if value was transformed and callback is provided
		if ($emitNotice !== null && $original !== $sanitized) {
			$contextMsg = $context !== '' ? " for {$context}" : '';
			$emitNotice("String value{$contextMsg} was trimmed during sanitization");
		}

		return $sanitized;
	}

	/**
	 * Sanitize a boolean value with form-friendly coercion.
	 * Uses Sanitize::bool()->to_bool() for consistent form-friendly boolean handling.
	 *
	 * @param mixed $value Value to sanitize
	 * @param string $context Context for error messages
	 * @param callable|null $emitNotice Optional callback to emit display notices
	 * @return bool Sanitized boolean value
	 */
	protected function _sanitize_boolean(mixed $value, string $context = '', ?callable $emitNotice = null): bool {
		$original = $value;

		// Use the form-friendly boolean sanitizer
		$result = Sanitize::bool()->to_bool($value);

		// If it was successfully converted to boolean, return it
		if (is_bool($result)) {
			// Emit notice if value was transformed and callback is provided
			if ($emitNotice !== null && $original !== $result) {
				$contextMsg      = $context !== '' ? " for {$context}" : '';
				$originalDisplay = is_scalar($original) ? var_export($original, true) : gettype($original);
				$resultDisplay   = $result ? 'true' : 'false';
				$emitNotice("Boolean value{$contextMsg} was converted from {$originalDisplay} to {$resultDisplay}");
			}

			return $result;
		}

		// If not convertible, throw error
		$contextMsg = $context !== '' ? " for {$context}" : '';
		$error      = "Expected boolean{$contextMsg}, got " . gettype($value);

		$this->logger->error('Type validation failed in normalizer', array(
			'expected_type'  => 'boolean',
			'actual_type'    => gettype($value),
			'actual_value'   => is_scalar($value) ? $value : '[non-scalar]',
			'context'        => $context,
			'component_type' => $this->componentType
		));

		throw new \InvalidArgumentException($error);
	}

	/**
	 * Handle name attribute normalization.
	 */
	protected function _normalize_name(array $context): array {
		if (isset($context['name'])) {
			$context['attributes']['name'] = (string) $context['name'];
		}
		return $context;
	}

	/**
	 * Handle value attribute normalization.
	 */
	protected function _normalize_value(array $context, string $key = 'value'): array {
		if (isset($context[$key])) {
			$context['attributes']['value'] = (string) $context[$key];
		}
		return $context;
	}

	/**
	 * Handle placeholder attribute normalization.
	 */
	protected function _normalize_placeholder(array $context): array {
		if (isset($context['placeholder'])) {
			$context['attributes']['placeholder'] = (string) $context['placeholder'];
		}
		return $context;
	}

	/**
	 * Get the logger instance.
	 */
	protected function _get_logger(): Logger {
		return $this->logger;
	}

	/**
	 * Validate basic component configuration during normalization (fail-fast).
	 * Override this method in child classes to add component-specific validations.
	 *
	 * @param array $context Component context
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	protected function _validate_basic_component_config(array $context): void {
		// Default implementation - child classes can override to add validations
		// Common validations can be added here as needed
	}

	/**
	 * Validate configuration string fields (not user input).
	 * Use this for developer-provided configuration like labels, IDs, etc.
	 *
	 * @param mixed $value The value to validate
	 * @param string $fieldName The field name for error messages
	 * @return string|null The validated string or null if not provided
	 * @throws \InvalidArgumentException If validation fails
	 */
	protected function _validate_config_string(mixed $value, string $fieldName): ?string {
		if ($value === null || $value === '') {
			return null;
		}

		if (!Validate::basic()->is_string()($value)) {
			$error = "Configuration field '{$fieldName}' must be a string, got " . gettype($value);

			$this->logger->error('Invalid configuration string type', array(
				'field_name'     => $fieldName,
				'expected_type'  => 'string',
				'actual_type'    => gettype($value),
				'component_type' => $this->componentType
			));

			throw new \InvalidArgumentException($error);
		}

		// Use Sanitize utility for consistent string cleaning
		$trimmed = Sanitize::string()->trim()((string) $value);
		if ($trimmed === '') {
			$error = "Configuration field '{$fieldName}' cannot be empty if provided";

			$this->logger->error('Empty configuration string', array(
				'field_name'     => $fieldName,
				'component_type' => $this->componentType
			));

			throw new \InvalidArgumentException($error);
		}

		return $trimmed;
	}

	/**
	 * Validate configuration array fields (not user input).
	 * Use this for developer-provided configuration like data attributes, options, etc.
	 *
	 * @param mixed $value The value to validate
	 * @param string $fieldName The field name for error messages
	 * @return array|null The validated array or null if not provided
	 * @throws \InvalidArgumentException If validation fails
	 */
	protected function _validate_config_array(mixed $value, string $fieldName): ?array {
		if ($value === null) {
			return null;
		}

		if (!Validate::basic()->is_array()($value)) {
			$error = "Configuration field '{$fieldName}' must be an array, got " . gettype($value);

			$this->logger->error('Invalid configuration array type', array(
				'field_name'     => $fieldName,
				'expected_type'  => 'array',
				'actual_type'    => gettype($value),
				'component_type' => $this->componentType
			));

			throw new \InvalidArgumentException($error);
		}

		return $value;
	}

	/**
	 * Validate that a value is one of the allowed choices.
	 *
	 * @param mixed $value The value to validate
	 * @param array $allowedValues Array of allowed values
	 * @param string $fieldName The field name for error messages
	 * @param mixed $defaultValue Default value if not provided
	 * @return mixed The validated value
	 * @throws \InvalidArgumentException If validation fails
	 */
	protected function _validate_choice(mixed $value, array $allowedValues, string $fieldName, mixed $defaultValue = null): mixed {
		if ($value === null || $value === '') {
			if ($defaultValue !== null) {
				return $defaultValue;
			}
			$value = reset($allowedValues); // Use first allowed value as default
		}

		if (!in_array($value, $allowedValues, true)) {
			$error = "Field '{$fieldName}' must be one of: " . implode(', ', $allowedValues) . '. Got: ' . var_export($value, true);

			$this->logger->error('Invalid choice field value', array(
				'field_name'     => $fieldName,
				'provided_value' => $value,
				'allowed_values' => $allowedValues,
				'component_type' => $this->componentType
			));

			throw new \InvalidArgumentException($error);
		}

		return $value;
	}

	/**
	 * Normalize extensions array to lowercase and trimmed.
	 * Common utility for file extension handling.
	 *
	 * @param array $extensions Array of file extensions
	 * @return array Normalized extensions (lowercase, trimmed)
	 */
	protected function _normalize_extensions(array $extensions): array {
		return array_map(
			static fn($ext) => Sanitize::string()->trim(strtolower((string) $ext)),
			$extensions
		);
	}
}
