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

		// Get selected values using base class validation
		$selectedValues = array();
		if (isset($context['values'])) {
			$values = $this->_validate_config_array($context['values'], 'values');
			if ($values !== null) {
				$selectedValues = array_map(fn($v) => $this->_sanitize_string($v, 'selected value'), $values);
			}
		} elseif (isset($context['value'])) {
			$selectedValues = array($this->_sanitize_string($context['value'], 'value'));
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
		$context['select_attributes'] = $this->session->formatAttributes($context['attributes']);
		$context['options_html']      = $optionsMarkup;

		return $context;
	}

	/**
	 * Build options HTML markup.
	 */
	private function _build_options(mixed $options, array $selectedValues): array {
		if (!is_array($options)) {
			return array();
		}

		$grouped = array();
		foreach ($options as $option) {
			if (!is_array($option)) {
				continue;
			}
			$groupRaw          = $option['group'] ?? '';
			$group             = $groupRaw !== '' ? $this->_sanitize_string($groupRaw, 'option group') : null;
			$grouped[$group][] = $this->_render_option_markup($option, $selectedValues);
		}

		$markup = array();
		foreach ($grouped as $groupLabel => $optionMarkupList) {
			if ($groupLabel === null) {
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

		$attrString = $this->session->formatAttributes($attributes);
		return sprintf('<option%s>%s</option>', $attrString !== '' ? ' ' . $attrString : '', esc_html($label));
	}
}
