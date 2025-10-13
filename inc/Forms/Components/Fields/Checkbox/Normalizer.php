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

		if (isset($context['unchecked_value'])) {
			$context['unchecked_value'] = $this->_sanitize_string($context['unchecked_value'], 'unchecked value');
		}

		// Normalize default checked state
		if (isset($context['default_checked'])) {
			$context['default_checked'] = $this->_sanitize_boolean($context['default_checked'], 'default checked');
		}

		// Normalize label text
		if (isset($context['label_text'])) {
			$context['label_text'] = $this->_sanitize_string($context['label_text'], 'label text');
		}

		// Build checkbox attributes
		$context['checkbox_attributes'] = $this->_build_checkbox_attributes($context);

		// Build hidden input attributes if unchecked value is set
		if (isset($context['unchecked_value'])) {
			$context['hidden_attributes'] = $this->_build_hidden_attributes($context);
		}

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
			$attributes['name'] = $context['name'];
		}

		if (!empty($context['id'])) {
			$attributes['id'] = $context['id'];
		}

		// Use base class boolean sanitization for form states
		if ($this->_sanitize_boolean($context['default_checked'] ?? false, 'default_checked')) {
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

		return $this->session->formatAttributes($attributes);
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
			$attributes['name'] = $context['name'];
		}

		return $this->session->formatAttributes($attributes);
	}
}
