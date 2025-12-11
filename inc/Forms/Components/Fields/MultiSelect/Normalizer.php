<?php
/**
 * Multi-select field component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Handle name and ID
		$context                     = $this->_normalize_name($context);
		$selectId                    = $this->_generate_and_reserve_id($context, 'multi_select');
		$context['attributes']['id'] = $selectId;

		// Set multiple attribute
		$context['attributes']['multiple'] = 'multiple';

		// Ensure name ends with [] for PHP to receive array values
		if (isset($context['attributes']['name'])) {
			$name = $context['attributes']['name'];
			if (!str_ends_with($name, '[]')) {
				$context['attributes']['name'] = $name . '[]';
			}
		}

		// Get selected values using base class validation
		// Multi-select can receive values as 'values' (array), 'value' (string or array), or 'default' (array)
		$selectedValues = array();
		if (isset($context['values'])) {
			$values = $this->_validate_config_array($context['values'], 'values');
			if ($values !== null) {
				$selectedValues = array_map(fn($v) => $this->_sanitize_string($v, 'selected value'), $values);
			}
		} elseif (isset($context['value'])) {
			// Handle both array and string for 'value' key
			if (is_array($context['value'])) {
				$selectedValues = array_map(fn($v) => $this->_sanitize_string($v, 'value'), $context['value']);
			} else {
				$selectedValues = array($this->_sanitize_string($context['value'], 'value'));
			}
		} elseif (isset($context['default'])) {
			$defaults = $this->_validate_config_array($context['default'], 'default');
			if ($defaults !== null) {
				$selectedValues = array_map(fn($v) => $this->_sanitize_string($v, 'default value'), $defaults);
			}
		}

		// Validate options array using base class method
		$options = $this->_validate_config_array($context['options'] ?? null, 'options') ?? array();

		// Build options HTML
		$optionsMarkup = $this->_build_options($options, $selectedValues);

		// Build template context
		$context['select_attributes'] = $this->session->format_attributes($context['attributes']);
		$context['options_html']      = $optionsMarkup;

		return $context;
	}

	/**
	 * Build options HTML markup.
	 *
	 * Supports two formats:
	 * 1. Simple key-value: ['value1' => 'Label 1', 'value2' => 'Label 2']
	 * 2. Structured array: [['value' => 'value1', 'label' => 'Label 1', 'group' => 'Group'], ...]
	 */
	private function _build_options(mixed $options, array $selectedValues): array {
		if (!is_array($options)) {
			return array();
		}

		// Normalize options to structured format
		$normalizedOptions = $this->_normalize_options_format($options);

		// Use a sentinel key for ungrouped options
		$ungroupedKey = '__ungrouped__';
		$grouped      = array();
		foreach ($normalizedOptions as $option) {
			$groupRaw          = $option['group'] ?? '';
			$group             = $groupRaw !== '' ? $this->_sanitize_string($groupRaw, 'option group') : $ungroupedKey;
			$grouped[$group][] = $this->_render_option_markup($option, $selectedValues);
		}

		$markup = array();
		foreach ($grouped as $groupLabel => $optionMarkupList) {
			if ($groupLabel === $ungroupedKey) {
				foreach ($optionMarkupList as $optionMarkup) {
					$markup[] = $optionMarkup;
				}
				continue;
			}

			$label    = esc_html($groupLabel);
			$markup[] = sprintf('<optgroup label="%s">%s</optgroup>', $label, implode('', $optionMarkupList));
		}

		return $markup;
	}

	/**
	 * Normalize options to structured array format.
	 *
	 * Converts simple key-value format to structured format.
	 *
	 * @param array $options Raw options array
	 * @return array<int, array{value: string, label: string, group?: string}> Normalized options
	 */
	private function _normalize_options_format(array $options): array {
		$normalized = array();

		foreach ($options as $key => $value) {
			if (is_array($value)) {
				// Already structured format: ['value' => 'x', 'label' => 'X']
				$normalized[] = $value;
			} else {
				// Simple key-value format: 'value' => 'Label'
				$normalized[] = array(
					'value' => (string) $key,
					'label' => (string) $value,
				);
			}
		}

		return $normalized;
	}

	/**
	 * Render individual option markup.
	 */
	private function _render_option_markup(array $option, array $selectedValues): string {
		$attributes = isset($option['attributes']) && is_array($option['attributes']) ? $option['attributes'] : array();
		$value      = $this->_sanitize_string($option['value'] ?? '', 'option value');
		$label      = $this->_sanitize_string($option['label'] ?? $value, 'option label');

		$attributes['value'] = $value;
		if ($this->_sanitize_boolean($option['disabled'] ?? false, 'option disabled')) {
			$attributes['disabled'] = 'disabled';
		}
		$explicitSelected = $this->_sanitize_boolean($option['selected'] ?? false, 'option selected');
		if ($explicitSelected || in_array($value, $selectedValues, true)) {
			$attributes['selected'] = 'selected';
		}

		$attrString = $this->session->format_attributes($attributes);
		return sprintf('<option%s>%s</option>', $attrString !== '' ? ' ' . $attrString : '', esc_html($label));
	}
}
