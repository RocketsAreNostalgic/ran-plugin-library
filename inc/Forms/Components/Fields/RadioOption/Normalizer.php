<?php
/**
 * Radio option component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Set radio attributes using base class string sanitization
		$name  = $this->_sanitize_string($context['name'] ?? '', 'name');
		$value = $this->_sanitize_string($context['value'] ?? '', 'value');

		$context['attributes']['type'] = 'radio';
		if ($name !== '') {
			$context['attributes']['name'] = $name;
		}
		$context['attributes']['value'] = $value;

		// Generate ID if not provided
		$id = $this->_sanitize_string($context['id'] ?? '', 'id');
		if ($id === '' && $name !== '' && $value !== '') {
			$id = $this->session->generateId($name, $value);
		}
		$optionId                    = $this->session->reserveId($context['attributes']['id'] ?? $id, 'radio_option');
		$context['attributes']['id'] = $optionId;

		// Handle checked state using base class boolean sanitization
		if ($this->_sanitize_boolean($context['checked'] ?? false, 'checked')) {
			$context['attributes']['checked'] = 'checked';
		}

		// Build template context
		$context['input_attributes'] = $this->session->formatAttributes($context['attributes']);
		$context['label']            = $this->_sanitize_string($context['label'] ?? '', 'label');

		// Validate label attributes using base class array validation
		$labelAttributes             = $this->_validate_config_array($context['label_attributes'] ?? null, 'label_attributes') ?? array();
		$context['label_attributes'] = $this->session->formatAttributes($labelAttributes);

		return $context;
	}
}
