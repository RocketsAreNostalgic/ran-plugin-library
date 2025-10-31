<?php
/**
 * Media picker component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	use WPWrappersTrait;

	protected function _normalize_component_specific(array $context): array {
		// Validate configuration (fail-fast principle)
		$this->_validate_media_picker_config($context);

		// Handle name and ID
		$context                     = $this->_normalize_name($context);
		$inputId                     = $this->_generate_and_reserve_id($context, 'media_picker');
		$context['attributes']['id'] = $inputId;

		// Set hidden input attributes
		$value                          = isset($context['value']) ? (string) $context['value'] : '';
		$context['attributes']['type']  = 'hidden';
		$context['attributes']['value'] = $value;

		// Handle multiple selection
		$multiple = !empty($context['multiple']);
		if ($multiple) {
			$context['attributes']['data-multiple'] = 'true';
		}

		// Handle data attributes
		$dataAttrs = isset($context['data']) && is_array($context['data']) ? $context['data'] : array();
		foreach ($dataAttrs as $key => $dataValue) {
			if ($dataValue === null || $dataValue === '') {
				continue;
			}
			$sanitized = $this->_do_sanitize_key((string) $key);
			if ($sanitized === '') {
				continue;
			}
			$context['attributes']['data-' . $sanitized] = (string) $dataValue;
		}

		// Generate button and remove IDs
		$buttonIdBase = isset($context['button_id']) ? (string) $context['button_id'] : $inputId . '__button';
		$buttonId     = $this->session->reserveId($buttonIdBase, 'media_button');
		$removeIdBase = isset($context['remove_id']) ? (string) $context['remove_id'] : $inputId . '__remove';
		$removeId     = $this->session->reserveId($removeIdBase, 'media_remove');

		// Build template context
		$context['input_attributes'] = $this->session->formatAttributes($context['attributes']);
		$context['select_label']     = isset($context['select_label']) ? (string) $context['select_label'] : 'Select media';
		$context['replace_label']    = isset($context['replace_label']) ? (string) $context['replace_label'] : 'Replace media';
		$context['remove_label']     = isset($context['remove_label']) ? (string) $context['remove_label'] : 'Remove';
		$context['button_id']        = $buttonId;
		$context['remove_id']        = $removeId;
		$context['has_selection']    = isset($context['has_selection']) ? (bool) $context['has_selection'] : ($value !== '');
		$context['preview_html']     = isset($context['preview_html']) ? (string) $context['preview_html'] : '';
		$context['multiple']         = $multiple;

		return $context;
	}

	/**
	 * Validate media picker configuration during normalization (fail-fast).
	 *
	 * @param array $context Component context
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	private function _validate_media_picker_config(array $context): void {
		// Validate custom ID configurations using base class method
		$customIds = array('button_id', 'remove_id');
		foreach ($customIds as $idKey) {
			$this->_validate_config_string($context[$idKey] ?? null, $idKey);
		}

		// Validate label configurations using base class method
		$labelKeys = array('select_label', 'replace_label', 'remove_label');
		foreach ($labelKeys as $labelKey) {
			$this->_validate_config_string($context[$labelKey] ?? null, $labelKey);
		}

		// Validate data attributes using base class method
		$this->_validate_config_array($context['data'] ?? null, 'data');
	}
}
