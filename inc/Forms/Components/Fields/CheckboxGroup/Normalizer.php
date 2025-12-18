<?php
/**
 * Checkbox group component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Generate fieldset ID if needed
		$fieldsetId = '';
		$idSource   = $context['attributes']['id'] ?? ($context['id'] ?? null);
		if ($idSource !== null) {
			$fieldsetId                  = $this->session->reserve_id(is_string($idSource) ? $idSource : null, 'checkbox_group');
			$context['attributes']['id'] = $fieldsetId;
		}

		// Get the group name - required for form submission
		// Ensure name ends with [] for PHP to receive array values
		$groupName = $this->_sanitize_string($context['name'] ?? '', 'name');
		if ($groupName !== '' && !str_ends_with($groupName, '[]')) {
			$groupName .= '[]';
		}

		// Get stored/selected values from context
		// 'value' comes from the database (stored value), 'values' from hardcoded defaults
		$selectedValues = array();
		if (isset($context['value']) && is_array($context['value'])) {
			$selectedValues = array_map('strval', $context['value']);
		} elseif (isset($context['values']) && is_array($context['values'])) {
			$selectedValues = array_map('strval', $context['values']);
		}

		// Validate options array using base class method
		$options = $this->_validate_config_array($context['options'] ?? null, 'options') ?? array();

		// Render individual options
		$renderedOptions = array();
		foreach ($options as $index => $option) {
			$renderedOptions[] = $this->_render_option($option, $fieldsetId, $index, $groupName, $selectedValues);
		}

		unset($context['attributes']['required'], $context['attributes']['aria-required']);

		// Build template context
		$context['attributes']   = $this->session->format_attributes($context['attributes']);
		$context['legend']       = $this->_sanitize_string($context['legend'] ?? '', 'legend');
		$context['options_html'] = $renderedOptions;

		return $context;
	}

	/**
	 * Render individual checkbox option.
	 *
	 * @param array<string,mixed> $option The option configuration.
	 * @param string $fieldsetId The fieldset ID for generating option IDs.
	 * @param int $index The option index.
	 * @param string $groupName The group name attribute (with [] suffix).
	 * @param array<int,string> $selectedValues Array of currently selected values.
	 */
	private function _render_option(array $option, string $fieldsetId, int $index, string $groupName, array $selectedValues): string {
		$attributes = isset($option['attributes']) && is_array($option['attributes']) ? $option['attributes'] : array();

		// Set checkbox type
		$attributes['type'] = 'checkbox';

		// Set the value attribute first (needed for checked determination)
		$optionValue         = $this->_sanitize_string($option['value'] ?? '', 'option value');
		$attributes['value'] = $optionValue;

		// Determine checked state: stored value takes precedence, then hardcoded 'checked'
		$isChecked = false;
		if (!empty($selectedValues)) {
			// Use stored/selected values to determine checked state
			$isChecked = in_array($optionValue, $selectedValues, true);
		} else {
			// Fall back to hardcoded 'checked' property (for initial defaults)
			$isChecked = $this->_sanitize_boolean($option['checked'] ?? false, 'option checked');
		}
		if ($isChecked) {
			$attributes['checked'] = 'checked';
		}

		if ($this->_sanitize_boolean($option['disabled'] ?? false, 'option disabled')) {
			$attributes['disabled'] = 'disabled';
		}

		// Set the name attribute for form submission (with [] for array values)
		if ($groupName !== '') {
			$attributes['name'] = $groupName;
		}

		// Generate option ID
		$optionIdBase     = $attributes['id'] ?? ($fieldsetId !== '' ? $fieldsetId . '__option-' . ($index + 1) : null);
		$optionId         = $this->session->reserve_id(is_string($optionIdBase) ? $optionIdBase : null, 'checkbox_option');
		$attributes['id'] = $optionId;

		// Handle option description using base class string sanitization
		$optionDesc   = $this->_sanitize_string($option['description'] ?? '', 'option description');
		$optionDescId = '';
		if ($optionDesc !== '') {
			$optionDescId = $this->session->reserve_id($optionId . '__desc', 'desc');
			$this->session->append_aria_described_by($attributes, $optionDescId);
		}

		$result = $this->views->render_payload('fields.checkbox-option', array(
			'input_attributes' => $this->session->format_attributes($attributes),
			'label'            => $this->_sanitize_string($option['label'] ?? '', 'option label'),
			'description'      => $optionDesc,
			'description_id'   => $optionDescId,
		));

		if ($result instanceof \Ran\PluginLib\Forms\Component\ComponentRenderResult) {
			return $result->markup;
		}

		// Legacy array format support
		if (is_array($result) && isset($result['markup'])) {
			return $result['markup'];
		}

		$error = 'fields.checkbox-option must return ComponentRenderResult or array with markup.';
		$this->logger->error('Template payload validation failed', array(
			'template'     => 'fields.checkbox-option',
			'payload_type' => gettype($result),
		));
		throw new \UnexpectedValueException($error);
	}
}
