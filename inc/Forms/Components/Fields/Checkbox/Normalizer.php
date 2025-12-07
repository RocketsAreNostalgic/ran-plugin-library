<?php
/**
 * Checkbox component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Normalize checkbox values
		$context['checked_value'] = $this->_sanitize_string($context['checked_value'] ?? 'on', 'checked value');

		// Always set unchecked_value to ensure hidden input is rendered (defaults to empty string)
		$context['unchecked_value'] = $this->_sanitize_string($context['unchecked_value'] ?? '', 'unchecked value');

		// Normalize default checked state
		if (isset($context['default_checked'])) {
			$context['default_checked'] = $this->_sanitize_boolean($context['default_checked'], 'default checked');
		}

		// Normalize label
		if (isset($context['label'])) {
			$context['label'] = $this->_sanitize_string($context['label'], 'label');
		}

		// Build checkbox attributes
		$context['checkbox_attributes'] = $this->_build_checkbox_attributes($context);

		// Always build hidden input attributes for proper checkbox handling
		$context['hidden_attributes'] = $this->_build_hidden_attributes($context);

		return $context;
	}

	/**
	 * Build the checkbox input attributes.
	 */
	private function _build_checkbox_attributes(array $context): string {
		$attributes = $context['attributes'] ?? array();

		$attributes['type']  = 'checkbox';
		$attributes['value'] = $context['checked_value'];

		if (!empty($context['name'])) {
			$attributes['name'] = $this->_sanitize_string($context['name'], 'name');
		}

		if (!empty($context['id'])) {
			$attributes['id'] = $this->_sanitize_string($context['id'], 'id');
		}

		// Determine checked state: use stored value if present, otherwise use default_checked
		$storedValue  = $context['value'] ?? null;
		$checkedValue = $context['checked_value'];
		$isChecked    = false;

		if ($storedValue !== null) {
			// Compare stored value with checked_value to determine state
			$isChecked = ($storedValue === $checkedValue) || ($storedValue === true) || ($storedValue === '1') || ($storedValue === 'on');
		} elseif ($this->_sanitize_boolean($context['default_checked'] ?? false, 'default_checked')) {
			$isChecked = true;
		}

		if ($isChecked) {
			$attributes['checked'] = true;
		}

		if ($this->_sanitize_boolean($context['required'] ?? false, 'required')) {
			$attributes['required'] = true;
		}

		if ($this->_sanitize_boolean($context['disabled'] ?? false, 'disabled')) {
			$attributes['disabled'] = true;
		}

		if ($this->_sanitize_boolean($context['readonly'] ?? false, 'readonly')) {
			$attributes['readonly'] = true;
		}

		return $this->session->format_attributes($attributes);
	}

	/**
	 * Build the hidden input attributes for unchecked value.
	 */
	private function _build_hidden_attributes(array $context): string {
		$attributes = array(
			'type'  => 'hidden',
			'value' => $context['unchecked_value']
		);

		if (!empty($context['name'])) {
			$attributes['name'] = $this->_sanitize_string($context['name'], 'name');
		}

		return $this->session->format_attributes($attributes);
	}
}
