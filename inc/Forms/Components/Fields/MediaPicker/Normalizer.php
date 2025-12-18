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
		$value                          = $this->_sanitize_string($context['value'] ?? '', 'value');
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
			$context['attributes']['data-' . $sanitized] = $this->_sanitize_string($dataValue, 'data_' . $sanitized);
		}

		// Generate button and remove IDs
		$buttonIdBase = $this->_sanitize_string($context['button_id'] ?? $inputId . '__button', 'button_id');
		$buttonId     = $this->session->reserve_id($buttonIdBase, 'media_button');
		$removeIdBase = $this->_sanitize_string($context['remove_id'] ?? $inputId . '__remove', 'remove_id');
		$removeId     = $this->session->reserve_id($removeIdBase, 'media_remove');

		// Build template context
		unset($context['attributes']['required'], $context['attributes']['aria-required']);
		$context['input_attributes'] = $this->session->format_attributes($context['attributes']);
		$context['select_label']     = $this->_sanitize_string($context['select_label'] ?? 'Select media', 'select_label');
		$context['replace_label']    = $this->_sanitize_string($context['replace_label'] ?? 'Replace media', 'replace_label');
		$context['remove_label']     = $this->_sanitize_string($context['remove_label'] ?? 'Remove', 'remove_label');
		$context['button_id']        = $buttonId;
		$context['remove_id']        = $removeId;
		$context['has_selection']    = isset($context['has_selection']) ? (bool) $context['has_selection'] : ($value !== '');
		$context['preview_html']     = isset($context['preview_html']) ? $this->_sanitize_string($context['preview_html'], 'preview_html') : '';
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
