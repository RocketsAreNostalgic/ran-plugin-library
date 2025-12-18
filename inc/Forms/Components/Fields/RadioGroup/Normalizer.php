<?php
/**
 * Radio group component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		$name    = $this->_sanitize_string($context['name'] ?? '', 'name');
		$default = $this->_sanitize_string($context['default'] ?? '', 'default');

		// Get stored value from context - 'value' comes from database
		// For radio groups, this is a single string value
		$storedValue = null;
		if (isset($context['value']) && is_string($context['value']) && $context['value'] !== '') {
			$storedValue = $context['value'];
		}

		// Generate fieldset ID
		$fieldsetId = '';
		$idSource   = $context['attributes']['id'] ?? ($context['id'] ?? ($name !== '' ? $name : null));
		if ($idSource !== null) {
			$fieldsetId                  = $this->session->reserve_id(is_string($idSource) ? $idSource : null, 'fieldset');
			$context['attributes']['id'] = $fieldsetId;
		}

		// Validate options array using base class method
		$options = $this->_validate_config_array($context['options'] ?? null, 'options') ?? array();

		// Render individual options
		$renderedOptions = array();
		foreach ($options as $option) {
			$optionContext         = $option;
			$optionContext['name'] = $optionContext['name'] ?? $name;

			// Determine checked state: stored value > hardcoded checked > default
			$optionValue = $this->_sanitize_string($option['value'] ?? '', 'option value');
			if ($storedValue !== null) {
				// Use stored value to determine checked state
				$optionContext['checked'] = ($optionValue === $storedValue);
			} elseif (!isset($optionContext['checked'])) {
				// Fall back to 'default' property for initial state
				$optionContext['checked'] = ($default !== '' && $optionValue === $default);
			}

			$renderedOptions[] = $this->_render_option($optionContext);
		}

		unset($context['attributes']['required'], $context['attributes']['aria-required']);

		// Build template context
		$context['legend']       = $this->_sanitize_string($context['legend'] ?? '', 'legend');
		$context['attributes']   = $this->session->format_attributes($context['attributes']);
		$context['options_html'] = $renderedOptions;

		return $context;
	}

	/**
	 * Render individual radio option.
	 */
	private function _render_option(array $option): string {
		$attributes = isset($option['attributes']) && is_array($option['attributes']) ? $option['attributes'] : array();
		$name       = $this->_sanitize_string($option['name'] ?? '', 'option name');
		$value      = $this->_sanitize_string($option['value'] ?? '', 'option value');

		// Set radio attributes
		$attributes['type']  = 'radio';
		$attributes['name']  = $name;
		$attributes['value'] = $value;

		// Generate option ID
		$id = $this->_sanitize_string($option['id'] ?? '', 'option id');
		if ($id === '' && $name !== '' && $value !== '') {
			$id = $this->session->generate_id($name, $value);
		}
		$optionId         = $this->session->reserve_id($attributes['id'] ?? $id, 'radio_option');
		$attributes['id'] = $optionId;

		// Handle states using base class boolean sanitization
		if ($this->_sanitize_boolean($option['checked'] ?? false, 'option checked')) {
			$attributes['checked'] = 'checked';
		}
		if ($this->_sanitize_boolean($option['disabled'] ?? false, 'option disabled')) {
			$attributes['disabled'] = 'disabled';
		}

		// Handle option description using base class string sanitization
		$description   = $this->_sanitize_string($option['description'] ?? '', 'option description');
		$descriptionId = '';
		if ($description !== '') {
			$descriptionId = $this->session->reserve_id($optionId . '__desc', 'desc');
			$this->session->append_aria_described_by($attributes, $descriptionId);
		}

		// Validate label attributes using base class array validation
		$label_attributes = $this->_validate_config_array($option['label_attributes'] ?? null, 'label_attributes') ?? array();

		$result = $this->views->render_payload('fields.radio-option', array(
			'input_attributes' => $this->session->format_attributes($attributes),
			'label'            => $this->_sanitize_string($option['label'] ?? '', 'option label'),
			'description'      => $description,
			'description_id'   => $descriptionId,
			'label_attributes' => $this->session->format_attributes($label_attributes),
		));

		if ($result instanceof \Ran\PluginLib\Forms\Component\ComponentRenderResult) {
			return $result->markup;
		}

		// Legacy array format support
		if (is_array($result) && isset($result['markup'])) {
			return $result['markup'];
		}

		$error = 'fields.radio-option must return ComponentRenderResult or array with markup.';
		$this->logger->error('Template payload validation failed', array(
			'template'     => 'fields.radio-option',
			'payload_type' => gettype($result),
		));
		throw new \UnexpectedValueException($error);
	}
}
